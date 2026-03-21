#!/usr/bin/env node
/**
 * 스마트팜 자동 제어 데몬 (Node.js)
 * 천창/측창 스크린 온도 기반 자동 개폐 제어
 *
 * - MQTT retain 토픽에서 UI 설정값(모드/autoActive/tempPoints) 읽음
 * - realtime_sensor.json에서 현재 온도 읽음 (60초 주기)
 * - 온도→개도율 선형 보간 계산
 * - MQTT 명령 발행 (OPEN → 타이머 → STOP)
 */

const mqtt = require('mqtt');
const fs   = require('fs');
const path = require('path');

// ─── 설정 ────────────────────────────────────────────────────────────────────
const MQTT_HOST     = '22ada06fd6cf4059bd700ddbf6004d68.s1.eu.hivemq.cloud';
const MQTT_PORT     = 8883;
const MQTT_USERNAME = 'esp32-client-01';
const MQTT_PASSWORD = 'Qjawns3445';

const SENSOR_FILE   = path.join(__dirname, '../config/realtime_sensor.json');
const LOG_FILE      = path.join(__dirname, '../logs/auto_control_daemon.log');

// 제어 주기 (ms) — 60초마다 온도 체크
const CHECK_INTERVAL_MS = 60000;

// 천창: 0%→100% 전체 이동 시간 (초)
const SKY_FULL_TIME_S  = 300;
// 측창: 0%→100% 전체 이동 시간 (초)
const SIDE_FULL_TIME_S = 120;

// 히스테리시스: 마지막 목표와 이 값(%) 미만 차이면 재명령 안 함
const HYSTERESIS_PCT = 2;

// 장치 정의
const DEVICES = {
  sky: [
    { id: 'skylight_left',  name: '천창 좌측', esp32Id: 'ctlr-0012', mqttId: 'windowL' },
    { id: 'skylight_right', name: '천창 우측', esp32Id: 'ctlr-0012', mqttId: 'windowR' },
  ],
  side: [
    { id: 'sidescreen_left',  name: '측창 좌측', esp32Id: 'ctlr-0021', mqttId: 'sideL' },
    { id: 'sidescreen_right', name: '측창 우측', esp32Id: 'ctlr-0021', mqttId: 'sideR' },
  ],
};

// ─── 상태 ────────────────────────────────────────────────────────────────────
const ctrl = {
  sky: {
    mode:       'MANUAL',
    autoActive: false,
    autoType:   'temp',   // 'temp' | 'time'
    tempPoints: [{ temp: 20, rate: 10 }, { temp: 23, rate: 30 }, { temp: 28, rate: 100 }],
    timePoints: [{ time: '08:00', rate: 30 }, { time: '12:00', rate: 80 }, { time: '18:00', rate: 0 }],
    currentPos: {},   // { deviceId: number }
    lastTarget: {},   // { deviceId: number | null }
    timers:     {},   // { deviceId: Timeout }
    fullTimeS:  SKY_FULL_TIME_S,
    label:      '천창',
  },
  side: {
    mode:       'MANUAL',
    autoActive: false,
    tempPoints: [{ temp: 20, rate: 10 }, { temp: 23, rate: 30 }, { temp: 28, rate: 100 }],
    currentPos: {},
    lastTarget: {},
    timers:     {},
    fullTimeS:  SIDE_FULL_TIME_S,
    label:      '측창',
  },
};

// ─── 로그 ────────────────────────────────────────────────────────────────────
function log(msg) {
  const ts = new Date().toLocaleString('ko-KR', { timeZone: 'Asia/Seoul' });
  const line = `[${ts}] ${msg}`;
  console.log(line);
  try { fs.appendFileSync(LOG_FILE, line + '\n'); } catch (_) {}
}

// ─── 온도→개도율 선형 보간 ────────────────────────────────────────────────────
function calcTargetRate(avgTemp, tempPoints) {
  const sorted = [...tempPoints].sort((a, b) => a.temp - b.temp);
  if (avgTemp < sorted[0].temp) return 0;
  if (avgTemp >= sorted[sorted.length - 1].temp) return 100;
  for (let i = 0; i < sorted.length - 1; i++) {
    if (avgTemp >= sorted[i].temp && avgTemp < sorted[i + 1].temp) {
      const ratio = (avgTemp - sorted[i].temp) / (sorted[i + 1].temp - sorted[i].temp);
      return Math.round(sorted[i].rate + ratio * (sorted[i + 1].rate - sorted[i].rate));
    }
  }
  return 0;
}

// ─── 시각→개도율 선형 보간 ────────────────────────────────────────────────────
function calcTargetRateByTime(timePoints) {
  const toMin = (t) => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
  const now = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Seoul' }));
  const nowMin = now.getHours() * 60 + now.getMinutes();
  const sorted = [...timePoints].sort((a, b) => toMin(a.time) - toMin(b.time));
  if (sorted.length === 0) return 0;
  if (nowMin < toMin(sorted[0].time)) return sorted[0].rate;
  if (nowMin >= toMin(sorted[sorted.length - 1].time)) return sorted[sorted.length - 1].rate;
  for (let i = 0; i < sorted.length - 1; i++) {
    const s = toMin(sorted[i].time), e = toMin(sorted[i + 1].time);
    if (nowMin >= s && nowMin < e) {
      const ratio = (nowMin - s) / (e - s);
      return Math.round(sorted[i].rate + ratio * (sorted[i + 1].rate - sorted[i].rate));
    }
  }
  return 0;
}

// ─── 장치 이동 명령 ──────────────────────────────────────────────────────────
function moveDevice(mqttClient, ctrlState, device, targetRate) {
  const { id, name, esp32Id, mqttId } = device;
  const currentPos = ctrlState.currentPos[id] ?? 0;
  const difference = targetRate - currentPos;

  if (Math.abs(difference) < 1) {
    log(`[SKIP] ${name} 이미 목표 위치 (${currentPos}%)`);
    return;
  }

  // 기존 타이머 취소
  if (ctrlState.timers[id]) {
    clearTimeout(ctrlState.timers[id]);
    delete ctrlState.timers[id];
  }

  const moveSeconds = (Math.abs(difference) / 100) * ctrlState.fullTimeS;
  const command = difference > 0 ? 'OPEN' : 'CLOSE';
  const cmdTopic = `tansaeng/${esp32Id}/${mqttId}/cmd`;

  log(`[CMD] ${name}: ${currentPos}% → ${targetRate}% (${command}, ${moveSeconds.toFixed(1)}초)`);
  mqttClient.publish(cmdTopic, command, { qos: 1 });

  // 이동 시간 후 STOP + 현재 위치 retain 발행 (브라우저 게이지 동기화)
  const screenType = ctrlState.label === '천창' ? 'sky' : 'side';
  const posTopic = `tansaeng/${screenType}-control/${mqttId}/currentPos`;

  ctrlState.timers[id] = setTimeout(() => {
    log(`[STOP] ${name}: ${targetRate}% 도달 → STOP`);
    mqttClient.publish(cmdTopic, 'STOP', { qos: 1 });
    ctrlState.currentPos[id] = targetRate;
    ctrlState.lastTarget[id] = targetRate;
    delete ctrlState.timers[id];
    // 브라우저 게이지 동기화용 retain 발행
    mqttClient.publish(posTopic, String(targetRate), { qos: 1, retain: true });
    log(`[POS] ${name} 위치 발행: ${posTopic} = ${targetRate}%`);
  }, moveSeconds * 1000);
}

// ─── AUTO 제어 실행 ──────────────────────────────────────────────────────────
function runAutoControl(mqttClient) {
  // 센서 파일 읽기
  let sensorData;
  try {
    const raw = fs.readFileSync(SENSOR_FILE, 'utf8');
    sensorData = JSON.parse(raw);
  } catch (e) {
    log(`[ERROR] 센서 파일 읽기 실패: ${e.message}`);
    return;
  }

  // 유효한 온도값만 수집
  const temps = [sensorData.front?.temperature, sensorData.back?.temperature, sensorData.top?.temperature]
    .filter(t => typeof t === 'number' && !isNaN(t));

  const avgTemp = temps.length > 0 ? temps.reduce((a, b) => a + b, 0) / temps.length : null;
  if (avgTemp !== null) log(`[TEMP] 평균온도: ${avgTemp.toFixed(1)}°C (센서 ${temps.length}개)`);

  // 천창 / 측창 각각 처리
  for (const [key, ctrlState] of Object.entries(ctrl)) {
    if (ctrlState.mode !== 'AUTO' || !ctrlState.autoActive) continue;

    let targetRate;
    if (key === 'sky' && ctrlState.autoType === 'time') {
      // 시간 기준 제어
      targetRate = calcTargetRateByTime(ctrlState.timePoints);
      const now = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Seoul' }));
      log(`[${ctrlState.label}] TIME AUTO 목표개도율: ${targetRate}% (현재 ${now.getHours()}:${String(now.getMinutes()).padStart(2,'0')})`);
    } else {
      // 온도 기준 제어
      if (avgTemp === null) {
        log(`[WARN] 온도 데이터 없음 — ${ctrlState.label} AUTO 건너뜀`);
        continue;
      }
      targetRate = calcTargetRate(avgTemp, ctrlState.tempPoints);
      log(`[${ctrlState.label}] TEMP AUTO 목표개도율: ${targetRate}%`);
    }

    for (const device of DEVICES[key]) {
      const lastTarget = ctrlState.lastTarget[device.id] ?? null;
      // 히스테리시스
      if (lastTarget !== null && Math.abs(targetRate - lastTarget) < HYSTERESIS_PCT) {
        log(`[SKIP] ${device.name} 히스테리시스 (마지막:${lastTarget}%, 목표:${targetRate}%)`);
        continue;
      }
      ctrlState.lastTarget[device.id] = targetRate;
      moveDevice(mqttClient, ctrlState, device, targetRate);
    }
  }
}

// ─── MQTT 연결 및 구독 ────────────────────────────────────────────────────────
function main() {
  log('=== 스마트팜 자동 제어 데몬 시작 ===');

  const mqttOptions = {
    host:            MQTT_HOST,
    port:            MQTT_PORT,
    protocol:        'mqtts',
    username:        MQTT_USERNAME,
    password:        MQTT_PASSWORD,
    clientId:        'tansaeng_auto_ctrl_' + Math.random().toString(16).substring(2, 8),
    reconnectPeriod: 5000,
    rejectUnauthorized: false,
  };

  const client = mqtt.connect(`mqtts://${MQTT_HOST}:${MQTT_PORT}`, mqttOptions);

  client.on('connect', () => {
    log('HiveMQ Cloud 연결 성공');

    // 설정 retain 토픽 구독 (UI에서 발행한 값 복원)
    const topics = [
      'tansaeng/sky-control/mode',
      'tansaeng/sky-control/autoActive',
      'tansaeng/sky-control/tempPoints',
      'tansaeng/sky-control/autoType',
      'tansaeng/sky-control/timePoints',
      'tansaeng/side-control/mode',
      'tansaeng/side-control/autoActive',
      'tansaeng/side-control/tempPoints',
    ];
    topics.forEach(t => client.subscribe(t, { qos: 1 }, (err) => {
      if (err) log(`구독 실패 ${t}: ${err.message}`);
      else log(`구독: ${t}`);
    }));

    // 시작 후 5초 대기(retain 메시지 수신) 후 첫 번째 제어 실행
    setTimeout(() => {
      log('[INIT] 초기 AUTO 제어 실행');
      runAutoControl(client);
      // 이후 60초 주기
      setInterval(() => runAutoControl(client), CHECK_INTERVAL_MS);
    }, 5000);
  });

  client.on('message', (topic, message) => {
    const payload = message.toString().trim();

    if (topic === 'tansaeng/sky-control/mode') {
      ctrl.sky.mode = (payload === 'AUTO' || payload === 'MANUAL') ? payload : 'MANUAL';
      log(`[설정] 천창 모드: ${ctrl.sky.mode}`);

    } else if (topic === 'tansaeng/sky-control/autoActive') {
      ctrl.sky.autoActive = payload === 'true';
      log(`[설정] 천창 autoActive: ${ctrl.sky.autoActive}`);
      // autoActive 변경 시 즉시 제어
      if (ctrl.sky.autoActive) {
        ctrl.sky.lastTarget = {};
        setTimeout(() => runAutoControl(client), 500);
      }

    } else if (topic === 'tansaeng/sky-control/tempPoints') {
      try {
        const parsed = JSON.parse(payload);
        if (Array.isArray(parsed) && parsed.length >= 2) {
          ctrl.sky.tempPoints = parsed;
          log(`[설정] 천창 tempPoints: ${JSON.stringify(ctrl.sky.tempPoints)}`);
        }
      } catch (_) {}

    } else if (topic === 'tansaeng/sky-control/autoType') {
      if (payload === 'temp' || payload === 'time') {
        ctrl.sky.autoType = payload;
        ctrl.sky.lastTarget = {};
        log(`[설정] 천창 autoType: ${ctrl.sky.autoType}`);
      }

    } else if (topic === 'tansaeng/sky-control/timePoints') {
      try {
        const parsed = JSON.parse(payload);
        if (Array.isArray(parsed) && parsed.length >= 2) {
          ctrl.sky.timePoints = parsed;
          log(`[설정] 천창 timePoints: ${JSON.stringify(ctrl.sky.timePoints)}`);
        }
      } catch (_) {}

    } else if (topic === 'tansaeng/side-control/mode') {
      ctrl.side.mode = (payload === 'AUTO' || payload === 'MANUAL') ? payload : 'MANUAL';
      log(`[설정] 측창 모드: ${ctrl.side.mode}`);

    } else if (topic === 'tansaeng/side-control/autoActive') {
      ctrl.side.autoActive = payload === 'true';
      log(`[설정] 측창 autoActive: ${ctrl.side.autoActive}`);
      if (ctrl.side.autoActive) {
        ctrl.side.lastTarget = {};
        setTimeout(() => runAutoControl(client), 500);
      }

    } else if (topic === 'tansaeng/side-control/tempPoints') {
      try {
        const parsed = JSON.parse(payload);
        if (Array.isArray(parsed) && parsed.length >= 2) {
          ctrl.side.tempPoints = parsed;
          log(`[설정] 측창 tempPoints: ${JSON.stringify(ctrl.side.tempPoints)}`);
        }
      } catch (_) {}
    }
  });

  client.on('error', (e) => log(`[MQTT ERROR] ${e.message}`));
  client.on('offline', () => log('[MQTT] 연결 끊김 — 재연결 중...'));
  client.on('reconnect', () => log('[MQTT] 재연결 시도...'));

  process.on('SIGTERM', () => { log('SIGTERM — 종료'); client.end(); process.exit(0); });
  process.on('SIGINT',  () => { log('SIGINT — 종료');  client.end(); process.exit(0); });
}

main();

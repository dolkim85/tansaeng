#!/usr/bin/env node
/**
 * 스마트팜 자동 제어 데몬 (Node.js)
 * 천창/측창 스크린 온도 기반 자동 개폐 제어
 * 히트시스템(펌프/히터/팬) 온도 기반 자동 ON/OFF 제어
 *
 * - MQTT retain 토픽에서 UI 설정값(모드/autoActive/tempPoints/ranges) 읽음
 * - realtime_sensor.json에서 팜 평균온도 읽음 (60초 주기)
 * - 히트시스템 팬: 장치제어실 내부 온도(ctlr-heat-001/air/temperature) 기준
 * - 히트시스템 펌프/히터: 팜 평균온도 기준
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

// ─── 팬 제어 상태 ────────────────────────────────────────────────────────────
const fan = {
  mode:       'MANUAL',
  autoActive: false,
  ranges:  {},   // { fan_id: { low, high } }
  lastCmd: {},   // { fan_id: 'ON' | 'OFF' | null }
};

// 팬 장치 목록 (devices.ts와 동일한 토픽)
const FAN_DEVICES = [
  { id: 'fan_front',  name: '내부팬 앞', cmdTopic: 'tansaeng/ctlr-0001/fan1/cmd' },
  { id: 'fan_back',   name: '내부팬 뒤', cmdTopic: 'tansaeng/ctlr-0002/fan2/cmd' },
  { id: 'fan_top',    name: '천장팬',    cmdTopic: 'tansaeng/ctlr-0003/fan_top/cmd' },
  { id: 'fan_ground', name: '지상팬',    cmdTopic: 'tansaeng/ctlr-0003/fan_ground/cmd' },
];

// ─── 히트시스템 상태 ──────────────────────────────────────────────────────────
const hp = {
  mode:       'MANUAL',
  autoActive: false,
  ranges: {
    hp_pump:   { low: 15, high: 22 },
    hp_heater: { low: 15, high: 22 },
    hp_fan:    { low: 15, high: 22 },
  },
  lastCmd: { hp_pump: null, hp_heater: null, hp_fan: null },
};
// 장치제어실 내부 온도 (팬 전용, MQTT 구독)
let roomTemp = null;

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

// ─── 팬 ON/OFF 제어 ──────────────────────────────────────────────────────────
function runFanAutoControl(mqttClient, avgTemp) {
  if (fan.mode !== 'AUTO' || !fan.autoActive) return;
  if (avgTemp === null) { log('[FAN] 팜 평균온도 없음 — 건너뜀'); return; }

  FAN_DEVICES.forEach(({ id, name, cmdTopic }) => {
    const range = fan.ranges[id];
    if (!range) return;
    const cmd = (avgTemp >= range.low && avgTemp <= range.high) ? 'ON' : 'OFF';
    if (fan.lastCmd[id] !== cmd) {
      fan.lastCmd[id] = cmd;
      mqttClient.publish(cmdTopic, cmd, { qos: 1 });
      log(`[FAN] ${name}: ${cmd} (평균온도 ${avgTemp.toFixed(1)}°C, 범위 ${range.low}~${range.high}°C)`);
    }
  });
}

// ─── 히트시스템 ON/OFF 제어 ───────────────────────────────────────────────────
function runHpAutoControl(mqttClient, avgTemp) {
  if (hp.mode !== 'AUTO' || !hp.autoActive) return;

  const devices = [
    { key: 'hp_pump',   mqttId: 'pump',   name: '히트펌프 순환펌프', temp: avgTemp  },
    { key: 'hp_heater', mqttId: 'heater', name: '전기온열기',        temp: avgTemp  },
    { key: 'hp_fan',    mqttId: 'fan',    name: '열교환기 팬',       temp: roomTemp },
  ];

  devices.forEach(({ key, mqttId, name, temp }) => {
    if (temp === null) { log(`[HP] ${name}: 온도 데이터 없음 — 건너뜀`); return; }
    const { low, high } = hp.ranges[key];
    const cmd = (temp >= low && temp <= high) ? 'ON' : 'OFF';
    if (hp.lastCmd[key] !== cmd) {
      hp.lastCmd[key] = cmd;
      mqttClient.publish(`tansaeng/ctlr-heat-001/${mqttId}/cmd`, cmd, { qos: 1 });
      log(`[HP] ${name}: ${cmd} (온도 ${temp.toFixed(1)}°C, 범위 ${low}~${high}°C)`);
    }
  });
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

  // 팬 AUTO 제어
  runFanAutoControl(mqttClient, avgTemp);

  // 히트시스템 AUTO 제어
  runHpAutoControl(mqttClient, avgTemp);

  // 천창 / 측창 각각 처리
  for (const [key, ctrlState] of Object.entries(ctrl)) {
    if (ctrlState.mode !== 'AUTO' || !ctrlState.autoActive) continue;

    let targetRate;
    if (key === 'sky' && ctrlState.autoType === 'time') {
      // 시간 기준 제어
      targetRate = calcTargetRateByTime(ctrlState.timePoints);
      const now = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Seoul' }));
      log(`[${ctrlState.label}] TIME AUTO 목표개도율: ${targetRate}% (현재 ${now.getHours()}:${String(now.getMinutes()).padStart(2,'0')})`);
    } else if (key === 'sky' && ctrlState.autoType === 'combined') {
      // 복합 모드: min(시간허용치, 온도기준) — 햇빛 확보 + 환기 균형
      if (avgTemp === null) {
        log(`[WARN] 온도 데이터 없음 — ${ctrlState.label} COMBINED 건너뜀`);
        continue;
      }
      const timeLimit = calcTargetRateByTime(ctrlState.timePoints);
      const tempRate  = calcTargetRate(avgTemp, ctrlState.tempPoints);
      targetRate = Math.min(timeLimit, tempRate);
      const now = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Seoul' }));
      log(`[${ctrlState.label}] COMBINED 목표개도율: ${targetRate}% (시간허용:${timeLimit}%, 온도기준:${tempRate}%, ${now.getHours()}:${String(now.getMinutes()).padStart(2,'0')}, ${avgTemp.toFixed(1)}°C)`);
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
      // 팬 제어
      'tansaeng/fan-control/mode',
      'tansaeng/fan-control/autoActive',
      'tansaeng/fan-control/ranges',
      // 히트시스템
      'tansaeng/hp-control/mode',
      'tansaeng/hp-control/autoActive',
      'tansaeng/hp-control/ranges',
      // 장치제어실 내부 온도 (팬 제어용)
      'tansaeng/ctlr-heat-001/air/temperature',
      // 스크린 현재 위치 retain (재시작 후 위치 복원)
      'tansaeng/sky-control/windowL/currentPos',
      'tansaeng/sky-control/windowR/currentPos',
      'tansaeng/side-control/sideL/currentPos',
      'tansaeng/side-control/sideR/currentPos',
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
      if (payload === 'temp' || payload === 'time' || payload === 'combined') {
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

    // ── 팬 설정 ──────────────────────────────────────────────────────────────
    } else if (topic === 'tansaeng/fan-control/mode') {
      fan.mode = (payload === 'AUTO' || payload === 'MANUAL') ? payload : 'MANUAL';
      log(`[FAN] 모드: ${fan.mode}`);

    } else if (topic === 'tansaeng/fan-control/autoActive') {
      fan.autoActive = payload === 'true';
      log(`[FAN] autoActive: ${fan.autoActive}`);
      if (fan.autoActive) {
        fan.lastCmd = {};
        setTimeout(() => runAutoControl(client), 500);
      }

    } else if (topic === 'tansaeng/fan-control/ranges') {
      try {
        const parsed = JSON.parse(payload);
        if (parsed && typeof parsed === 'object') {
          fan.ranges  = { ...fan.ranges, ...parsed };
          fan.lastCmd = {};
          log(`[FAN] 온도 범위 업데이트: ${JSON.stringify(fan.ranges)}`);
        }
      } catch (_) {}

    // ── 히트시스템 설정 ──────────────────────────────────────────────────────
    } else if (topic === 'tansaeng/hp-control/mode') {
      hp.mode = (payload === 'AUTO' || payload === 'MANUAL') ? payload : 'MANUAL';
      log(`[HP] 모드: ${hp.mode}`);

    } else if (topic === 'tansaeng/hp-control/autoActive') {
      hp.autoActive = payload === 'true';
      log(`[HP] autoActive: ${hp.autoActive}`);
      if (hp.autoActive) {
        hp.lastCmd = { hp_pump: null, hp_heater: null, hp_fan: null };
        setTimeout(() => runAutoControl(client), 500);
      }

    } else if (topic === 'tansaeng/hp-control/ranges') {
      try {
        const parsed = JSON.parse(payload);
        if (parsed && typeof parsed === 'object') {
          if (parsed.hp_pump)   hp.ranges.hp_pump   = parsed.hp_pump;
          if (parsed.hp_heater) hp.ranges.hp_heater = parsed.hp_heater;
          if (parsed.hp_fan)    hp.ranges.hp_fan    = parsed.hp_fan;
          hp.lastCmd = { hp_pump: null, hp_heater: null, hp_fan: null }; // 범위 변경 시 재평가
          log(`[HP] 온도 범위 업데이트: ${JSON.stringify(hp.ranges)}`);
        }
      } catch (_) {}

    } else if (topic === 'tansaeng/ctlr-heat-001/air/temperature') {
      const n = parseFloat(payload);
      if (!isNaN(n)) {
        roomTemp = n;
        // 팬 제어: 온도 수신 시마다 즉시 평가
        if (hp.mode === 'AUTO' && hp.autoActive) {
          const { low, high } = hp.ranges.hp_fan;
          const cmd = (roomTemp >= low && roomTemp <= high) ? 'ON' : 'OFF';
          if (hp.lastCmd.hp_fan !== cmd) {
            hp.lastCmd.hp_fan = cmd;
            client.publish('tansaeng/ctlr-heat-001/fan/cmd', cmd, { qos: 1 });
            log(`[HP] 열교환기 팬: ${cmd} (실내온도 ${roomTemp.toFixed(1)}°C, 범위 ${low}~${high}°C)`);
          }
        }
      }

    // ── 스크린 위치 복원 (retain) ─────────────────────────────────────────────
    } else if (topic === 'tansaeng/sky-control/windowL/currentPos') {
      const pos = parseFloat(payload);
      if (!isNaN(pos)) { ctrl.sky.currentPos['skylight_left'] = pos; log(`[POS 복원] 천창 좌측: ${pos}%`); }

    } else if (topic === 'tansaeng/sky-control/windowR/currentPos') {
      const pos = parseFloat(payload);
      if (!isNaN(pos)) { ctrl.sky.currentPos['skylight_right'] = pos; log(`[POS 복원] 천창 우측: ${pos}%`); }

    } else if (topic === 'tansaeng/side-control/sideL/currentPos') {
      const pos = parseFloat(payload);
      if (!isNaN(pos)) { ctrl.side.currentPos['sidescreen_left'] = pos; log(`[POS 복원] 측창 좌측: ${pos}%`); }

    } else if (topic === 'tansaeng/side-control/sideR/currentPos') {
      const pos = parseFloat(payload);
      if (!isNaN(pos)) { ctrl.side.currentPos['sidescreen_right'] = pos; log(`[POS 복원] 측창 우측: ${pos}%`); }
    }
  });

  client.on('error', (e) => log(`[MQTT ERROR] ${e.message}`));
  client.on('offline', () => log('[MQTT] 연결 끊김 — 재연결 중...'));
  client.on('reconnect', () => log('[MQTT] 재연결 시도...'));

  process.on('SIGTERM', () => { log('SIGTERM — 종료'); client.end(); process.exit(0); });
  process.on('SIGINT',  () => { log('SIGINT — 종료');  client.end(); process.exit(0); });
}

main();

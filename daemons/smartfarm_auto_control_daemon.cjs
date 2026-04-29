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

// 천창/측창 좌우 각각 전체 이동 시간 기본값 (초) — MQTT retain으로 덮어씀
const SKY_LEFT_FULL_TIME_S   = 300;
const SKY_RIGHT_FULL_TIME_S  = 300;
const SIDE_LEFT_FULL_TIME_S  = 120;
const SIDE_RIGHT_FULL_TIME_S = 120;

// 히스테리시스: 마지막 목표와 이 값(%) 미만 차이면 재명령 안 함
const HYSTERESIS_PCT = 2;

// ─── 팬 제어 상태 ────────────────────────────────────────────────────────────
const fan = {
  mode:       'MANUAL',
  autoActive: false,
  autoSensor: 'temp',  // 'temp' | 'humi'
  ranges:     {},      // { fan_id: { low, high } } — 온도 범위
  humRanges:  {},      // { fan_id: { low, high } } — 습도 범위
  lastCmd:    {},      // { fan_id: 'ON' | 'OFF' | null }
  dayNight: {
    enabled: false,
    dayStart: '06:00',
    nightStart: '20:00',
    day:   { sensor: 'temp', ranges: {}, humRanges: {} },
    night: { sensor: 'temp', ranges: {}, humRanges: {} },
  },
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
  dayNight: {
    enabled: false,
    dayStart: '06:00',
    nightStart: '20:00',
    day:   { ranges: { hp_pump: {low:15,high:22}, hp_heater: {low:15,high:22}, hp_fan: {low:15,high:22} } },
    night: { ranges: { hp_pump: {low:8,high:15},  hp_heater: {low:8,high:15},  hp_fan: {low:8,high:15}  } },
  },
};
// 장치제어실 내부 온도 (팬 전용, MQTT 구독)
let roomTemp = null;
// 냉각수 온도 (hp_heater 전용, MQTT 구독)
let waterTemp = null;

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
    fullTimeSMap: { skylight_left: SKY_LEFT_FULL_TIME_S, skylight_right: SKY_RIGHT_FULL_TIME_S },
    label:      '천창',
  },
  side: {
    mode:       'MANUAL',
    autoActive: false,
    autoType:   'temp',   // 'temp' | 'time' | 'daynight'
    autoSensor: 'temp',   // 'temp' | 'humi' (autoType=temp 일 때)
    tempPoints: [{ temp: 20, rate: 10 }, { temp: 23, rate: 30 }, { temp: 28, rate: 100 }],
    humPoints:  [{ humi: 60, rate: 10 }, { humi: 70, rate: 30 }, { humi: 80, rate: 100 }],
    timePoints: [{ time: '08:00', rate: 0 }, { time: '14:00', rate: 100 }, { time: '20:00', rate: 0 }],
    dayNightConfig: {
      dayStart: '06:00',
      nightStart: '20:00',
      day:   { sensor: 'temp', tempPoints: [{temp:20,rate:0},{temp:28,rate:100}], humPoints: [{humi:60,rate:0},{humi:80,rate:100}] },
      night: { sensor: 'temp', tempPoints: [{temp:15,rate:0},{temp:20,rate:50}], humPoints: [{humi:70,rate:0},{humi:85,rate:100}] },
    },
    currentPos: {},
    lastTarget: {},
    timers:     {},
    fullTimeSMap: { sidescreen_left: SIDE_LEFT_FULL_TIME_S, sidescreen_right: SIDE_RIGHT_FULL_TIME_S },
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

// ─── 시각→개도율 스텝 함수 (해당 시간이 되면 즉시 그 개도율로 이동) ──────────
function calcTargetRateByTime(timePoints) {
  const toMin = (t) => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
  const now = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Seoul' }));
  const nowMin = now.getHours() * 60 + now.getMinutes();
  const sorted = [...timePoints].sort((a, b) => toMin(a.time) - toMin(b.time));
  if (sorted.length === 0) return 0;
  // 현재 시각 이전의 마지막 포인트를 찾아 해당 개도율 반환 (스텝 함수)
  let activePoint = sorted[sorted.length - 1]; // 기본값: 마지막 포인트
  for (let i = sorted.length - 1; i >= 0; i--) {
    if (nowMin >= toMin(sorted[i].time)) {
      activePoint = sorted[i];
      break;
    }
  }
  return activePoint.rate;
}

// ─── 주간/야간 구분 ──────────────────────────────────────────────────────────
function getCurrentPeriod(dayStart, nightStart) {
  const toMin = (t) => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
  const now = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Seoul' }));
  const nowMin = now.getHours() * 60 + now.getMinutes();
  const dayMin = toMin(dayStart);
  const nightMin = toMin(nightStart);
  if (dayMin < nightMin) return (nowMin >= dayMin && nowMin < nightMin) ? 'day' : 'night';
  return (nowMin >= dayMin || nowMin < nightMin) ? 'day' : 'night';
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

  const fullTimeS = (ctrlState.fullTimeSMap && ctrlState.fullTimeSMap[id]) ? ctrlState.fullTimeSMap[id] : 300;
  const moveSeconds = (Math.abs(difference) / 100) * fullTimeS;
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
function runFanAutoControl(mqttClient, avgTemp, avgHumi) {
  if (fan.mode !== 'AUTO' || !fan.autoActive) return;

  let activeRanges, activeValue, sensorLabel;

  if (fan.dayNight.enabled) {
    const period = getCurrentPeriod(fan.dayNight.dayStart, fan.dayNight.nightStart);
    const cfg = fan.dayNight[period];
    const now = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Seoul' }));
    log(`[FAN] ${period === 'day' ? '주간' : '야간'} 모드 (${now.getHours()}:${String(now.getMinutes()).padStart(2,'0')})`);
    if (cfg.sensor === 'humi') {
      if (avgHumi === null) { log('[FAN] 습도 데이터 없음 — 건너뜀'); return; }
      activeRanges = cfg.humRanges;
      activeValue  = avgHumi;
      sensorLabel  = `습도 ${avgHumi.toFixed(0)}%RH`;
    } else {
      if (avgTemp === null) { log('[FAN] 온도 데이터 없음 — 건너뜀'); return; }
      activeRanges = cfg.ranges;
      activeValue  = avgTemp;
      sensorLabel  = `온도 ${avgTemp.toFixed(1)}°C`;
    }
  } else if (fan.autoSensor === 'humi') {
    if (avgHumi === null) { log('[FAN] 습도 데이터 없음 — 건너뜀'); return; }
    activeRanges = fan.humRanges;
    activeValue  = avgHumi;
    sensorLabel  = `습도 ${avgHumi.toFixed(0)}%RH`;
  } else {
    if (avgTemp === null) { log('[FAN] 온도 데이터 없음 — 건너뜀'); return; }
    activeRanges = fan.ranges;
    activeValue  = avgTemp;
    sensorLabel  = `온도 ${avgTemp.toFixed(1)}°C`;
  }

  FAN_DEVICES.forEach(({ id, name, cmdTopic }) => {
    const range = activeRanges[id];
    if (!range) return;
    const cmd = (activeValue >= range.low && activeValue <= range.high) ? 'ON' : 'OFF';
    if (fan.lastCmd[id] !== cmd) {
      fan.lastCmd[id] = cmd;
      mqttClient.publish(cmdTopic, cmd, { qos: 1 });
      log(`[FAN] ${name}: ${cmd} (${sensorLabel}, 범위 ${range.low}~${range.high})`);
    }
  });
}

// ─── 히트시스템 ON/OFF 제어 ───────────────────────────────────────────────────
function runHpAutoControl(mqttClient, avgTemp) {
  if (hp.mode !== 'AUTO' || !hp.autoActive) return;

  let activeRanges;
  if (hp.dayNight.enabled) {
    const period = getCurrentPeriod(hp.dayNight.dayStart, hp.dayNight.nightStart);
    const now = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Seoul' }));
    log(`[HP] ${period === 'day' ? '주간' : '야간'} 모드 (${now.getHours()}:${String(now.getMinutes()).padStart(2,'0')})`);
    activeRanges = hp.dayNight[period].ranges;
  } else {
    activeRanges = hp.ranges;
  }

  const devices = [
    { key: 'hp_pump',   mqttId: 'pump',   name: '히트펌프 순환펌프', temp: avgTemp   },
    { key: 'hp_heater', mqttId: 'heater', name: '냉각기',            temp: waterTemp },
    { key: 'hp_fan',    mqttId: 'fan',    name: '열교환기 팬',       temp: roomTemp  },
  ];

  devices.forEach(({ key, mqttId, name, temp }) => {
    if (temp === null) {
      log(`[HP] ${name}: 온도 데이터 없음 — OFF 명령`);
      if (hp.lastCmd[key] !== 'OFF') {
        hp.lastCmd[key] = 'OFF';
        mqttClient.publish(`tansaeng/ctlr-heat-001/${mqttId}/cmd`, 'OFF', { qos: 1 });
      }
      return;
    }
    const range = activeRanges[key] ?? hp.ranges[key];
    if (!range) return;
    const { low, high } = range;
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

  // 유효한 온도/습도값 수집
  const temps = [sensorData.front?.temperature, sensorData.back?.temperature, sensorData.top?.temperature]
    .filter(t => typeof t === 'number' && !isNaN(t));
  const humis = [sensorData.front?.humidity, sensorData.back?.humidity, sensorData.top?.humidity]
    .filter(h => typeof h === 'number' && !isNaN(h));

  const avgTemp = temps.length > 0 ? temps.reduce((a, b) => a + b, 0) / temps.length : null;
  const avgHumi = humis.length > 0 ? humis.reduce((a, b) => a + b, 0) / humis.length : null;
  if (avgTemp !== null) log(`[TEMP] 평균온도: ${avgTemp.toFixed(1)}°C (센서 ${temps.length}개)`);

  // 팬 AUTO 제어
  runFanAutoControl(mqttClient, avgTemp, avgHumi);

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
    } else if (key === 'side' && ctrlState.autoType === 'time') {
      // 측창 시간 기준 제어
      targetRate = calcTargetRateByTime(ctrlState.timePoints);
      const now = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Seoul' }));
      log(`[${ctrlState.label}] TIME AUTO 목표개도율: ${targetRate}% (현재 ${now.getHours()}:${String(now.getMinutes()).padStart(2,'0')})`);
    } else if (key === 'side' && ctrlState.autoType === 'daynight') {
      // 측창 주간/야간 기준 제어
      const cfg = ctrlState.dayNightConfig;
      const period = getCurrentPeriod(cfg.dayStart, cfg.nightStart);
      const periodCfg = cfg[period];
      const now = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Seoul' }));
      const periodLabel = period === 'day' ? '주간' : '야간';
      log(`[${ctrlState.label}] ${periodLabel} 모드 (${now.getHours()}:${String(now.getMinutes()).padStart(2,'0')})`);
      if (periodCfg.sensor === 'humi') {
        const periodHumis = [sensorData.front?.humidity, sensorData.back?.humidity, sensorData.top?.humidity]
          .filter(h => typeof h === 'number' && !isNaN(h));
        if (periodHumis.length === 0) { log(`[WARN] 습도 없음 — ${ctrlState.label} ${periodLabel} HUMI 건너뜀`); continue; }
        const avg = periodHumis.reduce((a, b) => a + b, 0) / periodHumis.length;
        const pts = [...periodCfg.humPoints].sort((a, b) => a.humi - b.humi);
        if (avg < pts[0].humi) targetRate = 0;
        else if (avg >= pts[pts.length - 1].humi) targetRate = 100;
        else {
          targetRate = 0;
          for (let i = 0; i < pts.length - 1; i++) {
            if (avg >= pts[i].humi && avg < pts[i + 1].humi) {
              targetRate = Math.round(pts[i].rate + (avg - pts[i].humi) / (pts[i + 1].humi - pts[i].humi) * (pts[i + 1].rate - pts[i].rate));
              break;
            }
          }
        }
        log(`[${ctrlState.label}] ${periodLabel} HUMI AUTO 목표개도율: ${targetRate}% (평균습도 ${avg.toFixed(0)}%RH)`);
      } else {
        if (avgTemp === null) { log(`[WARN] 온도 없음 — ${ctrlState.label} ${periodLabel} TEMP 건너뜀`); continue; }
        targetRate = calcTargetRate(avgTemp, periodCfg.tempPoints);
        log(`[${ctrlState.label}] ${periodLabel} TEMP AUTO 목표개도율: ${targetRate}% (${avgTemp.toFixed(1)}°C)`);
      }
    } else if (key === 'side' && ctrlState.autoSensor === 'humi') {
      // 측창 습도 기준 제어
      const humis = [sensorData.front?.humidity, sensorData.back?.humidity, sensorData.top?.humidity]
        .filter(h => typeof h === 'number' && !isNaN(h));
      if (humis.length === 0) {
        log(`[WARN] 습도 데이터 없음 — ${ctrlState.label} HUMI AUTO 건너뜀`);
        continue;
      }
      const avgHumi = humis.reduce((a, b) => a + b, 0) / humis.length;
      const humSorted = [...ctrlState.humPoints].sort((a, b) => a.humi - b.humi);
      if (avgHumi < humSorted[0].humi) targetRate = 0;
      else if (avgHumi >= humSorted[humSorted.length - 1].humi) targetRate = 100;
      else {
        targetRate = 0;
        for (let i = 0; i < humSorted.length - 1; i++) {
          if (avgHumi >= humSorted[i].humi && avgHumi < humSorted[i + 1].humi) {
            const ratio = (avgHumi - humSorted[i].humi) / (humSorted[i + 1].humi - humSorted[i].humi);
            targetRate = Math.round(humSorted[i].rate + ratio * (humSorted[i + 1].rate - humSorted[i].rate));
            break;
          }
        }
      }
      log(`[${ctrlState.label}] HUMI AUTO 목표개도율: ${targetRate}% (평균습도 ${avgHumi.toFixed(0)}%RH)`);
    } else {
      // 온도 기준 제어 (측창 기본 + 천창 temp)
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
      'tansaeng/sky-control/fullTimeSeconds/left',
      'tansaeng/sky-control/fullTimeSeconds/right',
      'tansaeng/side-control/mode',
      'tansaeng/side-control/autoActive',
      'tansaeng/side-control/autoType',
      'tansaeng/side-control/autoSensor',
      'tansaeng/side-control/tempPoints',
      'tansaeng/side-control/humPoints',
      'tansaeng/side-control/timePoints',
      'tansaeng/side-control/fullTimeSeconds/left',
      'tansaeng/side-control/fullTimeSeconds/right',
      // 팬 제어
      'tansaeng/fan-control/mode',
      'tansaeng/fan-control/autoActive',
      'tansaeng/fan-control/autoSensor',
      'tansaeng/fan-control/ranges',
      'tansaeng/fan-control/humRanges',
      'tansaeng/fan-control/dayNightConfig',
      // 히트시스템
      'tansaeng/hp-control/mode',
      'tansaeng/hp-control/autoActive',
      'tansaeng/hp-control/ranges',
      'tansaeng/hp-control/dayNightConfig',
      // 측창 주야간
      'tansaeng/side-control/dayNightConfig',
      // 장치제어실 내부 온도 (팬 제어용)
      'tansaeng/ctlr-heat-001/air/temperature',
      // 냉각수 온도 (hp_heater 제어용)
      'tansaeng/ctlr-heat-001/water/temperature',
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

    } else if (topic === 'tansaeng/side-control/autoType') {
      if (payload === 'temp' || payload === 'time' || payload === 'daynight') {
        ctrl.side.autoType = payload;
        ctrl.side.lastTarget = {};
        log(`[설정] 측창 autoType: ${ctrl.side.autoType}`);
      }

    } else if (topic === 'tansaeng/side-control/autoSensor') {
      if (payload === 'temp' || payload === 'humi') {
        ctrl.side.autoSensor = payload;
        ctrl.side.lastTarget = {};
        log(`[설정] 측창 autoSensor: ${ctrl.side.autoSensor}`);
      }

    } else if (topic === 'tansaeng/side-control/humPoints') {
      try {
        const parsed = JSON.parse(payload);
        if (Array.isArray(parsed) && parsed.length >= 2) {
          ctrl.side.humPoints = parsed;
          log(`[설정] 측창 humPoints: ${JSON.stringify(ctrl.side.humPoints)}`);
        }
      } catch (_) {}

    } else if (topic === 'tansaeng/side-control/timePoints') {
      try {
        const parsed = JSON.parse(payload);
        if (Array.isArray(parsed) && parsed.length >= 2) {
          ctrl.side.timePoints = parsed;
          log(`[설정] 측창 timePoints: ${JSON.stringify(ctrl.side.timePoints)}`);
        }
      } catch (_) {}

    } else if (topic === 'tansaeng/side-control/dayNightConfig') {
      try {
        const parsed = JSON.parse(payload);
        if (parsed && typeof parsed === 'object') {
          ctrl.side.dayNightConfig = { ...ctrl.side.dayNightConfig, ...parsed };
          ctrl.side.lastTarget = {};
          log(`[설정] 측창 dayNightConfig 업데이트`);
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

    } else if (topic === 'tansaeng/fan-control/autoSensor') {
      if (payload === 'temp' || payload === 'humi') {
        fan.autoSensor = payload;
        fan.lastCmd = {};
        log(`[FAN] autoSensor: ${fan.autoSensor}`);
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

    } else if (topic === 'tansaeng/fan-control/humRanges') {
      try {
        const parsed = JSON.parse(payload);
        if (parsed && typeof parsed === 'object') {
          fan.humRanges = { ...fan.humRanges, ...parsed };
          fan.lastCmd   = {};
          log(`[FAN] 습도 범위 업데이트: ${JSON.stringify(fan.humRanges)}`);
        }
      } catch (_) {}

    } else if (topic === 'tansaeng/fan-control/dayNightConfig') {
      try {
        const parsed = JSON.parse(payload);
        if (parsed && typeof parsed === 'object') {
          fan.dayNight = { ...fan.dayNight, ...parsed };
          fan.lastCmd  = {};
          log(`[FAN] dayNightConfig 업데이트 (enabled: ${fan.dayNight.enabled})`);
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
          hp.lastCmd = { hp_pump: null, hp_heater: null, hp_fan: null };
          log(`[HP] 온도 범위 업데이트: ${JSON.stringify(hp.ranges)}`);
        }
      } catch (_) {}

    } else if (topic === 'tansaeng/hp-control/dayNightConfig') {
      try {
        const parsed = JSON.parse(payload);
        if (parsed && typeof parsed === 'object') {
          hp.dayNight = { ...hp.dayNight, ...parsed };
          hp.lastCmd  = { hp_pump: null, hp_heater: null, hp_fan: null };
          log(`[HP] dayNightConfig 업데이트 (enabled: ${hp.dayNight.enabled})`);
        }
      } catch (_) {}

    } else if (topic === 'tansaeng/ctlr-heat-001/water/temperature') {
      const n = parseFloat(payload);
      if (!isNaN(n)) {
        waterTemp = n;
        log(`[HP] 냉각수 온도: ${waterTemp.toFixed(1)}°C`);
      }

    } else if (topic === 'tansaeng/ctlr-heat-001/air/temperature') {
      const n = parseFloat(payload);
      if (!isNaN(n)) {
        roomTemp = n;
        // 팬 제어: 온도 수신 시마다 즉시 평가
        if (hp.mode === 'AUTO' && hp.autoActive) {
          const fanRange = hp.dayNight.enabled
            ? (hp.dayNight[getCurrentPeriod(hp.dayNight.dayStart, hp.dayNight.nightStart)].ranges.hp_fan ?? hp.ranges.hp_fan)
            : hp.ranges.hp_fan;
          const { low, high } = fanRange;
          const cmd = (roomTemp >= low && roomTemp <= high) ? 'ON' : 'OFF';
          if (hp.lastCmd.hp_fan !== cmd) {
            hp.lastCmd.hp_fan = cmd;
            client.publish('tansaeng/ctlr-heat-001/fan/cmd', cmd, { qos: 1 });
            log(`[HP] 열교환기 팬: ${cmd} (실내온도 ${roomTemp.toFixed(1)}°C, 범위 ${low}~${high}°C)`);
          }
        }
      }

    // ── 개폐 기준시간 설정 ───────────────────────────────────────────────────
    } else if (topic === 'tansaeng/sky-control/fullTimeSeconds/left') {
      const s = parseInt(payload, 10);
      if (!isNaN(s) && s >= 10 && s <= 3600) {
        ctrl.sky.fullTimeSMap['skylight_left'] = s;
        log(`[설정] 천창 좌측 개폐 기준시간: ${s}초`);
      }

    } else if (topic === 'tansaeng/sky-control/fullTimeSeconds/right') {
      const s = parseInt(payload, 10);
      if (!isNaN(s) && s >= 10 && s <= 3600) {
        ctrl.sky.fullTimeSMap['skylight_right'] = s;
        log(`[설정] 천창 우측 개폐 기준시간: ${s}초`);
      }

    } else if (topic === 'tansaeng/side-control/fullTimeSeconds/left') {
      const s = parseInt(payload, 10);
      if (!isNaN(s) && s >= 10 && s <= 3600) {
        ctrl.side.fullTimeSMap['sidescreen_left'] = s;
        log(`[설정] 측창 좌측 개폐 기준시간: ${s}초`);
      }

    } else if (topic === 'tansaeng/side-control/fullTimeSeconds/right') {
      const s = parseInt(payload, 10);
      if (!isNaN(s) && s >= 10 && s <= 3600) {
        ctrl.side.fullTimeSMap['sidescreen_right'] = s;
        log(`[설정] 측창 우측 개폐 기준시간: ${s}초`);
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

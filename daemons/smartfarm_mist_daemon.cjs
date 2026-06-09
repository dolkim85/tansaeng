#!/usr/bin/env node
/**
 * 스마트팜 분무수경 자동 제어 데몬 (Node.js)
 *
 * - MQTT retain에서 isRunning / schedule 읽음 (브라우저 설정과 동기화)
 * - AUTO + isRunning=true 인 존: 분무(ON) → 정지(OFF) → 반복
 * - 모든 실행 중인 존이 공유 사이클로 동시에 ON/OFF (위상 동기화)
 * - 주간/야간 시간대 체크 후 해당 스케줄 적용 (첫 번째 활성 존 기준)
 * - 각 전환 시 timerState retain 발행 (브라우저 타이머 동기화)
 */

const mqtt  = require('mqtt');
const fs    = require('fs');
const path  = require('path');
const https = require("https");
const http  = require("http");

// ─── 설정 ────────────────────────────────────────────────────────────────────
const MQTT_HOST     = '22ada06fd6cf4059bd700ddbf6004d68.s1.eu.hivemq.cloud';
const MQTT_PORT     = 8883;
const MQTT_USERNAME = 'esp32-client-01';
const MQTT_PASSWORD = 'Qjawns3445';

const SETTINGS_FILE = path.join(__dirname, '../config/device_settings.json');
const ALERT_FILE    = path.join(__dirname, '../config/alert_config.json');
const LOG_FILE      = path.join(__dirname, '../logs/mist_daemon.log');

// ─── Zone 정의 ───────────────────────────────────────────────────────────────
const ZONES = {
  zone_a:  { name: '구역A', controllerId: 'ctlr-0004', deviceId: 'valve1' },
  zone_b:  { name: '구역B', controllerId: 'ctlr-0005', deviceId: 'valve1' },
  zone_c:  { name: '구역C', controllerId: 'ctlr-0006', deviceId: 'valve1' },
  zone_d:  { name: '구역D', controllerId: 'ctlr-0007', deviceId: 'valve1' },
  zone_e:  { name: '구역E', controllerId: 'ctlr-0008', deviceId: 'valve1' },
  fogging: { name: '포깅',   controllerId: 'ctlr-0004', deviceId: 'valve2' },
};

// ─── 존별 상태 (isRunning / 스케줄 / 습도 설정 보관) ────────────────────────
const zoneState = {};
Object.keys(ZONES).forEach(id => {
  zoneState[id] = {
    isRunning:       false,
    mode:            'OFF',
    daySchedule:     null,
    nightSchedule:   null,
    humidityControl: null,  // { enabled, threshold } — 포깅 등에서 사용
  };
});

// ─── 팜 습도 센서 (front/back/top 평균) ─────────────────────────────────────
const humidity = { front: null, back: null, top: null };
const HUMIDITY_CTRL_TO_LOC = { 'ctlr-0001': 'front', 'ctlr-0002': 'back', 'ctlr-0003': 'top' };

function getAvgHumidity() {
  const vals = [humidity.front, humidity.back, humidity.top].filter(v => v !== null);
  return vals.length > 0 ? vals.reduce((a, b) => a + b, 0) / vals.length : null;
}

// ─── 구역별 독립 사이클 상태 (각 구역이 자기 스케줄대로 동작) ─────────────────
const zoneCycle = {};
Object.keys(ZONES).forEach(id => {
  zoneCycle[id] = { sprayTimer: null, stopTimer: null, cycling: false };
});

// ─── 로그 ────────────────────────────────────────────────────────────────────
function log(msg) {
  const ts = new Date().toLocaleString('ko-KR', { timeZone: 'Asia/Seoul' });
  const line = `[${ts}] ${msg}`;
  console.log(line);
  try { fs.appendFileSync(LOG_FILE, line + '\n'); } catch (_) {}
}

// ─── DB 분무 이벤트 로그 ─────────────────────────────────────────────────────
function saveMistLog(zoneId, zoneName, eventType, mode) {
  try {
    const body = JSON.stringify({ zone_id: zoneId, zone_name: zoneName, event_type: eventType, mode });
    const req = https.request({
      hostname: 'www.tansaeng.com',
      path:     '/api/smartfarm/save_mist_log.php',
      method:   'POST',
      headers:  { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(body) },
    });
    req.on('error', (e) => log(`[LOG DB 오류] ${e.message}`));
    req.write(body);
    req.end();
  } catch (_) {}
}

// ─── 텔레그램 알림 ───────────────────────────────────────────────────────────
function sendTelegram(message) {
  try {
    const config = JSON.parse(fs.readFileSync(ALERT_FILE, 'utf8'));
    const t = config?.telegram;
    if (!t?.enabled || !t?.bot_token || !t?.chat_id) return;

    const body = JSON.stringify({ chat_id: t.chat_id, text: message, parse_mode: 'HTML' });
    const req = https.request({
      hostname: 'api.telegram.org',
      path:     `/bot${t.bot_token}/sendMessage`,
      method:   'POST',
      headers:  { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(body) },
    });
    req.on('error', () => {});
    req.write(body);
    req.end();
  } catch (_) {}
}

function seoulTime() {
  return new Date().toLocaleString('ko-KR', { timeZone: 'Asia/Seoul', hour12: false });
}

// ─── 현재 시각 → 해당 스케줄 반환 ───────────────────────────────────────────
function getCurrentSchedule(state) {
  const now = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Seoul' }));
  const cur = now.getHours() * 60 + now.getMinutes();

  const parse = t => { if (!t) return 0; const [h, m] = t.split(':').map(Number); return h * 60 + m; };

  if (state.daySchedule?.enabled) {
    const s = parse(state.daySchedule.startTime);
    const e = parse(state.daySchedule.endTime);
    if (s <= cur && cur < e) return state.daySchedule;
  }
  if (state.nightSchedule?.enabled) {
    const s = parse(state.nightSchedule.startTime);
    const e = parse(state.nightSchedule.endTime);
    if (s > e) {
      if (cur >= s || cur < e) return state.nightSchedule;
    } else {
      if (s <= cur && cur < e) return state.nightSchedule;
    }
  }
  return null;
}

// ─── 현재 활성 존 목록 (AUTO + isRunning=true) ───────────────────────────────
function getActiveZones() {
  return Object.keys(ZONES).filter(id =>
    zoneState[id].isRunning && zoneState[id].mode === 'AUTO'
  );
}

// ─── 특정 구역 사이클 중지 ───────────────────────────────────────────────────
function stopZoneCycle(zoneId) {
  const zc = zoneCycle[zoneId];
  if (zc.sprayTimer) { clearTimeout(zc.sprayTimer); zc.sprayTimer = null; }
  if (zc.stopTimer)  { clearTimeout(zc.stopTimer);  zc.stopTimer  = null; }
  zc.cycling = false;
}

// ─── 모든 구역 사이클 중지 ───────────────────────────────────────────────────
function stopAllCycles() {
  Object.keys(ZONES).forEach(stopZoneCycle);
}

// ─── 특정 구역 독립 사이클 시작 (자기 스케줄대로 분무/정지 반복) ─────────────
function startZoneCycle(mqttClient, zoneId) {
  stopZoneCycle(zoneId);

  const st = zoneState[zoneId];
  if (!st.isRunning || st.mode !== 'AUTO') return;

  const zc = zoneCycle[zoneId];
  zc.cycling = true;
  log(`[${ZONES[zoneId].name}] 사이클 시작`);

  // 분무 단계 — 매번 현재 시각 기준 스케줄 재평가 (주간↔야간 자동 전환)
  const doSpray = () => {
    if (!zc.cycling) return;
    if (!st.isRunning || st.mode !== 'AUTO') { zc.cycling = false; return; }

    const schedule = getCurrentSchedule(st);
    if (!schedule) {
      log(`[${ZONES[zoneId].name}] 현재 시간대 스케줄 없음 — 1분 후 재확인`);
      zc.cycling = false;
      zc.stopTimer = setTimeout(() => startZoneCycle(mqttClient, zoneId), 60 * 1000);
      return;
    }

    const sprayMs = (schedule.sprayDurationSeconds ?? 0) * 1000;
    const stopMs  = (schedule.stopDurationSeconds  ?? 0) * 1000;
    if (sprayMs <= 0) {
      log(`[${ZONES[zoneId].name}] sprayDurationSeconds 미설정 — 사이클 불가`);
      zc.cycling = false;
      return;
    }

    const ts = Date.now();
    const avgH = getAvgHumidity();

    // (a) 습도 조건 체크 — 미충족이면 이 구역만 건너뛰고 정지시간 후 재평가
    const hCtrl = st.humidityControl;
    if (hCtrl?.enabled && avgH !== null && avgH >= hCtrl.threshold) {
      log(`[${ZONES[zoneId].name}] 습도 조건 미충족 (${avgH.toFixed(1)}% >= 기준 ${hCtrl.threshold}%) → 건너뜀`);
      // 분무도 안 하고 텔레그램도 안 보냄 — 정지시간만큼 대기 후 다시 평가
      zc.stopTimer = setTimeout(doSpray, Math.max(stopMs, 1000));
      return;
    }

    // 실제 분무 실행
    mqttClient.publish(`tansaeng/${ZONES[zoneId].controllerId}/${ZONES[zoneId].deviceId}/cmd`, 'OPEN', { qos: 1 });
    mqttClient.publish(`tansaeng/mist-control/${zoneId}/timerState`, JSON.stringify({ state: 'OPEN', timestamp: ts }), { qos: 1, retain: true });
    saveMistLog(zoneId, ZONES[zoneId].name, 'start', st.mode || 'AUTO');
    log(`[${ZONES[zoneId].name}] OPEN — 분무 ${sprayMs/1000}s / 정지 ${stopMs/1000}s${avgH !== null ? ` / 습도 ${avgH.toFixed(1)}%` : ''}`);
    // (a) 실제 분무했을 때만 텔레그램 발송
    sendTelegram(`💧 <b>분무수경 분무 시작</b>\n구역: ${ZONES[zoneId].name}\n지속: ${sprayMs/1000}초\n시각: ${seoulTime()}`);

    zc.sprayTimer = setTimeout(() => doStop(stopMs), sprayMs);
  };

  // 정지 단계
  const doStop = (stopMs) => {
    if (!zc.cycling) return;

    const ts = Date.now();
    mqttClient.publish(`tansaeng/${ZONES[zoneId].controllerId}/${ZONES[zoneId].deviceId}/cmd`, 'CLOSE', { qos: 1 });
    mqttClient.publish(`tansaeng/mist-control/${zoneId}/timerState`, JSON.stringify({ state: 'CLOSE', timestamp: ts }), { qos: 1, retain: true });
    saveMistLog(zoneId, ZONES[zoneId].name, 'stop', st.mode || 'AUTO');
    log(`[${ZONES[zoneId].name}] CLOSE — 정지 ${stopMs/1000}s`);
    sendTelegram(`⏸ <b>분무수경 대기 시작</b>\n구역: ${ZONES[zoneId].name}\n대기: ${stopMs/1000}초\n시각: ${seoulTime()}`);

    zc.stopTimer = setTimeout(doSpray, Math.max(stopMs, 500));
  };

  doSpray();
}

// ─── 모든 활성 구역의 독립 사이클 시작 ───────────────────────────────────────
function startActiveCycles(mqttClient) {
  const active = getActiveZones();
  if (active.length === 0) {
    log('[CYCLE] 활성 구역 없음 — 대기');
    return;
  }
  active.forEach(zoneId => startZoneCycle(mqttClient, zoneId));
}

// ─── MQTT 연결 및 구독 ────────────────────────────────────────────────────────
function main() {
  log('=== 분무수경 자동 제어 데몬 시작 (공유 사이클) ===');

  const client = mqtt.connect(`mqtts://${MQTT_HOST}:${MQTT_PORT}`, {
    host:               MQTT_HOST,
    port:               MQTT_PORT,
    protocol:           'mqtts',
    username:           MQTT_USERNAME,
    password:           MQTT_PASSWORD,
    clientId:           'tansaeng_mist_' + Math.random().toString(16).substring(2, 8),
    reconnectPeriod:    5000,
    rejectUnauthorized: false,
  });

  let firstConnect = true;

  client.on('connect', () => {
    log('HiveMQ Cloud 연결 성공');

    const topics = [];
    Object.keys(ZONES).forEach(zoneId => {
      topics.push(`tansaeng/mist-control/${zoneId}/isRunning`);
      topics.push(`tansaeng/mist-control/${zoneId}/schedule`);
      topics.push(`tansaeng/mist-control/${zoneId}/humidityConfig`);
    });
    // 팜 습도 센서 구독
    topics.push('tansaeng/ctlr-0001/+/humidity');
    topics.push('tansaeng/ctlr-0002/+/humidity');
    topics.push('tansaeng/ctlr-0003/+/humidity');

    topics.forEach(t => client.subscribe(t, { qos: 1 }, err => {
      if (err) log(`구독 실패 ${t}: ${err.message}`);
      else     log(`구독: ${t}`);
    }));

    if (firstConnect) {
      firstConnect = false;
      // 첫 연결: 서버 설정 파일에서 초기 상태 로드 (retain 도착 전 fallback)
      setTimeout(() => loadSettingsFromFile(client), 3000);
    } else {
      // (c) 재연결: 진행 중이던 활성 구역 사이클을 재시작해 밸브 상태 재동기화
      //     (연결 끊긴 동안 ESP32가 재부팅돼 밸브가 닫혔을 수 있으므로 다음 분무를 즉시 재개)
      log('[CYCLE] 재연결 감지 — 활성 구역 사이클 재동기화');
      setTimeout(() => startActiveCycles(client), 1000);
    }
  });

  client.on('message', (topic, message) => {
    const payload = message.toString().trim();

    Object.keys(ZONES).forEach(zoneId => {
      if (topic === `tansaeng/mist-control/${zoneId}/isRunning`) {
        const running = zoneState[zoneId].isRunning;
        const newRunning = payload === 'true';
        zoneState[zoneId].isRunning = newRunning;
        log(`[${ZONES[zoneId].name}] isRunning: ${newRunning}`);

        if (running !== newRunning) {
          // (b) 해당 구역만 독립적으로 시작/중지 (다른 구역에 영향 없음)
          if (newRunning && zoneState[zoneId].mode === 'AUTO') {
            log(`[${ZONES[zoneId].name}] 활성화 → 독립 사이클 시작`);
            startZoneCycle(client, zoneId);
          } else if (!newRunning) {
            // 이 구역만 사이클 중지 + 밸브 닫기
            stopZoneCycle(zoneId);
            client.publish(
              `tansaeng/${ZONES[zoneId].controllerId}/${ZONES[zoneId].deviceId}/cmd`, 'CLOSE', { qos: 1 }
            );
            log(`[${ZONES[zoneId].name}] 중지 — 밸브 OFF`);
          }
        }
      }

      if (topic === `tansaeng/mist-control/${zoneId}/schedule`) {
        try {
          const parsed = JSON.parse(payload);
          if (parsed.mode)          zoneState[zoneId].mode          = parsed.mode;
          if (parsed.daySchedule)   zoneState[zoneId].daySchedule   = parsed.daySchedule;
          if (parsed.nightSchedule) zoneState[zoneId].nightSchedule = parsed.nightSchedule;
          log(`[${ZONES[zoneId].name}] 스케줄 업데이트: mode=${parsed.mode}`);

          // 실행 중이었으면 이 구역만 새 스케줄로 재시작
          if (zoneState[zoneId].isRunning && zoneState[zoneId].mode === 'AUTO') {
            startZoneCycle(client, zoneId);
          }
        } catch (e) {
          log(`[${ZONES[zoneId].name}] 스케줄 파싱 오류: ${e.message}`);
        }
      }

      if (topic === `tansaeng/mist-control/${zoneId}/humidityConfig`) {
        try {
          const parsed = JSON.parse(payload);
          if (parsed && typeof parsed === 'object') {
            zoneState[zoneId].humidityControl = { enabled: !!parsed.enabled, threshold: Number(parsed.threshold) || 70 };
            log(`[${ZONES[zoneId].name}] 습도 조건 업데이트: enabled=${parsed.enabled}, threshold=${parsed.threshold}%`);
          }
        } catch (e) {
          log(`[${ZONES[zoneId].name}] humidityConfig 파싱 오류: ${e.message}`);
        }
      }
    });

    // 팜 습도 센서 수신
    const parts = topic.split('/');
    if (parts.length === 4 && parts[0] === 'tansaeng' && parts[3] === 'humidity') {
      const loc = HUMIDITY_CTRL_TO_LOC[parts[1]];
      if (loc) {
        const val = parseFloat(payload);
        if (!isNaN(val) && val >= 0 && val <= 100) {
          humidity[loc] = val;
        }
      }
    }
  });

  client.on('error',     e  => log(`[MQTT ERROR] ${e.message}`));
  client.on('offline',   () => log('[MQTT] 연결 끊김 — 재연결 중...'));
  client.on('reconnect', () => log('[MQTT] 재연결 시도...'));

  process.on('SIGTERM', () => { log('SIGTERM — 종료'); stopAllCycles(); client.end(); process.exit(0); });
  process.on('SIGINT',  () => { log('SIGINT — 종료');  stopAllCycles(); client.end(); process.exit(0); });
}

function loadSettingsFromFile(client) {
  try {
    const raw  = fs.readFileSync(SETTINGS_FILE, 'utf8');
    const data = JSON.parse(raw);
    const zones = data.mist_zones ?? {};

    Object.keys(ZONES).forEach(zoneId => {
      const z = zones[zoneId];
      if (!z) return;
      zoneState[zoneId].mode          = z.mode          ?? 'OFF';
      zoneState[zoneId].daySchedule   = z.daySchedule   ?? null;
      zoneState[zoneId].nightSchedule = z.nightSchedule ?? null;
      log(`[${ZONES[zoneId].name}] 설정 로드: mode=${zoneState[zoneId].mode}`);
    });

    // 설정 로드 후 활성 구역이 있으면 각 구역 독립 사이클 시작
    const active = getActiveZones();
    if (active.length > 0) {
      log(`[CYCLE] 설정 로드 완료 — 활성 구역: [${active.join(', ')}] → 독립 사이클 시작`);
      startActiveCycles(client);
    }
  } catch (e) {
    log(`[ERROR] 설정 파일 로드 실패: ${e.message}`);
  }
}

main();

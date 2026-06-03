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
const https = require('https');

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
  zone_a: { name: 'Zone A', controllerId: 'ctlr-0004' },
  zone_b: { name: 'Zone B', controllerId: 'ctlr-0005' },
  zone_c: { name: 'Zone C', controllerId: 'ctlr-0006' },
  zone_d: { name: 'Zone D', controllerId: 'ctlr-0007' },
  zone_e: { name: 'Zone E', controllerId: 'ctlr-0008' },
};

// ─── 존별 상태 (isRunning / 스케줄 설정만 보관) ─────────────────────────────
const zoneState = {};
Object.keys(ZONES).forEach(id => {
  zoneState[id] = {
    isRunning:     false,
    mode:          'OFF',
    daySchedule:   null,
    nightSchedule: null,
  };
});

// ─── 공유 사이클 상태 (모든 활성 존이 동시에 동작) ──────────────────────────
const sharedCycle = {
  sprayTimer: null,
  stopTimer:  null,
  cycling:    false,
};

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
      hostname: '1.201.17.34',
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

// ─── 공유 사이클 중지 ─────────────────────────────────────────────────────────
function stopSharedCycle() {
  if (sharedCycle.sprayTimer) { clearTimeout(sharedCycle.sprayTimer); sharedCycle.sprayTimer = null; }
  if (sharedCycle.stopTimer)  { clearTimeout(sharedCycle.stopTimer);  sharedCycle.stopTimer  = null; }
  sharedCycle.cycling = false;
}

// ─── 공유 사이클 시작 (모든 활성 존 동시 제어) ───────────────────────────────
function startSharedCycle(mqttClient) {
  stopSharedCycle();

  const activeZones = getActiveZones();
  if (activeZones.length === 0) {
    log('[CYCLE] 활성 존 없음 — 사이클 대기');
    return;
  }

  sharedCycle.cycling = true;
  log(`[CYCLE] 공유 사이클 시작 — 존: [${activeZones.join(', ')}]`);

  // doSpray: 매 사이클마다 스케줄 재확인 (주간↔야간 전환 자동 적용)
  const doSpray = () => {
    if (!sharedCycle.cycling) return;
    const zones = getActiveZones();
    if (zones.length === 0) { sharedCycle.cycling = false; return; }

    // 매번 현재 시각 기준 스케줄 재평가
    const schedule = getCurrentSchedule(zoneState[zones[0]]);
    if (!schedule) {
      log('[CYCLE] 현재 시간대 스케줄 없음 — 1분 후 재확인');
      sharedCycle.cycling = false;
      sharedCycle.stopTimer = setTimeout(() => startSharedCycle(mqttClient), 60 * 1000);
      return;
    }

    const sprayMs = (schedule.sprayDurationSeconds ?? 0) * 1000;
    const stopMs  = (schedule.stopDurationSeconds  ?? 0) * 1000;
    if (sprayMs <= 0) { log('[CYCLE] sprayDurationSeconds 미설정 — 사이클 불가'); sharedCycle.cycling = false; return; }

    const ts = Date.now();
    const zoneNames = zones.map(id => ZONES[id].name).join(', ');
    log(`[CYCLE] OPEN → 존: [${zones.join(', ')}] / 분무 ${sprayMs/1000}s / 정지 ${stopMs/1000}s`);
    zones.forEach(zoneId => {
      mqttClient.publish(`tansaeng/${ZONES[zoneId].controllerId}/valve1/cmd`, 'OPEN', { qos: 1 });
      mqttClient.publish(`tansaeng/mist-control/${zoneId}/timerState`, JSON.stringify({ state: 'OPEN', timestamp: ts }), { qos: 1, retain: true });
      saveMistLog(zoneId, ZONES[zoneId].name, 'start', zoneState[zoneId].mode || 'AUTO');
    });
    sendTelegram(`💧 <b>분무수경 분무 시작</b>\n구역: ${zoneNames}\n지속: ${sprayMs/1000}초\n시각: ${seoulTime()}`);

    sharedCycle.sprayTimer = setTimeout(() => doStop(stopMs, zoneNames), sprayMs);
  };

  const doStop = (stopMs, zoneNames) => {
    if (!sharedCycle.cycling) return;
    const zones = getActiveZones();

    const ts = Date.now();
    log(`[CYCLE] CLOSE → 존: [${zones.join(', ')}] / 정지 ${stopMs/1000}s`);
    zones.forEach(zoneId => {
      mqttClient.publish(`tansaeng/${ZONES[zoneId].controllerId}/valve1/cmd`, 'CLOSE', { qos: 1 });
      mqttClient.publish(`tansaeng/mist-control/${zoneId}/timerState`, JSON.stringify({ state: 'CLOSE', timestamp: ts }), { qos: 1, retain: true });
      saveMistLog(zoneId, ZONES[zoneId].name, 'stop', zoneState[zoneId].mode || 'AUTO');
    });
    sendTelegram(`⏸ <b>분무수경 대기 시작</b>\n구역: ${zoneNames}\n대기: ${stopMs/1000}초\n시각: ${seoulTime()}`);

    sharedCycle.stopTimer = setTimeout(() => {
      if (getActiveZones().length === 0) { log('[CYCLE] 활성 존 없음 — 사이클 종료'); sharedCycle.cycling = false; return; }
      doSpray(); // 다음 사이클 시작 시 스케줄 재평가
    }, Math.max(stopMs, 500));
  };

  doSpray();
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

  client.on('connect', () => {
    log('HiveMQ Cloud 연결 성공');

    const topics = [];
    Object.keys(ZONES).forEach(zoneId => {
      topics.push(`tansaeng/mist-control/${zoneId}/isRunning`);
      topics.push(`tansaeng/mist-control/${zoneId}/schedule`);
    });

    topics.forEach(t => client.subscribe(t, { qos: 1 }, err => {
      if (err) log(`구독 실패 ${t}: ${err.message}`);
      else     log(`구독: ${t}`);
    }));

    // 서버 설정 파일에서 초기 상태 로드 (retain 도착 전 fallback)
    setTimeout(() => loadSettingsFromFile(client), 3000);
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
          // 상태 변경 → 공유 사이클 재시작
          if (newRunning && zoneState[zoneId].mode === 'AUTO') {
            log(`[${ZONES[zoneId].name}] 활성화 → 공유 사이클 재시작`);
            startSharedCycle(client);
          } else if (!newRunning) {
            // 이 존의 밸브 닫기
            client.publish(
              `tansaeng/${ZONES[zoneId].controllerId}/valve1/cmd`, 'CLOSE', { qos: 1 }
            );
            log(`[${ZONES[zoneId].name}] 중지 — 밸브 OFF`);
            // 남은 활성 존이 있으면 계속, 없으면 사이클 종료
            if (getActiveZones().length === 0) {
              stopSharedCycle();
              log('[CYCLE] 모든 존 중지 — 사이클 종료');
            }
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

          // 실행 중이었으면 새 스케줄로 재시작
          if (zoneState[zoneId].isRunning && zoneState[zoneId].mode === 'AUTO') {
            startSharedCycle(client);
          }
        } catch (e) {
          log(`[${ZONES[zoneId].name}] 스케줄 파싱 오류: ${e.message}`);
        }
      }
    });
  });

  client.on('error',     e  => log(`[MQTT ERROR] ${e.message}`));
  client.on('offline',   () => log('[MQTT] 연결 끊김 — 재연결 중...'));
  client.on('reconnect', () => log('[MQTT] 재연결 시도...'));

  process.on('SIGTERM', () => { log('SIGTERM — 종료'); stopSharedCycle(); client.end(); process.exit(0); });
  process.on('SIGINT',  () => { log('SIGINT — 종료');  stopSharedCycle(); client.end(); process.exit(0); });
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

    // 설정 로드 후 활성 존이 있으면 공유 사이클 시작
    const active = getActiveZones();
    if (active.length > 0) {
      log(`[CYCLE] 설정 로드 완료 — 활성 존: [${active.join(', ')}] → 공유 사이클 시작`);
      startSharedCycle(client);
    }
  } catch (e) {
    log(`[ERROR] 설정 파일 로드 실패: ${e.message}`);
  }
}

main();

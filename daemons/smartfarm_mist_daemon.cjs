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

const SETTINGS_FILE   = path.join(__dirname, '../config/device_settings.json');
const ALERT_FILE      = path.join(__dirname, '../config/alert_config.json');
const LOG_FILE        = path.join(__dirname, '../logs/mist_daemon.log');
const FLOW_STATS_FILE = path.join(__dirname, '../config/flow_stats.json');

// ─── Zone 정의 ───────────────────────────────────────────────────────────────
const ZONES = {
  zone_a:  { name: '구역A', controllerId: 'ctlr-0004', deviceId: 'valve1' },
  zone_b:  { name: '구역B', controllerId: 'ctlr-0005', deviceId: 'valve1' },
  zone_c:  { name: '구역C', controllerId: 'ctlr-0006', deviceId: 'valve1' },
  zone_d:  { name: '구역D', controllerId: 'ctlr-0007', deviceId: 'valve1' },
  zone_e:  { name: '구역E', controllerId: 'ctlr-0008', deviceId: 'valve1' },
  fogging: { name: '포깅',   controllerId: 'ctlr-0004', deviceId: 'valve2' },
};
const CONTROLLER_IDS = [...new Set(Object.values(ZONES).map(z => z.controllerId))];

// ─── 구역 컨트롤러(ESP32) 온라인 상태 ────────────────────────────────────────
// AUTO 사이클은 컨트롤러가 오프라인이어도 명령 발행 자체는 계속 시도하지만(재연결 시 즉시 정상화),
// "분무 시작/정지 성공" 로그·DB 기록·텔레그램은 실제로 받을 수 있는 상태일 때만 남긴다.
// (오프라인인데도 성공한 것처럼 기록되면 대시보드 로그가 실제 동작과 어긋남 — 2026-08-01 수정)
const esp32Online = {};  // { controllerId: boolean } — 미수신(undefined)이면 판단 보류로 간주해 낙관적으로 허용
function isControllerOnline(zoneId) {
  return esp32Online[ZONES[zoneId].controllerId] !== false;
}

// ─── 바이패스 (구역A 전용) ───────────────────────────────────────────────────
// 메인 전자밸브(valve1) 고장 시 UI에서 바이패스 모드 ON → 같은 ctlr-0004의
// 바이패스 밸브(valve3)로 구역A 분무를 대신 수행. 스케줄/안전타임아웃 전부 동일.
const bypassState = {};  // { zoneId: bool } — 현재는 zone_a만 사용
function effectiveDeviceId(zoneId) {
  if (zoneId === 'zone_a' && bypassState['zone_a']) return 'valve3';  // 바이패스 밸브
  return ZONES[zoneId].deviceId;
}

// ─── 유량계 감시 (구역A 메인밸브 고장 자동 감지 → 알림/자동 바이패스 전환) ───
// valve1(실측 state)이 OPEN인데 유량(flow1/rate)이 설정된 시간 연속 0이면 고장으로 판단.
// AUTO 사이클/MANUAL 조작 어느 쪽으로 열렸든 동일하게 감지(실측 state 기준이라 출처 무관).
// 감시는 두 토글로 독립 제어: flowAlertEnabled(알림만) / flowGuardEnabled(알림+자동 바이패스 전환)
const FLOW_TOPIC_STATE      = 'tansaeng/ctlr-0004/valve1/state';
const FLOW_TOPIC_RATE       = 'tansaeng/ctlr-0004/flow1/rate';
const FLOW_TOPIC_TOTAL      = 'tansaeng/ctlr-0004/flow1/total';
const FLOW_ALERT_COOLDOWN_MS   = 30 * 60 * 1000;  // 텔레그램 재알림 최소 간격 30분(스팸 방지)
const FLOW_TIMEOUT_DEFAULT_SEC = 5;
const FLOW_TIMEOUT_MIN_SEC     = 3;
const FLOW_TIMEOUT_MAX_SEC     = 60;

let flowGuardEnabled    = false;  // UI 토글로 켜기 전까지는 자동 바이패스 전환 비활성 (유량계 미설치/미검증 상태 오작동 방지)
let flowAlertEnabled    = false;  // 무유량 알림만(밸브 전환 없음) — flowGuardEnabled와 독립
let flowNoFlowTimeoutMs = FLOW_TIMEOUT_DEFAULT_SEC * 1000;  // UI에서 설정/저장 가능
let valve1IsOpen    = false;
let flowFailTimer   = null;
let lastFlowAlertAt = 0;

function flowMonitoringActive() {
  return flowGuardEnabled || flowAlertEnabled;
}

function clearFlowFailTimer() {
  if (flowFailTimer) { clearTimeout(flowFailTimer); flowFailTimer = null; }
}

// 무유량 타이머 (재)무장 — 유량>0 확인될 때마다 호출해 설정된 타임아웃 카운트를 리셋
function armFlowFailTimer() {
  clearFlowFailTimer();
  flowFailTimer = setTimeout(() => {
    flowFailTimer = null;
    if (!flowMonitoringActive() || !valve1IsOpen || bypassState['zone_a']) return;  // 그 사이 상태가 바뀌었으면 무시
    handleFlowFailure();
  }, flowNoFlowTimeoutMs);
}

function handleFlowFailure() {
  const now = Date.now();
  const willBypass = flowGuardEnabled;
  const willAlert  = flowGuardEnabled || flowAlertEnabled;
  const timeoutSec = flowNoFlowTimeoutMs / 1000;

  log(`[구역A] ⚠️ 메인밸브(valve1) 작동중 ${timeoutSec}초간 유량 미검출` +
      (willBypass ? ' → 자동 바이패스 전환' : ' (알림만 — 자동 바이패스 감시 꺼짐)'));

  recordNoFlowEvent(now, willBypass);

  if (willBypass && gClient) {
    // 수동 "바이패스 전환" 버튼과 동일 경로(retain) — 데몬 자신도 구독 중이라 즉시 valve1 CLOSE/valve3 OPEN 처리됨
    gClient.publish('tansaeng/mist-control/zone_a/bypass', 'true', { qos: 1, retain: true });
  }

  if (willAlert && now - lastFlowAlertAt >= FLOW_ALERT_COOLDOWN_MS) {
    lastFlowAlertAt = now;
    sendTelegram(
      `🚨 <b>구역A 메인밸브 유량 이상</b>\n` +
      `메인밸브(valve1) 작동 중 ${timeoutSec}초간 유량이 감지되지 않았습니다.\n` +
      (willBypass
        ? `바이패스 밸브(valve3)로 자동 전환했습니다. 메인밸브 점검이 필요합니다.\n`
        : `(자동 바이패스 전환은 꺼져 있어 메인밸브를 계속 사용 중입니다. 점검이 필요합니다.)\n`) +
      `시각: ${seoulTime()}`
    );
  }
}
// ─── 유량 통계 (일별/시간별/분무 세션별) ─────────────────────────────────────
// flow1/total은 ESP32 재부팅 시 0으로 초기화되므로 그대로 누적하면 안 됨 —
// 데몬이 직접 증가분(delta)만 집계해서 재부팅에 영향받지 않게 함.
const flowStats = {
  lastTotalLiters: null,          // 마지막 관측 total (재부팅 감지용)
  day:  { date: null, liters: 0 },   // 오늘 누적 (Asia/Seoul 자정 리셋)
  hour: { hourKey: null, liters: 0 },// 이번 시간 누적 (정시 리셋)
  session: null,                     // 진행 중인 분무 세션 { startedAt, liters }
  sessionHistory: [],                // 최근 세션 이력 (최대 30건)
  noFlowEvents: [],                  // 최근 무유량 이벤트 이력 (최대 20건)
};
let statsDirty = false;

function todayKeySeoul() {
  return new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Seoul' });  // "YYYY-MM-DD"
}
function hourKeySeoul() {
  const h = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Seoul' })).getHours();
  return `${todayKeySeoul()}T${String(h).padStart(2, '0')}`;
}

// 자정/정시가 지났으면 일별/시간별 카운터 리셋
function rolloverFlowStats() {
  const dKey = todayKeySeoul();
  if (flowStats.day.date !== dKey) {
    flowStats.day.date = dKey;
    flowStats.day.liters = 0;
    statsDirty = true;
  }
  const hKey = hourKeySeoul();
  if (flowStats.hour.hourKey !== hKey) {
    flowStats.hour.hourKey = hKey;
    flowStats.hour.liters = 0;
    statsDirty = true;
  }
}

// flow1/total 신규 값 수신 시 증가분만 누적
function accumulateFlowTotal(total) {
  const prev = flowStats.lastTotalLiters;
  flowStats.lastTotalLiters = total;
  if (prev === null) return;   // 최초 관측값은 기준점으로만 사용

  const delta = total >= prev ? (total - prev) : total;  // 감소 = ESP32 재부팅으로 판단, 새 total 자체를 증가분으로 취급
  if (delta <= 0) return;

  rolloverFlowStats();
  flowStats.day.liters  += delta;
  flowStats.hour.liters += delta;
  if (flowStats.session) flowStats.session.liters += delta;

  statsDirty = true;
  publishFlowStats();
}

function startFlowSession() {
  flowStats.session = { startedAt: Date.now(), liters: 0 };
}

function endFlowSession() {
  if (!flowStats.session) return;
  const s = flowStats.session;
  const endedAt = Date.now();
  const entry = {
    startedAt:   s.startedAt,
    endedAt,
    durationSec: Math.round((endedAt - s.startedAt) / 1000),
    liters:      Math.round(s.liters * 1000) / 1000,
  };
  flowStats.sessionHistory.unshift(entry);
  flowStats.sessionHistory = flowStats.sessionHistory.slice(0, 30);
  flowStats.session = null;
  statsDirty = true;
  log(`[구역A] 분무 세션 종료: ${entry.durationSec}초, ${entry.liters}L`);
  publishFlowStats();
}

function recordNoFlowEvent(time, bypassTriggered) {
  flowStats.noFlowEvents.unshift({ time, bypassTriggered });
  flowStats.noFlowEvents = flowStats.noFlowEvents.slice(0, 20);
  statsDirty = true;
  if (gClient) {
    gClient.publish('tansaeng/ctlr-0004/flow1/noFlowHistory', JSON.stringify(flowStats.noFlowEvents), { qos: 1, retain: true });
  }
}

function publishFlowStats() {
  if (!gClient) return;
  gClient.publish('tansaeng/ctlr-0004/flow1/dailyTotal',  flowStats.day.liters.toFixed(2),  { qos: 1, retain: true });
  gClient.publish('tansaeng/ctlr-0004/flow1/hourlyTotal', flowStats.hour.liters.toFixed(2), { qos: 1, retain: true });
  gClient.publish('tansaeng/ctlr-0004/flow1/sessionHistory', JSON.stringify(flowStats.sessionHistory), { qos: 1, retain: true });
}

function loadFlowStats() {
  try {
    const raw  = fs.readFileSync(FLOW_STATS_FILE, 'utf8');
    const data = JSON.parse(raw);
    flowStats.lastTotalLiters = data.lastTotalLiters ?? null;
    flowStats.day             = data.day             ?? { date: null, liters: 0 };
    flowStats.hour            = data.hour             ?? { hourKey: null, liters: 0 };
    flowStats.sessionHistory  = Array.isArray(data.sessionHistory) ? data.sessionHistory : [];
    flowStats.noFlowEvents    = Array.isArray(data.noFlowEvents)   ? data.noFlowEvents   : [];
    if (typeof data.flowNoFlowTimeoutMs === 'number') flowNoFlowTimeoutMs = data.flowNoFlowTimeoutMs;
    flowGuardEnabled = !!data.flowGuardEnabled;
    flowAlertEnabled = !!data.flowAlertEnabled;
    rolloverFlowStats();
    log('[구역A] 유량 통계 파일 로드 완료');
  } catch (_) {
    // 파일 없으면 기본값 사용 (최초 실행) — retain 토픽 도착 시 정상 값으로 갱신됨
  }
}

function saveFlowStats() {
  try {
    fs.writeFileSync(FLOW_STATS_FILE, JSON.stringify({
      lastTotalLiters: flowStats.lastTotalLiters,
      day:             flowStats.day,
      hour:            flowStats.hour,
      sessionHistory:  flowStats.sessionHistory,
      noFlowEvents:    flowStats.noFlowEvents,
      flowNoFlowTimeoutMs,
      flowGuardEnabled,
      flowAlertEnabled,
    }, null, 2));
    statsDirty = false;
  } catch (e) {
    log(`[ERROR] 유량 통계 저장 실패: ${e.message}`);
  }
}

function flushFlowStatsIfDirty() {
  if (statsDirty) saveFlowStats();
}

// 존 밸브 cmd 토픽 (바이패스면 valve3으로 주소만 바뀜)
function zoneCmd(zoneId) {
  return `tansaeng/${ZONES[zoneId].controllerId}/${effectiveDeviceId(zoneId)}/cmd`;
}

// ─── 존별 상태 (isRunning / 스케줄 / 습도 설정 보관) ────────────────────────
const zoneState = {};
Object.keys(ZONES).forEach(id => {
  zoneState[id] = {
    isRunning:       false,
    mode:            'OFF',
    daySchedule:     null,
    nightSchedule:   null,
    humidityControl: null,  // { enabled, threshold } — 포깅 등에서 사용
    humBlocked:      false,  // 습도 충족으로 분무 차단 중인지 (히스테리시스 상태)
  };
});

// 습도 히스테리시스(이력) — 임계값 근처 출렁임 방지
// 분무 멈춤: 습도 >= threshold / 분무 재개: 습도 < threshold - HYST
const HUMIDITY_HYSTERESIS = 5;  // % (기본 5%, 필요 시 조정)

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
  zoneCycle[id] = { sprayTimer: null, stopTimer: null, cycling: false, valveOpen: false };
});

// 전역 MQTT 클라이언트 참조 (밸브 강제 닫기/워치독에서 사용)
let gClient = null;

// 열린 밸브를 강제로 닫음 — 사이클 중단/습도차단/재시작 시 밸브가 열린 채 방치되는 것 방지
function closeZoneValveIfOpen(zoneId) {
  const zc = zoneCycle[zoneId];
  if (!zc.valveOpen || !gClient) return;
  zc.valveOpen = false;
  const ts = Date.now();
  gClient.publish(zoneCmd(zoneId), 'CLOSE', { qos: 1 });
  gClient.publish(`tansaeng/mist-control/${zoneId}/timerState`, JSON.stringify({ state: 'CLOSE', timestamp: ts }), { qos: 1, retain: true });
  log(`[${ZONES[zoneId].name}] 밸브 강제 CLOSE (방치 방지)`);
}

// 안전 워치독 — 60초마다 이상 상태 점검 (이상시에만 동작, 주기 발행 없음)
let _watchdogTimer = null;
function startWatchdog() {
  if (_watchdogTimer) return;
  _watchdogTimer = setInterval(() => {
    Object.keys(ZONES).forEach(zoneId => {
      const zc = zoneCycle[zoneId];
      const st = zoneState[zoneId];
      // (1) 사이클 비활성인데 밸브가 열려 있음 → 강제 닫기
      if (zc.valveOpen && !zc.cycling) {
        log(`[${ZONES[zoneId].name}] [WATCHDOG] 비활성인데 밸브 OPEN → 강제 CLOSE`);
        closeZoneValveIfOpen(zoneId);
      }
      // (2) 실행 중이어야 하는데 사이클이 멈춰있음(타이머 없음) → 재시작
      if (st.isRunning && st.mode === 'AUTO' && zc.cycling && !zc.sprayTimer && !zc.stopTimer) {
        log(`[${ZONES[zoneId].name}] [WATCHDOG] 사이클 정지 감지 → 재시작`);
        zc.cycling = false;
        if (gClient) startZoneCycle(gClient, zoneId);
      }
    });
  }, 60000);
}

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
  closeZoneValveIfOpen(zoneId);   // 사이클 중단 시 열린 밸브 반드시 닫기
}

// ─── 모든 구역 사이클 중지 ───────────────────────────────────────────────────
function stopAllCycles() {
  Object.keys(ZONES).forEach(stopZoneCycle);
}

// ─── 특정 구역 독립 사이클 시작 (자기 스케줄대로 분무/정지 반복) ─────────────
function startZoneCycle(mqttClient, zoneId) {
  const st = zoneState[zoneId];
  if (!st.isRunning || st.mode !== 'AUTO') { stopZoneCycle(zoneId); return; }

  const zc = zoneCycle[zoneId];
  if (zc.cycling) return;        // 이미 사이클 진행 중 — 중복 시작 방지 (닫기 타이머 유실 차단)
  stopZoneCycle(zoneId);         // 남은 타이머/열린 밸브 정리 후 새로 시작
  zc.cycling = true;
  log(`[${ZONES[zoneId].name}] 사이클 시작`);

  // 분무 단계 — 매번 현재 시각 기준 스케줄 재평가 (주간↔야간 자동 전환)
  const doSpray = () => {
    if (!zc.cycling) return;
    if (!st.isRunning || st.mode !== 'AUTO') { zc.cycling = false; closeZoneValveIfOpen(zoneId); return; }

    const schedule = getCurrentSchedule(st);
    if (!schedule) {
      log(`[${ZONES[zoneId].name}] 현재 시간대 스케줄 없음 — 1분 후 재확인`);
      closeZoneValveIfOpen(zoneId);          // 분무 안 함 → 열린 밸브 닫기
      zc.cycling = false;
      zc.stopTimer = setTimeout(() => startZoneCycle(mqttClient, zoneId), 60 * 1000);
      return;
    }

    const sprayMs = (schedule.sprayDurationSeconds ?? 0) * 1000;
    const stopMs  = (schedule.stopDurationSeconds  ?? 0) * 1000;
    if (sprayMs <= 0) {
      log(`[${ZONES[zoneId].name}] sprayDurationSeconds 미설정 — 사이클 불가`);
      closeZoneValveIfOpen(zoneId);
      zc.cycling = false;
      return;
    }

    const ts = Date.now();
    const avgH = getAvgHumidity();

    // 습도 조건 체크 — 히스테리시스로 임계값 근처 출렁임 방지
    const hCtrl = st.humidityControl;
    if (hCtrl?.enabled && avgH !== null) {
      const hyst   = (hCtrl.hysteresis != null ? Number(hCtrl.hysteresis) : HUMIDITY_HYSTERESIS);
      const stopAt = hCtrl.threshold;          // 이 이상이면 분무 멈춤
      const startAt = hCtrl.threshold - hyst;  // 이 미만으로 떨어져야 다시 분무

      if (st.humBlocked) {
        // 차단 중 — startAt 미만으로 충분히 떨어졌을 때만 재개
        if (avgH >= startAt) {
          // 아직 데드밴드 안 — 계속 건너뜀 (로그/텔레그램 없이 조용히 대기)
          closeZoneValveIfOpen(zoneId);   // 차단 중 — 혹시 열려 있으면 닫기
          zc.stopTimer = setTimeout(doSpray, Math.max(stopMs, 1000));
          return;
        }
        st.humBlocked = false;  // 데드밴드 아래로 떨어짐 → 분무 재개
        log(`[${ZONES[zoneId].name}] 습도 ${avgH.toFixed(1)}% < ${startAt}% → 분무 재개`);
      } else {
        // 분무 중 — threshold 도달 시 차단으로 전환
        if (avgH >= stopAt) {
          st.humBlocked = true;
          log(`[${ZONES[zoneId].name}] 습도 ${avgH.toFixed(1)}% >= 기준 ${stopAt}% → 분무 멈춤 (재개 ${startAt}% 미만)`);
          closeZoneValveIfOpen(zoneId);   // 차단 전환 — 분무 중이던 밸브 즉시 닫기
          zc.stopTimer = setTimeout(doSpray, Math.max(stopMs, 1000));
          return;
        }
      }
    }

    // 실제 분무 실행 — 명령은 항상 발행(재연결 시 다음 사이클부터 정상화되게), 성공 기록은 온라인일 때만
    mqttClient.publish(zoneCmd(zoneId), 'OPEN', { qos: 1 });
    mqttClient.publish(`tansaeng/mist-control/${zoneId}/timerState`, JSON.stringify({ state: 'OPEN', timestamp: ts }), { qos: 1, retain: true });
    zc.valveOpen = true;
    if (isControllerOnline(zoneId)) {
      saveMistLog(zoneId, ZONES[zoneId].name, 'start', st.mode || 'AUTO');
      log(`[${ZONES[zoneId].name}] OPEN — 분무 ${sprayMs/1000}s / 정지 ${stopMs/1000}s${avgH !== null ? ` / 습도 ${avgH.toFixed(1)}%` : ''}`);
      // (a) 실제 분무했을 때만 텔레그램 발송
      sendTelegram(`💧 <b>분무수경 분무 시작</b>\n구역: ${ZONES[zoneId].name}\n지속: ${sprayMs/1000}초\n시각: ${seoulTime()}`);
    } else {
      // 컨트롤러 오프라인 — 명령을 받을 수 없어 실제 작동을 확인할 수 없으므로 성공 로그/DB기록/알림 생략
      log(`[${ZONES[zoneId].name}] ⚠️ 컨트롤러(${ZONES[zoneId].controllerId}) 오프라인 — OPEN 명령 발행했으나 실제 작동 미확인(로그/알림 생략)`);
    }

    zc.sprayTimer = setTimeout(() => doStop(stopMs), sprayMs);
  };

  // 정지 단계 — 밸브는 항상 닫는다 (cycling=false여도 방치 금지). 다음 분무 예약만 cycling일 때.
  const doStop = (stopMs) => {
    const ts = Date.now();
    mqttClient.publish(zoneCmd(zoneId), 'CLOSE', { qos: 1 });
    mqttClient.publish(`tansaeng/mist-control/${zoneId}/timerState`, JSON.stringify({ state: 'CLOSE', timestamp: ts }), { qos: 1, retain: true });
    zc.valveOpen = false;
    if (isControllerOnline(zoneId)) {
      saveMistLog(zoneId, ZONES[zoneId].name, 'stop', st.mode || 'AUTO');
      log(`[${ZONES[zoneId].name}] CLOSE — 정지 ${stopMs/1000}s`);
      sendTelegram(`⏸ <b>분무수경 대기 시작</b>\n구역: ${ZONES[zoneId].name}\n대기: ${stopMs/1000}초\n시각: ${seoulTime()}`);
    } else {
      log(`[${ZONES[zoneId].name}] ⚠️ 컨트롤러(${ZONES[zoneId].controllerId}) 오프라인 — CLOSE 명령 발행(로그/알림 생략)`);
    }

    if (!zc.cycling) return;   // 사이클 종료됐으면 다음 분무 예약 안 함 (밸브는 위에서 닫음)
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

  loadFlowStats();
  // 1분마다 일/시간 롤오버 체크(무유량 상태에서도 시간 경계 반영) + 통계 파일 flush
  setInterval(() => {
    rolloverFlowStats();
    publishFlowStats();
    flushFlowStatsIfDirty();
  }, 60000);

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
  gClient = client;   // 전역 참조 (밸브 강제 닫기/워치독용)

  let firstConnect = true;

  client.on('connect', () => {
    log('HiveMQ Cloud 연결 성공');
    startWatchdog();   // 안전 워치독 시작 (최초 1회)

    const topics = [];
    Object.keys(ZONES).forEach(zoneId => {
      topics.push(`tansaeng/mist-control/${zoneId}/isRunning`);
      topics.push(`tansaeng/mist-control/${zoneId}/schedule`);
      topics.push(`tansaeng/mist-control/${zoneId}/humidityConfig`);
    });
    // 바이패스 전환 (구역A) 구독
    topics.push('tansaeng/mist-control/zone_a/bypass');
    // 구역A 메인밸브 유량계 감시 구독 (+ 감시 on/off 토글, 타임아웃 설정)
    topics.push(
      FLOW_TOPIC_STATE, FLOW_TOPIC_RATE, FLOW_TOPIC_TOTAL,
      'tansaeng/mist-control/zone_a/flowGuard',
      'tansaeng/mist-control/zone_a/flowAlert',
      'tansaeng/mist-control/zone_a/flowNoFlowTimeoutSec',
    );
    // 구역 컨트롤러(ESP32) 온라인 상태 구독 (분무 성공 로그/알림 판단용)
    CONTROLLER_IDS.forEach(cid => topics.push(`tansaeng/${cid}/status`));
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

    // ── 바이패스 전환 (구역A): 메인밸브(valve1) ↔ 바이패스밸브(valve3) ──
    if (topic === 'tansaeng/mist-control/zone_a/bypass') {
      const newBypass = payload === 'true';
      const oldBypass = bypassState['zone_a'] || false;
      bypassState['zone_a'] = newBypass;
      log(`[구역A] 바이패스 모드: ${newBypass ? 'ON (valve3 바이패스밸브 사용)' : 'OFF (valve1 메인밸브)'}`);
      if (newBypass) clearFlowFailTimer();  // 바이패스 전환 시 유량 감시 즉시 중단(재판정 오발화 방지)
      if (newBypass !== oldBypass && gClient) {
        // 전환 시 양쪽 밸브 모두 강제 CLOSE(동시 열림 방지) → 다음 사이클에 올바른 밸브로 재개
        gClient.publish('tansaeng/ctlr-0004/valve1/cmd', 'CLOSE', { qos: 1 });
        gClient.publish('tansaeng/ctlr-0004/valve3/cmd', 'CLOSE', { qos: 1 });
        const zc = zoneCycle['zone_a'];
        if (zc) zc.valveOpen = false;
      }
      return;
    }

    // ── 구역A 메인밸브 자동 바이패스 전환 on/off 토글 (UI 스위치) ──
    if (topic === 'tansaeng/mist-control/zone_a/flowGuard') {
      const enabled = payload === 'true';
      const wasActive = flowMonitoringActive();
      flowGuardEnabled = enabled;
      statsDirty = true;
      log(`[구역A] 자동 바이패스 감시: ${enabled ? 'ON' : 'OFF'}`);
      if (!flowMonitoringActive()) {
        clearFlowFailTimer();
      } else if (!wasActive && valve1IsOpen && !bypassState['zone_a']) {
        // 이미 밸브가 열려 있는 도중에 켠 경우 즉시 감시 시작
        log('[구역A] 감시 ON — 현재 열려있는 메인밸브 즉시 모니터링 시작');
        armFlowFailTimer();
      }
      return;
    }

    // ── 구역A 메인밸브 무유량 알림 on/off 토글 (밸브 전환 없이 알림만) ──
    if (topic === 'tansaeng/mist-control/zone_a/flowAlert') {
      const enabled = payload === 'true';
      const wasActive = flowMonitoringActive();
      flowAlertEnabled = enabled;
      statsDirty = true;
      log(`[구역A] 무유량 알림: ${enabled ? 'ON' : 'OFF'}`);
      if (!flowMonitoringActive()) {
        clearFlowFailTimer();
      } else if (!wasActive && valve1IsOpen && !bypassState['zone_a']) {
        log('[구역A] 감시 ON — 현재 열려있는 메인밸브 즉시 모니터링 시작');
        armFlowFailTimer();
      }
      return;
    }

    // ── 구역A 무유량 판단 타임아웃 설정 (UI 입력 → 저장 버튼) ──
    if (topic === 'tansaeng/mist-control/zone_a/flowNoFlowTimeoutSec') {
      const sec = parseFloat(payload);
      if (!isNaN(sec) && sec >= FLOW_TIMEOUT_MIN_SEC && sec <= FLOW_TIMEOUT_MAX_SEC) {
        flowNoFlowTimeoutMs = sec * 1000;
        statsDirty = true;
        log(`[구역A] 무유량 판단 타임아웃 설정: ${sec}초`);
      }
      return;
    }

    // ── 구역A 메인밸브 유량 감시 ──
    if (topic === FLOW_TOPIC_STATE) {
      const isOpen = payload === 'OPEN';
      if (isOpen !== valve1IsOpen) {
        valve1IsOpen = isOpen;
        if (isOpen) {
          startFlowSession();
          if (flowMonitoringActive() && !bypassState['zone_a']) {
            log(`[구역A] 메인밸브 OPEN 감지 → 유량 모니터링 시작(${flowNoFlowTimeoutMs / 1000}초)`);
            armFlowFailTimer();
          }
        } else {
          endFlowSession();
          clearFlowFailTimer();
        }
      }
      return;
    }
    if (topic === FLOW_TOPIC_RATE) {
      const rate = parseFloat(payload);
      if (!isNaN(rate) && rate > 0 && flowMonitoringActive() && valve1IsOpen && !bypassState['zone_a']) {
        armFlowFailTimer();  // 유량 정상 확인 → 무유량 카운트 리셋
      }
      return;
    }
    if (topic === FLOW_TOPIC_TOTAL) {
      const total = parseFloat(payload);
      if (!isNaN(total)) accumulateFlowTotal(total);
      return;
    }

    // ── 구역 컨트롤러(ESP32) 온라인 상태 ──
    if (CONTROLLER_IDS.some(cid => topic === `tansaeng/${cid}/status`)) {
      const cid = topic.split('/')[1];
      const online = payload === 'online';
      if (esp32Online[cid] !== online) {
        esp32Online[cid] = online;
        log(`[${cid}] 온라인 상태: ${online ? 'online' : 'offline'}`);
      }
      return;
    }

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
              zoneCmd(zoneId), 'CLOSE', { qos: 1 }
            );
            log(`[${ZONES[zoneId].name}] 중지 — 밸브 OFF`);
          }
        }
      }

      if (topic === `tansaeng/mist-control/${zoneId}/schedule`) {
        try {
          const parsed = JSON.parse(payload);
          // 변경 감지 — retain 재수신(무변경)으로 인한 불필요한 재시작/churn 방지
          const sigBefore = JSON.stringify([zoneState[zoneId].mode, zoneState[zoneId].daySchedule, zoneState[zoneId].nightSchedule]);
          if (parsed.mode)          zoneState[zoneId].mode          = parsed.mode;
          if (parsed.daySchedule)   zoneState[zoneId].daySchedule   = parsed.daySchedule;
          if (parsed.nightSchedule) zoneState[zoneId].nightSchedule = parsed.nightSchedule;
          const sigAfter = JSON.stringify([zoneState[zoneId].mode, zoneState[zoneId].daySchedule, zoneState[zoneId].nightSchedule]);
          log(`[${ZONES[zoneId].name}] 스케줄 업데이트: mode=${parsed.mode}`);

          if (zoneState[zoneId].isRunning && zoneState[zoneId].mode === 'AUTO') {
            if (sigBefore !== sigAfter) {
              // 실제로 스케줄이 바뀜 → 깨끗이 재시작해 즉시 적용
              stopZoneCycle(zoneId);
              startZoneCycle(client, zoneId);
            } else {
              // 무변경(retain 재수신) → 사이클 중이면 skip-if-cycling이 알아서 no-op
              startZoneCycle(client, zoneId);
            }
          }
        } catch (e) {
          log(`[${ZONES[zoneId].name}] 스케줄 파싱 오류: ${e.message}`);
        }
      }

      if (topic === `tansaeng/mist-control/${zoneId}/humidityConfig`) {
        try {
          const parsed = JSON.parse(payload);
          if (parsed && typeof parsed === 'object') {
            zoneState[zoneId].humidityControl = {
              enabled:    !!parsed.enabled,
              threshold:  Number(parsed.threshold) || 70,
              // UI가 hysteresis를 보내면 사용, 없으면 기본값
              hysteresis: parsed.hysteresis != null ? Number(parsed.hysteresis) : HUMIDITY_HYSTERESIS,
            };
            log(`[${ZONES[zoneId].name}] 습도 조건 업데이트: enabled=${parsed.enabled}, threshold=${parsed.threshold}%, 히스테리시스=${zoneState[zoneId].humidityControl.hysteresis}%`);
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

  process.on('SIGTERM', () => { log('SIGTERM — 종료'); stopAllCycles(); saveFlowStats(); client.end(); process.exit(0); });
  process.on('SIGINT',  () => { log('SIGINT — 종료');  stopAllCycles(); saveFlowStats(); client.end(); process.exit(0); });
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

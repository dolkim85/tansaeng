#!/usr/bin/env node
/**
 * MQTT Heartbeat Daemon (Node.js)
 * ESP32 장치들의 heartbeat를 수신하고 연결 상태를 DB에 저장
 */

const mqtt = require('mqtt');
const mysql = require('mysql2/promise');
const fs = require('fs');

// 로그 함수
function log(message) {
  const timestamp = new Date().toISOString();
  const logMessage = `[${timestamp}] ${message}\n`;
  console.log(logMessage.trim());

  // 로그 파일에 기록
  const logFile = __dirname + '/../logs/mqtt_daemon.log';
  fs.appendFileSync(logFile, logMessage);
}

// ESP32 컨트롤러 목록
const controllers = {
  'ctlr-0001': '내부팬 앞',
  'ctlr-0002': '내부팬 뒤',
  'ctlr-0003': '천장 환기',
  'ctlr-0004': '메인 밸브',
  'ctlr-0005': '라인 밸브',
  'ctlr-0006': '가압 펌프',
  'ctlr-0007': '주입 펌프',
  'ctlr-0008': '히트펌프 밸브',
  'ctlr-0009': '칠러 펌프',
  'ctlr-0010': '칠러/히트 통합 펌프',
  'ctlr-0012': '천창 스크린',
  'ctlr-0021': '측창 스크린',
  'ctlr-0013': '천창 칠러라인 펌프 밸브'
};

// Heartbeat로 인식할 값들
const validHeartbeatValues = ['online', 'on', '1', 'connected', 'true', 'active', 'alive', 'heartbeat'];
const offlineValues = ['offline', 'off', '0', 'disconnected', 'false', 'inactive', 'dead'];

// DB 연결 풀
let dbPool;

async function initDB() {
  dbPool = mysql.createPool({
    host: 'localhost',
    user: 'root',
    password: 'qjawns3445',
    database: 'tansaeng_db',
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
  });

  log('데이터베이스 연결 풀 생성 완료');
}

// ESP32 상태 업데이트
async function updateESP32Status(controllerId, isConnected) {
  try {
    const [result] = await dbPool.execute(
      `UPDATE esp32_status
       SET is_connected = ?,
           last_heartbeat = NOW(),
           updated_at = NOW()
       WHERE controller_id = ?`,
      [isConnected ? 1 : 0, controllerId]
    );
    return true;
  } catch (error) {
    log(`DB 업데이트 오류 [${controllerId}]: ${error.message}`);
    return false;
  }
}

// 타임아웃 체크 (180초 동안 heartbeat 없으면 offline)
async function checkTimeouts() {
  try {
    const [result] = await dbPool.execute(
      `UPDATE esp32_status
       SET is_connected = FALSE
       WHERE is_connected = TRUE
       AND last_heartbeat < DATE_SUB(NOW(), INTERVAL 180 SECOND)`
    );

    if (result.affectedRows > 0) {
      log(`타임아웃으로 ${result.affectedRows}개 장치 offline 처리`);
    }
  } catch (error) {
    log(`타임아웃 체크 오류: ${error.message}`);
  }
}

// 메인 실행
async function main() {
  log('=== MQTT Heartbeat Daemon 시작 ===');

  // DB 초기화
  await initDB();

  // MQTT 연결 설정
  const mqttOptions = {
    host: '22ada06fd6cf4059bd700ddbf6004d68.s1.eu.hivemq.cloud',
    port: 8883,
    protocol: 'mqtts',
    username: 'esp32-client-01',
    password: 'Qjawns3445',
    rejectUnauthorized: false,
    keepalive: 60,
    clientId: 'tansaeng_daemon_' + Math.random().toString(16).substring(2, 8),
    reconnectPeriod: 5000
  };

  log('MQTT 브로커 연결 중...');
  const client = mqtt.connect(mqttOptions);

  client.on('connect', () => {
    log('HiveMQ Cloud 연결 성공!');

    // 모든 ESP32 status 토픽 구독
    Object.keys(controllers).forEach(controllerId => {
      const topic = `tansaeng/${controllerId}/status`;
      client.subscribe(topic, { qos: 0 }, (err) => {
        if (!err) {
          log(`구독: ${topic}`);
        } else {
          log(`구독 실패 ${topic}: ${err.message}`);
        }
      });
    });
  });

  client.on('message', async (topic, message) => {
    // topic에서 controller ID 추출
    const match = topic.match(/tansaeng\/(ctlr-\d+)\/status/);
    if (!match) return;

    const controllerId = match[1];
    const payload = message.toString().toLowerCase().trim();

    // Heartbeat 값 체크
    if (validHeartbeatValues.includes(payload)) {
      if (await updateESP32Status(controllerId, true)) {
        log(`[${controllerId}] ${controllers[controllerId]} - ONLINE (${payload})`);
      }
    } else if (offlineValues.includes(payload)) {
      if (await updateESP32Status(controllerId, false)) {
        log(`[${controllerId}] ${controllers[controllerId]} - OFFLINE (${payload})`);
      }
    }
  });

  client.on('error', (error) => {
    log(`MQTT 오류: ${error.message}`);
  });

  client.on('offline', () => {
    log('MQTT 연결 끊김 - 재연결 시도 중...');
  });

  client.on('reconnect', () => {
    log('MQTT 재연결 중...');
  });

  // 60초마다 타임아웃 체크
  setInterval(checkTimeouts, 60000);

  // 종료 시그널 처리
  process.on('SIGTERM', async () => {
    log('종료 시그널 수신 - 정리 중...');
    client.end();
    await dbPool.end();
    process.exit(0);
  });

  process.on('SIGINT', async () => {
    log('Ctrl+C 수신 - 종료 중...');
    client.end();
    await dbPool.end();
    process.exit(0);
  });
}

// 실행
main().catch(error => {
  log(`치명적 오류: ${error.message}`);
  log(`Stack trace: ${error.stack}`);
  process.exit(1);
});

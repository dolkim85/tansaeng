#!/usr/bin/env php
<?php
/**
 * MQTT Heartbeat Daemon
 * ESP32 장치들의 heartbeat를 수신하고 연결 상태를 DB에 저장
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

// 로그 함수
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] {$message}\n";

    // 로그 파일에도 기록
    $logFile = __DIR__ . '/../logs/mqtt_daemon.log';
    file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

// 데이터베이스 연결
$db = getDBConnection();

// ESP32 컨트롤러 목록
$controllers = [
    'ctlr-0001' => '내부팬 앞',
    'ctlr-0002' => '내부팬 뒤',
    'ctlr-0003' => '천장 환기',
    'ctlr-0004' => '메인 밸브',
    'ctlr-0005' => '라인 밸브',
    'ctlr-0006' => '가압 펌프',
    'ctlr-0007' => '주입 펌프',
    'ctlr-0008' => '히트펌프 밸브',
    'ctlr-0009' => '칠러 펌프',
    'ctlr-0010' => '칠러/히트 통합 펌프',
    'ctlr-0011' => '천창 스크린',
    'ctlr-0012' => '측창 스크린',
    'ctlr-0013' => '천창 칠러라인 펌프 밸브',
];

// Heartbeat 메시지로 인식할 값들 (OR 조건)
$validHeartbeatValues = [
    'online',
    'on',
    '1',
    'connected',
    'true',
    'active',
    'alive',
    'heartbeat'
];

// Offline 메시지로 인식할 값들
$offlineValues = [
    'offline',
    'off',
    '0',
    'disconnected',
    'false',
    'inactive',
    'dead'
];

// ESP32 상태 업데이트 함수
function updateESP32Status($db, $controllerId, $isConnected) {
    try {
        $stmt = $db->prepare("
            UPDATE esp32_status
            SET is_connected = ?,
                last_heartbeat = NOW(),
                updated_at = NOW()
            WHERE controller_id = ?
        ");
        $stmt->execute([$isConnected ? 1 : 0, $controllerId]);
        return true;
    } catch (Exception $e) {
        logMessage("DB 업데이트 오류: " . $e->getMessage());
        return false;
    }
}

// 타임아웃 체크 함수 (90초 동안 heartbeat 없으면 offline)
function checkTimeouts($db) {
    try {
        $stmt = $db->prepare("
            UPDATE esp32_status
            SET is_connected = FALSE
            WHERE is_connected = TRUE
            AND last_heartbeat < DATE_SUB(NOW(), INTERVAL 90 SECOND)
        ");
        $stmt->execute();

        $affected = $stmt->rowCount();
        if ($affected > 0) {
            logMessage("타임아웃으로 {$affected}개 장치 offline 처리");
        }
    } catch (Exception $e) {
        logMessage("타임아웃 체크 오류: " . $e->getMessage());
    }
}

logMessage("=== MQTT Heartbeat Daemon 시작 ===");

try {
    // HiveMQ Cloud 설정
    $server = 'c2ff8c69cebe4c62a58f0db0b3b4c6f0.s1.eu.hivemq.cloud';
    $port = 8883;
    $clientId = 'tansaeng_daemon_' . uniqid();

    logMessage("MQTT 클라이언트 생성 중...");
    $mqtt = new MqttClient($server, $port, $clientId);

    // TLS/SSL 연결 설정
    $connectionSettings = (new ConnectionSettings)
        ->setUsername('korea_tansaeng')
        ->setPassword('qjawns3445')
        ->setUseTls(true)
        ->setTlsSelfSignedAllowed(true)
        ->setKeepAliveInterval(60)
        ->setConnectTimeout(10);

    logMessage("HiveMQ Cloud 연결 중...");
    $mqtt->connect($connectionSettings, true);
    logMessage("HiveMQ Cloud 연결 성공!");

    // 모든 ESP32 장치의 status 토픽 구독
    foreach ($controllers as $controllerId => $name) {
        $topic = "tansaeng/{$controllerId}/status";
        $mqtt->subscribe($topic, function ($topic, $message) use ($db, $controllerId, $name, $validHeartbeatValues, $offlineValues) {
            $payload = strtolower(trim($message));

            // Heartbeat 값 체크 (OR 조건)
            if (in_array($payload, $validHeartbeatValues)) {
                if (updateESP32Status($db, $controllerId, true)) {
                    logMessage("[{$controllerId}] {$name} - ONLINE (payload: {$payload})");
                }
            }
            // Offline 값 체크
            elseif (in_array($payload, $offlineValues)) {
                if (updateESP32Status($db, $controllerId, false)) {
                    logMessage("[{$controllerId}] {$name} - OFFLINE (payload: {$payload})");
                }
            }
            // 알 수 없는 값
            else {
                logMessage("[{$controllerId}] {$name} - 알 수 없는 heartbeat 값: {$payload}");
            }
        }, 0);

        logMessage("구독: {$topic}");
    }

    logMessage("MQTT Loop 시작 (Ctrl+C로 종료)");

    // 마지막 타임아웃 체크 시간
    $lastTimeoutCheck = time();

    // 무한 루프로 메시지 수신
    $mqtt->loop(true, true, function() use (&$lastTimeoutCheck, $db) {
        // 30초마다 타임아웃 체크
        if (time() - $lastTimeoutCheck >= 30) {
            checkTimeouts($db);
            $lastTimeoutCheck = time();
        }
    });

} catch (Exception $e) {
    logMessage("오류 발생: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    exit(1);
}

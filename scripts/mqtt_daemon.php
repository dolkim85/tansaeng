#!/usr/bin/php
<?php
/**
 * MQTT 백그라운드 데몬
 * - 센서 데이터 수신 및 데이터베이스 저장
 * - 자동 제어 로직 실행
 * - 메인밸브 스케줄 제어
 */

// Composer autoload
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../classes/Database.php';

use \PhpMqtt\Client\MqttClient;
use \PhpMqtt\Client\ConnectionSettings;

echo "=== MQTT 데몬 시작 ===\n";
echo "시작 시간: " . date('Y-m-d H:i:s') . "\n\n";

// MQTT 설정
$server = '22ada06fd6cf4059bd700ddbf6004d68.s1.eu.hivemq.cloud';
$port = 8883;
$clientId = 'php-daemon-' . uniqid();
$username = 'esp32-client-01';
$password = 'Qjawns3445';

// 데이터베이스 연결
$db = Database::getInstance();

// ESP32 장치 연결 상태 업데이트 함수
function updateDeviceStatus($db, $controllerId, $status) {
    try {
        // device_status 테이블에 상태 저장/업데이트
        $sql = "INSERT INTO device_status (controller_id, status, last_seen)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                last_seen = NOW()";

        $db->execute($sql, [$controllerId, $status]);
        echo "[DEVICE] {$controllerId} status: {$status}\n";
    } catch (Exception $e) {
        echo "[ERROR] Failed to update device status: " . $e->getMessage() . "\n";
    }
}

// 센서 데이터 저장 함수
function saveSensorData($db, $controllerId, $sensorType, $dataType, $value) {
    try {
        $location = match($controllerId) {
            'ctlr-0001' => 'front',
            'ctlr-0002' => 'back',
            'ctlr-0003' => 'top',
            default => 'unknown'
        };

        $data = [
            'controller_id' => $controllerId,
            'sensor_type' => $sensorType,
            'sensor_location' => $location,
            $dataType => floatval($value),
        ];

        $db->insert('sensor_data', $data);
        echo "[DB] Saved {$dataType}: {$value} from {$controllerId}\n";

        // 센서 데이터가 오면 장치가 연결된 것으로 간주
        updateDeviceStatus($db, $controllerId, 'online');
    } catch (Exception $e) {
        echo "[ERROR] Failed to save sensor data: " . $e->getMessage() . "\n";
    }
}

// 최근 평균 온습도 가져오기 (최근 5분)
function getRecentAverage($db) {
    $sql = "SELECT
                AVG(temperature) as avg_temp,
                AVG(humidity) as avg_hum
            FROM sensor_data
            WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                AND sensor_location IN ('front', 'back', 'top')";

    $result = $db->selectOne($sql);
    return [
        'temp' => $result['avg_temp'] ?? null,
        'hum' => $result['avg_hum'] ?? null
    ];
}

// 자동 제어 설정 로드 (파일 또는 DB에서)
function loadAutoControlSettings() {
    $settingsFile = __DIR__ . '/../config/auto_control.json';
    if (file_exists($settingsFile)) {
        $json = file_get_contents($settingsFile);
        return json_decode($json, true);
    }
    return null;
}

// 메인밸브 스케줄 로드
function loadValveSchedule() {
    $scheduleFile = __DIR__ . '/../config/valve_schedule.json';
    if (file_exists($scheduleFile)) {
        $json = file_get_contents($scheduleFile);
        return json_decode($json, true);
    }
    return ['enabled' => false, 'timeSlots' => []];
}

// 현재 시간에 맞는 시간대 찾기
function getCurrentTimeSlot($timeSlots) {
    $now = new DateTime();
    $currentTime = $now->format('H') * 60 + $now->format('i');

    foreach ($timeSlots as $slot) {
        list($startHour, $startMin) = explode(':', $slot['startTime']);
        list($endHour, $endMin) = explode(':', $slot['endTime']);
        $startMinutes = $startHour * 60 + $startMin;
        $endMinutes = $endHour * 60 + $endMin;

        // 자정 넘김 처리
        if ($startMinutes > $endMinutes) {
            if ($currentTime >= $startMinutes || $currentTime < $endMinutes) {
                return $slot;
            }
        } else {
            if ($currentTime >= $startMinutes && $currentTime < $endMinutes) {
                return $slot;
            }
        }
    }
    return null;
}

// MQTT 클라이언트 생성 시도
try {
    $connectionSettings = (new ConnectionSettings)
        ->setUsername($username)
        ->setPassword($password)
        ->setUseTls(true)
        ->setTlsSelfSignedAllowed(true);

    $mqtt = new MqttClient($server, $port, $clientId);

    echo "[MQTT] Connecting to {$server}:{$port}...\n";
    $mqtt->connect($connectionSettings, true);
    echo "[MQTT] Connected!\n\n";

    // 센서 토픽 구독
    $sensorTopics = [
        'tansaeng/ctlr-0001/+/temperature',
        'tansaeng/ctlr-0001/+/humidity',
        'tansaeng/ctlr-0002/+/temperature',
        'tansaeng/ctlr-0002/+/humidity',
        'tansaeng/ctlr-0003/+/temperature',
        'tansaeng/ctlr-0003/+/humidity',
    ];

    foreach ($sensorTopics as $topic) {
        $mqtt->subscribe($topic, function ($topic, $message) use ($db) {
            echo "[MQTT] {$topic}: {$message}\n";

            // 토픽 파싱: tansaeng/ctlr-0001/dht11/temperature
            $parts = explode('/', $topic);
            $controllerId = $parts[1];
            $sensorType = $parts[2];
            $dataType = $parts[3]; // temperature or humidity

            saveSensorData($db, $controllerId, $sensorType, $dataType, $message);
        }, 0);
    }

    // ESP32 heartbeat/status 토픽 구독
    $statusTopics = [
        'tansaeng/ctlr-0001/status',
        'tansaeng/ctlr-0002/status',
        'tansaeng/ctlr-0003/status',
        'tansaeng/ctlr-0004/status',
    ];

    foreach ($statusTopics as $topic) {
        $mqtt->subscribe($topic, function ($topic, $message) use ($db) {
            echo "[MQTT] {$topic}: {$message}\n";

            // 토픽 파싱: tansaeng/ctlr-0001/status
            $parts = explode('/', $topic);
            $controllerId = $parts[1];

            // 상태 메시지: "online" 또는 "offline"
            updateDeviceStatus($db, $controllerId, $message);
        }, 0);
    }

    // ESP32 pong 응답 토픽 구독
    $pongTopics = [
        'tansaeng/ctlr-0001/pong',
        'tansaeng/ctlr-0002/pong',
        'tansaeng/ctlr-0003/pong',
        'tansaeng/ctlr-0004/pong',
    ];

    foreach ($pongTopics as $topic) {
        $mqtt->subscribe($topic, function ($topic, $message) use ($db) {
            // 토픽 파싱: tansaeng/ctlr-0001/pong
            $parts = explode('/', $topic);
            $controllerId = $parts[1];

            echo "[PONG] Received from {$controllerId}\n";

            // pong 응답을 받으면 장치가 연결된 것으로 간주
            updateDeviceStatus($db, $controllerId, 'online');
        }, 0);
    }

    echo "[MQTT] Subscribed to sensor, status, and pong topics\n\n";

    // 메인 루프
    $lastAutoControl = 0;
    $lastValveControl = 0;
    $lastDeviceCheck = 0;
    $valveState = 'CLOSE';
    $valveTimer = 0;

    $mqtt->registerLoopEventHandler(function (MqttClient $mqtt) use ($db, &$lastAutoControl, &$lastValveControl, &$lastDeviceCheck, &$valveState, &$valveTimer) {
        $now = time();

        // 장치 상태 체크 (30초마다)
        if ($now - $lastDeviceCheck >= 30) {
            $lastDeviceCheck = $now;

            // 모든 장치에 ping 요청 전송
            $devices = ['ctlr-0001', 'ctlr-0002', 'ctlr-0003', 'ctlr-0004'];
            foreach ($devices as $deviceId) {
                // ping 요청 전송 (ESP32가 pong으로 응답해야 함)
                $mqtt->publish("tansaeng/{$deviceId}/ping", "ping", 0);
            }
            echo "[PING] Sent ping to all devices\n";

            // 2분 이상 응답이 없는 장치를 offline으로 표시
            $sql = "UPDATE device_status
                    SET status = 'offline'
                    WHERE status = 'online'
                    AND last_seen < DATE_SUB(NOW(), INTERVAL 2 MINUTE)";
            $db->execute($sql);
        }

        // 자동 제어 (30초마다)
        if ($now - $lastAutoControl >= 30) {
            $lastAutoControl = $now;

            $settings = loadAutoControlSettings();
            if ($settings && $settings['enabled']) {
                $avg = getRecentAverage($db);

                if ($avg['temp'] !== null && $avg['hum'] !== null) {
                    echo "[AUTO] Avg Temp: {$avg['temp']}°C, Avg Hum: {$avg['hum']}%\n";

                    // 온도 기반 제어 (예시)
                    foreach ($settings['devices'] ?? [] as $deviceId => $control) {
                        if (!$control['enabled']) continue;

                        if ($avg['temp'] > $control['tempMax']) {
                            // 팬 켜기 명령 전송
                            $mqtt->publish("tansaeng/{$deviceId}/fan1/cmd", json_encode(['power' => 'on']), 1);
                            echo "[AUTO] Turn ON fan for {$deviceId} (temp too high)\n";
                        } elseif ($avg['temp'] < $control['tempMin']) {
                            // 팬 끄기
                            $mqtt->publish("tansaeng/{$deviceId}/fan1/cmd", json_encode(['power' => 'off']), 1);
                            echo "[AUTO] Turn OFF fan for {$deviceId} (temp too low)\n";
                        }
                    }
                }
            }
        }

        // 메인밸브 스케줄 제어 (1초마다 체크)
        if ($now - $lastValveControl >= 1) {
            $lastValveControl = $now;

            $schedule = loadValveSchedule();
            if ($schedule['enabled']) {
                $currentSlot = getCurrentTimeSlot($schedule['timeSlots']);

                if ($currentSlot) {
                    // 타이머 로직
                    if ($valveTimer <= 0) {
                        if ($valveState === 'CLOSE') {
                            // 밸브 열기
                            $mqtt->publish('tansaeng/ctlr-0004/valve1/cmd', 'OPEN', 1);
                            $valveState = 'OPEN';
                            $valveTimer = $currentSlot['openSeconds'];
                            echo "[VALVE] OPEN for {$currentSlot['openSeconds']}s\n";
                        } else {
                            // 밸브 닫기
                            $mqtt->publish('tansaeng/ctlr-0004/valve1/cmd', 'CLOSE', 1);
                            $valveState = 'CLOSE';
                            $valveTimer = $currentSlot['closeSeconds'];
                            echo "[VALVE] CLOSE for {$currentSlot['closeSeconds']}s\n";
                        }
                    }
                    $valveTimer--;
                }
            }
        }
    });

    // 무한 루프 실행
    echo "[DAEMON] Starting main loop...\n";
    $mqtt->loop(true);

} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
?>

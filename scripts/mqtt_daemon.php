#!/usr/bin/php
<?php
/**
 * MQTT 백그라운드 데몬
 * - 센서 데이터 수신 및 데이터베이스 저장
 * - 장치 자동 제어 (device_settings.json 기반)
 * - 환기팬, 분무수경 밸브 등 모든 장치 지원
 */

// 한국 시간대 설정
date_default_timezone_set('Asia/Seoul');

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

// 장치별 밸브 상태 추적 (사이클 관리용)
$deviceCycleState = [];

// ESP32 장치 연결 상태 업데이트 함수
function updateDeviceStatus($db, $controllerId, $status) {
    try {
        $sql = "INSERT INTO device_status (controller_id, status, last_seen)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                last_seen = NOW()";

        $db->query($sql, [$controllerId, $status]);
        // echo "[DEVICE] {$controllerId} status: {$status}\n";
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
            'ctlr-0011' => 'window',
            'ctlr-0021' => 'sidescreen',
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

        updateDeviceStatus($db, $controllerId, 'online');
    } catch (Exception $e) {
        echo "[ERROR] Failed to save sensor data: " . $e->getMessage() . "\n";
    }
}

// 장치 설정 로드 (웹UI에서 저장한 설정)
function loadDeviceSettings() {
    $settingsFile = __DIR__ . '/../config/device_settings.json';
    if (file_exists($settingsFile)) {
        $json = file_get_contents($settingsFile);
        return json_decode($json, true);
    }
    return null;
}

// 현재 시간이 스케줄 시간대에 있는지 확인
function isInScheduleTime($schedule) {
    if (!$schedule || !$schedule['enabled']) {
        return false;
    }

    $now = new DateTime();
    $currentMinutes = $now->format('H') * 60 + $now->format('i');

    list($startHour, $startMin) = explode(':', $schedule['startTime']);
    list($endHour, $endMin) = explode(':', $schedule['endTime']);
    $startMinutes = intval($startHour) * 60 + intval($startMin);
    $endMinutes = intval($endHour) * 60 + intval($endMin);

    // 자정 넘김 처리 (예: 18:00 ~ 06:00)
    if ($startMinutes > $endMinutes) {
        return ($currentMinutes >= $startMinutes || $currentMinutes < $endMinutes);
    } else {
        return ($currentMinutes >= $startMinutes && $currentMinutes < $endMinutes);
    }
}

// 현재 시간대의 스케줄 가져오기
function getCurrentSchedule($zoneConfig) {
    // 주간 스케줄 확인
    if (isset($zoneConfig['daySchedule']) && isInScheduleTime($zoneConfig['daySchedule'])) {
        return $zoneConfig['daySchedule'];
    }
    // 야간 스케줄 확인
    if (isset($zoneConfig['nightSchedule']) && isInScheduleTime($zoneConfig['nightSchedule'])) {
        return $zoneConfig['nightSchedule'];
    }
    return null;
}

// MQTT 클라이언트 생성
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

            $parts = explode('/', $topic);
            $controllerId = $parts[1];
            $sensorType = $parts[2];
            $dataType = $parts[3];

            saveSensorData($db, $controllerId, $sensorType, $dataType, $message);
        }, 0);
    }

    // ESP32 status 토픽 구독
    $statusTopics = [
        'tansaeng/ctlr-0001/status',
        'tansaeng/ctlr-0002/status',
        'tansaeng/ctlr-0003/status',
        'tansaeng/ctlr-0004/status',
        'tansaeng/ctlr-0005/status',
        'tansaeng/ctlr-0006/status',
        'tansaeng/ctlr-0007/status',
        'tansaeng/ctlr-0008/status',
        'tansaeng/ctlr-0012/status',
        'tansaeng/ctlr-0021/status',
    ];

    foreach ($statusTopics as $topic) {
        $mqtt->subscribe($topic, function ($topic, $message) use ($db) {
            $parts = explode('/', $topic);
            $controllerId = $parts[1];
            updateDeviceStatus($db, $controllerId, $message);
        }, 0);
    }

    // pong 응답 토픽 구독
    $pongTopics = [
        'tansaeng/ctlr-0001/pong',
        'tansaeng/ctlr-0002/pong',
        'tansaeng/ctlr-0003/pong',
        'tansaeng/ctlr-0004/pong',
        'tansaeng/ctlr-0005/pong',
        'tansaeng/ctlr-0006/pong',
        'tansaeng/ctlr-0007/pong',
        'tansaeng/ctlr-0008/pong',
    ];

    foreach ($pongTopics as $topic) {
        $mqtt->subscribe($topic, function ($topic, $message) use ($db) {
            $parts = explode('/', $topic);
            $controllerId = $parts[1];
            updateDeviceStatus($db, $controllerId, 'online');
        }, 0);
    }

    // 장치 state 토픽 구독
    $mqtt->subscribe('tansaeng/+/+/state', function ($topic, $message) use ($db) {
        $parts = explode('/', $topic);
        $controllerId = $parts[1];
        updateDeviceStatus($db, $controllerId, 'online');
    }, 0);

    echo "[MQTT] Subscribed to all topics\n\n";

    // 메인 루프 변수
    $lastDeviceCheck = 0;
    $lastSettingsCheck = 0;
    $cachedSettings = null;

    $mqtt->registerLoopEventHandler(function (MqttClient $mqtt) use ($db, &$lastDeviceCheck, &$lastSettingsCheck, &$cachedSettings, &$deviceCycleState) {
        $now = time();
        $currentMicro = microtime(true);

        // 장치 상태 체크 (30초마다)
        if ($now - $lastDeviceCheck >= 30) {
            $lastDeviceCheck = $now;

            $devices = ['ctlr-0001', 'ctlr-0002', 'ctlr-0003', 'ctlr-0004', 'ctlr-0005', 'ctlr-0006', 'ctlr-0007', 'ctlr-0008', 'ctlr-0012', 'ctlr-0021'];
            foreach ($devices as $deviceId) {
                $mqtt->publish("tansaeng/{$deviceId}/ping", "ping", 0);
            }

            // 2분 이상 응답 없으면 offline
            $sql = "UPDATE device_status SET status = 'offline' WHERE status = 'online' AND last_seen < DATE_SUB(NOW(), INTERVAL 2 MINUTE)";
            $db->query($sql);
        }

        // 설정 파일 체크 (3초마다)
        if ($now - $lastSettingsCheck >= 3) {
            $lastSettingsCheck = $now;
            $cachedSettings = loadDeviceSettings();
        }

        if (!$cachedSettings) {
            return;
        }

        // ========== 환기팬 자동 제어 ==========
        if (isset($cachedSettings['fans'])) {
            foreach ($cachedSettings['fans'] as $fanId => $fanConfig) {
                $mode = $fanConfig['mode'] ?? 'OFF';
                $controllerId = $fanConfig['controllerId'] ?? null;
                $deviceId = $fanConfig['deviceId'] ?? null;

                if (!$controllerId || !$deviceId) continue;

                // MANUAL 모드: power 상태에 따라 제어 (설정 변경 시에만)
                if ($mode === 'MANUAL') {
                    $power = strtoupper($fanConfig['power'] ?? 'OFF');
                    $stateKey = "fan_{$fanId}_power";
                    $lastPower = $deviceCycleState[$stateKey] ?? null;

                    if ($lastPower !== $power) {
                        $topic = "tansaeng/{$controllerId}/{$deviceId}/cmd";
                        $mqtt->publish($topic, $power, 1);
                        $deviceCycleState[$stateKey] = $power;
                        echo "[" . date('H:i:s') . "] [FAN] {$fanId}: {$power}\n";
                    }
                }
                // OFF 모드: 팬 끄기
                elseif ($mode === 'OFF') {
                    $stateKey = "fan_{$fanId}_power";
                    $lastPower = $deviceCycleState[$stateKey] ?? null;

                    if ($lastPower !== 'OFF') {
                        $topic = "tansaeng/{$controllerId}/{$deviceId}/cmd";
                        $mqtt->publish($topic, 'OFF', 1);
                        $deviceCycleState[$stateKey] = 'OFF';
                        echo "[" . date('H:i:s') . "] [FAN] {$fanId}: OFF (mode=OFF)\n";
                    }
                }
                // AUTO 모드: 추후 온도 기반 자동 제어 구현 가능
            }
        }

        // ========== 분무수경 밸브 자동 제어 ==========
        if (isset($cachedSettings['mist_zones'])) {
            foreach ($cachedSettings['mist_zones'] as $zoneId => $zoneConfig) {
                $mode = $zoneConfig['mode'] ?? 'OFF';
                $controllerId = $zoneConfig['controllerId'] ?? null;
                $deviceId = $zoneConfig['deviceId'] ?? 'valve1';
                $isRunning = $zoneConfig['isRunning'] ?? false;

                if (!$controllerId) continue;

                $topic = "tansaeng/{$controllerId}/{$deviceId}/cmd";
                $stateKey = "mist_{$zoneId}";

                // OFF 모드: 밸브 닫기
                if ($mode === 'OFF') {
                    $lastState = $deviceCycleState[$stateKey]['valveState'] ?? null;
                    if ($lastState !== 'CLOSE') {
                        $mqtt->publish($topic, 'CLOSE', 1);
                        $deviceCycleState[$stateKey] = ['valveState' => 'CLOSE', 'startTime' => 0];
                        echo "[" . date('H:i:s') . "] [MIST] {$zoneId}: CLOSE (mode=OFF)\n";
                    }
                    continue;
                }

                // MANUAL 모드: 웹UI에서 직접 제어하므로 데몬은 개입 안함
                if ($mode === 'MANUAL') {
                    continue;
                }

                // AUTO 모드: 스케줄에 따라 자동 제어
                if ($mode === 'AUTO' && $isRunning) {
                    $schedule = getCurrentSchedule($zoneConfig);

                    if (!$schedule) {
                        // 현재 스케줄 시간대가 아니면 밸브 닫기
                        $lastState = $deviceCycleState[$stateKey]['valveState'] ?? null;
                        if ($lastState !== 'CLOSE') {
                            $mqtt->publish($topic, 'CLOSE', 1);
                            $deviceCycleState[$stateKey] = ['valveState' => 'CLOSE', 'startTime' => 0];
                            echo "[" . date('H:i:s') . "] [MIST] {$zoneId}: CLOSE (no schedule)\n";
                        }
                        continue;
                    }

                    // 사이클 계산
                    $spraySeconds = $schedule['sprayDurationSeconds'] ?? 5;
                    $stopSeconds = $schedule['stopDurationSeconds'] ?? 10;
                    $cycleTotal = $spraySeconds + $stopSeconds;

                    if ($cycleTotal <= 0) continue;

                    // 사이클 시작 시간 초기화
                    if (!isset($deviceCycleState[$stateKey]['startTime']) || $deviceCycleState[$stateKey]['startTime'] == 0) {
                        $deviceCycleState[$stateKey] = [
                            'valveState' => 'CLOSE',
                            'startTime' => $currentMicro
                        ];
                    }

                    // 사이클 내 현재 위치 계산
                    $elapsed = $currentMicro - $deviceCycleState[$stateKey]['startTime'];
                    $cycleElapsed = fmod($elapsed, $cycleTotal);

                    $currentState = $deviceCycleState[$stateKey]['valveState'];

                    // 분무 시간대 (0 ~ spraySeconds)
                    if ($cycleElapsed < $spraySeconds) {
                        if ($currentState !== 'OPEN') {
                            $mqtt->publish($topic, 'OPEN', 1);
                            $deviceCycleState[$stateKey]['valveState'] = 'OPEN';
                            echo "[" . date('H:i:s') . "] [MIST] {$zoneId}: OPEN (spray {$spraySeconds}s)\n";
                        }
                    }
                    // 정지 시간대 (spraySeconds ~ cycleTotal)
                    else {
                        if ($currentState !== 'CLOSE') {
                            $mqtt->publish($topic, 'CLOSE', 1);
                            $deviceCycleState[$stateKey]['valveState'] = 'CLOSE';
                            echo "[" . date('H:i:s') . "] [MIST] {$zoneId}: CLOSE (stop {$stopSeconds}s)\n";
                        }
                    }
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

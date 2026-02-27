#!/usr/bin/php
<?php
/**
 * MQTT 백그라운드 데몬
 * - 센서 데이터 수신 및 데이터베이스 저장
 * - 장치 자동 제어 (device_settings.json 기반)
 * - 환기팬, 분무수경 밸브 등 모든 장치 지원
 * - 이상 발생 시 Telegram/이메일 알림
 */

// 한국 시간대 설정
date_default_timezone_set('Asia/Seoul');

// systemd로 관리되므로 PID 파일 불필요 (systemd가 단일 인스턴스 보장)

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

// ========== 알림 설정 로드 ==========
function loadAlertConfig() {
    $file = __DIR__ . '/../config/alert_config.json';
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true) ?? [];
    }
    return [];
}

// 알림 발송 쿨다운 추적 (스팸 방지)
$GLOBALS['alertCooldowns'] = [];

// 센서별 최신 온도 추적 (평균 계산용)
$GLOBALS['latestTemps'] = [];

// Telegram 메시지 전송
function sendTelegramAlert($token, $chatId, $message) {
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = json_encode(['chat_id' => $chatId, 'text' => $message, 'parse_mode' => 'HTML']);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $result = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) {
        echo "[ALERT] Telegram 오류: {$err}\n";
        return false;
    }
    return true;
}

// 이메일 알림 전송 (sendmail/postfix 사용)
function sendEmailAlert($to, $from, $subject, $body) {
    $headers = "From: {$from}\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    $result = @mail($to, $subject, $body, $headers);
    if (!$result) {
        echo "[ALERT] 이메일 발송 실패\n";
    }
    return $result;
}

// 통합 알림 발송 (쿨다운 적용)
function sendAlert($alertKey, $title, $message) {
    $config = loadAlertConfig();
    if (empty($config)) return;

    $cooldownMinutes = $config['cooldown_minutes'] ?? 30;
    $now = time();

    // 쿨다운 체크
    $lastSent = $GLOBALS['alertCooldowns'][$alertKey] ?? 0;
    if ($now - $lastSent < ($cooldownMinutes * 60)) {
        echo "[ALERT] 쿨다운 중 ({$alertKey}) - 스킵\n";
        return;
    }
    $GLOBALS['alertCooldowns'][$alertKey] = $now;

    $timestamp = date('Y-m-d H:i:s');
    $fullMessage = "[탄생농원 스마트팜]\n{$title}\n{$message}\n시각: {$timestamp}";

    echo "[ALERT] 알림 발송: {$title}\n";

    // Telegram 발송
    if (!empty($config['telegram']['enabled']) && $config['telegram']['enabled']) {
        $token  = $config['telegram']['bot_token'] ?? '';
        $chatId = $config['telegram']['chat_id'] ?? '';
        if ($token && $chatId && $token !== 'YOUR_BOT_TOKEN_HERE') {
            $telegramMsg = "<b>[탄생농원 스마트팜]</b>\n<b>{$title}</b>\n{$message}\n시각: {$timestamp}";
            if (sendTelegramAlert($token, $chatId, $telegramMsg)) {
                echo "[ALERT] Telegram 발송 완료\n";
            }
        }
    }

    // 이메일 발송
    if (!empty($config['email']['enabled']) && $config['email']['enabled']) {
        $to   = $config['email']['to'] ?? '';
        $from = $config['email']['from'] ?? 'smartfarm@tansaeng.com';
        if ($to) {
            sendEmailAlert($to, $from, "[스마트팜] {$title}", $fullMessage);
            echo "[ALERT] 이메일 발송: {$to}\n";
        }
    }
}

// 센서 데이터 저장 쓰로틀링 (1분 단위 저장) - 전역 변수 사용
$GLOBALS['sensorSaveThrottle'] = [];
define('SENSOR_SAVE_INTERVAL', 300); // 5분 단위

// 실시간 센서 데이터 캐시 파일 (UI 표시용)
define('REALTIME_SENSOR_FILE', __DIR__ . '/../config/realtime_sensor.json');

// 실시간 센서 데이터 업데이트 함수 (매 MQTT 메시지마다 호출)
function updateRealtimeSensorCache($controllerId, $dataType, $value) {
    $location = match($controllerId) {
        'ctlr-0001' => 'front',
        'ctlr-0002' => 'back',
        'ctlr-0003' => 'top',
        default => null
    };

    if (!$location) return;

    // 기존 캐시 읽기
    $cache = [];
    if (file_exists(REALTIME_SENSOR_FILE)) {
        $json = file_get_contents(REALTIME_SENSOR_FILE);
        $cache = json_decode($json, true) ?? [];
    }

    // 위치별 데이터 초기화
    if (!isset($cache[$location])) {
        $cache[$location] = [
            'temperature' => null,
            'humidity' => null,
            'lastUpdate' => null
        ];
    }

    // 값 업데이트
    $cache[$location][$dataType] = floatval($value);
    $cache[$location]['lastUpdate'] = date('Y-m-d H:i:s');

    // 파일 저장
    file_put_contents(REALTIME_SENSOR_FILE, json_encode($cache, JSON_PRETTY_PRINT));
}

// ESP32 장치 연결 상태 업데이트 함수
function updateDeviceStatus($db, $controllerId, $status) {
    try {
        // 빈 메시지 무시 (retained 메시지 삭제 시 발생)
        $status = trim($status);
        if (empty($status)) return;

        // 유효한 상태값만 허용
        $validStatuses = ['online', 'offline', 'on', 'off', 'connected', 'disconnected'];
        $normalizedStatus = strtolower($status);
        if (in_array($normalizedStatus, ['online', 'on', 'connected', 'true', 'active', 'alive'])) {
            $status = 'online';
        } elseif (in_array($normalizedStatus, ['offline', 'off', 'disconnected', 'false', 'inactive', 'dead'])) {
            $status = 'offline';
        } else {
            // 알 수 없는 상태값은 무시
            return;
        }

        $sql = "INSERT INTO device_status (controller_id, status, last_seen)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                last_seen = NOW()";

        $db->query($sql, [$controllerId, $status]);
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

// 평균 온도 계산 및 임계값 알림
function checkTemperatureAlert() {
    $temps = array_values(array_filter($GLOBALS['latestTemps'], fn($v) => $v !== null));
    if (empty($temps)) return;

    $avg = round(array_sum($temps) / count($temps), 1);
    $config = loadAlertConfig();

    $low  = $config['temp_alert_low']  ?? 5;
    $high = $config['temp_alert_high'] ?? 28;

    if ($avg <= $low) {
        sendAlert('temp_low', '🥶 저온 경보',
            "하우스 평균온도 {$avg}°C\n기준온도({$low}°C) 이하입니다.\n현장 확인이 필요합니다.");
    } elseif ($avg >= $high) {
        sendAlert('temp_high', '🔥 고온 경보',
            "하우스 평균온도 {$avg}°C\n기준온도({$high}°C) 이상입니다.\n환기 또는 냉방 조치가 필요합니다.");
    }
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

    // 데몬 시작 알림 (재시작 감지용)
    $alertConfig = loadAlertConfig();
    if (!empty($alertConfig['alert_on_daemon_restart'])) {
        sendAlert('daemon_restart', '⚙️ 데몬 재시작',
            "MQTT 제어 데몬이 시작되었습니다.\n분무수경 자동제어가 활성화됩니다.");
    }

    // 시작 시간 기록 (retained 메시지 무시용)
    // MQTT 구독 직후 수신되는 메시지는 retained 메시지이므로 무시
    $daemonStartTime = microtime(true);
    $STARTUP_GRACE_PERIOD = 5; // 시작 후 5초간 status/state 메시지 무시

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
            $parts = explode('/', $topic);
            $controllerId = $parts[1];
            $sensorType = $parts[2];
            $dataType = $parts[3];

            // 실시간 캐시 업데이트 (UI 표시용 - 매번 호출)
            updateRealtimeSensorCache($controllerId, $dataType, $message);

            // 온도 알림 체크 (temperature 데이터일 때만)
            if ($dataType === 'temperature') {
                $GLOBALS['latestTemps'][$controllerId] = floatval($message);
                checkTemperatureAlert();
            }

            // 쓰로틀 키 생성 (컨트롤러+센서+데이터타입 조합)
            $throttleKey = "{$controllerId}_{$sensorType}_{$dataType}";
            $now = time();

            // 마지막 저장 시간 확인 (1분 경과 시에만 DB 저장)
            $lastSave = $GLOBALS['sensorSaveThrottle'][$throttleKey] ?? 0;
            if ($now - $lastSave >= SENSOR_SAVE_INTERVAL) {
                echo "[MQTT] {$topic}: {$message}\n";
                saveSensorData($db, $controllerId, $sensorType, $dataType, $message);
                $GLOBALS['sensorSaveThrottle'][$throttleKey] = $now;
            }

            // 장치 상태는 항상 업데이트
            updateDeviceStatus($db, $controllerId, 'online');
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
        $mqtt->subscribe($topic, function ($topic, $message) use ($db, $daemonStartTime, $STARTUP_GRACE_PERIOD) {
            // 시작 직후 수신된 메시지는 retained 메시지이므로 무시
            if ((microtime(true) - $daemonStartTime) < $STARTUP_GRACE_PERIOD) {
                $parts = explode('/', $topic);
                echo "[MQTT] Ignoring retained status from {$parts[1]}: {$message}\n";
                return;
            }
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
        'tansaeng/ctlr-0009/pong',
        'tansaeng/ctlr-0010/pong',
        'tansaeng/ctlr-0012/pong',
        'tansaeng/ctlr-0013/pong',
        'tansaeng/ctlr-0021/pong',
    ];

    foreach ($pongTopics as $topic) {
        $mqtt->subscribe($topic, function ($topic, $message) use ($db) {
            $parts = explode('/', $topic);
            $controllerId = $parts[1];
            updateDeviceStatus($db, $controllerId, 'online');
        }, 0);
    }

    // 장치 state 토픽 구독
    $mqtt->subscribe('tansaeng/+/+/state', function ($topic, $message) use ($db, $daemonStartTime, $STARTUP_GRACE_PERIOD) {
        // 시작 직후 수신된 메시지는 retained 메시지이므로 무시
        if ((microtime(true) - $daemonStartTime) < $STARTUP_GRACE_PERIOD) {
            $parts = explode('/', $topic);
            echo "[MQTT] Ignoring retained state from {$parts[1]}: {$message}\n";
            return;
        }
        $parts = explode('/', $topic);
        $controllerId = $parts[1];
        updateDeviceStatus($db, $controllerId, 'online');
    }, 0);

    echo "[MQTT] Subscribed to all topics\n\n";

    // 메인 루프 변수
    $lastDeviceCheck = 0;
    $lastSettingsCheck = 0;
    $cachedSettings = null;
    $missedPingCounts = []; // 연속 실패 카운터 (히스테리시스)

    $mqtt->registerLoopEventHandler(function (MqttClient $mqtt) use ($db, &$lastDeviceCheck, &$lastSettingsCheck, &$cachedSettings, &$deviceCycleState, &$missedPingCounts) {
        $now = time();
        $currentMicro = microtime(true);

        // 장치 상태 체크 (30초마다)
        if ($now - $lastDeviceCheck >= 30) {
            $lastDeviceCheck = $now;

            $devices = ['ctlr-0001', 'ctlr-0002', 'ctlr-0003', 'ctlr-0004', 'ctlr-0005', 'ctlr-0006', 'ctlr-0007', 'ctlr-0008', 'ctlr-0012', 'ctlr-0013', 'ctlr-0021'];
            foreach ($devices as $deviceId) {
                $mqtt->publish("tansaeng/{$deviceId}/ping", "ping", 0);
            }

            // 3분 이상 응답 없는 장치 확인 → 연속 2회 실패 시에만 offline 처리
            $alertCfg = loadAlertConfig();
            $mistZones = loadDeviceSettings()['mist_zones'] ?? [];

            $sql = "SELECT controller_id FROM device_status WHERE status = 'online' AND last_seen < DATE_SUB(NOW(), INTERVAL 3 MINUTE)";
            $staleDevices = $db->select($sql);
            foreach ($staleDevices as $device) {
                $cid = $device['controller_id'];
                $missedPingCounts[$cid] = ($missedPingCounts[$cid] ?? 0) + 1;
                if ($missedPingCounts[$cid] >= 2) {
                    $db->query("UPDATE device_status SET status = 'offline' WHERE controller_id = ?", [$cid]);
                    echo "[" . date('H:i:s') . "] [OFFLINE] {$cid} (missed {$missedPingCounts[$cid]} checks)\n";
                    $missedPingCounts[$cid] = 0;

                    // 분무수경 AUTO 작동 중인 존의 컨트롤러가 오프라인이면 알림
                    if (!empty($alertCfg['alert_on_device_offline'])) {
                        foreach ($mistZones as $zoneId => $zoneConfig) {
                            if (($zoneConfig['controllerId'] ?? '') === $cid
                                && ($zoneConfig['mode'] ?? '') === 'AUTO'
                                && ($zoneConfig['isRunning'] ?? false)) {
                                $zoneName = strtoupper($zoneId);
                                sendAlert("offline_{$cid}", "🚨 밸브 컨트롤러 오프라인",
                                    "{$cid} ({$zoneName})가 응답하지 않습니다.\n분무수경 AUTO 제어가 중단되었을 수 있습니다.\n현장 확인이 필요합니다.");
                            }
                        }
                    }
                }
            }

            // pong 수신 시 카운터 리셋 (updateDeviceStatus에서 online으로 업데이트 될 때)
            $sqlOnline = "SELECT controller_id FROM device_status WHERE status = 'online' AND last_seen >= DATE_SUB(NOW(), INTERVAL 3 MINUTE)";
            $onlineDevices = $db->select($sqlOnline);
            foreach ($onlineDevices as $device) {
                $missedPingCounts[$device['controller_id']] = 0;
            }
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
                            $deviceCycleState[$stateKey]['openedAt'] = $currentMicro;
                            echo "[" . date('H:i:s') . "] [MIST] {$zoneId}: OPEN (spray {$spraySeconds}s)\n";
                        }
                        // 밸브 장기 열림 감지 (스케줄 오류 등으로 밸브가 비정상적으로 오래 열린 경우)
                        $alertCfg2 = loadAlertConfig();
                        $stuckMinutes = $alertCfg2['alert_on_valve_stuck_minutes'] ?? 60;
                        $openedAt = $deviceCycleState[$stateKey]['openedAt'] ?? $currentMicro;
                        if ($currentState === 'OPEN' && ($currentMicro - $openedAt) > ($stuckMinutes * 60)) {
                            sendAlert("stuck_{$zoneId}", "⚠️ 밸브 장기 열림 감지",
                                "{$zoneId} ({$controllerId}) 밸브가 {$stuckMinutes}분 이상 열려있습니다.\n설정을 확인하거나 현장 점검이 필요합니다.");
                        }
                    }
                    // 정지 시간대 (spraySeconds ~ cycleTotal)
                    else {
                        if ($currentState !== 'CLOSE') {
                            $mqtt->publish($topic, 'CLOSE', 1);
                            $deviceCycleState[$stateKey]['valveState'] = 'CLOSE';
                            $deviceCycleState[$stateKey]['openedAt'] = 0;
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

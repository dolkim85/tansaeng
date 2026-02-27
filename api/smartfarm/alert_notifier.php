<?php
/**
 * AlertNotifier - 웹API에서 텔레그램 알림 발송
 * device_control.php, save_device_settings.php에서 include하여 사용
 */

function _apiLoadAlertConfig() {
    $configFile = __DIR__ . '/../../config/alert_config.json';
    if (!file_exists($configFile)) return null;
    return json_decode(file_get_contents($configFile), true);
}

function _apiSendTelegram($message) {
    $config = _apiLoadAlertConfig();
    if (!$config) return;

    $t = $config['telegram'] ?? [];
    if (empty($t['enabled']) || empty($t['bot_token']) || empty($t['chat_id'])) return;

    $url  = "https://api.telegram.org/bot{$t['bot_token']}/sendMessage";
    $body = http_build_query([
        'chat_id'    => $t['chat_id'],
        'text'       => $message,
        'parse_mode' => 'HTML',
    ]);

    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => 'Content-Type: application/x-www-form-urlencoded',
        'content' => $body,
        'timeout' => 3,
    ]]);
    @file_get_contents($url, false, $ctx);
}

function getDeviceName($controllerId, $deviceId) {
    $map = [
        'ctlr-0001'    => ['fan1'                => '내부팬 앞'],
        'ctlr-0002'    => ['fan2'                => '내부팬 뒤'],
        'esp32-node-1' => ['fan_top'             => '천장팬'],
        'ctlr-0012'    => ['windowL'             => '천창 좌측', 'windowR' => '천창 우측'],
        'ctlr-0021'    => ['sideL'               => '측창 좌측', 'sideR'   => '측창 우측'],
        'esp32-node-3' => [
            'pump_nutrient_fill' => '양액탱크 급수펌프',
            'pump_water_curtain' => '수막펌프',
            'pump_heating_fill'  => '히팅탱크 급수펌프',
        ],
        'ctlr-0004' => ['valve1' => '분무수경 A구역 밸브'],
        'ctlr-0005' => ['valve1' => '분무수경 B구역 밸브'],
        'ctlr-0006' => ['valve1' => '분무수경 C구역 밸브'],
        'ctlr-0007' => ['valve1' => '분무수경 D구역 밸브'],
        'ctlr-0008' => ['valve1' => '분무수경 E구역 밸브'],
    ];
    return $map[$controllerId][$deviceId] ?? "$controllerId/$deviceId";
}

function getZoneName($zoneId) {
    $map = [
        'zone_a' => 'A구역', 'zone_b' => 'B구역', 'zone_c' => 'C구역',
        'zone_d' => 'D구역', 'zone_e' => 'E구역',
    ];
    return $map[$zoneId] ?? $zoneId;
}

function getCommandText($command) {
    $map = [
        'ON'    => '켜짐 🟢',
        'OFF'   => '꺼짐 ⚫',
        'OPEN'  => '열기 🔓',
        'CLOSE' => '닫기 🔒',
        'STOP'  => '정지 ■',
    ];
    return $map[$command] ?? $command;
}

/**
 * 장치 명령 알림 (device_control.php에서 호출)
 */
function notifyDeviceCommand($controllerId, $deviceId, $command) {
    $device = getDeviceName($controllerId, $deviceId);
    $cmd    = getCommandText($command);
    $time   = date('H:i:s');
    $msg    = "🌿 <b>탄생농원 장치제어</b>\n장치: {$device}\n명령: {$cmd}\n시각: {$time}";
    _apiSendTelegram($msg);
}

/**
 * 분무수경 설정 변경 알림 (save_device_settings.php에서 호출)
 * 기존 설정과 새 설정을 비교하여 변경사항만 알림
 */
function notifyMistZoneChanges($existingSettings, $incomingData) {
    if (empty($incomingData['mist_zones'])) return;

    $existingZones = $existingSettings['mist_zones'] ?? [];
    $time = date('H:i:s');

    foreach ($incomingData['mist_zones'] as $zoneId => $newZone) {
        $old  = $existingZones[$zoneId] ?? [];
        $zone = getZoneName($zoneId);

        // 1. isRunning 변경 감지 (작동 시작 / 중지)
        if (isset($newZone['isRunning']) && ($newZone['isRunning'] !== ($old['isRunning'] ?? false))) {
            if ($newZone['isRunning']) {
                $mode = ($newZone['mode'] ?? $old['mode'] ?? '') === 'AUTO' ? 'AUTO' : 'MANUAL';
                _apiSendTelegram("💧 <b>분무수경 작동 시작</b>\n구역: {$zone} ({$mode})\n시각: {$time}");
            } else {
                _apiSendTelegram("⏹ <b>분무수경 작동 중지</b>\n구역: {$zone}\n시각: {$time}");
            }
        }
        // 2. 모드 변경 감지 (isRunning 변경 없는 순수 모드 변경)
        elseif (isset($newZone['mode']) && ($newZone['mode'] !== ($old['mode'] ?? ''))) {
            $modeLabels = ['AUTO' => '자동(AUTO)', 'MANUAL' => '수동(MANUAL)', 'OFF' => '꺼짐(OFF)'];
            $label = $modeLabels[$newZone['mode']] ?? $newZone['mode'];
            _apiSendTelegram("⚙️ <b>분무수경 모드 변경</b>\n구역: {$zone}\n모드: {$label}\n시각: {$time}");
        }

        // 3. 스케줄 활성화 상태 변경 감지
        if (isset($newZone['daySchedule']['enabled'])) {
            $wasEnabled = $old['daySchedule']['enabled'] ?? null;
            $nowEnabled = $newZone['daySchedule']['enabled'];
            if ($wasEnabled !== $nowEnabled) {
                $status = $nowEnabled ? '활성화 ✅' : '비활성화';
                _apiSendTelegram("📅 <b>분무수경 스케줄 변경</b>\n구역: {$zone}\n주간 스케줄: {$status}\n시각: {$time}");
            }
        }
        if (isset($newZone['nightSchedule']['enabled'])) {
            $wasEnabled = $old['nightSchedule']['enabled'] ?? null;
            $nowEnabled = $newZone['nightSchedule']['enabled'];
            if ($wasEnabled !== $nowEnabled) {
                $status = $nowEnabled ? '활성화 ✅' : '비활성화';
                _apiSendTelegram("📅 <b>분무수경 스케줄 변경</b>\n구역: {$zone}\n야간 스케줄: {$status}\n시각: {$time}");
            }
        }
    }
}

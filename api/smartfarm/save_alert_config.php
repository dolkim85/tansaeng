<?php
/**
 * 알림 설정 저장 API (관리자 전용)
 * POST: JSON body with allowed fields
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// 관리자 세션 확인
$basePath = dirname(dirname(__DIR__));
require_once $basePath . '/classes/Auth.php';
$auth = Auth::getInstance();
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = file_get_contents('php://input');
$data  = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$configFile = $basePath . '/config/alert_config.json';

// 기존 설정 로드
$current = [];
if (file_exists($configFile)) {
    $current = json_decode(file_get_contents($configFile), true) ?? [];
}

// 허용 필드만 업데이트 (화이트리스트)
$telegram = $data['telegram'] ?? [];
if ($telegram) {
    $current['telegram']['enabled']   = (bool)($telegram['enabled'] ?? false);
    $current['telegram']['bot_token'] = trim($telegram['bot_token'] ?? $current['telegram']['bot_token'] ?? '');
    $current['telegram']['chat_id']   = trim($telegram['chat_id'] ?? $current['telegram']['chat_id'] ?? '');
}

if (isset($data['cooldown_minutes']))              $current['cooldown_minutes']              = max(1, (int)$data['cooldown_minutes']);
if (isset($data['alert_on_device_offline']))       $current['alert_on_device_offline']       = (bool)$data['alert_on_device_offline'];
if (isset($data['alert_on_daemon_restart']))       $current['alert_on_daemon_restart']       = (bool)$data['alert_on_daemon_restart'];
if (isset($data['alert_on_valve_stuck_minutes']))  $current['alert_on_valve_stuck_minutes']  = max(1, (int)$data['alert_on_valve_stuck_minutes']);
if (isset($data['temp_alert_low']))                $current['temp_alert_low']                = (float)$data['temp_alert_low'];
if (isset($data['temp_alert_high']))               $current['temp_alert_high']               = (float)$data['temp_alert_high'];
if (isset($data['humidity_alert_low']))            $current['humidity_alert_low']            = (float)$data['humidity_alert_low'];
if (isset($data['humidity_alert_high']))           $current['humidity_alert_high']           = (float)$data['humidity_alert_high'];

$result = file_put_contents($configFile, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

if ($result === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save config']);
    exit;
}

echo json_encode(['success' => true, 'message' => '알림 설정이 저장되었습니다.', 'config' => $current], JSON_UNESCAPED_UNICODE);

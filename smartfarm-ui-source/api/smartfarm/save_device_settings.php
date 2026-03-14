<?php
/**
 * 장치 설정 저장 API
 * 웹UI에서 설정한 장치 모드/스케줄을 서버에 저장합니다.
 * 데몬이 이 설정을 읽어서 자동 제어합니다.
 */

header('Content-Type: application/json');
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

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

$settingsFile = __DIR__ . '/../../config/device_settings.json';

// 기존 설정 로드
$existingSettings = [];
if (file_exists($settingsFile)) {
    $existingSettings = json_decode(file_get_contents($settingsFile), true) ?? [];
}

// 설정 병합 (기존 설정에 새 설정 덮어쓰기)
// 구조: { "fans": {...}, "mist_zones": {...}, "skylights": {...}, "sidescreens": {...} }
$newSettings = array_replace_recursive($existingSettings, $data);

// 타임스탬프 추가
$newSettings['lastUpdated'] = date('Y-m-d H:i:s');

// 파일 저장
$result = file_put_contents($settingsFile, json_encode($newSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

if ($result === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save settings']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Settings saved successfully',
    'data' => $newSettings
]);

<?php
/**
 * 농장 기본 정보 저장 API
 * 웹UI 설정 탭에서 입력한 농장명/관리자명/메모를 서버에 저장합니다.
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
$data  = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

// 허용 필드만 추출
$settings = [
    'farmName'  => isset($data['farmName'])  ? trim($data['farmName'])  : '',
    'adminName' => isset($data['adminName']) ? trim($data['adminName']) : '',
    'notes'     => isset($data['notes'])     ? trim($data['notes'])     : '',
    'updatedAt' => date('Y-m-d H:i:s'),
];

$settingsFile = __DIR__ . '/../../config/farm_settings.json';

$result = file_put_contents(
    $settingsFile,
    json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

if ($result === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '파일 저장에 실패했습니다.']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => '농장 정보가 저장되었습니다.',
    'data'    => $settings,
]);

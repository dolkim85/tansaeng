<?php
/**
 * 알림 설정 읽기 API (관리자 전용)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
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

$configFile = $basePath . '/config/alert_config.json';

if (!file_exists($configFile)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Config file not found']);
    exit;
}

$config = json_decode(file_get_contents($configFile), true);
if ($config === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Invalid config JSON']);
    exit;
}

echo json_encode(['success' => true, 'config' => $config], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

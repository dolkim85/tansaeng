<?php
/**
 * 장치별 온도 범위 설정 저장 API (upsert)
 * POST { device_key, device_name, range_low, range_high }
 */
date_default_timezone_set('Asia/Seoul');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

require_once __DIR__ . '/../../classes/Database.php';

$input = json_decode(file_get_contents('php://input'), true);
$device_key  = trim($input['device_key']  ?? '');
$device_name = trim($input['device_name'] ?? '');
$range_low   = isset($input['range_low'])  ? (float)$input['range_low']  : null;
$range_high  = isset($input['range_high']) ? (float)$input['range_high'] : null;

if (!$device_key || !$device_name || $range_low === null || $range_high === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '필수 파라미터 누락']);
    exit;
}
if ($range_low >= $range_high) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '최솟값이 최댓값보다 작아야 합니다']);
    exit;
}

try {
    $db = Database::getInstance();

    $db->query("CREATE TABLE IF NOT EXISTS device_ranges (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        device_key  VARCHAR(50)  NOT NULL,
        device_name VARCHAR(100) NOT NULL,
        range_low   FLOAT        NOT NULL DEFAULT 15,
        range_high  FLOAT        NOT NULL DEFAULT 22,
        updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_device_key (device_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query(
        "INSERT INTO device_ranges (device_key, device_name, range_low, range_high)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE device_name=VALUES(device_name),
                                  range_low=VALUES(range_low),
                                  range_high=VALUES(range_high),
                                  updated_at=NOW()",
        [$device_key, $device_name, $range_low, $range_high]
    );

    $updated = $db->selectOne(
        "SELECT DATE_FORMAT(updated_at, '%m/%d %H:%i') AS updated_fmt FROM device_ranges WHERE device_key=?",
        [$device_key]
    );

    echo json_encode([
        'success'    => true,
        'updated_at' => $updated['updated_fmt'] ?? '',
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

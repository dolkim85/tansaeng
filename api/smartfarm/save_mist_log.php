<?php
/**
 * 분무수경 이벤트 로그 저장 API
 * mist-daemon (Node.js)에서 OPEN/CLOSE 시 호출
 * POST { zone_id, zone_name, event_type: "start"|"stop", mode }
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

$zone_id    = trim($input['zone_id']    ?? '');
$zone_name  = trim($input['zone_name']  ?? '');
$event_type = trim($input['event_type'] ?? '');
$mode       = trim($input['mode']       ?? 'AUTO');

if (!$zone_id || !$zone_name || !in_array($event_type, ['start', 'stop'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '필수 파라미터 누락']);
    exit;
}

try {
    $db = Database::getInstance();

    // mist_logs 테이블 없으면 자동 생성
    $db->query(
        "CREATE TABLE IF NOT EXISTS mist_logs (
            id         BIGINT AUTO_INCREMENT PRIMARY KEY,
            zone_id    VARCHAR(20)  NOT NULL,
            zone_name  VARCHAR(50)  NOT NULL,
            event_type ENUM('start','stop') NOT NULL,
            mode       VARCHAR(20)  NOT NULL DEFAULT 'AUTO',
            created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_zone_date  (zone_id, created_at),
            INDEX idx_date       (created_at),
            INDEX idx_event_date (event_type, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $db->insert('mist_logs', [
        'zone_id'    => $zone_id,
        'zone_name'  => $zone_name,
        'event_type' => $event_type,
        'mode'       => $mode,
    ]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

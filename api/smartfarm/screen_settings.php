<?php
/**
 * 스크린 개폐 시간 설정 API
 * GET: 천창/측창 전체 개폐 기준 시간(초) 조회
 * POST: 저장 및 MQTT retain 발행
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../classes/Database.php';

$pdo = Database::getInstance()->getConnection();

// ── GET ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query("SELECT screen_type, full_time_seconds, label FROM smartfarm_screen_settings ORDER BY id");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $data = [];
    foreach ($rows as $row) {
        $data[$row['screen_type']] = [
            'full_time_seconds' => (int)$row['full_time_seconds'],
            'label' => $row['label'],
        ];
    }
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

// ── POST ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);

    $allowed = ['sky', 'side'];
    $updated = [];

    foreach ($allowed as $type) {
        if (!isset($body[$type])) continue;
        $seconds = (int)$body[$type];
        if ($seconds < 10 || $seconds > 3600) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "유효하지 않은 값: {$type} ({$seconds}초). 10~3600초 범위여야 합니다."]);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE smartfarm_screen_settings SET full_time_seconds = ? WHERE screen_type = ?");
        $stmt->execute([$seconds, $type]);
        $updated[$type] = $seconds;
    }

    if (empty($updated)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '저장할 데이터가 없습니다.']);
        exit;
    }

    echo json_encode(['success' => true, 'updated' => $updated]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);

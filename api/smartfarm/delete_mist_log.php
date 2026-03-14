<?php
/**
 * 분무 로그 삭제 API
 * POST { "id": 123 }                            → 단건 삭제
 * POST { "date": "YYYY-MM-DD" }                 → 날짜 전체 삭제
 * POST { "date": "YYYY-MM-DD", "zone_id": "x" } → 날짜+구역 삭제
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

date_default_timezone_set('Asia/Seoul');
require_once __DIR__ . '/../../classes/Database.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

try {
    $db  = Database::getInstance();
    $pdo = $db->getConnection();

    if (isset($input['id'])) {
        // 단건 삭제
        $stmt = $pdo->prepare("DELETE FROM mist_logs WHERE id = ?");
        $stmt->execute([(int)$input['id']]);
        $deleted = $stmt->rowCount();

    } elseif (isset($input['date'])) {
        // 날짜 (+ 구역) 삭제
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['date'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid date format']);
            exit;
        }
        $sql    = "DELETE FROM mist_logs WHERE DATE(created_at) = ?";
        $params = [$input['date']];
        if (!empty($input['zone_id'])) {
            $sql     .= " AND zone_id = ?";
            $params[] = $input['zone_id'];
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $deleted = $stmt->rowCount();

    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'id or date required']);
        exit;
    }

    echo json_encode(['success' => true, 'deleted' => $deleted]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Auth.php';

header('Content-Type: application/json');

try {
    $auth = Auth::getInstance();
    if (!$auth->isLoggedIn()) {
        throw new Exception('로그인이 필요합니다.');
    }

    $currentUser = $auth->getCurrentUser();
    $userId = $currentUser['id'];

    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['device_id'])) {
        throw new Exception('디바이스 ID가 필요합니다.');
    }

    $db = Database::getInstance();

    // 권한 확인
    $device = $db->selectOne(
        "SELECT * FROM smartfarm_devices WHERE id = ? AND user_id = ?",
        [$input['device_id'], $userId]
    );

    if (!$device) {
        throw new Exception('디바이스를 찾을 수 없거나 권한이 없습니다.');
    }

    // 삭제
    $db->execute(
        "DELETE FROM smartfarm_devices WHERE id = ? AND user_id = ?",
        [$input['device_id'], $userId]
    );

    echo json_encode([
        'success' => true,
        'message' => '디바이스가 삭제되었습니다.'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

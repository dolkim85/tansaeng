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

    if (!isset($input['device_id']) || !isset($input['device_name']) || !isset($input['device_type'])) {
        throw new Exception('필수 정보가 누락되었습니다.');
    }

    $db = Database::getInstance();

    // 중복 확인
    $existing = $db->selectOne(
        "SELECT * FROM smartfarm_devices WHERE device_id = ?",
        [$input['device_id']]
    );

    if ($existing) {
        throw new Exception('이미 등록된 디바이스 ID입니다.');
    }

    // 디바이스 등록
    $sql = "INSERT INTO smartfarm_devices
            (user_id, device_id, device_name, device_type, is_active)
            VALUES (?, ?, ?, ?, 1)";

    $db->execute($sql, [
        $userId,
        $input['device_id'],
        $input['device_name'],
        $input['device_type']
    ]);

    echo json_encode([
        'success' => true,
        'message' => '디바이스가 등록되었습니다.'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

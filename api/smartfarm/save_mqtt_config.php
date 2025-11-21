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

    if (!isset($input['broker_url']) || !isset($input['username'])) {
        throw new Exception('필수 정보가 누락되었습니다.');
    }

    $db = Database::getInstance();

    // 기존 설정 확인
    $existing = $db->selectOne(
        "SELECT * FROM smartfarm_mqtt_configs WHERE user_id = ?",
        [$userId]
    );

    if ($existing) {
        // 업데이트
        $sql = "UPDATE smartfarm_mqtt_configs SET
                broker_url = ?, broker_port = ?, username = ?,
                " . ($input['password'] ? "password = ?," : "") . "
                use_tls = ?, updated_at = NOW()
                WHERE user_id = ?";

        $params = [
            $input['broker_url'],
            $input['broker_port'],
            $input['username']
        ];

        if ($input['password']) {
            $params[] = password_hash($input['password'], PASSWORD_DEFAULT);
        }

        $params[] = $input['use_tls'];
        $params[] = $userId;

        $db->execute($sql, $params);
    } else {
        // 새로 삽입
        $sql = "INSERT INTO smartfarm_mqtt_configs
                (user_id, broker_url, broker_port, username, password, use_tls)
                VALUES (?, ?, ?, ?, ?, ?)";

        $db->execute($sql, [
            $userId,
            $input['broker_url'],
            $input['broker_port'],
            $input['username'],
            password_hash($input['password'], PASSWORD_DEFAULT),
            $input['use_tls']
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'MQTT 설정이 저장되었습니다.'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

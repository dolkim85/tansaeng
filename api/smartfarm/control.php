<?php
/**
 * Smart Farm Control API
 * 스마트팜 제어 명령을 처리하고 저장
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Auth.php';

header('Content-Type: application/json');

try {
    // 사용자 인증 확인
    $auth = Auth::getInstance();
    if (!$auth->isLoggedIn()) {
        throw new Exception('로그인이 필요합니다.');
    }

    $currentUser = $auth->getCurrentUser();
    $userId = $currentUser['id'];

    // POST 데이터 받기
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['device']) || !isset($input['action'])) {
        throw new Exception('필수 파라미터가 누락되었습니다.');
    }

    $device = $input['device'];
    $action = $input['action'];
    $value = $input['value'] ?? null;
    $timestamp = $input['timestamp'] ?? time();

    // 데이터베이스에 제어 로그 저장
    $db = Database::getInstance();

    $sql = "INSERT INTO smartfarm_controls
            (user_id, device_name, action, value, created_at)
            VALUES (?, ?, ?, ?, ?)";

    $result = $db->execute($sql, [
        $userId,
        $device,
        $action,
        $value,
        date('Y-m-d H:i:s', $timestamp / 1000)
    ]);

    // 현재 디바이스 상태 업데이트
    $updateSql = "INSERT INTO smartfarm_device_states (user_id, device_name, status, value, updated_at)
                  VALUES (?, ?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE status = VALUES(status), value = VALUES(value), updated_at = VALUES(updated_at)";

    $db->execute($updateSql, [
        $userId,
        $device,
        $action,
        $value,
        date('Y-m-d H:i:s')
    ]);

    // MQTT 토픽으로 발행할 메시지 생성
    $mqttTopic = "smartfarm/{$userId}/{$device}";
    $mqttMessage = json_encode([
        'action' => $action,
        'value' => $value,
        'timestamp' => $timestamp
    ]);

    echo json_encode([
        'success' => true,
        'message' => '제어 명령이 전송되었습니다.',
        'mqtt_topic' => $mqttTopic,
        'mqtt_message' => $mqttMessage,
        'device' => $device,
        'action' => $action,
        'value' => $value
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

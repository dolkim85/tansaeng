<?php
/**
 * Smart Farm Schedule API
 * 분무수경 스케줄 설정 저장
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

    if (!isset($input['device']) || !isset($input['schedule'])) {
        throw new Exception('필수 파라미터가 누락되었습니다.');
    }

    $device = $input['device'];
    $schedule = $input['schedule'];

    // 스케줄 데이터 검증
    if (!isset($schedule['mode']) || !isset($schedule['duration']) || !isset($schedule['interval'])) {
        throw new Exception('스케줄 정보가 불완전합니다.');
    }

    $db = Database::getInstance();

    // 기존 스케줄 삭제 후 새로 삽입
    $deleteSql = "DELETE FROM smartfarm_schedules WHERE user_id = ? AND device_name = ?";
    $db->execute($deleteSql, [$userId, $device]);

    $insertSql = "INSERT INTO smartfarm_schedules
                  (user_id, device_name, mode, start_time, end_time, duration, interval_time, enabled, created_at)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $result = $db->execute($insertSql, [
        $userId,
        $device,
        $schedule['mode'],
        $schedule['start_time'] ?? null,
        $schedule['end_time'] ?? null,
        $schedule['duration'],
        $schedule['interval'],
        1,
        date('Y-m-d H:i:s')
    ]);

    // MQTT로 스케줄 정보 전송
    $mqttTopic = "smartfarm/{$userId}/{$device}/schedule";
    $mqttMessage = json_encode($schedule);

    echo json_encode([
        'success' => true,
        'message' => '스케줄이 저장되었습니다.',
        'mqtt_topic' => $mqttTopic,
        'mqtt_message' => $mqttMessage,
        'schedule' => $schedule
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

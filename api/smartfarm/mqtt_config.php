<?php
/**
 * MQTT Configuration API
 * 사용자별 MQTT 연결 설정 반환
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

    $db = Database::getInstance();

    // 사용자별 MQTT 설정 조회
    $config = $db->selectOne(
        "SELECT * FROM smartfarm_mqtt_configs WHERE user_id = ?",
        [$userId]
    );

    if (!$config) {
        // 기본 설정 반환 (HiveMQ Cloud 또는 다른 브로커)
        $config = [
            'broker_url' => null,
            'broker_port' => 8883,
            'username' => null,
            'client_id' => 'smartfarm_web_' . $userId . '_' . time(),
            'topic_prefix' => "smartfarm/{$userId}/",
            'use_tls' => true
        ];

        echo json_encode([
            'success' => true,
            'message' => 'MQTT가 설정되지 않았습니다. 디바이스 설정 페이지에서 설정해주세요.',
            'configured' => false,
            'broker_url' => null
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'configured' => true,
            'broker_url' => $config['broker_url'],
            'broker_port' => $config['broker_port'],
            'username' => $config['username'],
            'client_id' => 'smartfarm_web_' . $userId . '_' . time(),
            'topic_prefix' => "smartfarm/{$userId}/",
            'use_tls' => $config['use_tls'] == 1
        ]);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

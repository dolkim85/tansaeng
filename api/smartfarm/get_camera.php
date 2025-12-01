<?php
/**
 * Get Camera Configuration API
 * 카메라 설정 정보 조회
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

    if (!isset($_GET['id'])) {
        throw new Exception('카메라 ID가 필요합니다.');
    }

    $cameraId = $_GET['id'];
    $db = Database::getInstance();

    // 카메라 설정 조회
    $camera = $db->selectOne(
        "SELECT * FROM smartfarm_cameras WHERE user_id = ? AND camera_id = ?",
        [$userId, $cameraId]
    );

    if ($camera && $camera['enabled']) {
        echo json_encode([
            'success' => true,
            'stream_url' => $camera['stream_url'],
            'stream_type' => $camera['stream_type'],
            'camera_name' => $camera['camera_name']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => '카메라가 설정되지 않았습니다.'
        ]);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

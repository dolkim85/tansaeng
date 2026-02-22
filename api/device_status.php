<?php
/**
 * ESP32 장치 연결 상태 API
 * GET /api/device_status.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../classes/Database.php';

try {
    $db = Database::getInstance();

    // 모든 장치 상태 조회
    $sql = "SELECT
                controller_id,
                status,
                last_seen,
                TIMESTAMPDIFF(SECOND, last_seen, NOW()) as seconds_ago
            FROM device_status
            ORDER BY controller_id";

    $devices = $db->select($sql);

    // 각 장치별로 정보 구성 (디바운스: 90초 이내면 online 유지)
    $result = [];
    foreach ($devices as $device) {
        $secondsAgo = (int)$device['seconds_ago'];
        // 디바운스: last_seen이 90초 이내면 DB status와 무관하게 online 반환
        $isOnline = ($secondsAgo <= 90) ? true : ($device['status'] === 'online');
        $result[$device['controller_id']] = [
            'status' => $isOnline ? 'online' : $device['status'],
            'last_seen' => $device['last_seen'],
            'seconds_ago' => $secondsAgo,
            'is_online' => $isOnline
        ];
    }

    echo json_encode([
        'success' => true,
        'devices' => $result,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

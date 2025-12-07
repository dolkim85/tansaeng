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

    // 각 장치별로 정보 구성
    $result = [];
    foreach ($devices as $device) {
        $result[$device['controller_id']] = [
            'status' => $device['status'],
            'last_seen' => $device['last_seen'],
            'seconds_ago' => (int)$device['seconds_ago'],
            'is_online' => $device['status'] === 'online'
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

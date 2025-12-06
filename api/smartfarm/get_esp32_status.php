<?php
/**
 * ESP32 장치 연결 상태 조회 API
 * 데몬이 수집한 상태를 DB에서 조회
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../config/database.php';

try {
    $db = getDBConnection();

    // 모든 ESP32 장치 상태 조회
    $stmt = $db->query("
        SELECT
            controller_id,
            controller_name,
            is_connected,
            last_heartbeat,
            TIMESTAMPDIFF(SECOND, last_heartbeat, NOW()) as seconds_since_heartbeat
        FROM esp32_status
        ORDER BY controller_id
    ");

    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 결과 가공
    $result = [];
    foreach ($devices as $device) {
        $result[$device['controller_id']] = [
            'name' => $device['controller_name'],
            'connected' => (bool)$device['is_connected'],
            'lastHeartbeat' => $device['last_heartbeat'],
            'secondsSinceHeartbeat' => $device['seconds_since_heartbeat']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $result,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

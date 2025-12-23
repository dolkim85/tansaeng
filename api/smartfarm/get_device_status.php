<?php
/**
 * ESP32 장치 연결 상태 조회 API
 * 서버가 수집한 heartbeat 기반으로 장치 연결 상태 반환
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // 타임존 설정 (한국 시간)
    date_default_timezone_set('Asia/Seoul');

    $base_path = dirname(dirname(__DIR__));
    require_once $base_path . '/classes/Database.php';

    $db = Database::getInstance();

    // Heartbeat 타임아웃 (10초)
    $HEARTBEAT_TIMEOUT = 10;

    // 모든 ESP32 컨트롤러 ID 목록
    $controllers = [
        'ctlr-0001', 'ctlr-0002', 'ctlr-0003',
        'ctlr-0004', 'ctlr-0005', 'ctlr-0006',
        'ctlr-0007', 'ctlr-0008', 'ctlr-0009',
        'ctlr-0010', 'ctlr-0011', 'ctlr-0012'
    ];

    $deviceStatus = [];
    $connectedCount = 0;

    foreach ($controllers as $controllerId) {
        $isConnected = false;
        $lastSeen = null;

        // 1. 먼저 device_status 테이블에서 확인 (ping/pong 시스템)
        $statusSql = "SELECT status, last_seen
                      FROM device_status
                      WHERE controller_id = ?";

        $statusResult = $db->selectOne($statusSql, [$controllerId]);

        if ($statusResult) {
            // device_status 테이블에 데이터가 있으면 이것 사용
            if ($statusResult['status'] === 'online' && $statusResult['last_seen']) {
                $lastSeenTime = strtotime($statusResult['last_seen']);
                $now = time();

                // 2분 이내면 online
                if (($now - $lastSeenTime) <= 120) {
                    $isConnected = true;
                    $connectedCount++;
                }
            }
            $lastSeen = $statusResult['last_seen'];
        } else {
            // 2. device_status에 없으면 sensor_data에서 확인 (센서 장치)
            $sensorSql = "SELECT MAX(recorded_at) as last_seen
                          FROM sensor_data
                          WHERE controller_id = ?
                          AND (temperature IS NOT NULL OR humidity IS NOT NULL)";

            $sensorResult = $db->selectOne($sensorSql, [$controllerId]);

            if ($sensorResult && $sensorResult['last_seen']) {
                $lastSeenTime = strtotime($sensorResult['last_seen']);
                $now = time();

                if (($now - $lastSeenTime) <= $HEARTBEAT_TIMEOUT) {
                    $isConnected = true;
                    $connectedCount++;
                }

                $lastSeen = $sensorResult['last_seen'];
            }
        }

        $deviceStatus[$controllerId] = [
            'connected' => $isConnected,
            'lastSeen' => $lastSeen
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'devices' => $deviceStatus,
            'connectedCount' => $connectedCount,
            'totalCount' => count($controllers),
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    error_log("Get device status error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

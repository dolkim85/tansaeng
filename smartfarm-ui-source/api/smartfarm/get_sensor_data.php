<?php
/**
 * 스마트팜 센서 데이터 조회 API
 * 날짜 범위별 센서 데이터 조회
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../classes/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $db = Database::getInstance();

    // 쿼리 파라미터
    $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days')); // 기본 7일 전
    $endDate = $_GET['end_date'] ?? date('Y-m-d'); // 기본 오늘
    $controllerId = $_GET['controller_id'] ?? null;
    $sensorLocation = $_GET['sensor_location'] ?? null;

    // SQL 쿼리 생성
    $sql = "SELECT
                controller_id,
                sensor_type,
                sensor_location,
                temperature,
                humidity,
                recorded_at
            FROM sensor_data
            WHERE DATE(recorded_at) BETWEEN :start_date AND :end_date";

    $params = [
        'start_date' => $startDate,
        'end_date' => $endDate
    ];

    // 필터 추가
    if ($controllerId) {
        $sql .= " AND controller_id = :controller_id";
        $params['controller_id'] = $controllerId;
    }

    if ($sensorLocation) {
        $sql .= " AND sensor_location = :sensor_location";
        $params['sensor_location'] = $sensorLocation;
    }

    $sql .= " ORDER BY recorded_at DESC LIMIT 10000"; // 최대 10000개 레코드

    $data = $db->select($sql, $params);

    echo json_encode([
        'success' => true,
        'data' => $data,
        'count' => count($data),
        'filters' => [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'controller_id' => $controllerId,
            'sensor_location' => $sensorLocation
        ]
    ]);

} catch (Exception $e) {
    error_log("Get sensor data error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

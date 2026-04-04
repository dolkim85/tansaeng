<?php
/**
 * 평균 온습도 조회 API
 * 최근 5분간의 센서 데이터 평균값 반환
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

    // 최근 5분간의 평균값 계산 (온도와 습도 별도 계산)
    $tempSql = "SELECT AVG(temperature) as avg_temp
                FROM sensor_data
                WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                    AND sensor_location IN ('front', 'back', 'top')
                    AND temperature IS NOT NULL";

    $humSql = "SELECT AVG(humidity) as avg_hum
               FROM sensor_data
               WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                   AND sensor_location IN ('front', 'back', 'top')
                   AND humidity IS NOT NULL";

    $tempResult = $db->selectOne($tempSql);
    $humResult = $db->selectOne($humSql);

    echo json_encode([
        'success' => true,
        'data' => [
            'avgTemperature' => $tempResult['avg_temp'] ? round(floatval($tempResult['avg_temp']), 1) : null,
            'avgHumidity' => $humResult['avg_hum'] ? round(floatval($humResult['avg_hum']), 1) : null,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    error_log("Get average values error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

<?php
/**
 * 실시간 센서 데이터 조회 API
 * 각 위치별(front, back, top) 최신 센서 데이터 반환
 * 5초 이상 오래된 데이터는 null로 반환
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

    $TIMEOUT_SECONDS = 5; // 5초 타임아웃

    // 각 위치별 최신 데이터 가져오기
    $locations = ['front', 'back', 'top'];
    $sensorData = [];

    foreach ($locations as $location) {
        // 최신 온도 데이터
        $tempSql = "SELECT temperature, recorded_at
                    FROM sensor_data
                    WHERE sensor_location = ?
                    AND temperature IS NOT NULL
                    ORDER BY recorded_at DESC
                    LIMIT 1";

        $tempResult = $db->selectOne($tempSql, [$location]);

        // 최신 습도 데이터
        $humSql = "SELECT humidity, recorded_at
                   FROM sensor_data
                   WHERE sensor_location = ?
                   AND humidity IS NOT NULL
                   ORDER BY recorded_at DESC
                   LIMIT 1";

        $humResult = $db->selectOne($humSql, [$location]);

        // 타임아웃 체크 (5초 이상 오래된 데이터는 null)
        $now = time();

        $temperature = null;
        $humidity = null;
        $lastUpdate = null;

        if ($tempResult && $tempResult['temperature'] !== null) {
            $tempTimestamp = strtotime($tempResult['recorded_at']);
            if (($now - $tempTimestamp) <= $TIMEOUT_SECONDS) {
                $temperature = floatval($tempResult['temperature']);
                $lastUpdate = $tempResult['recorded_at'];
            }
        }

        if ($humResult && $humResult['humidity'] !== null) {
            $humTimestamp = strtotime($humResult['recorded_at']);
            if (($now - $humTimestamp) <= $TIMEOUT_SECONDS) {
                $humidity = floatval($humResult['humidity']);
                if (!$lastUpdate || $humTimestamp > strtotime($lastUpdate)) {
                    $lastUpdate = $humResult['recorded_at'];
                }
            }
        }

        $sensorData[$location] = [
            'temperature' => $temperature,
            'humidity' => $humidity,
            'lastUpdate' => $lastUpdate
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $sensorData,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    error_log("Get realtime sensor data error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

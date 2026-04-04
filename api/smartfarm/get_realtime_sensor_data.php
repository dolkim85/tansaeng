<?php
/**
 * 실시간 센서 데이터 조회 API
 * 각 위치별(front, back, top) 최신 센서 데이터 반환
 * JSON 캐시 파일에서 읽음 (MQTT 데몬이 실시간 업데이트)
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

    // 실시간 센서 캐시 파일 경로
    $cacheFile = dirname(dirname(__DIR__)) . '/config/realtime_sensor.json';

    $TIMEOUT_SECONDS = 120; // 120초 타임아웃 (데이터가 2분 이상 오래되면 null)

    // 기본값
    $sensorData = [
        'front' => ['temperature' => null, 'humidity' => null, 'lastUpdate' => null],
        'back' => ['temperature' => null, 'humidity' => null, 'lastUpdate' => null],
        'top' => ['temperature' => null, 'humidity' => null, 'lastUpdate' => null],
    ];

    // 캐시 파일에서 데이터 읽기
    if (file_exists($cacheFile)) {
        $json = file_get_contents($cacheFile);
        $cache = json_decode($json, true);

        if ($cache) {
            $now = time();

            foreach (['front', 'back', 'top'] as $location) {
                if (isset($cache[$location])) {
                    $lastUpdate = $cache[$location]['lastUpdate'] ?? null;

                    // 타임아웃 체크
                    if ($lastUpdate) {
                        $updateTime = strtotime($lastUpdate);
                        if (($now - $updateTime) <= $TIMEOUT_SECONDS) {
                            $sensorData[$location] = [
                                'temperature' => $cache[$location]['temperature'] ?? null,
                                'humidity' => $cache[$location]['humidity'] ?? null,
                                'lastUpdate' => $lastUpdate
                            ];
                        }
                    }
                }
            }
        }
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

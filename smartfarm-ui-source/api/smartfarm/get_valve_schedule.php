<?php
/**
 * 메인밸브 스케줄 조회 API
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
    $configFile = __DIR__ . '/../../config/valve_schedule.json';

    if (!file_exists($configFile)) {
        // 기본값 반환
        echo json_encode([
            'success' => true,
            'data' => [
                'enabled' => false,
                'mode' => 'manual',
                'timeSlots' => [
                    [
                        'startTime' => '06:00',
                        'endTime' => '18:00',
                        'openMinutes' => 0,
                        'openSeconds' => 10,
                        'closeMinutes' => 5,
                        'closeSeconds' => 0,
                    ],
                    [
                        'startTime' => '18:00',
                        'endTime' => '06:00',
                        'openMinutes' => 0,
                        'openSeconds' => 10,
                        'closeMinutes' => 10,
                        'closeSeconds' => 0,
                    ],
                ],
                'useEnvironmentConditions' => false,
                'maxTemperature' => 30,
            ]
        ]);
        exit;
    }

    $scheduleData = json_decode(file_get_contents($configFile), true);

    if (!$scheduleData) {
        throw new Exception('Failed to parse schedule data');
    }

    echo json_encode([
        'success' => true,
        'data' => $scheduleData
    ]);

} catch (Exception $e) {
    error_log("Get valve schedule error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

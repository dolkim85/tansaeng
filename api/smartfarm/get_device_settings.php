<?php
/**
 * 장치 설정 조회 API
 * 서버에 저장된 장치 설정을 조회합니다.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$settingsFile = __DIR__ . '/../../config/device_settings.json';

if (!file_exists($settingsFile)) {
    // 기본 설정 반환
    echo json_encode([
        'success' => true,
        'data' => [
            'fans' => [
                'fan_front' => ['mode' => 'OFF', 'power' => 'off'],
                'fan_back' => ['mode' => 'OFF', 'power' => 'off'],
            ],
            'mist_zones' => [
                'zone_a' => [
                    'mode' => 'OFF',
                    'controllerId' => 'ctlr-0004',
                    'isRunning' => false,
                    'daySchedule' => ['enabled' => false, 'startTime' => '06:00', 'endTime' => '18:00', 'sprayDurationSeconds' => 5, 'stopDurationSeconds' => 10],
                    'nightSchedule' => ['enabled' => false, 'startTime' => '18:00', 'endTime' => '06:00', 'sprayDurationSeconds' => 8, 'stopDurationSeconds' => 5]
                ],
                'zone_b' => [
                    'mode' => 'OFF',
                    'controllerId' => 'ctlr-0005',
                    'isRunning' => false,
                    'daySchedule' => ['enabled' => false, 'startTime' => '06:00', 'endTime' => '18:00', 'sprayDurationSeconds' => 5, 'stopDurationSeconds' => 10],
                    'nightSchedule' => ['enabled' => false, 'startTime' => '18:00', 'endTime' => '06:00', 'sprayDurationSeconds' => 8, 'stopDurationSeconds' => 5]
                ],
                'zone_c' => [
                    'mode' => 'OFF',
                    'controllerId' => 'ctlr-0006',
                    'isRunning' => false,
                    'daySchedule' => ['enabled' => false, 'startTime' => '06:00', 'endTime' => '18:00', 'sprayDurationSeconds' => 5, 'stopDurationSeconds' => 10],
                    'nightSchedule' => ['enabled' => false, 'startTime' => '18:00', 'endTime' => '06:00', 'sprayDurationSeconds' => 8, 'stopDurationSeconds' => 5]
                ],
                'zone_d' => [
                    'mode' => 'OFF',
                    'controllerId' => 'ctlr-0007',
                    'isRunning' => false,
                    'daySchedule' => ['enabled' => false, 'startTime' => '06:00', 'endTime' => '18:00', 'sprayDurationSeconds' => 5, 'stopDurationSeconds' => 10],
                    'nightSchedule' => ['enabled' => false, 'startTime' => '18:00', 'endTime' => '06:00', 'sprayDurationSeconds' => 8, 'stopDurationSeconds' => 5]
                ],
                'zone_e' => [
                    'mode' => 'OFF',
                    'controllerId' => 'ctlr-0008',
                    'isRunning' => false,
                    'daySchedule' => ['enabled' => false, 'startTime' => '06:00', 'endTime' => '18:00', 'sprayDurationSeconds' => 5, 'stopDurationSeconds' => 10],
                    'nightSchedule' => ['enabled' => false, 'startTime' => '18:00', 'endTime' => '06:00', 'sprayDurationSeconds' => 8, 'stopDurationSeconds' => 5]
                ],
            ],
            'lastUpdated' => null
        ]
    ]);
    exit;
}

$settings = json_decode(file_get_contents($settingsFile), true);

echo json_encode([
    'success' => true,
    'data' => $settings
]);

<?php
/**
 * Device Control API
 * ESP32 장치 제어 명령을 MQTT로 발행합니다.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// POST 요청만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed'
    ]);
    exit;
}

// 요청 데이터 파싱
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['controllerId']) || !isset($data['deviceId']) || !isset($data['command'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields: controllerId, deviceId, command'
    ]);
    exit;
}

$controllerId = $data['controllerId'];
$deviceId = $data['deviceId'];
$command = strtoupper($data['command']);

// MQTT 토픽 구성
// 패턴: tansaeng/<controllerId>/<deviceId>/cmd
$topic = "tansaeng/{$controllerId}/{$deviceId}/cmd";

// MQTT 메시지 발행 (Node.js 스크립트 호출)
$scriptPath = __DIR__ . '/../../scripts/mqtt_publish.js';
$nodeCmd = "/usr/bin/node " . escapeshellarg($scriptPath) . " " . escapeshellarg($topic) . " " . escapeshellarg($command);

// 명령 실행
$output = [];
$returnCode = 0;
exec($nodeCmd . " 2>&1", $output, $returnCode);

$outputStr = implode("\n", $output);

// 로그 기록
$logFile = __DIR__ . '/../../logs/device_control.log';
$logMessage = sprintf(
    "[%s] Controller: %s, Device: %s, Command: %s, Topic: %s, Result: %s\n",
    date('Y-m-d H:i:s'),
    $controllerId,
    $deviceId,
    $command,
    $topic,
    $returnCode === 0 ? 'SUCCESS' : 'FAILED'
);
file_put_contents($logFile, $logMessage, FILE_APPEND);

// 응답 반환
if ($returnCode === 0) {
    echo json_encode([
        'success' => true,
        'message' => 'Command sent successfully',
        'data' => [
            'controllerId' => $controllerId,
            'deviceId' => $deviceId,
            'command' => $command,
            'topic' => $topic,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send command',
        'error' => $outputStr
    ]);
}

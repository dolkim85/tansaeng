<?php
/**
 * 메인밸브 스케줄 저장 API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    $configDir = __DIR__ . '/../../config';
    if (!is_dir($configDir)) {
        mkdir($configDir, 0755, true);
    }

    $configFile = $configDir . '/valve_schedule.json';
    file_put_contents($configFile, json_encode($input, JSON_PRETTY_PRINT));

    echo json_encode([
        'success' => true,
        'message' => 'Valve schedule saved'
    ]);

} catch (Exception $e) {
    error_log("Save valve schedule error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

<?php
/**
 * 스마트팜 센서 데이터 저장 API
 * MQTT로 받은 센서 데이터를 데이터베이스에 저장
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../classes/Database.php';

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

    // 필수 파라미터 확인
    $requiredFields = ['controller_id', 'sensor_type', 'sensor_location'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            throw new Exception("Missing required field: {$field}");
        }
    }

    // 온도 또는 습도 중 하나는 있어야 함
    if (!isset($input['temperature']) && !isset($input['humidity'])) {
        throw new Exception('Either temperature or humidity must be provided');
    }

    $db = Database::getInstance();

    // 데이터 저장
    $data = [
        'controller_id' => $input['controller_id'],
        'sensor_type' => $input['sensor_type'],
        'sensor_location' => $input['sensor_location'],
        'temperature' => isset($input['temperature']) ? floatval($input['temperature']) : null,
        'humidity' => isset($input['humidity']) ? floatval($input['humidity']) : null,
    ];

    $id = $db->insert('sensor_data', $data);

    echo json_encode([
        'success' => true,
        'message' => 'Sensor data saved successfully',
        'id' => $id
    ]);

} catch (Exception $e) {
    error_log("Save sensor data error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

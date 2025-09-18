<?php
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Simple API key authentication
$apiKey = $_POST['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey !== RASPBERRY_PI_TOKEN) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Get sensor data from POST
    $sensorData = [
        'raspberry_id' => $_POST['raspberry_id'] ?? 'default',
        'temperature' => floatval($_POST['temperature'] ?? 0),
        'humidity' => floatval($_POST['humidity'] ?? 0),
        'light_intensity' => floatval($_POST['light_intensity'] ?? 0),
        'ph_level' => floatval($_POST['ph_level'] ?? 0),
        'ec_level' => floatval($_POST['ec_level'] ?? 0),
        'soil_moisture' => floatval($_POST['soil_moisture'] ?? 0),
        'recorded_at' => date('Y-m-d H:i:s')
    ];
    
    // Insert sensor data
    $sensorId = $db->insert('sensor_readings', $sensorData);
    
    // Clean up old sensor data (keep only last 1000 records)
    $db->query(
        "DELETE FROM sensor_readings WHERE id NOT IN (
            SELECT id FROM (
                SELECT id FROM sensor_readings ORDER BY recorded_at DESC LIMIT 1000
            ) as keep_records
        )"
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Sensor data recorded successfully',
        'data' => [
            'sensor_id' => $sensorId,
            'recorded_at' => $sensorData['recorded_at']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
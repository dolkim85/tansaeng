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
    
    // Check if file was uploaded
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No image uploaded or upload error');
    }
    
    $file = $_FILES['image'];
    $userId = intval($_POST['user_id'] ?? 0);
    $raspberryId = $_POST['raspberry_id'] ?? 'default';
    
    // Validate file
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Invalid file type');
    }
    
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        throw new Exception('File too large');
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'plant_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.' . $extension;
    $uploadPath = RASPBERRY_PI_UPLOAD_PATH;
    
    // Create upload directory if it doesn't exist
    if (!is_dir($uploadPath)) {
        mkdir($uploadPath, 0755, true);
    }
    
    $fullPath = $uploadPath . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        throw new Exception('Failed to save uploaded file');
    }
    
    // Save to database
    $imageId = $db->insert('plant_images', [
        'user_id' => $userId,
        'filename' => $filename,
        'original_filename' => $file['name'],
        'file_path' => $fullPath,
        'file_size' => $file['size'],
        'captured_at' => date('Y-m-d H:i:s'),
        'analysis_status' => 'pending',
        'raspberry_id' => $raspberryId,
        'metadata' => json_encode([
            'uploaded_via' => 'raspberry_pi',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ])
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Image uploaded successfully',
        'data' => [
            'image_id' => $imageId,
            'filename' => $filename,
            'upload_time' => date('Y-m-d H:i:s')
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
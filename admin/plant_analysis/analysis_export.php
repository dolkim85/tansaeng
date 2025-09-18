<?php
// Initialize session and auth before any output
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
require_once $base_path . '/classes/Database.php';

$auth = Auth::getInstance();
$auth->requireAdmin();

$analysis_id = $_GET['id'] ?? 0;

if (!$analysis_id) {
    header('Location: index.php');
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();
    
    // Get analysis details
    $sql = "SELECT par.*, u.name as user_name, u.email as user_email 
            FROM plant_analysis_results par 
            LEFT JOIN users u ON par.user_id = u.id 
            WHERE par.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$analysis_id]);
    $analysis = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$analysis) {
        header('Location: index.php?error=not_found');
        exit;
    }
    
    // Set headers for JSON export
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="plant_analysis_' . $analysis_id . '_' . date('Y-m-d_H-i-s') . '.json"');
    
    // Prepare export data
    $export_data = [
        'analysis_info' => [
            'id' => $analysis['id'],
            'analysis_date' => $analysis['processed_at'],
            'processing_time_ms' => $analysis['processing_time_ms'],
            'original_filename' => $analysis['original_filename']
        ],
        'user_info' => [
            'name' => $analysis['user_name'],
            'email' => $analysis['user_email']
        ],
        'results' => [
            'plant_species' => $analysis['plant_species'],
            'health_status' => $analysis['health_status'],
            'confidence_score' => $analysis['confidence_score'],
            'recommendations' => $analysis['recommendations']
        ],
        'raw_analysis_result' => $analysis['analysis_result'] ? json_decode($analysis['analysis_result'], true) : null,
        'export_info' => [
            'exported_at' => date('Y-m-d H:i:s'),
            'exported_by' => $auth->getCurrentUser()['name'],
            'export_version' => '1.0'
        ]
    ];
    
    echo json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
    
} catch (Exception $e) {
    header('Location: index.php?error=export_failed');
    exit;
}
?>
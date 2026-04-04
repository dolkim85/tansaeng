<?php
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/User.php';

header('Content-Type: application/json');

// Check if admin is logged in using Auth class
$auth = Auth::getInstance();
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '권한이 없습니다.']);
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '허용되지 않는 요청 방법입니다.']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['user_id']) || !isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '필수 데이터가 누락되었습니다.']);
    exit;
}

$userId = intval($input['user_id']);
$action = $input['action'];

if ($userId <= 0 || !in_array($action, ['grant', 'revoke'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '잘못된 데이터입니다.']);
    exit;
}

try {
    $user = new User();
    
    // Check if user exists
    $userData = $user->getUserById($userId);
    if (!$userData) {
        throw new Exception('사용자를 찾을 수 없습니다.');
    }
    
    $currentUser = $auth->getCurrentUser();
    $adminId = $currentUser['id'];
    
    if ($action === 'grant') {
        if ($userData['plant_analysis_permission']) {
            throw new Exception('이미 식물분석 권한이 부여된 사용자입니다.');
        }
        $user->grantPlantAnalysisPermission($userId, $adminId);
        $message = '식물분석 권한이 성공적으로 부여되었습니다.';
    } else {
        if (!$userData['plant_analysis_permission']) {
            throw new Exception('식물분석 권한이 없는 사용자입니다.');
        }
        $user->revokePlantAnalysisPermission($userId, $adminId);
        $message = '식물분석 권한이 성공적으로 해제되었습니다.';
    }
    
    echo json_encode(['success' => true, 'message' => $message]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
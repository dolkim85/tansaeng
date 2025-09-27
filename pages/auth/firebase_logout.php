<?php
// Firebase 로그아웃 처리
header('Content-Type: application/json');

// CORS 헤더
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// OPTIONS 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST 메소드만 허용됩니다.']);
    exit;
}

try {
    // 세션 시작
    session_start();
    
    // 세션 데이터 정리
    session_unset();
    session_destroy();
    
    // 쿠키 정리
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    echo json_encode([
        'success' => true,
        'message' => '로그아웃이 완료되었습니다.'
    ]);

} catch (Exception $e) {
    error_log('Firebase logout error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => '로그아웃 처리 중 오류가 발생했습니다.'
    ]);
}
?>
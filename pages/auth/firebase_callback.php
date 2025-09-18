<?php
// Firebase Authentication 콜백 처리
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

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '잘못된 요청 데이터입니다.']);
    exit;
}

$required_fields = ['uid', 'email', 'idToken'];
foreach ($required_fields as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "필수 필드가 누락되었습니다: {$field}"]);
        exit;
    }
}

try {
    // Firebase ID 토큰 검증 (실제 환경에서는 Firebase Admin SDK 사용 권장)
    $idToken = $input['idToken'];
    
    // 간단한 토큰 검증 (프로덕션에서는 Firebase Admin SDK 사용)
    $isValidToken = verifyFirebaseToken($idToken);
    
    if (!$isValidToken) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => '유효하지 않은 토큰입니다.']);
        exit;
    }
    
    // 데이터베이스 연결 및 사용자 처리
    require_once __DIR__ . '/../../classes/Auth.php';
    require_once __DIR__ . '/../../classes/User.php';
    
    $auth = Auth::getInstance();
    $user = new User();
    
    // Firebase 사용자 정보
    $firebaseUid = $input['uid'];
    $email = $input['email'];
    $displayName = $input['displayName'] ?? '';
    $photoURL = $input['photoURL'] ?? '';
    
    // 기존 사용자 확인 (Firebase UID 또는 이메일로)
    $existingUser = $user->getUserByFirebaseUid($firebaseUid);
    
    if (!$existingUser && !empty($email)) {
        // 이메일로 기존 사용자 확인
        $existingUser = $user->getUserByEmail($email);
        
        if ($existingUser) {
            // 기존 계정에 Firebase UID 연결
            $user->updateFirebaseUid($existingUser['id'], $firebaseUid);
        }
    }
    
    if ($existingUser) {
        // 기존 사용자 정보 업데이트
        $userData = [
            'name' => !empty($displayName) ? $displayName : $existingUser['name'],
            'avatar_url' => !empty($photoURL) ? $photoURL : $existingUser['avatar_url'],
            'last_login' => date('Y-m-d H:i:s')
        ];
        
        $user->updateUser($existingUser['id'], $userData);
        $userId = $existingUser['id'];
    } else {
        // 새 사용자 생성
        $userData = [
            'name' => !empty($displayName) ? $displayName : 'Firebase 사용자',
            'email' => $email,
            'password' => null, // Firebase 인증이므로 패스워드 없음
            'user_level' => 1, // 일반 사용자
            'plant_analysis_permission' => 0,
            'firebase_uid' => $firebaseUid,
            'avatar_url' => $photoURL,
            'email_verified' => 1, // Firebase로 인증된 이메일
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $userId = $user->createUser($userData);
    }
    
    // 사용자 정보 다시 가져오기
    $userInfo = $user->getUserById($userId);
    
    // 세션 생성
    session_start();
    $_SESSION['user_id'] = $userInfo['id'];
    $_SESSION['user_email'] = $userInfo['email'];
    $_SESSION['user_name'] = $userInfo['name'];
    $_SESSION['user_level'] = $userInfo['user_level'];
    $_SESSION['plant_analysis_permission'] = $userInfo['plant_analysis_permission'];
    $_SESSION['firebase_uid'] = $firebaseUid;
    $_SESSION['last_activity'] = time();
    $_SESSION['login_time'] = time();
    
    // CSRF 토큰 생성
    $auth->generateCSRFToken();
    
    echo json_encode([
        'success' => true,
        'message' => 'Firebase 로그인 성공',
        'user' => [
            'id' => $userInfo['id'],
            'name' => $userInfo['name'],
            'email' => $userInfo['email'],
            'user_level' => $userInfo['user_level']
        ]
    ]);

} catch (Exception $e) {
    error_log('Firebase authentication error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => '인증 처리 중 오류가 발생했습니다: ' . $e->getMessage()
    ]);
}

/**
 * Firebase ID 토큰 검증 (간단한 버전)
 * 실제 프로덕션에서는 Firebase Admin SDK를 사용하여 검증해야 합니다.
 */
function verifyFirebaseToken($idToken) {
    try {
        // JWT 토큰 디코딩 (간단한 검증)
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            return false;
        }
        
        $payload = json_decode(base64_decode($parts[1]), true);
        
        // 기본적인 검증
        if (!$payload) {
            return false;
        }
        
        // 만료 시간 확인
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return false;
        }
        
        // 발급자 확인
        if (!isset($payload['iss']) || $payload['iss'] !== 'https://securetoken.google.com/tansaeng-users') {
            return false;
        }
        
        // audience 확인
        if (!isset($payload['aud']) || $payload['aud'] !== 'tansaeng-users') {
            return false;
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log('Token verification error: ' . $e->getMessage());
        return false;
    }
}
?>
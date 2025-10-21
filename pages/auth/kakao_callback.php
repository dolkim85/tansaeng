<?php
// 즉시 로그 기록
$logFile = __DIR__ . '/../../kakao_debug.log';
file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] === KAKAO CALLBACK (pages/auth) EXECUTED ===\n", FILE_APPEND);
file_put_contents($logFile, "GET: " . print_r($_GET, true) . "\n", FILE_APPEND);

session_start();

require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/SocialLogin.php';

// 로그 함수
function writeLog($msg) {
    $logFile = __DIR__ . '/../../kakao_debug.log';
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n", FILE_APPEND);
}

// 에러 체크
if (isset($_GET['error'])) {
    writeLog('Error parameter received: ' . $_GET['error']);
    $_SESSION['auth_error'] = '카카오 로그인이 취소되었습니다.';
    header('Location: /pages/auth/login.php');
    exit;
}

// 인가 코드 체크
if (!isset($_GET['code'])) {
    writeLog('No authorization code received');
    $_SESSION['auth_error'] = '카카오 로그인 인가 코드가 없습니다.';
    header('Location: /pages/auth/login.php');
    exit;
}

writeLog('Authorization code received: ' . substr($_GET['code'], 0, 20) . '...');

try {
    writeLog('Creating SocialLogin instance...');
    $socialLogin = new SocialLogin();

    writeLog('Calling handleKakaoCallback...');
    $user = $socialLogin->handleKakaoCallback($_GET['code']);

    writeLog('handleKakaoCallback returned: ' . json_encode($user));

    if ($user) {
        // 로그인 성공 - 세션에 사용자 정보 저장
        writeLog('User found, setting session...');
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['name'] ?? $user['username'] ?? '카카오 사용자';
        $_SESSION['user_role'] = $user['role'] ?? 'user';

        writeLog('Session set: user_id=' . $user['id'] . ', email=' . $user['email']);

        $_SESSION['auth_success'] = '카카오 계정으로 로그인되었습니다.';

        // 관리자는 관리자 페이지로, 일반 사용자는 메인 페이지로
        if (isset($user['role']) && $user['role'] === 'admin') {
            $redirectUrl = '/admin/';
        } else {
            $redirectUrl = $_SESSION['redirect_after_login'] ?? '/';
        }
        unset($_SESSION['redirect_after_login']);

        writeLog('Login successful, redirecting to: ' . $redirectUrl);
        header('Location: ' . $redirectUrl);
        exit;
    } else {
        writeLog('handleKakaoCallback returned null/false');
        throw new Exception('카카오 로그인 처리 중 오류가 발생했습니다.');
    }

} catch (Exception $e) {
    writeLog('!!! EXCEPTION: ' . $e->getMessage());
    writeLog('Stack trace: ' . $e->getTraceAsString());
    error_log('Kakao callback error: ' . $e->getMessage());
    $_SESSION['auth_error'] = $e->getMessage();
    header('Location: /pages/auth/login.php');
    exit;
}
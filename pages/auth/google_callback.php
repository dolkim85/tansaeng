<?php
session_start();

require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/SocialLogin.php';

// 에러 체크
if (isset($_GET['error'])) {
    $_SESSION['auth_error'] = '구글 로그인이 취소되었습니다.';
    header('Location: /pages/auth/login.php');
    exit;
}

// 인가 코드 체크
if (!isset($_GET['code'])) {
    $_SESSION['auth_error'] = '구글 로그인 인가 코드가 없습니다.';
    header('Location: /pages/auth/login.php');
    exit;
}

try {
    $socialLogin = new SocialLogin();
    $user = $socialLogin->handleGoogleCallback($_GET['code']);
    
    if ($user) {
        // 로그인 성공
        $auth = Auth::getInstance();
        $auth->login($user['id']);
        
        $_SESSION['auth_success'] = '구글 계정으로 로그인되었습니다.';
        
        // 리디렉션 URL이 있으면 해당 페이지로, 없으면 메인으로
        $redirectUrl = $_SESSION['redirect_after_login'] ?? '/';
        unset($_SESSION['redirect_after_login']);
        
        header('Location: ' . $redirectUrl);
        exit;
    } else {
        throw new Exception('구글 로그인 처리 중 오류가 발생했습니다.');
    }
    
} catch (Exception $e) {
    error_log('Google callback error: ' . $e->getMessage());
    $_SESSION['auth_error'] = $e->getMessage();
    header('Location: /pages/auth/login.php');
    exit;
}
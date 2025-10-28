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

    // 액세스 토큰 요청
    $tokenData = $socialLogin->getGoogleAccessToken($_GET['code']);

    if (!$tokenData || !isset($tokenData['access_token'])) {
        throw new Exception('Failed to get access token');
    }

    // 사용자 정보 요청
    $userInfo = $socialLogin->getGoogleUserInfo($tokenData['access_token']);

    if (!$userInfo) {
        throw new Exception('Failed to get user info');
    }

    $socialId = $userInfo['id'];
    $email = $userInfo['email'];
    $username = $userInfo['name'] ?? '구글사용자';
    $avatarUrl = $userInfo['picture'] ?? null;

    // 기존 사용자 확인
    $user = $socialLogin->findExistingUser('google', $socialId);

    if (!$user && $email) {
        // 이메일로 기존 사용자 확인
        $user = $socialLogin->findUserByEmail($email);
        if ($user) {
            // 기존 계정에 구글 로그인 연결
            $pdo = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare("
                UPDATE users SET
                    oauth_provider = ?,
                    oauth_id = ?,
                    avatar_url = COALESCE(NULLIF(?, ''), avatar_url),
                    last_login = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute(['google', $socialId, $avatarUrl, $user['id']]);
        }
    }

    if ($user) {
        // 기존 사용자 - 로그인 처리
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['name'] ?? $user['username'] ?? '구글 사용자';
        $_SESSION['user_role'] = $user['role'] ?? 'user';

        $_SESSION['auth_success'] = '구글 계정으로 로그인되었습니다.';

        // 리다이렉트
        if (isset($user['role']) && $user['role'] === 'admin') {
            $redirectUrl = '/admin/';
        } else {
            $redirectUrl = $_SESSION['redirect_after_login'] ?? '/';
        }
        unset($_SESSION['redirect_after_login']);

        header('Location: ' . $redirectUrl);
        exit;
    } else {
        // 신규 사용자 - 추가 정보 입력 페이지로
        $_SESSION['social_temp_user'] = [
            'provider' => 'google',
            'social_id' => $socialId,
            'email' => $email,
            'username' => $username,
            'avatar_url' => $avatarUrl
        ];

        header('Location: /pages/auth/social_register.php');
        exit;
    }
    
} catch (Exception $e) {
    error_log('Google callback error: ' . $e->getMessage());
    $_SESSION['auth_error'] = $e->getMessage();
    header('Location: /pages/auth/login.php');
    exit;
}
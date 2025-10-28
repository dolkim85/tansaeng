<?php
session_start();

require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/SocialLogin.php';

// 에러 체크
if (isset($_GET['error'])) {
    $_SESSION['auth_error'] = '네이버 로그인이 취소되었습니다.';
    header('Location: /pages/auth/login.php');
    exit;
}

// 인가 코드 및 state 체크
if (!isset($_GET['code']) || !isset($_GET['state'])) {
    $_SESSION['auth_error'] = '네이버 로그인 인가 정보가 부족합니다.';
    header('Location: /pages/auth/login.php');
    exit;
}

try {
    $socialLogin = new SocialLogin();

    // state 확인
    if (!isset($_SESSION['naver_state']) || $_SESSION['naver_state'] !== $_GET['state']) {
        throw new Exception('Invalid state parameter');
    }

    // 액세스 토큰 요청
    $tokenData = $socialLogin->getNaverAccessToken($_GET['code'], $_GET['state']);

    if (!$tokenData || !isset($tokenData['access_token'])) {
        throw new Exception('Failed to get access token');
    }

    // 사용자 정보 요청
    $userInfo = $socialLogin->getNaverUserInfo($tokenData['access_token']);

    if (!$userInfo || !isset($userInfo['response'])) {
        throw new Exception('Failed to get user info');
    }

    $naverUser = $userInfo['response'];
    $socialId = $naverUser['id'];
    $email = $naverUser['email'] ?? null;
    $username = $naverUser['name'] ?? $naverUser['nickname'] ?? '네이버사용자';
    $avatarUrl = $naverUser['profile_image'] ?? null;

    // 기존 사용자 확인
    $user = $socialLogin->findExistingUser('naver', $socialId);

    if (!$user && $email) {
        // 이메일로 기존 사용자 확인
        $user = $socialLogin->findUserByEmail($email);
        if ($user) {
            // 기존 계정에 네이버 로그인 연결
            $pdo = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare("
                UPDATE users SET
                    oauth_provider = ?,
                    oauth_id = ?,
                    avatar_url = COALESCE(NULLIF(?, ''), avatar_url),
                    last_login = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute(['naver', $socialId, $avatarUrl, $user['id']]);
        }
    }

    if ($user) {
        // 기존 사용자 - 로그인 처리
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['name'] ?? $user['username'] ?? '네이버 사용자';
        $_SESSION['user_role'] = $user['role'] ?? 'user';

        $_SESSION['auth_success'] = '네이버 계정으로 로그인되었습니다.';

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
            'provider' => 'naver',
            'social_id' => $socialId,
            'email' => $email ?? 'naver_' . $socialId . '@naver.local',
            'username' => $username,
            'avatar_url' => $avatarUrl
        ];

        header('Location: /pages/auth/social_register.php');
        exit;
    }
    
} catch (Exception $e) {
    error_log('Naver callback error: ' . $e->getMessage());
    $_SESSION['auth_error'] = $e->getMessage();
    header('Location: /pages/auth/login.php');
    exit;
}
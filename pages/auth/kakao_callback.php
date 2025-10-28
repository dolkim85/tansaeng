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

    writeLog('Getting access token...');
    $tokenData = $socialLogin->getKakaoAccessToken($_GET['code']);

    if (!$tokenData || !isset($tokenData['access_token'])) {
        throw new Exception('Failed to get access token');
    }

    writeLog('Getting user info...');
    $userInfo = $socialLogin->getKakaoUserInfo($tokenData['access_token']);

    if (!$userInfo) {
        throw new Exception('Failed to get user info');
    }

    $socialId = $userInfo['id'];
    $email = $userInfo['kakao_account']['email'] ?? null;
    $username = $userInfo['properties']['nickname'] ?? '카카오사용자';
    $avatarUrl = $userInfo['properties']['profile_image'] ?? null;

    writeLog('Social ID: ' . $socialId);
    writeLog('Email: ' . ($email ?? 'none'));

    // 기존 사용자 확인
    $user = $socialLogin->findExistingUser('kakao', $socialId);

    if (!$user && $email) {
        // 이메일로 기존 사용자 확인
        $user = $socialLogin->findUserByEmail($email);
        if ($user) {
            // 기존 계정에 카카오 로그인 연결
            writeLog('Linking kakao to existing user by email');
            $pdo = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare("
                UPDATE users SET
                    oauth_provider = ?,
                    oauth_id = ?,
                    avatar_url = COALESCE(NULLIF(?, ''), avatar_url),
                    last_login = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute(['kakao', $socialId, $avatarUrl, $user['id']]);
        }
    }

    if ($user) {
        // 기존 사용자 - 로그인 처리
        writeLog('Existing user found, logging in...');
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['name'] ?? $user['username'] ?? '카카오 사용자';
        $_SESSION['user_role'] = $user['role'] ?? 'user';

        $_SESSION['auth_success'] = '카카오 계정으로 로그인되었습니다.';

        // 리다이렉트
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
        // 신규 사용자 - 추가 정보 입력 페이지로
        writeLog('New user, redirecting to social_register...');
        $_SESSION['social_temp_user'] = [
            'provider' => 'kakao',
            'social_id' => $socialId,
            'email' => $email ?? 'kakao_' . $socialId . '@kakao.local',
            'username' => $username,
            'avatar_url' => $avatarUrl
        ];

        header('Location: /pages/auth/social_register.php');
        exit;
    }

} catch (Exception $e) {
    writeLog('!!! EXCEPTION: ' . $e->getMessage());
    writeLog('Stack trace: ' . $e->getTraceAsString());
    error_log('Kakao callback error: ' . $e->getMessage());
    $_SESSION['auth_error'] = $e->getMessage();
    header('Location: /pages/auth/login.php');
    exit;
}
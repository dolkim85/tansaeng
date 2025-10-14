<?php
/**
 * 카카오 로그인 콜백 처리
 *
 * 카카오 OAuth 인증 후 리다이렉트되는 페이지
 */

session_start();

require_once __DIR__ . '/../../../classes/Auth.php';
require_once __DIR__ . '/../../../classes/Database.php';

// OAuth 설정 로드
$oauthConfig = require __DIR__ . '/../../../config/oauth.php';
$kakaoConfig = $oauthConfig['kakao'];

// 에러 처리
if (isset($_GET['error'])) {
    $errorMsg = $_GET['error_description'] ?? '카카오 로그인에 실패했습니다.';
    header('Location: /pages/auth/login.php?error=' . urlencode($errorMsg));
    exit;
}

// 인가 코드 확인
$code = $_GET['code'] ?? null;
if (!$code) {
    header('Location: /pages/auth/login.php?error=' . urlencode('인가 코드가 없습니다.'));
    exit;
}

try {
    error_log('=== Kakao Callback Started ===');
    error_log('Authorization Code: ' . substr($code, 0, 20) . '...');

    // 1. 인가 코드로 액세스 토큰 요청
    $tokenData = getKakaoAccessToken($code, $kakaoConfig);

    error_log('Token Data: ' . json_encode($tokenData));

    if (!$tokenData || !isset($tokenData['access_token'])) {
        error_log('Token Error: No access token received');
        throw new Exception('액세스 토큰을 받지 못했습니다.');
    }

    error_log('Access Token Received: ' . substr($tokenData['access_token'], 0, 20) . '...');

    // 2. 액세스 토큰으로 사용자 정보 가져오기
    $userInfo = getKakaoUserInfo($tokenData['access_token'], $kakaoConfig);

    error_log('User Info: ' . json_encode($userInfo));

    if (!$userInfo || !isset($userInfo['id'])) {
        error_log('UserInfo Error: No user ID received');
        throw new Exception('사용자 정보를 가져오지 못했습니다.');
    }

    // 3. 사용자 정보 추출
    $kakaoId = (string)$userInfo['id'];
    $email = $userInfo['kakao_account']['email'] ?? null;
    $nickname = $userInfo['kakao_account']['profile']['nickname'] ?? '카카오 사용자';

    error_log("Kakao ID: $kakaoId, Email: $email, Nickname: $nickname");

    // 이메일이 없으면 카카오ID로 생성 (카카오는 이메일 선택적 제공)
    if (!$email) {
        $email = 'kakao_' . $kakaoId . '@kakao.local';
    }

    // 4. 데이터베이스에서 소셜 로그인 사용자 찾기 또는 생성
    error_log('Creating/Finding OAuth User...');
    $auth = Auth::getInstance();
    $user = $auth->findOrCreateOAuthUser([
        'oauth_provider' => 'kakao',
        'oauth_id' => $kakaoId,
        'email' => $email,
        'name' => $nickname,
    ]);

    if (!$user) {
        error_log('User Creation Failed');
        throw new Exception('사용자 생성에 실패했습니다.');
    }

    error_log('User Created/Found: ID=' . $user['id'] . ', Name=' . $user['name']);

    // 5. 세션에 로그인 정보 저장
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['name'];

    error_log('Session saved. Redirecting to home...');

    // 6. 메인 페이지로 리다이렉트
    header('Location: /?login=success&provider=kakao');
    exit;

} catch (Exception $e) {
    error_log('Kakao OAuth Error: ' . $e->getMessage());
    header('Location: /pages/auth/login.php?error=' . urlencode('카카오 로그인 중 오류가 발생했습니다.'));
    exit;
}

/**
 * 카카오 액세스 토큰 요청
 */
function getKakaoAccessToken($code, $config) {
    $data = [
        'grant_type' => 'authorization_code',
        'client_id' => $config['client_id'],
        'redirect_uri' => $config['redirect_uri'],
        'code' => $code,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $config['token_url']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded;charset=utf-8'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log('Kakao Token Error: ' . $response);
        return null;
    }

    return json_decode($response, true);
}

/**
 * 카카오 사용자 정보 조회
 */
function getKakaoUserInfo($accessToken, $config) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $config['user_info_url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/x-www-form-urlencoded;charset=utf-8'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log('Kakao UserInfo Error: ' . $response);
        return null;
    }

    return json_decode($response, true);
}

<?php
/**
 * OAuth 소셜 로그인 설정
 *
 * 환경에 따라 자동으로 base_url이 변경됩니다.
 * - 로컬: http://127.0.0.1:8000
 * - 운영: https://www.tansaeng.com
 */

// .env 파일 로드 (있을 경우에만)
$envFile = __DIR__ . '/env.php';
if (file_exists($envFile)) {
    require_once $envFile;
}

// env() 함수가 정의되지 않은 경우 정의
if (!function_exists('env')) {
    function env($key, $default = null) {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }
}

// 환경 자동 감지
$isLocal = (
    strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false ||
    strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false
);

$baseUrl = $isLocal ? 'http://127.0.0.1:8000' : 'https://www.tansaeng.com';

return [
    'base_url' => $baseUrl,

    // 카카오 로그인 설정
    'kakao' => [
        'client_id' => env('KAKAO_CLIENT_ID', ''), // REST API 키
        'client_secret' => env('KAKAO_CLIENT_SECRET', ''), // Client Secret 코드
        'redirect_uri' => $baseUrl . '/pages/auth/kakao_callback.php',
        'authorize_url' => 'https://kauth.kakao.com/oauth/authorize',
        'token_url' => 'https://kauth.kakao.com/oauth/token',
        'user_info_url' => 'https://kapi.kakao.com/v2/user/me',
    ],

    // 구글 로그인 설정
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID', ''),
        'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
        'redirect_uri' => $baseUrl . '/pages/auth/google_callback.php',
        'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_url' => 'https://oauth2.googleapis.com/token',
        'user_info_url' => 'https://www.googleapis.com/oauth2/v2/userinfo',
        'scope' => 'email profile',
    ],

    // 네이버 로그인 설정
    'naver' => [
        'client_id' => env('NAVER_CLIENT_ID', ''),
        'client_secret' => env('NAVER_CLIENT_SECRET', ''),
        'redirect_uri' => $baseUrl . '/pages/auth/naver_callback.php',
        'authorize_url' => 'https://nid.naver.com/oauth2.0/authorize',
        'token_url' => 'https://nid.naver.com/oauth2.0/token',
        'user_info_url' => 'https://openapi.naver.com/v1/nid/me',
    ],
];

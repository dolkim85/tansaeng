<?php
/**
 * OAuth 소셜 로그인 설정
 *
 * 환경에 따라 자동으로 base_url이 변경됩니다.
 * - 로컬: http://127.0.0.1:8000
 * - 운영: https://www.tansaeng.com
 */

// .env 파일 로드
require_once __DIR__ . '/env.php';

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
        'client_id' => env('KAKAO_CLIENT_ID', 'fcb1f0af57098d3ce5e3d97e355c159c'), // REST API 키
        'client_secret' => env('KAKAO_CLIENT_SECRET', ''), // Client Secret 코드
        'redirect_uri' => $baseUrl . '/pages/auth/kakao_callback.php',
        'authorize_url' => 'https://kauth.kakao.com/oauth/authorize',
        'token_url' => 'https://kauth.kakao.com/oauth/token',
        'user_info_url' => 'https://kapi.kakao.com/v2/user/me',
    ],

    // 구글 로그인 설정 (나중에 추가)
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID', ''),
        'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
        'redirect_uri' => $baseUrl . '/api/auth/google/callback.php',
        'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_url' => 'https://oauth2.googleapis.com/token',
        'user_info_url' => 'https://www.googleapis.com/oauth2/v1/userinfo',
    ],

    // 네이버 로그인 설정 (나중에 추가)
    'naver' => [
        'client_id' => env('NAVER_CLIENT_ID', ''),
        'client_secret' => env('NAVER_CLIENT_SECRET', ''),
        'redirect_uri' => $baseUrl . '/api/auth/naver/callback.php',
        'authorize_url' => 'https://nid.naver.com/oauth2.0/authorize',
        'token_url' => 'https://nid.naver.com/oauth2.0/token',
        'user_info_url' => 'https://openapi.naver.com/v1/nid/me',
    ],
];

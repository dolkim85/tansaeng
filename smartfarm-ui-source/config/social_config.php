<?php
// 소셜 로그인 설정 파일
// 실제 사용 시 각 서비스에서 발급받은 키를 입력하세요

return [
    'google' => [
        'client_id' => '983535094803-v0ljlvpqhl4f5oiagv1v5sgu7ab6jsdj.apps.googleusercontent.com',
        'client_secret' => 'YOUR_GOOGLE_CLIENT_SECRET', // 필요시 추가
        'redirect_uri' => 'http://localhost:8080/pages/auth/google_callback.php',
        'scope' => 'openid email profile',
        'api_key' => 'AIzaSyAz2KBntJtG1DwD-Xju2ZsyjOeLLVkLw0g',
        'auth_domain' => 'tansaeng-users.firebaseapp.com',
        'project_id' => 'tansaeng-users'
    ],
    
    'kakao' => [
        'client_id' => 'YOUR_KAKAO_REST_API_KEY',
        'client_secret' => 'YOUR_KAKAO_CLIENT_SECRET', // 선택사항
        'redirect_uri' => 'http://localhost:8080/pages/auth/kakao_callback.php'
    ],
    
    'naver' => [
        'client_id' => 'YOUR_NAVER_CLIENT_ID',
        'client_secret' => 'YOUR_NAVER_CLIENT_SECRET',
        'redirect_uri' => 'http://localhost:8080/pages/auth/naver_callback.php'
    ]
];

/* 
API 키 발급 방법:

1. 구글 (Google OAuth 2.0)
   - https://console.developers.google.com/ 접속
   - 새 프로젝트 생성 또는 기존 프로젝트 선택
   - API 및 서비스 > 사용자 인증 정보 > OAuth 2.0 클라이언트 ID 생성
   - 승인된 리디렉션 URI에 callback URL 추가
   
2. 카카오 (Kakao Developers)
   - https://developers.kakao.com/ 접속
   - 애플리케이션 추가하기
   - 플랫폼 > Web > 도메인 등록
   - 제품 설정 > 카카오 로그인 > 활성화 설정
   - Redirect URI 등록
   
3. 네이버 (NAVER Developers)
   - https://developers.naver.com/ 접속
   - Application 등록
   - 사용 API: 네이버 로그인
   - 서비스 URL과 Callback URL 등록
*/
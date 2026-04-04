<?php
session_start();
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/SocialLogin.php';

try {
    $socialLogin = new SocialLogin();
    $kakaoUrl = $socialLogin->getKakaoLoginUrl();

    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>카카오 로그인 테스트</title></head><body style="font-family: Arial; padding: 20px;">';
    echo '<h1>🔐 카카오 로그인 테스트</h1>';
    echo '<p><strong>생성된 카카오 로그인 URL:</strong></p>';
    echo '<pre style="background:#f5f5f5; padding:15px; overflow:auto; border:1px solid #ddd;">' . htmlspecialchars($kakaoUrl) . '</pre>';
    echo '<br>';
    echo '<a href="' . htmlspecialchars($kakaoUrl) . '" style="display:inline-block; padding:15px 30px; background:#FEE500; color:#3C1E1E; text-decoration:none; border-radius:5px; font-weight:bold; font-size:16px;">🔑 카카오 로그인 시작하기</a>';

    // 설정 확인
    echo '<hr><h2>📋 OAuth 설정 확인:</h2>';
    $oauthConfig = require __DIR__ . '/config/oauth.php';
    echo '<table border="1" cellpadding="8" style="border-collapse:collapse;">';
    echo '<tr><td><strong>Client ID</strong></td><td>' . htmlspecialchars(substr($oauthConfig['kakao']['client_id'], 0, 20)) . '...</td></tr>';
    echo '<tr><td><strong>Redirect URI</strong></td><td>' . htmlspecialchars($oauthConfig['kakao']['redirect_uri']) . '</td></tr>';
    echo '<tr><td><strong>Base URL</strong></td><td>' . htmlspecialchars($oauthConfig['base_url']) . '</td></tr>';
    echo '</table>';

    echo '<hr><h2>📝 테스트 방법:</h2>';
    echo '<ol>';
    echo '<li>위의 "카카오 로그인 시작하기" 버튼을 클릭하세요</li>';
    echo '<li>카카오 계정으로 로그인하세요</li>';
    echo '<li>동의 화면에서 "동의하고 계속하기"를 클릭하세요</li>';
    echo '<li>로그인 후 <a href="/show_kakao_log.php" target="_blank">로그 확인 페이지</a>를 열어보세요</li>';
    echo '</ol>';

    echo '</body></html>';
} catch (Exception $e) {
    echo '<h1 style="color:red;">❌ 에러 발생</h1>';
    echo '<pre style="background:#ffe6e6; padding:15px; border:1px solid #ff0000;">' . htmlspecialchars($e->getMessage()) . '</pre>';
    echo '<pre style="background:#f5f5f5; padding:15px; border:1px solid #ddd;">' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
}
?>

<?php
/**
 * 스마트팜 환경제어 시스템
 * React 기반 실시간 환경제어 UI (관리자 전용)
 */

$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';

$auth = Auth::getInstance();
$auth->requireAdmin();

$currentUser = $auth->getCurrentUser();

// korea_tansaeng@naver.com 계정만 접근 가능
if ($currentUser['email'] !== 'korea_tansaeng@naver.com') {
    header('HTTP/1.1 403 Forbidden');
    die('
    <!DOCTYPE html>
    <html lang="ko">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>접근 권한 없음</title>
        <link rel="stylesheet" href="/assets/css/main.css">
        <style>
            .access-denied {
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                text-align: center;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 20px;
            }
            .access-denied-content {
                max-width: 500px;
            }
            .access-denied h1 {
                font-size: 4rem;
                margin: 0 0 20px;
            }
            .access-denied h2 {
                font-size: 2rem;
                margin: 0 0 20px;
            }
            .access-denied p {
                font-size: 1.1rem;
                opacity: 0.9;
                margin-bottom: 30px;
            }
            .access-denied a {
                display: inline-block;
                padding: 12px 30px;
                background: white;
                color: #667eea;
                text-decoration: none;
                border-radius: 5px;
                font-weight: bold;
                transition: transform 0.2s;
            }
            .access-denied a:hover {
                transform: translateY(-2px);
            }
        </style>
    </head>
    <body>
        <div class="access-denied">
            <div class="access-denied-content">
                <h1>🔒</h1>
                <h2>접근 권한 없음</h2>
                <p>이 페이지는 특정 관리자만 접근할 수 있습니다.</p>
                <p><strong>' . htmlspecialchars($currentUser['email']) . '</strong> 계정으로는 접근할 수 없습니다.</p>
                <a href="/admin/">대시보드로 돌아가기</a>
            </div>
        </div>
    </body>
    </html>
    ');
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>스마트팜 환경제어 시스템 - 탄생</title>
</head>
<body>
    <?php
    // 캐시 방지 헤더 (Clear-Site-Data 제거 - 무한 새로고침 방지)
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');

    // 타임스탬프 + 랜덤으로 강력한 캐시 무효화
    $timestamp = time() . rand(1000, 9999);
    ?>
    <div id="root"></div>
    <script type="module" crossorigin src="/smartfarm-ui/assets/index-HkI6XjgJ.js?v=<?php echo $timestamp; ?>"></script>
    <link rel="stylesheet" crossorigin href="/smartfarm-ui/assets/index-DKZVB6jk.css?v=<?php echo $timestamp; ?>">
</body>
</html>

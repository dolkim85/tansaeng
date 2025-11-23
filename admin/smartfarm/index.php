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
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        .smartfarm-container {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #000;
        }

        .smartfarm-iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            z-index: 1000;
            transition: opacity 0.3s;
        }

        .loading-overlay.loaded {
            opacity: 0;
            pointer-events: none;
        }

        .loading-spinner {
            display: inline-block;
            width: 50px;
            height: 50px;
            border: 5px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .back-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 24px;
            background: rgba(0,0,0,0.7);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            z-index: 2000;
            transition: background 0.2s;
            border: 2px solid rgba(255,255,255,0.3);
        }

        .back-button:hover {
            background: rgba(0,0,0,0.9);
            border-color: rgba(255,255,255,0.5);
        }
    </style>
</head>
<body>
    <?php
    // React 빌드 파일이 존재하는지 확인
    $distPath = '/var/www/html/smartfarm-ui/dist';
    $indexFile = $distPath . '/index.html';

    if (file_exists($indexFile)) {
        // React 앱 HTML을 직접 include하고 base URL 수정
        $html = file_get_contents($indexFile);
        // /smartfarm-admin/ 경로를 /smartfarm-ui/dist/ 경로로 변경
        $html = str_replace('/smartfarm-admin/', '/smartfarm-ui/dist/', $html);
        echo $html;
        exit;
    } else {
        // 빌드 파일이 없으면 에러 메시지 표시
        ?>
        <div class="smartfarm-container">
            <div style="color: white; text-align: center; padding: 50px; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
                <h2>❌ 빌드 파일 없음</h2>
                <p>React 앱이 아직 빌드되지 않았습니다.</p>
                <p style="margin-top: 20px;">서버에서 다음 명령을 실행하세요:</p>
                <code style="background: rgba(0,0,0,0.3); padding: 10px; display: block; margin: 10px 0;">cd /var/www/html/smartfarm-ui && npm run build</code>
                <p style="margin-top: 20px;"><a href="/admin/" style="color: white; text-decoration: underline;">대시보드로 돌아가기</a></p>
            </div>
        </div>
        <?php
    }
    ?>
    </div>

    <script>
        // iframe 로드 실패 감지
        setTimeout(function() {
            const overlay = document.getElementById('loadingOverlay');
            if (!overlay.classList.contains('loaded')) {
                overlay.innerHTML = '<div><h2>❌ 로딩 실패</h2><p>스마트팜 시스템을 불러올 수 없습니다.</p><p><a href="/admin/" style="color: white; text-decoration: underline;">대시보드로 돌아가기</a></p></div>';
            }
        }, 10000); // 10초 후에도 로드 안되면 에러 표시
    </script>
</body>
</html>

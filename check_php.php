<?php
/**
 * PHP 설정 검증 스크립트
 * 이 파일이 정상적으로 실행되면 PHP가 올바르게 설정된 것입니다.
 */

// PHP 버전 확인
$phpVersion = phpversion();
$requiredVersion = '8.0';

// 필수 확장 모듈 확인
$requiredExtensions = ['mysqli', 'curl', 'mbstring', 'json'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

// Apache 모듈 확인 (가능한 경우)
$apacheModules = [];
if (function_exists('apache_get_modules')) {
    $apacheModules = apache_get_modules();
}

$status = (version_compare($phpVersion, $requiredVersion, '>=') && empty($missingExtensions)) ? 'success' : 'error';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP 설정 검증 - 탄생</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f8f9fa;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #2c3e50; margin-bottom: 30px; }
        .status {
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 30px;
        }
        .info-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }
        .info-box h3 {
            margin-top: 0;
            color: #495057;
        }
        ul { list-style-type: none; padding: 0; }
        li {
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        li:last-child { border-bottom: none; }
        .check { color: #28a745; }
        .cross { color: #dc3545; }
        .timestamp {
            text-align: center;
            color: #6c757d;
            font-size: 0.9em;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 PHP 설정 검증</h1>

        <div class="status <?= $status ?>">
            <?php if ($status === 'success'): ?>
                ✅ PHP가 정상적으로 설정되었습니다!
            <?php else: ?>
                ❌ PHP 설정에 문제가 있습니다.
            <?php endif; ?>
        </div>

        <div class="info-grid">
            <div class="info-box">
                <h3>PHP 정보</h3>
                <ul>
                    <li>
                        <strong>버전:</strong> <?= $phpVersion ?>
                        <?= version_compare($phpVersion, $requiredVersion, '>=') ? '<span class="check">✓</span>' : '<span class="cross">✗</span>' ?>
                    </li>
                    <li><strong>SAPI:</strong> <?= php_sapi_name() ?></li>
                    <li><strong>서버:</strong> <?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?></li>
                    <li><strong>OS:</strong> <?= PHP_OS ?></li>
                </ul>
            </div>

            <div class="info-box">
                <h3>필수 확장 모듈</h3>
                <ul>
                    <?php foreach ($requiredExtensions as $ext): ?>
                        <li>
                            <?= $ext ?>:
                            <?= extension_loaded($ext) ? '<span class="check">설치됨 ✓</span>' : '<span class="cross">누락 ✗</span>' ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <?php if (!empty($missingExtensions)): ?>
        <div class="status error">
            <strong>누락된 확장 모듈:</strong> <?= implode(', ', $missingExtensions) ?>
            <br><br>
            <strong>설치 명령:</strong><br>
            <code>sudo apt install -y <?= implode(' ', array_map(function($ext) { return "php-$ext"; }, $missingExtensions)) ?></code>
        </div>
        <?php endif; ?>

        <div class="info-grid">
            <div class="info-box">
                <h3>메모리 및 제한</h3>
                <ul>
                    <li><strong>메모리 제한:</strong> <?= ini_get('memory_limit') ?></li>
                    <li><strong>최대 실행 시간:</strong> <?= ini_get('max_execution_time') ?>초</li>
                    <li><strong>업로드 최대 크기:</strong> <?= ini_get('upload_max_filesize') ?></li>
                    <li><strong>POST 최대 크기:</strong> <?= ini_get('post_max_size') ?></li>
                </ul>
            </div>

            <div class="info-box">
                <h3>보안 설정</h3>
                <ul>
                    <li><strong>display_errors:</strong> <?= ini_get('display_errors') ? 'On' : 'Off' ?></li>
                    <li><strong>log_errors:</strong> <?= ini_get('log_errors') ? 'On' : 'Off' ?></li>
                    <li><strong>expose_php:</strong> <?= ini_get('expose_php') ? 'On' : 'Off' ?></li>
                    <li><strong>session.cookie_httponly:</strong> <?= ini_get('session.cookie_httponly') ? 'On' : 'Off' ?></li>
                </ul>
            </div>
        </div>

        <div class="timestamp">
            검증 시간: <?= date('Y-m-d H:i:s') ?>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="/" style="color: #007bff; text-decoration: none; font-weight: bold;">← 메인 페이지로 돌아가기</a>
        </div>
    </div>
</body>
</html>
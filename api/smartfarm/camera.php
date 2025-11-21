<?php
/**
 * Smart Farm Camera Feed API
 * 카메라 스트림 표시
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Auth.php';

// 사용자 인증 확인
try {
    $auth = Auth::getInstance();
    if (!$auth->isLoggedIn()) {
        header('Location: /pages/auth/login.php');
        exit;
    }

    $currentUser = $auth->getCurrentUser();
    $userId = $currentUser['id'];

} catch (Exception $e) {
    header('Location: /pages/auth/login.php');
    exit;
}

$cameraId = $_GET['id'] ?? 'cam1_1';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>카메라 - <?= htmlspecialchars($cameraId) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #000;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .camera-header {
            background: rgba(255,255,255,0.1);
            color: white;
            padding: 1rem 2rem;
            width: 100%;
            text-align: center;
        }

        .camera-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 2rem;
        }

        .camera-frame {
            background: #1a1a1a;
            border-radius: 8px;
            padding: 2rem;
            max-width: 1200px;
            width: 100%;
        }

        .camera-placeholder {
            aspect-ratio: 16/9;
            background: linear-gradient(135deg, #2a2a2a, #1a1a1a);
            border: 2px dashed #444;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #888;
        }

        .camera-icon {
            font-size: 5rem;
            margin-bottom: 1rem;
        }

        .camera-info {
            text-align: center;
            color: #aaa;
            margin-top: 1rem;
        }

        .camera-status {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: rgba(244, 67, 54, 0.2);
            color: #f44336;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .camera-status.online {
            background: rgba(76, 175, 80, 0.2);
            color: #4CAF50;
        }
    </style>
</head>
<body>
    <div class="camera-header">
        <h2>📹 <?= htmlspecialchars($cameraId) ?></h2>
    </div>

    <div class="camera-container">
        <div class="camera-frame">
            <div class="camera-placeholder">
                <div class="camera-icon">📹</div>
                <h3>카메라 피드</h3>
                <p class="camera-info">
                    카메라 ID: <?= htmlspecialchars($cameraId) ?><br>
                    <span class="camera-status">오프라인</span>
                </p>
                <p style="margin-top: 1rem; color: #666;">
                    카메라를 연결하려면 디바이스 설정에서 카메라 URL을 설정해주세요.
                </p>
            </div>
        </div>
    </div>

    <script>
        // 실제 카메라 피드는 사용자가 설정한 URL로 연결됩니다
        // MJPEG, RTSP, WebRTC 등 다양한 프로토콜 지원 가능
        console.log('Camera ID: <?= $cameraId ?>');
    </script>
</body>
</html>

<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// 관리자 인증 확인
$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ||
          (isset($_SESSION['user_level']) && $_SESSION['user_level'] == 9);

if (!$isAdmin) {
    header('Location: /pages/auth/login.php?redirect=/admin/pages/');
    exit;
}

$pageKey = $_GET['page'] ?? '';
if (empty($pageKey)) {
    header('Location: index.php');
    exit;
}

$pdo = DatabaseConfig::getConnection();

// 페이지 정보 매핑
$pageMap = [
    'product_coco' => ['title' => '코코피트 배지', 'file' => '/pages/products/coco.php'],
    'product_perlite' => ['title' => '펄라이트 배지', 'file' => '/pages/products/perlite.php'],
    'product_mixed' => ['title' => '혼합 배지', 'file' => '/pages/products/mixed.php'],
    'product_compare' => ['title' => '제품 비교', 'file' => '/pages/products/compare.php'],
    'support_technical' => ['title' => '기술지원', 'file' => '/pages/support/technical.php'],
    'support_faq' => ['title' => 'FAQ', 'file' => '/pages/support/faq.php']
];

if (!isset($pageMap[$pageKey])) {
    header('Location: index.php');
    exit;
}

$pageInfo = $pageMap[$pageKey];
$filePath = __DIR__ . '/../../' . ltrim($pageInfo['file'], '/');

$success = '';
$error = '';

// 페이지 저장 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
    try {
        $content = $_POST['content'];

        // 파일에 직접 저장
        if (file_put_contents($filePath, $content) !== false) {
            $success = '페이지가 성공적으로 저장되었습니다.';
        } else {
            $error = '파일 저장에 실패했습니다.';
        }
    } catch (Exception $e) {
        $error = '저장 중 오류가 발생했습니다: ' . $e->getMessage();
    }
}

// 현재 페이지 내용 읽기
$currentContent = file_get_contents($filePath);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageInfo['title']) ?> 수정 - 탄생 관리자</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        .editor-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            height: calc(100vh - 200px);
        }
        .editor-panel {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
        }
        .editor-panel h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #2c3e50;
        }
        .code-editor {
            flex: 1;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            resize: none;
        }
        .preview-frame {
            flex: 1;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 100%;
        }
        .toolbar {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .btn-primary {
            background: #3498db;
            color: white;
        }
        .btn-success {
            background: #2ecc71;
            color: white;
        }
        .btn-warning {
            background: #f39c12;
            color: white;
        }
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        @media (max-width: 1200px) {
            .editor-container {
                grid-template-columns: 1fr;
                height: auto;
            }
            .code-editor, .preview-frame {
                min-height: 500px;
            }
        }
    </style>
</head>
<body class="admin-body">
    <?php include '../includes/admin_header.php'; ?>

    <div class="admin-container">
        <?php include '../includes/admin_sidebar.php'; ?>

        <main class="admin-main">
            <div class="admin-content">
                <div class="settings-header">
                    <h1>✏️ <?= htmlspecialchars($pageInfo['title']) ?> 수정</h1>
                    <p>좌측에서 코드를 수정하고 우측에서 실시간으로 미리보기 할 수 있습니다</p>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" id="editForm">
                    <div class="toolbar">
                        <button type="submit" class="btn btn-success">💾 저장하기</button>
                        <button type="button" class="btn btn-primary" onclick="refreshPreview()">🔄 미리보기 새로고침</button>
                        <button type="button" class="btn btn-warning" onclick="resetContent()">↩️ 되돌리기</button>
                        <a href="index.php" class="btn btn-secondary">← 목록으로</a>
                        <a href="<?= htmlspecialchars($pageInfo['file']) ?>" target="_blank" class="btn btn-primary">👁️ 새 창에서 보기</a>
                    </div>

                    <div class="editor-container">
                        <div class="editor-panel">
                            <h3>📝 코드 편집기</h3>
                            <textarea name="content" id="codeEditor" class="code-editor"><?= htmlspecialchars($currentContent) ?></textarea>
                        </div>

                        <div class="editor-panel">
                            <h3>👁️ 실시간 미리보기</h3>
                            <iframe id="previewFrame" class="preview-frame" src="about:blank"></iframe>
                        </div>
                    </div>
                </form>

                <div style="margin-top: 20px; padding: 20px; background: #fff3cd; border-radius: 10px;">
                    <h4 style="margin-top: 0;">⚠️ 주의사항</h4>
                    <ul style="line-height: 1.8;">
                        <li>변경사항은 저장 즉시 실제 웹사이트에 반영됩니다</li>
                        <li>잘못된 코드 수정으로 페이지가 깨질 수 있으니 주의하세요</li>
                        <li>중요한 변경 전에는 백업을 권장합니다</li>
                        <li>PHP 문법 오류가 있으면 페이지가 표시되지 않을 수 있습니다</li>
                    </ul>
                </div>
            </div>
        </main>
    </div>

    <script>
        const originalContent = document.getElementById('codeEditor').value;
        let updateTimeout = null;

        function refreshPreview() {
            document.getElementById('previewFrame').src = document.getElementById('previewFrame').src;
        }

        function resetContent() {
            if (confirm('수정한 내용을 모두 되돌리시겠습니까?')) {
                document.getElementById('codeEditor').value = originalContent;
                updateLivePreview();
            }
        }

        // 실시간 미리보기 업데이트
        function updateLivePreview() {
            clearTimeout(updateTimeout);

            updateTimeout = setTimeout(() => {
                const content = document.getElementById('codeEditor').value;
                const iframe = document.getElementById('previewFrame');

                // iframe의 contentDocument에 직접 HTML 적용
                try {
                    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                    iframeDoc.open();
                    iframeDoc.write(content);
                    iframeDoc.close();
                } catch (error) {
                    console.error('미리보기 업데이트 오류:', error);
                }
            }, 500); // 0.5초 디바운스
        }

        // 코드 편집기 입력 시 실시간 업데이트
        document.getElementById('codeEditor').addEventListener('input', function() {
            hasChanges = true;
            updateLivePreview();
        });

        // 자동 저장 (Ctrl+S)
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                document.getElementById('editForm').submit();
            }
        });

        // 변경사항 감지
        let hasChanges = false;

        // 페이지 떠날 때 경고
        window.addEventListener('beforeunload', function(e) {
            if (hasChanges) {
                e.preventDefault();
                e.returnValue = '';
                return '';
            }
        });

        // 폼 제출 시 변경사항 플래그 해제
        document.getElementById('editForm').addEventListener('submit', function() {
            hasChanges = false;
        });

        // 페이지 로드 시 초기 미리보기 표시
        window.addEventListener('load', function() {
            updateLivePreview();
        });
    </script>
</body>
</html>

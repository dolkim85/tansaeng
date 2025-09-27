<?php
// 에디터 테스트 페이지
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
require_once $base_path . '/classes/Database.php';

$auth = Auth::getInstance();
$auth->requireAdmin();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>에디터 테스트 - 탄생 관리자</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <link rel="stylesheet" href="/assets/css/korean-editor.css">
</head>
<body class="admin-body">
    <?php include '../includes/admin_header.php'; ?>

    <div class="admin-container">
        <?php include '../includes/admin_sidebar.php'; ?>

        <main class="admin-main">
            <div class="admin-content">
                <div class="page-header">
                    <div class="page-title">
                        <h1>🛠️ 에디터 테스트</h1>
                        <p>한국어 에디터 기능을 테스트합니다</p>
                    </div>
                </div>

                <div class="admin-card">
                    <div class="card-header">
                        <h3>에디터 테스트</h3>
                    </div>
                    <div class="card-body">
                        <form method="post" class="admin-form">
                            <div class="form-group">
                                <label for="test_content">테스트 내용</label>
                                <div class="editor-container">
                                    <textarea id="test_content" name="test_content" class="form-control large"
                                              data-korean-editor
                                              data-height="500px"
                                              data-upload-url="/admin/api/image_upload.php"
                                              placeholder="에디터에 내용을 입력하고 이미지를 업로드해보세요..."></textarea>
                                </div>
                                <small>드래그 앤 드롭으로 이미지를 업로드하거나 툴바의 이미지 버튼을 사용하세요.</small>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">테스트 저장</button>
                                <button type="button" onclick="testEditor()" class="btn btn-secondary">에디터 상태 확인</button>
                                <a href="add.php" class="btn btn-outline">상품 추가 페이지로</a>
                            </div>
                        </form>

                        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                        <div class="alert alert-success" style="margin-top: 20px;">
                            <h4>저장된 내용:</h4>
                            <div style="border: 1px solid #ddd; padding: 15px; background: #f9f9f9; margin-top: 10px;">
                                <?= $_POST['test_content'] ?? '내용이 없습니다.' ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="/assets/js/main.js"></script>
    <script src="/assets/js/image-resize.js"></script>
    <script src="/assets/js/korean-editor.js"></script>
    <script>
        function testEditor() {
            const editor = window.koreanEditor;
            if (editor) {
                alert('에디터가 활성화되어 있습니다.\n' +
                      '현재 내용 길이: ' + editor.getTextContent().length + '자\n' +
                      '이미지 업로드 URL: ' + editor.options.imageUploadUrl);
            } else {
                alert('에디터가 초기화되지 않았습니다.');
            }
        }

        // 에디터 초기화 확인
        document.addEventListener('DOMContentLoaded', function() {
            console.log('페이지 로드 완료');

            setTimeout(() => {
                const editorContainer = document.querySelector('.korean-editor-container');
                const textarea = document.querySelector('textarea[data-korean-editor]');

                if (editorContainer) {
                    console.log('✅ 에디터가 성공적으로 초기화되었습니다.');

                    // 상태 표시 추가
                    const statusDiv = document.createElement('div');
                    statusDiv.className = 'alert alert-success';
                    statusDiv.innerHTML = '<strong>✅ 에디터 초기화 성공!</strong> 이미지 업로드와 모든 기능을 사용할 수 있습니다.';
                    document.querySelector('.card-body').insertBefore(statusDiv, document.querySelector('form'));
                } else if (textarea) {
                    console.log('❌ 에디터 초기화 실패. 수동으로 초기화를 시도합니다.');
                    // 수동 초기화
                    try {
                        const container = textarea.parentElement;
                        const editor = new KoreanEditor(container, {
                            height: textarea.dataset.height || '500px',
                            placeholder: textarea.placeholder || '내용을 입력하세요...',
                            imageUploadUrl: textarea.dataset.uploadUrl || '/admin/api/image_upload.php'
                        });
                        console.log('✅ 수동 초기화 성공');

                        // 상태 표시 추가
                        const statusDiv = document.createElement('div');
                        statusDiv.className = 'alert alert-warning';
                        statusDiv.innerHTML = '<strong>⚠️ 수동 초기화 완료!</strong> 에디터가 수동으로 초기화되었습니다.';
                        document.querySelector('.card-body').insertBefore(statusDiv, document.querySelector('form'));
                    } catch (error) {
                        console.error('❌ 수동 초기화 실패:', error);

                        // 에러 상태 표시 추가
                        const statusDiv = document.createElement('div');
                        statusDiv.className = 'alert alert-danger';
                        statusDiv.innerHTML = '<strong>❌ 에디터 초기화 실패!</strong> ' + error.message;
                        document.querySelector('.card-body').insertBefore(statusDiv, document.querySelector('form'));
                    }
                } else {
                    console.log('❌ 에디터 대상 요소를 찾을 수 없습니다.');

                    // 에러 상태 표시 추가
                    const statusDiv = document.createElement('div');
                    statusDiv.className = 'alert alert-danger';
                    statusDiv.innerHTML = '<strong>❌ 에디터 대상 요소 없음!</strong> data-korean-editor 속성을 가진 textarea를 찾을 수 없습니다.';
                    document.querySelector('.card-body').insertBefore(statusDiv, document.querySelector('form'));
                }
            }, 1500);
        });
    </script>

    <style>
        .editor-container {
            margin: 10px 0;
        }

        .alert {
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
        }

        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }

        .alert-warning {
            background-color: #fff3cd;
            border-color: #ffeaa7;
            color: #856404;
        }

        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
    </style>
</body>
</html>
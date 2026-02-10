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

$pdo = DatabaseConfig::getConnection();

// 커스텀 제목 불러오기
$customTitles = [];
$titleConfigFile = __DIR__ . '/../../config/page_titles.json';
if (file_exists($titleConfigFile)) {
    $customTitles = json_decode(file_get_contents($titleConfigFile), true) ?: [];
}

// 페이지 목록 정의
$pages = [
    'products' => [
        'title' => '제품 페이지',
        'items' => [
            ['key' => 'product_index', 'title' => $customTitles['product_index'] ?? '배지설명 메인', 'file' => 'index.php'],
            ['key' => 'product_coco', 'title' => $customTitles['product_coco'] ?? '코코피트 배지', 'file' => 'coco.php'],
            ['key' => 'product_perlite', 'title' => $customTitles['product_perlite'] ?? '펄라이트 배지', 'file' => 'perlite.php'],
            ['key' => 'product_mixed', 'title' => $customTitles['product_mixed'] ?? '혼합 배지', 'file' => 'mixed.php'],
            ['key' => 'product_compare', 'title' => $customTitles['product_compare'] ?? '제품 비교', 'file' => 'compare.php']
        ]
    ],
    'support' => [
        'title' => '서비스/지원 페이지',
        'items' => [
            ['key' => 'support_technical', 'title' => $customTitles['support_technical'] ?? '기술지원', 'file' => 'technical.php'],
            ['key' => 'support_faq', 'title' => $customTitles['support_faq'] ?? 'FAQ', 'file' => 'faq.php']
        ]
    ]
];

$totalPages = 0;
$productCount = count($pages['products']['items']);
$supportCount = count($pages['support']['items']);
$totalPages = $productCount + $supportCount;
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>페이지 관리 - 탄생 관리자</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        .page-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .page-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .page-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .page-card h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.3em;
        }
        .btn-edit {
            padding: 8px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 0.9em;
            transition: background 0.3s;
            border: none;
            cursor: pointer;
        }
        .btn-edit:hover {
            background: #2980b9;
        }
        .section-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .section-header h2 {
            margin: 0;
            font-size: 1.5em;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-card .number {
            font-size: 2.5em;
            font-weight: bold;
            color: #3498db;
        }
        .stat-card .label {
            color: #7f8c8d;
            margin-top: 10px;
        }
        /* 인라인 제목 편집 */
        .title-display {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 15px;
        }
        .title-display h3 {
            margin-bottom: 0;
        }
        .btn-title-edit {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 0.8em;
            color: #95a5a6;
            padding: 2px 6px;
            border-radius: 3px;
            transition: color 0.2s, background 0.2s;
        }
        .btn-title-edit:hover {
            color: #3498db;
            background: #ecf0f1;
        }
        .title-edit-form {
            display: none;
            margin-bottom: 15px;
        }
        .title-edit-form.active {
            display: flex;
            gap: 6px;
            align-items: center;
        }
        .title-edit-input {
            flex: 1;
            padding: 6px 10px;
            border: 2px solid #3498db;
            border-radius: 5px;
            font-size: 1em;
            font-weight: 600;
            color: #2c3e50;
            outline: none;
        }
        .title-edit-input:focus {
            border-color: #2ecc71;
        }
        .btn-title-save {
            padding: 6px 12px;
            background: #2ecc71;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85em;
        }
        .btn-title-cancel {
            padding: 6px 12px;
            background: #95a5a6;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85em;
        }
        .title-save-status {
            font-size: 0.75em;
            margin-left: 5px;
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
                    <h1>페이지 관리</h1>
                    <p>웹사이트의 각 페이지 컨텐츠를 실시간으로 미리보기하며 수정할 수 있습니다</p>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="number"><?= $totalPages ?></div>
                        <div class="label">총 관리 페이지</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?= $productCount ?></div>
                        <div class="label">제품 페이지</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?= $supportCount ?></div>
                        <div class="label">서비스 페이지</div>
                    </div>
                </div>

                <?php foreach ($pages as $category => $section): ?>
                    <div class="section-header">
                        <h2><?= htmlspecialchars($section['title']) ?></h2>
                    </div>

                    <div class="page-grid">
                        <?php foreach ($section['items'] as $page): ?>
                            <div class="page-card" id="card-<?= htmlspecialchars($page['key']) ?>">
                                <!-- 제목 표시 모드 -->
                                <div class="title-display" id="title-display-<?= htmlspecialchars($page['key']) ?>">
                                    <h3 id="title-text-<?= htmlspecialchars($page['key']) ?>"><?= htmlspecialchars($page['title']) ?></h3>
                                    <button type="button" class="btn-title-edit" onclick="startTitleEdit('<?= htmlspecialchars($page['key']) ?>')" title="제목 수정">&#9998;</button>
                                </div>

                                <!-- 제목 편집 모드 -->
                                <div class="title-edit-form" id="title-edit-<?= htmlspecialchars($page['key']) ?>">
                                    <input type="text" class="title-edit-input"
                                           id="title-input-<?= htmlspecialchars($page['key']) ?>"
                                           value="<?= htmlspecialchars($page['title']) ?>"
                                           maxlength="50"
                                           onkeydown="handleTitleKey(event, '<?= htmlspecialchars($page['key']) ?>')">
                                    <button type="button" class="btn-title-save" onclick="saveTitle('<?= htmlspecialchars($page['key']) ?>')">저장</button>
                                    <button type="button" class="btn-title-cancel" onclick="cancelTitleEdit('<?= htmlspecialchars($page['key']) ?>')">취소</button>
                                    <span class="title-save-status" id="title-status-<?= htmlspecialchars($page['key']) ?>"></span>
                                </div>

                                <p style="color: #7f8c8d; font-size: 0.9em; margin-bottom: 15px;">
                                    파일: <?= htmlspecialchars($page['file']) ?>
                                </p>
                                <div style="display: flex; gap: 10px;">
                                    <a href="edit.php?page=<?= urlencode($page['key']) ?>" class="btn-edit">
                                        수정
                                    </a>
                                    <a href="/pages/<?= $category ?>/<?= $page['file'] ?>"
                                       target="_blank"
                                       class="btn-edit"
                                       style="background: #2ecc71;">
                                        미리보기
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

                <div class="section-header" style="margin-top: 40px;">
                    <h2>1:1 문의 관리</h2>
                </div>

                <div class="page-card" style="max-width: 600px;">
                    <h3>문의 목록</h3>
                    <p style="color: #7f8c8d; margin-bottom: 15px;">고객이 보낸 문의를 확인하고 답변할 수 있습니다</p>
                    <a href="inquiries.php" class="btn-edit" style="background: #e74c3c;">
                        문의 관리
                    </a>
                </div>
            </div>
        </main>
    </div>

    <script>
        function startTitleEdit(key) {
            document.getElementById('title-display-' + key).style.display = 'none';
            var editForm = document.getElementById('title-edit-' + key);
            editForm.classList.add('active');
            var input = document.getElementById('title-input-' + key);
            input.focus();
            input.select();
        }

        function cancelTitleEdit(key) {
            document.getElementById('title-edit-' + key).classList.remove('active');
            document.getElementById('title-display-' + key).style.display = 'flex';
            // 원래 값으로 되돌리기
            var currentTitle = document.getElementById('title-text-' + key).textContent;
            document.getElementById('title-input-' + key).value = currentTitle;
            document.getElementById('title-status-' + key).textContent = '';
        }

        function handleTitleKey(event, key) {
            if (event.key === 'Enter') {
                event.preventDefault();
                saveTitle(key);
            } else if (event.key === 'Escape') {
                cancelTitleEdit(key);
            }
        }

        async function saveTitle(key) {
            var input = document.getElementById('title-input-' + key);
            var status = document.getElementById('title-status-' + key);
            var newTitle = input.value.trim();

            if (!newTitle) {
                status.textContent = '제목을 입력하세요';
                status.style.color = '#e74c3c';
                return;
            }

            status.textContent = '저장 중...';
            status.style.color = '#f39c12';

            try {
                var response = await fetch('/admin/api/save_page_title.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ page: key, title: newTitle })
                });

                var data = await response.json();

                if (data.success) {
                    // 제목 텍스트 업데이트
                    document.getElementById('title-text-' + key).textContent = newTitle;
                    // 편집 모드 종료
                    document.getElementById('title-edit-' + key).classList.remove('active');
                    document.getElementById('title-display-' + key).style.display = 'flex';
                    status.textContent = '';
                } else {
                    status.textContent = data.message || '저장 실패';
                    status.style.color = '#e74c3c';
                }
            } catch (error) {
                status.textContent = '통신 오류';
                status.style.color = '#e74c3c';
            }
        }
    </script>
</body>
</html>

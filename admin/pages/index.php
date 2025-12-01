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

// 페이지 목록 정의
$pages = [
    'products' => [
        'title' => '제품 페이지',
        'items' => [
            ['key' => 'product_coco', 'title' => '코코피트 배지', 'file' => 'coco.php'],
            ['key' => 'product_perlite', 'title' => '펄라이트 배지', 'file' => 'perlite.php'],
            ['key' => 'product_mixed', 'title' => '혼합 배지', 'file' => 'mixed.php'],
            ['key' => 'product_compare', 'title' => '제품 비교', 'file' => 'compare.php']
        ]
    ],
    'support' => [
        'title' => '서비스/지원 페이지',
        'items' => [
            ['key' => 'support_technical', 'title' => '기술지원', 'file' => 'technical.php'],
            ['key' => 'support_faq', 'title' => 'FAQ', 'file' => 'faq.php']
        ]
    ]
];
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
        .page-list {
            list-style: none;
            padding: 0;
        }
        .page-list li {
            padding: 12px 0;
            border-bottom: 1px solid #ecf0f1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .page-list li:last-child {
            border-bottom: none;
        }
        .btn-edit {
            padding: 8px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 0.9em;
            transition: background 0.3s;
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
    </style>
</head>
<body class="admin-body">
    <?php include '../includes/admin_header.php'; ?>

    <div class="admin-container">
        <?php include '../includes/admin_sidebar.php'; ?>

        <main class="admin-main">
            <div class="admin-content">
                <div class="settings-header">
                    <h1>📄 페이지 관리</h1>
                    <p>웹사이트의 각 페이지 컨텐츠를 실시간으로 미리보기하며 수정할 수 있습니다</p>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="number">8</div>
                        <div class="label">총 관리 페이지</div>
                    </div>
                    <div class="stat-card">
                        <div class="number">4</div>
                        <div class="label">제품 페이지</div>
                    </div>
                    <div class="stat-card">
                        <div class="number">2</div>
                        <div class="label">서비스 페이지</div>
                    </div>
                    <div class="stat-card">
                        <div class="number">0</div>
                        <div class="label">미작성 문의</div>
                    </div>
                </div>

                <?php foreach ($pages as $category => $section): ?>
                    <div class="section-header">
                        <h2><?= htmlspecialchars($section['title']) ?></h2>
                    </div>

                    <div class="page-grid">
                        <?php foreach ($section['items'] as $page): ?>
                            <div class="page-card">
                                <h3><?= htmlspecialchars($page['title']) ?></h3>
                                <p style="color: #7f8c8d; font-size: 0.9em; margin-bottom: 15px;">
                                    파일: <?= htmlspecialchars($page['file']) ?>
                                </p>
                                <div style="display: flex; gap: 10px;">
                                    <a href="edit.php?page=<?= urlencode($page['key']) ?>" class="btn-edit">
                                        ✏️ 수정
                                    </a>
                                    <a href="/pages/<?= $category ?>/<?= $page['file'] ?>"
                                       target="_blank"
                                       class="btn-edit"
                                       style="background: #2ecc71;">
                                        👁️ 미리보기
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

                <div class="section-header" style="margin-top: 40px;">
                    <h2>💬 1:1 문의 관리</h2>
                </div>

                <div class="page-card" style="max-width: 600px;">
                    <h3>문의 목록</h3>
                    <p style="color: #7f8c8d; margin-bottom: 15px;">고객이 보낸 문의를 확인하고 답변할 수 있습니다</p>
                    <a href="inquiries.php" class="btn-edit" style="background: #e74c3c;">
                        📬 문의 관리
                    </a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

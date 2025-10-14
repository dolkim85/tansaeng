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

// 답변 저장 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inquiry_id'])) {
    $inquiryId = (int)$_POST['inquiry_id'];
    $reply = trim($_POST['reply'] ?? '');

    if (!empty($reply)) {
        $sql = "UPDATE inquiries SET reply = ?, status = 'answered', replied_at = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$reply, $inquiryId]);

        $success = '답변이 저장되었습니다.';
    }
}

// 문의 목록 가져오기
$filter = $_GET['filter'] ?? 'all';
$sql = "SELECT * FROM inquiries";
if ($filter === 'pending') {
    $sql .= " WHERE status = 'pending'";
} elseif ($filter === 'answered') {
    $sql .= " WHERE status = 'answered'";
}
$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->query($sql);
$inquiries = $stmt->fetchAll();

// 통계
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM inquiries")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM inquiries WHERE status = 'pending'")->fetchColumn(),
    'answered' => $pdo->query("SELECT COUNT(*) FROM inquiries WHERE status = 'answered'")->fetchColumn()
];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>1:1 문의 관리 - 탄생 관리자</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
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
        .stat-card.pending .number {
            color: #e74c3c;
        }
        .stat-card.answered .number {
            color: #2ecc71;
        }
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .filter-tab {
            padding: 10px 20px;
            background: white;
            border: 2px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #2c3e50;
            transition: all 0.3s;
        }
        .filter-tab.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        .inquiry-list {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .inquiry-item {
            padding: 20px;
            border-bottom: 1px solid #ecf0f1;
        }
        .inquiry-item:last-child {
            border-bottom: none;
        }
        .inquiry-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        .inquiry-title {
            font-size: 1.2em;
            font-weight: bold;
            color: #2c3e50;
        }
        .inquiry-meta {
            display: flex;
            gap: 15px;
            color: #7f8c8d;
            font-size: 0.9em;
            margin-top: 5px;
        }
        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: bold;
        }
        .status-pending {
            background: #fee;
            color: #e74c3c;
        }
        .status-answered {
            background: #efe;
            color: #27ae60;
        }
        .category-badge {
            padding: 3px 10px;
            background: #ecf0f1;
            border-radius: 3px;
            font-size: 0.85em;
        }
        .inquiry-content {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            white-space: pre-wrap;
            line-height: 1.6;
        }
        .reply-section {
            margin-top: 15px;
        }
        .reply-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .reply-textarea {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            min-height: 100px;
            font-family: inherit;
            resize: vertical;
        }
        .btn-reply {
            align-self: flex-end;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-reply:hover {
            background: #2980b9;
        }
        .reply-display {
            background: #e8f5e9;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #27ae60;
        }
        .reply-display strong {
            color: #27ae60;
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
        .toggle-reply {
            background: none;
            border: none;
            color: #3498db;
            cursor: pointer;
            text-decoration: underline;
            font-size: 0.9em;
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
                    <h1>📬 1:1 문의 관리</h1>
                    <p>고객 문의를 확인하고 답변을 작성할 수 있습니다</p>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="number"><?= $stats['total'] ?></div>
                        <div class="label">전체 문의</div>
                    </div>
                    <div class="stat-card pending">
                        <div class="number"><?= $stats['pending'] ?></div>
                        <div class="label">대기 중</div>
                    </div>
                    <div class="stat-card answered">
                        <div class="number"><?= $stats['answered'] ?></div>
                        <div class="label">답변 완료</div>
                    </div>
                </div>

                <div class="filter-tabs">
                    <a href="?filter=all" class="filter-tab <?= ($filter === 'all') ? 'active' : '' ?>">
                        전체 (<?= $stats['total'] ?>)
                    </a>
                    <a href="?filter=pending" class="filter-tab <?= ($filter === 'pending') ? 'active' : '' ?>">
                        대기 중 (<?= $stats['pending'] ?>)
                    </a>
                    <a href="?filter=answered" class="filter-tab <?= ($filter === 'answered') ? 'active' : '' ?>">
                        답변 완료 (<?= $stats['answered'] ?>)
                    </a>
                </div>

                <div class="inquiry-list">
                    <?php if (empty($inquiries)): ?>
                        <div style="padding: 40px; text-align: center; color: #7f8c8d;">
                            <p>문의가 없습니다.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($inquiries as $inquiry): ?>
                            <div class="inquiry-item">
                                <div class="inquiry-header">
                                    <div>
                                        <div class="inquiry-title"><?= htmlspecialchars($inquiry['subject']) ?></div>
                                        <div class="inquiry-meta">
                                            <span>👤 <?= htmlspecialchars($inquiry['name']) ?></span>
                                            <span>📧 <?= htmlspecialchars($inquiry['email']) ?></span>
                                            <?php if (!empty($inquiry['phone'])): ?>
                                                <span>📞 <?= htmlspecialchars($inquiry['phone']) ?></span>
                                            <?php endif; ?>
                                            <span class="category-badge"><?= htmlspecialchars($inquiry['category']) ?></span>
                                            <span>🕐 <?= date('Y-m-d H:i', strtotime($inquiry['created_at'])) ?></span>
                                        </div>
                                    </div>
                                    <span class="status-badge status-<?= htmlspecialchars($inquiry['status']) ?>">
                                        <?= $inquiry['status'] === 'pending' ? '대기중' : '답변완료' ?>
                                    </span>
                                </div>

                                <div class="inquiry-content">
                                    <?= htmlspecialchars($inquiry['message']) ?>
                                </div>

                                <div class="reply-section">
                                    <?php if (!empty($inquiry['reply'])): ?>
                                        <div class="reply-display">
                                            <strong>✅ 답변 (<?= date('Y-m-d H:i', strtotime($inquiry['replied_at'])) ?>)</strong>
                                            <p style="margin: 10px 0 0 0; white-space: pre-wrap;"><?= htmlspecialchars($inquiry['reply']) ?></p>
                                        </div>
                                        <button class="toggle-reply" onclick="toggleReplyForm(<?= $inquiry['id'] ?>)">
                                            답변 수정
                                        </button>
                                        <form method="POST" class="reply-form" id="reply-form-<?= $inquiry['id'] ?>" style="display: none; margin-top: 10px;">
                                            <input type="hidden" name="inquiry_id" value="<?= $inquiry['id'] ?>">
                                            <textarea name="reply" class="reply-textarea" required><?= htmlspecialchars($inquiry['reply']) ?></textarea>
                                            <button type="submit" class="btn-reply">답변 수정</button>
                                        </form>
                                    <?php else: ?>
                                        <button class="toggle-reply" onclick="toggleReplyForm(<?= $inquiry['id'] ?>)">
                                            답변 작성
                                        </button>
                                        <form method="POST" class="reply-form" id="reply-form-<?= $inquiry['id'] ?>" style="display: none; margin-top: 10px;">
                                            <input type="hidden" name="inquiry_id" value="<?= $inquiry['id'] ?>">
                                            <textarea name="reply" class="reply-textarea" placeholder="답변 내용을 입력하세요..." required></textarea>
                                            <button type="submit" class="btn-reply">답변 저장</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        function toggleReplyForm(id) {
            const form = document.getElementById('reply-form-' + id);
            if (form.style.display === 'none') {
                form.style.display = 'flex';
            } else {
                form.style.display = 'none';
            }
        }
    </script>
</body>
</html>

<?php
// Initialize session and auth before any output
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
require_once $base_path . '/classes/Database.php';

$auth = Auth::getInstance();
$auth->requireAdmin();

$success = '';
$error = '';

// 답변 저장 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inquiry_id'])) {
    $inquiry_id = intval($_POST['inquiry_id']);
    $reply = trim($_POST['reply'] ?? '');
    $admin_id = $auth->getCurrentUserId();

    if (!empty($reply)) {
        try {
            $pdo = Database::getInstance()->getConnection();
            $sql = "UPDATE contact_inquiries
                    SET reply = ?, status = 'answered', replied_at = NOW(), replied_by = ?
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$reply, $admin_id, $inquiry_id]);

            $success = '답변이 저장되었습니다.';
        } catch (Exception $e) {
            $error = '답변 저장에 실패했습니다: ' . $e->getMessage();
        }
    }
}

// 문의 삭제 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_inquiry'])) {
    $inquiry_id = intval($_POST['delete_inquiry']);

    try {
        $pdo = Database::getInstance()->getConnection();
        $sql = "DELETE FROM contact_inquiries WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$inquiry_id]);

        $success = '문의가 삭제되었습니다.';
    } catch (Exception $e) {
        $error = '문의 삭제에 실패했습니다: ' . $e->getMessage();
    }
}

// 페이지네이션 및 필터링
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

$inquiries = [];
$total_inquiries = 0;
$total_pages = 0;

try {
    $pdo = Database::getInstance()->getConnection();

    $where_conditions = [];
    $params = [];

    // 필터 조건
    if ($filter === 'pending') {
        $where_conditions[] = "status = 'pending'";
    } elseif ($filter === 'answered') {
        $where_conditions[] = "status = 'answered'";
    }

    // 검색 조건
    if ($search) {
        $where_conditions[] = "(name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)";
        $params = ["%$search%", "%$search%", "%$search%", "%$search%"];
    }

    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    // 전체 개수 조회
    $count_sql = "SELECT COUNT(*) FROM contact_inquiries $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_inquiries = $stmt->fetchColumn();

    $total_pages = ceil($total_inquiries / $per_page);

    // 문의 목록 조회
    $sql = "SELECT ci.*, u.name as user_name
            FROM contact_inquiries ci
            LEFT JOIN users u ON ci.user_id = u.id
            $where_clause
            ORDER BY ci.created_at DESC
            LIMIT ? OFFSET ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($params, [$per_page, $offset]));
    $inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 통계 조회
    $stats = [
        'total' => $pdo->query("SELECT COUNT(*) FROM contact_inquiries")->fetchColumn(),
        'pending' => $pdo->query("SELECT COUNT(*) FROM contact_inquiries WHERE status = 'pending'")->fetchColumn(),
        'answered' => $pdo->query("SELECT COUNT(*) FROM contact_inquiries WHERE status = 'answered'")->fetchColumn()
    ];

} catch (Exception $e) {
    $error = "문의 정보를 불러오는데 실패했습니다: " . $e->getMessage();
    $stats = ['total' => 0, 'pending' => 0, 'answered' => 0];
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>문의 관리 - 탄생 관리자</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-body">
    <?php include '../includes/admin_header.php'; ?>

    <div class="admin-container">
        <?php include '../includes/admin_sidebar.php'; ?>

        <main class="admin-main">
            <div class="admin-content">
                <div class="page-header">
                    <h1>📬 문의 관리</h1>
                    <p>고객 문의를 확인하고 답변을 작성할 수 있습니다</p>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <strong>성공:</strong> <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <strong>오류:</strong> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <!-- 통계 카드 -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">📊</div>
                        <div class="stat-number"><?= number_format($stats['total']) ?></div>
                        <div class="stat-label">전체 문의</div>
                    </div>
                    <div class="stat-card stat-pending">
                        <div class="stat-icon">⏳</div>
                        <div class="stat-number"><?= number_format($stats['pending']) ?></div>
                        <div class="stat-label">대기 중</div>
                    </div>
                    <div class="stat-card stat-answered">
                        <div class="stat-icon">✅</div>
                        <div class="stat-number"><?= number_format($stats['answered']) ?></div>
                        <div class="stat-label">답변 완료</div>
                    </div>
                </div>

                <!-- 검색 및 필터 -->
                <div class="content-wrapper">
                    <div class="search-section">
                        <div class="filter-tabs">
                            <a href="?filter=all<?= $search ? '&search=' . urlencode($search) : '' ?>"
                               class="filter-tab <?= ($filter === 'all') ? 'active' : '' ?>">
                                전체 (<?= $stats['total'] ?>)
                            </a>
                            <a href="?filter=pending<?= $search ? '&search=' . urlencode($search) : '' ?>"
                               class="filter-tab <?= ($filter === 'pending') ? 'active' : '' ?>">
                                대기 중 (<?= $stats['pending'] ?>)
                            </a>
                            <a href="?filter=answered<?= $search ? '&search=' . urlencode($search) : '' ?>"
                               class="filter-tab <?= ($filter === 'answered') ? 'active' : '' ?>">
                                답변 완료 (<?= $stats['answered'] ?>)
                            </a>
                        </div>

                        <form class="search-form" method="get">
                            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                   placeholder="이름, 이메일, 제목, 내용으로 검색..." class="search-input">
                            <button type="submit" class="btn btn-primary">🔍 검색</button>
                            <?php if ($search): ?>
                                <a href="?filter=<?= $filter ?>" class="btn btn-outline">전체보기</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- 문의 목록 -->
                    <div class="inquiry-list">
                        <?php if (empty($inquiries)): ?>
                            <div class="no-data">
                                <div class="no-data-icon">📬</div>
                                <div class="no-data-text">
                                    <?= $search ? '검색 결과가 없습니다.' : '문의가 없습니다.' ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($inquiries as $inquiry): ?>
                                <div class="inquiry-item">
                                    <div class="inquiry-header">
                                        <div class="inquiry-info">
                                            <div class="inquiry-title"><?= htmlspecialchars($inquiry['subject']) ?></div>
                                            <div class="inquiry-meta">
                                                <span class="meta-item">
                                                    <span class="meta-icon">👤</span>
                                                    <?= htmlspecialchars($inquiry['name']) ?>
                                                    <?php if ($inquiry['user_id']): ?>
                                                        <span class="badge badge-user">회원</span>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="meta-item">
                                                    <span class="meta-icon">📧</span>
                                                    <?= htmlspecialchars($inquiry['email']) ?>
                                                </span>
                                                <?php if (!empty($inquiry['phone'])): ?>
                                                    <span class="meta-item">
                                                        <span class="meta-icon">📞</span>
                                                        <?= htmlspecialchars($inquiry['phone']) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <span class="meta-item">
                                                    <span class="category-badge">
                                                        <?php
                                                        $types = [
                                                            'general' => '일반 문의',
                                                            'product' => '제품 문의',
                                                            'technical' => '기술 지원',
                                                            'order' => '주문/배송',
                                                            'plant_analysis' => '식물분석 권한',
                                                            'partnership' => '제휴 문의',
                                                            'complaint' => '불만/건의'
                                                        ];
                                                        echo $types[$inquiry['inquiry_type']] ?? $inquiry['inquiry_type'];
                                                        ?>
                                                    </span>
                                                </span>
                                                <span class="meta-item">
                                                    <span class="meta-icon">🕐</span>
                                                    <?= date('Y-m-d H:i', strtotime($inquiry['created_at'])) ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="inquiry-actions">
                                            <span class="status-badge status-<?= htmlspecialchars($inquiry['status']) ?>">
                                                <?= $inquiry['status'] === 'pending' ? '대기중' : '답변완료' ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="inquiry-content">
                                        <?= nl2br(htmlspecialchars($inquiry['message'])) ?>
                                    </div>

                                    <div class="reply-section">
                                        <?php if (!empty($inquiry['reply'])): ?>
                                            <div class="reply-display">
                                                <div class="reply-header">
                                                    <strong>✅ 관리자 답변</strong>
                                                    <span class="reply-date">
                                                        <?= date('Y-m-d H:i', strtotime($inquiry['replied_at'])) ?>
                                                    </span>
                                                </div>
                                                <div class="reply-content">
                                                    <?= nl2br(htmlspecialchars($inquiry['reply'])) ?>
                                                </div>
                                            </div>
                                            <div class="reply-buttons">
                                                <button class="btn btn-small btn-outline" onclick="toggleReplyForm(<?= $inquiry['id'] ?>)">
                                                    답변 수정
                                                </button>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('이 문의를 삭제하시겠습니까?');">
                                                    <input type="hidden" name="delete_inquiry" value="<?= $inquiry['id'] ?>">
                                                    <button type="submit" class="btn btn-small btn-danger">삭제</button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <div class="reply-buttons">
                                                <button class="btn btn-primary" onclick="toggleReplyForm(<?= $inquiry['id'] ?>)">
                                                    답변 작성
                                                </button>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('이 문의를 삭제하시겠습니까?');">
                                                    <input type="hidden" name="delete_inquiry" value="<?= $inquiry['id'] ?>">
                                                    <button type="submit" class="btn btn-small btn-danger">삭제</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>

                                        <form method="POST" class="reply-form" id="reply-form-<?= $inquiry['id'] ?>" style="display: none;">
                                            <input type="hidden" name="inquiry_id" value="<?= $inquiry['id'] ?>">
                                            <textarea name="reply" class="reply-textarea" placeholder="답변 내용을 입력하세요..." required><?= htmlspecialchars($inquiry['reply'] ?? '') ?></textarea>
                                            <div class="form-actions">
                                                <button type="submit" class="btn btn-primary">답변 저장</button>
                                                <button type="button" class="btn btn-outline" onclick="toggleReplyForm(<?= $inquiry['id'] ?>)">취소</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- 페이지네이션 -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination-wrapper">
                            <div class="pagination">
                                <?php
                                $page_group = ceil($page / 10);
                                $start_page = ($page_group - 1) * 10 + 1;
                                $end_page = min($start_page + 9, $total_pages);
                                ?>

                                <?php if ($start_page > 1): ?>
                                    <a href="?page=1&filter=<?= $filter ?><?= $search ? '&search=' . urlencode($search) : '' ?>">처음</a>
                                    <a href="?page=<?= $start_page - 1 ?>&filter=<?= $filter ?><?= $search ? '&search=' . urlencode($search) : '' ?>">이전</a>
                                <?php endif; ?>

                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <?php if ($i == $page): ?>
                                        <span class="current"><?= $i ?></span>
                                    <?php else: ?>
                                        <a href="?page=<?= $i ?>&filter=<?= $filter ?><?= $search ? '&search=' . urlencode($search) : '' ?>"><?= $i ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <?php if ($end_page < $total_pages): ?>
                                    <a href="?page=<?= $end_page + 1 ?>&filter=<?= $filter ?><?= $search ? '&search=' . urlencode($search) : '' ?>">다음</a>
                                    <a href="?page=<?= $total_pages ?>&filter=<?= $filter ?><?= $search ? '&search=' . urlencode($search) : '' ?>">마지막</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="/assets/js/main.js"></script>
    <script>
        function toggleReplyForm(id) {
            const form = document.getElementById('reply-form-' + id);
            if (form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        }
    </script>

    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #3498db;
            margin-bottom: 5px;
        }

        .stat-pending .stat-number {
            color: #e74c3c;
        }

        .stat-answered .stat-number {
            color: #2ecc71;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 0.9em;
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
            padding: 25px;
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
            font-size: 1.3em;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .inquiry-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            color: #7f8c8d;
            font-size: 0.9em;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .meta-icon {
            font-size: 1.1em;
        }

        .status-badge {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: bold;
            white-space: nowrap;
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
            color: #2c3e50;
        }

        .badge-user {
            background: #3498db;
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 0.75em;
            margin-left: 5px;
        }

        .inquiry-content {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin: 15px 0;
            line-height: 1.6;
            color: #2c3e50;
        }

        .reply-section {
            margin-top: 15px;
        }

        .reply-display {
            background: #e8f5e9;
            padding: 20px;
            border-radius: 5px;
            border-left: 4px solid #27ae60;
            margin-bottom: 10px;
        }

        .reply-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .reply-header strong {
            color: #27ae60;
        }

        .reply-date {
            color: #7f8c8d;
            font-size: 0.9em;
        }

        .reply-content {
            color: #2c3e50;
            line-height: 1.6;
        }

        .reply-buttons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .reply-form {
            margin-top: 15px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .reply-textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            min-height: 120px;
            font-family: inherit;
            resize: vertical;
            margin-bottom: 10px;
        }

        .form-actions {
            display: flex;
            gap: 10px;
        }

        .no-data {
            padding: 60px;
            text-align: center;
        }

        .no-data-icon {
            font-size: 4em;
            margin-bottom: 15px;
            opacity: 0.3;
        }

        .no-data-text {
            color: #7f8c8d;
            font-size: 1.1em;
        }
    </style>
</body>
</html>

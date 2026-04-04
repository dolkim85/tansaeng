<?php
// Initialize session and auth before any output
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
require_once $base_path . '/classes/Database.php';
require_once $base_path . '/classes/Mailer.php';

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

            // 먼저 문의 정보 조회 (이메일 발송을 위해)
            $infoSql = "SELECT name, email, subject, message FROM contact_inquiries WHERE id = ?";
            $infoStmt = $pdo->prepare($infoSql);
            $infoStmt->execute([$inquiry_id]);
            $inquiryInfo = $infoStmt->fetch(PDO::FETCH_ASSOC);

            // 답변 저장
            $sql = "UPDATE contact_inquiries
                    SET admin_reply = ?, status = 'answered', replied_at = NOW(), replied_by = ?
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$reply, $admin_id, $inquiry_id]);

            // 이메일 발송
            $emailSent = false;
            $emailError = '';
            if ($inquiryInfo) {
                try {
                    $mailer = new Mailer();
                    $emailSent = $mailer->sendInquiryReplyEmail(
                        $inquiryInfo['email'],
                        $inquiryInfo['name'],
                        $inquiryInfo['subject'],
                        $inquiryInfo['message'],
                        $reply
                    );
                    if (!$emailSent) {
                        $emailError = $mailer->getLastError();
                    }
                } catch (Exception $mailEx) {
                    $emailError = $mailEx->getMessage();
                    error_log('문의 답변 이메일 발송 실패: ' . $mailEx->getMessage());
                }
            }

            if ($emailSent) {
                $success = '답변이 저장되고 고객에게 이메일이 발송되었습니다. (' . htmlspecialchars($inquiryInfo['email']) . ')';
            } else {
                $success = '답변이 저장되었습니다. (이메일 발송 실패: ' . htmlspecialchars($emailError) . ')';
            }
        } catch (Exception $e) {
            $error = '답변 저장에 실패했습니다: ' . $e->getMessage();
        }
    }
}

// 문의 삭제 처리 (단일)
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

// 선택 삭제 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_selected'])) {
    $selected_ids = $_POST['selected_ids'] ?? [];

    if (!empty($selected_ids)) {
        try {
            $pdo = Database::getInstance()->getConnection();
            $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
            $sql = "DELETE FROM contact_inquiries WHERE id IN ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_map('intval', $selected_ids));

            $deleted_count = $stmt->rowCount();
            $success = $deleted_count . '개의 문의가 삭제되었습니다.';
        } catch (Exception $e) {
            $error = '선택 삭제에 실패했습니다: ' . $e->getMessage();
        }
    } else {
        $error = '삭제할 항목을 선택해주세요.';
    }
}

// 전체 삭제 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all'])) {
    try {
        $pdo = Database::getInstance()->getConnection();

        // 필터 조건에 맞는 항목만 삭제
        $where_conditions = [];
        $params = [];
        $filter_for_delete = $_POST['current_filter'] ?? 'all';
        $search_for_delete = $_POST['current_search'] ?? '';

        if ($filter_for_delete === 'pending') {
            $where_conditions[] = "status = 'pending'";
        } elseif ($filter_for_delete === 'answered') {
            $where_conditions[] = "status = 'answered'";
        }

        if ($search_for_delete) {
            $where_conditions[] = "(name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)";
            $params = ["%$search_for_delete%", "%$search_for_delete%", "%$search_for_delete%", "%$search_for_delete%"];
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        $sql = "DELETE FROM contact_inquiries $where_clause";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $deleted_count = $stmt->rowCount();
        $success = $deleted_count . '개의 문의가 삭제되었습니다.';
    } catch (Exception $e) {
        $error = '전체 삭제에 실패했습니다: ' . $e->getMessage();
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

    // 문의 목록 조회 - LIMIT와 OFFSET은 정수로 직접 삽입
    $per_page_int = (int) $per_page;
    $offset_int = (int) $offset;
    $sql = "SELECT ci.*, u.name as user_name
            FROM contact_inquiries ci
            LEFT JOIN users u ON ci.user_id = u.id
            $where_clause
            ORDER BY ci.created_at DESC
            LIMIT $per_page_int OFFSET $offset_int";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
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

                    <!-- 대량 삭제 버튼 -->
                    <?php if (!empty($inquiries)): ?>
                    <div class="bulk-actions">
                        <div class="bulk-select">
                            <label class="checkbox-label">
                                <input type="checkbox" id="select-all" onchange="toggleSelectAll()">
                                <span>전체 선택</span>
                            </label>
                            <span class="selected-count" id="selected-count">0개 선택됨</span>
                        </div>
                        <div class="bulk-buttons">
                            <button type="button" class="btn btn-danger" onclick="deleteSelected()" id="btn-delete-selected" disabled>
                                🗑️ 선택 삭제
                            </button>
                            <button type="button" class="btn btn-danger-outline" onclick="deleteAll()">
                                ⚠️ 전체 삭제
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- 문의 목록 (제목 리스트 형태) -->
                    <form method="POST" id="bulk-delete-form">
                        <input type="hidden" name="current_filter" value="<?= htmlspecialchars($filter) ?>">
                        <input type="hidden" name="current_search" value="<?= htmlspecialchars($search) ?>">

                        <div class="inquiry-list">
                            <?php if (empty($inquiries)): ?>
                                <div class="no-data">
                                    <div class="no-data-icon">📬</div>
                                    <div class="no-data-text">
                                        <?= $search ? '검색 결과가 없습니다.' : '문의가 없습니다.' ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- 테이블 헤더 -->
                                <div class="inquiry-table-header">
                                    <div class="col-checkbox"></div>
                                    <div class="col-status">상태</div>
                                    <div class="col-title">제목</div>
                                    <div class="col-name">작성자</div>
                                    <div class="col-type">분류</div>
                                    <div class="col-date">등록일</div>
                                </div>

                                <?php foreach ($inquiries as $inquiry): ?>
                                    <!-- 제목 행 (클릭 가능) -->
                                    <div class="inquiry-row" data-id="<?= $inquiry['id'] ?>">
                                        <div class="col-checkbox" onclick="event.stopPropagation();">
                                            <input type="checkbox" name="selected_ids[]" value="<?= $inquiry['id'] ?>"
                                                   class="inquiry-checkbox" onchange="updateSelectedCount()">
                                        </div>
                                        <div class="col-status">
                                            <span class="status-badge status-<?= htmlspecialchars($inquiry['status']) ?>">
                                                <?= $inquiry['status'] === 'pending' ? '대기' : '완료' ?>
                                            </span>
                                        </div>
                                        <div class="col-title" onclick="toggleInquiryDetail(<?= $inquiry['id'] ?>)">
                                            <span class="title-text"><?= htmlspecialchars($inquiry['subject']) ?></span>
                                            <span class="toggle-icon" id="toggle-icon-<?= $inquiry['id'] ?>">▼</span>
                                        </div>
                                        <div class="col-name">
                                            <?= htmlspecialchars($inquiry['name']) ?>
                                            <?php if ($inquiry['user_id']): ?>
                                                <span class="badge badge-user">회원</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-type">
                                            <?php
                                            $types = [
                                                'general' => '일반',
                                                'product' => '제품',
                                                'technical' => '기술',
                                                'order' => '주문',
                                                'plant_analysis' => '식물분석',
                                                'partnership' => '제휴',
                                                'complaint' => '불만'
                                            ];
                                            echo $types[$inquiry['inquiry_type']] ?? $inquiry['inquiry_type'];
                                            ?>
                                        </div>
                                        <div class="col-date"><?= date('Y-m-d', strtotime($inquiry['created_at'])) ?></div>
                                    </div>

                                    <!-- 상세 내용 (숨겨진 상태) -->
                                    <div class="inquiry-detail" id="detail-<?= $inquiry['id'] ?>" style="display: none;">
                                        <div class="detail-meta">
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
                                                <span class="meta-icon">🕐</span>
                                                <?= date('Y-m-d H:i:s', strtotime($inquiry['created_at'])) ?>
                                            </span>
                                        </div>

                                        <div class="inquiry-content">
                                            <strong>📝 문의 내용:</strong>
                                            <div class="content-text"><?= nl2br(htmlspecialchars($inquiry['message'])) ?></div>
                                        </div>

                                        <div class="reply-section">
                                            <?php if (!empty($inquiry['admin_reply'])): ?>
                                                <div class="reply-display">
                                                    <div class="reply-header">
                                                        <strong>✅ 관리자 답변</strong>
                                                        <span class="reply-date">
                                                            <?= date('Y-m-d H:i', strtotime($inquiry['replied_at'])) ?>
                                                        </span>
                                                    </div>
                                                    <div class="reply-content">
                                                        <?= nl2br(htmlspecialchars($inquiry['admin_reply'])) ?>
                                                    </div>
                                                </div>
                                                <div class="reply-buttons">
                                                    <button type="button" class="btn btn-small btn-outline" onclick="toggleReplyForm(<?= $inquiry['id'] ?>)">
                                                        답변 수정
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <div class="reply-buttons">
                                                    <button type="button" class="btn btn-primary" onclick="toggleReplyForm(<?= $inquiry['id'] ?>)">
                                                        답변 작성
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="reply-form-container" id="reply-form-<?= $inquiry['id'] ?>" style="display: none;">
                                            <textarea name="reply_text_<?= $inquiry['id'] ?>" class="reply-textarea" placeholder="답변 내용을 입력하세요..."><?= htmlspecialchars($inquiry['admin_reply'] ?? '') ?></textarea>
                                            <div class="form-actions">
                                                <button type="button" class="btn btn-primary" onclick="submitReply(<?= $inquiry['id'] ?>)">답변 저장</button>
                                                <button type="button" class="btn btn-outline" onclick="toggleReplyForm(<?= $inquiry['id'] ?>)">취소</button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </form>

                    <!-- 숨겨진 삭제 폼들 -->
                    <form method="POST" id="delete-selected-form" style="display: none;">
                        <input type="hidden" name="delete_selected" value="1">
                        <div id="selected-ids-container"></div>
                    </form>

                    <form method="POST" id="delete-all-form" style="display: none;">
                        <input type="hidden" name="delete_all" value="1">
                        <input type="hidden" name="current_filter" value="<?= htmlspecialchars($filter) ?>">
                        <input type="hidden" name="current_search" value="<?= htmlspecialchars($search) ?>">
                    </form>

                    <!-- 답변 저장 폼 -->
                    <form method="POST" id="reply-submit-form" style="display: none;">
                        <input type="hidden" name="inquiry_id" id="reply-inquiry-id">
                        <input type="hidden" name="reply" id="reply-content">
                    </form>

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
        // 상세 내용 토글
        function toggleInquiryDetail(id) {
            const detail = document.getElementById('detail-' + id);
            const icon = document.getElementById('toggle-icon-' + id);

            if (detail.style.display === 'none' || detail.style.display === '') {
                detail.style.display = 'block';
                icon.textContent = '▲';
            } else {
                detail.style.display = 'none';
                icon.textContent = '▼';
            }
        }

        // 답변 폼 토글
        function toggleReplyForm(id) {
            const form = document.getElementById('reply-form-' + id);
            if (form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        }

        // 답변 제출
        function submitReply(id) {
            const textarea = document.querySelector('textarea[name="reply_text_' + id + '"]');
            const reply = textarea.value.trim();

            if (!reply) {
                alert('답변 내용을 입력해주세요.');
                return;
            }

            document.getElementById('reply-inquiry-id').value = id;
            document.getElementById('reply-content').value = reply;
            document.getElementById('reply-submit-form').submit();
        }

        // 전체 선택 토글
        function toggleSelectAll() {
            const selectAll = document.getElementById('select-all');
            const checkboxes = document.querySelectorAll('.inquiry-checkbox');

            checkboxes.forEach(cb => {
                cb.checked = selectAll.checked;
            });

            updateSelectedCount();
        }

        // 선택된 개수 업데이트
        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.inquiry-checkbox:checked');
            const count = checkboxes.length;
            const countEl = document.getElementById('selected-count');
            const deleteBtn = document.getElementById('btn-delete-selected');

            countEl.textContent = count + '개 선택됨';

            if (count > 0) {
                deleteBtn.disabled = false;
                deleteBtn.classList.add('active');
            } else {
                deleteBtn.disabled = true;
                deleteBtn.classList.remove('active');
            }

            // 전체 선택 체크박스 상태 업데이트
            const allCheckboxes = document.querySelectorAll('.inquiry-checkbox');
            const selectAll = document.getElementById('select-all');
            if (selectAll) {
                selectAll.checked = (allCheckboxes.length > 0 && checkboxes.length === allCheckboxes.length);
            }
        }

        // 선택 삭제
        function deleteSelected() {
            const checkboxes = document.querySelectorAll('.inquiry-checkbox:checked');
            const count = checkboxes.length;

            if (count === 0) {
                alert('삭제할 항목을 선택해주세요.');
                return;
            }

            if (!confirm(count + '개의 문의를 삭제하시겠습니까?')) {
                return;
            }

            const container = document.getElementById('selected-ids-container');
            container.innerHTML = '';

            checkboxes.forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_ids[]';
                input.value = cb.value;
                container.appendChild(input);
            });

            document.getElementById('delete-selected-form').submit();
        }

        // 전체 삭제
        function deleteAll() {
            const total = document.querySelectorAll('.inquiry-checkbox').length;

            if (total === 0) {
                alert('삭제할 항목이 없습니다.');
                return;
            }

            if (!confirm('현재 목록의 ' + total + '개 문의를 모두 삭제하시겠습니까?\n\n⚠️ 이 작업은 되돌릴 수 없습니다!')) {
                return;
            }

            if (!confirm('정말로 삭제하시겠습니까? 한 번 더 확인해주세요.')) {
                return;
            }

            document.getElementById('delete-all-form').submit();
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

        /* 대량 삭제 버튼 영역 */
        .bulk-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0;
            border-bottom: 2px solid #ecf0f1;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .bulk-select {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-weight: 500;
        }

        .checkbox-label input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .selected-count {
            color: #7f8c8d;
            font-size: 0.9em;
        }

        .bulk-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-danger-outline {
            background: white;
            color: #e74c3c;
            border: 2px solid #e74c3c;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-danger-outline:hover {
            background: #fee;
        }

        #btn-delete-selected:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        #btn-delete-selected.active {
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        /* 문의 목록 테이블 스타일 */
        .inquiry-list {
            background: white;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .inquiry-table-header {
            display: grid;
            grid-template-columns: 50px 80px 1fr 120px 80px 100px;
            gap: 10px;
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 2px solid #ecf0f1;
            font-weight: bold;
            color: #2c3e50;
            font-size: 0.9em;
        }

        .inquiry-row {
            display: grid;
            grid-template-columns: 50px 80px 1fr 120px 80px 100px;
            gap: 10px;
            padding: 15px 20px;
            border-bottom: 1px solid #ecf0f1;
            align-items: center;
            transition: background 0.2s;
        }

        .inquiry-row:hover {
            background: #f8f9fa;
        }

        .col-checkbox {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .col-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .col-title {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-weight: 500;
            color: #2c3e50;
        }

        .col-title:hover {
            color: #3498db;
        }

        .title-text {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .toggle-icon {
            color: #7f8c8d;
            font-size: 0.8em;
            transition: transform 0.2s;
        }

        .col-name {
            font-size: 0.9em;
            color: #2c3e50;
        }

        .col-type {
            font-size: 0.85em;
            color: #7f8c8d;
        }

        .col-date {
            font-size: 0.85em;
            color: #7f8c8d;
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
            white-space: nowrap;
            display: inline-block;
        }

        .status-pending {
            background: #fee;
            color: #e74c3c;
        }

        .status-answered {
            background: #efe;
            color: #27ae60;
        }

        .badge-user {
            background: #3498db;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.7em;
            margin-left: 5px;
        }

        /* 상세 내용 영역 */
        .inquiry-detail {
            padding: 20px 30px 25px 70px;
            background: #fafbfc;
            border-bottom: 1px solid #ecf0f1;
        }

        .detail-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px dashed #ddd;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #7f8c8d;
            font-size: 0.9em;
        }

        .meta-icon {
            font-size: 1em;
        }

        .inquiry-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            margin-bottom: 15px;
        }

        .inquiry-content strong {
            display: block;
            margin-bottom: 10px;
            color: #2c3e50;
        }

        .content-text {
            color: #2c3e50;
            line-height: 1.7;
        }

        .reply-section {
            margin-top: 15px;
        }

        .reply-display {
            background: #e8f5e9;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #27ae60;
            margin-bottom: 15px;
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
        }

        .reply-form-container {
            margin-top: 15px;
            padding: 20px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
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
            box-sizing: border-box;
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

        /* 반응형 */
        @media (max-width: 768px) {
            .inquiry-table-header {
                display: none;
            }

            .inquiry-row {
                grid-template-columns: 40px 1fr;
                gap: 5px;
            }

            .inquiry-row .col-status,
            .inquiry-row .col-name,
            .inquiry-row .col-type,
            .inquiry-row .col-date {
                display: none;
            }

            .inquiry-row .col-title {
                grid-column: 2;
            }

            .inquiry-detail {
                padding: 15px;
            }

            .bulk-actions {
                flex-direction: column;
                gap: 10px;
            }

            .bulk-buttons {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</body>
</html>

<?php
// Initialize session and auth before any output
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
require_once $base_path . '/classes/Database.php';

$auth = Auth::getInstance();
$auth->requireAdmin();

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all';

try {
    $pdo = Database::getInstance()->getConnection();

    $where_conditions = [];
    $params = [];

    if ($filter === 'published') {
        $where_conditions[] = "b.status = 'published'";
    } elseif ($filter === 'draft') {
        $where_conditions[] = "b.status = 'draft'";
    } elseif ($filter === 'deleted') {
        $where_conditions[] = "b.status = 'deleted'";
    } else {
        // 기본적으로 삭제된 게시글 제외
        $where_conditions[] = "b.status != 'deleted'";
    }

    if ($search) {
        $where_conditions[] = "(b.title LIKE ? OR b.content LIKE ? OR u.name LIKE ?)";
        $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
    }

    $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

    $count_sql = "SELECT COUNT(*) FROM boards b
                  LEFT JOIN users u ON b.user_id = u.id
                  LEFT JOIN board_categories c ON b.category_id = c.id
                  $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_posts = $stmt->fetchColumn();

    $total_pages = ceil($total_posts / $per_page);

    // Fix LIMIT/OFFSET binding issue
    $per_page = (int) $per_page;
    $offset = (int) $offset;
    $sql = "SELECT b.id, b.title, b.views, b.is_notice, b.is_featured, b.status, b.created_at,
                   u.name as author, c.name as category_name
            FROM boards b
            LEFT JOIN users u ON b.user_id = u.id
            LEFT JOIN board_categories c ON b.category_id = c.id
            $where_clause
            ORDER BY b.created_at DESC
            LIMIT $per_page OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $posts = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = "게시글을 불러오는데 실패했습니다.";
    $posts = [];
    $total_posts = 0;
    $total_pages = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $post_ids = $_POST['post_ids'] ?? [];
    
    if (!empty($post_ids) && in_array($action, ['delete', 'restore', 'notice_on', 'notice_off'])) {
        try {
            $pdo->beginTransaction();
            
            $placeholders = str_repeat('?,', count($post_ids) - 1) . '?';
            
            switch ($action) {
                case 'delete':
                    $sql = "UPDATE boards SET status = 'deleted' WHERE id IN ($placeholders)";
                    break;
                case 'restore':
                    $sql = "UPDATE boards SET status = 'published' WHERE id IN ($placeholders)";
                    break;
                case 'notice_on':
                    $sql = "UPDATE boards SET is_notice = 1 WHERE id IN ($placeholders)";
                    break;
                case 'notice_off':
                    $sql = "UPDATE boards SET is_notice = 0 WHERE id IN ($placeholders)";
                    break;
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($post_ids);
            
            $pdo->commit();
            $success = "선택한 게시글이 처리되었습니다.";
            
            header("Location: ?page=$page&search=" . urlencode($search) . "&filter=$filter&success=1");
            exit;
            
        } catch (Exception $e) {
            $pdo->rollback();
            $error = "게시글 처리에 실패했습니다.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>게시글 관리 - 탄생 관리자</title>
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
                    <h1>게시글 관리</h1>
                    <p>사용자들이 작성한 게시글을 관리합니다</p>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success">게시글이 성공적으로 처리되었습니다.</div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="admin-card">
                    <div class="card-header">
                        <div class="search-form">
                            <form method="get" class="admin-search">
                                <select name="filter" class="form-select">
                                    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>전체</option>
                                    <option value="published" <?= $filter === 'published' ? 'selected' : '' ?>>게시됨</option>
                                    <option value="draft" <?= $filter === 'draft' ? 'selected' : '' ?>>임시저장</option>
                                    <option value="deleted" <?= $filter === 'deleted' ? 'selected' : '' ?>>삭제됨</option>
                                </select>
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                       placeholder="제목, 내용, 작성자로 검색" class="form-input">
                                <button type="submit" class="btn btn-primary">검색</button>
                                <a href="?" class="btn btn-outline">전체</a>
                            </form>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <?php if (!empty($posts)): ?>
                            <form method="post" id="bulkForm">
                                <div class="bulk-actions">
                                    <select name="action" id="bulkAction" class="form-select">
                                        <option value="">선택된 게시글 작업</option>
                                        <option value="notice_on">공지로 설정</option>
                                        <option value="notice_off">공지 해제</option>
                                        <option value="delete">삭제</option>
                                        <option value="restore">복원</option>
                                    </select>
                                    <button type="submit" class="btn btn-secondary" onclick="return confirmBulkAction()">실행</button>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="admin-table">
                                        <thead>
                                            <tr>
                                                <th width="40">
                                                    <input type="checkbox" id="selectAll">
                                                </th>
                                                <th width="60">번호</th>
                                                <th>제목</th>
                                                <th width="100">작성자</th>
                                                <th width="100">카테고리</th>
                                                <th width="60">조회수</th>
                                                <th width="80">상태</th>
                                                <th width="120">작성일</th>
                                                <th width="100">관리</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($posts as $post): ?>
                                                <tr class="<?= $post['status'] === 'deleted' ? 'row-deleted' : '' ?>">
                                                    <td>
                                                        <input type="checkbox" name="post_ids[]" value="<?= $post['id'] ?>">
                                                    </td>
                                                    <td><?= $post['id'] ?></td>
                                                    <td>
                                                        <div class="post-title-cell">
                                                            <?php if ($post['is_notice']): ?>
                                                                <span class="badge badge-notice">공지</span>
                                                            <?php endif; ?>
                                                            <?php if ($post['is_featured']): ?>
                                                                <span class="badge badge-featured">추천</span>
                                                            <?php endif; ?>
                                                            <a href="/pages/board/view.php?id=<?= $post['id'] ?>" target="_blank" class="post-title-link">
                                                                <?= htmlspecialchars($post['title']) ?>
                                                            </a>
                                                        </div>
                                                    </td>
                                                    <td><?= htmlspecialchars($post['author'] ?: '익명') ?></td>
                                                    <td>
                                                        <span class="category-name">
                                                            <?= htmlspecialchars($post['category_name'] ?: '기본') ?>
                                                        </span>
                                                    </td>
                                                    <td><?= number_format($post['views']) ?></td>
                                                    <td>
                                                        <span class="status-badge status-<?= $post['status'] ?>">
                                                            <?= $post['status'] === 'published' ? '게시됨' : ($post['status'] === 'draft' ? '임시저장' : '삭제됨') ?>
                                                        </span>
                                                    </td>
                                                    <td><?= date('m-d H:i', strtotime($post['created_at'])) ?></td>
                                                    <td>
                                                        <div class="action-buttons">
                                                            <a href="/pages/board/view.php?id=<?= $post['id'] ?>" target="_blank" 
                                                               class="btn btn-sm btn-outline" title="보기">👁️</a>
                                                            <?php if ($post['status'] === 'published'): ?>
                                                                <a href="delete_post.php?id=<?= $post['id'] ?>" 
                                                                   class="btn btn-sm btn-danger" title="삭제"
                                                                   onclick="return confirm('정말 삭제하시겠습니까?')">🗑️</a>
                                                            <?php else: ?>
                                                                <a href="restore_post.php?id=<?= $post['id'] ?>" 
                                                                   class="btn btn-sm btn-success" title="복원"
                                                                   onclick="return confirm('게시글을 복원하시겠습니까?')">↩️</a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </form>
                            
                            <?php if ($total_pages > 1): ?>
                                <div class="pagination-wrapper">
                                    <div class="pagination">
                                        <?php if ($page > 1): ?>
                                            <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&filter=<?= $filter ?>" 
                                               class="pagination-link">이전</a>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page-5); $i <= min($total_pages, $page+5); $i++): ?>
                                            <?php if ($i == $page): ?>
                                                <span class="pagination-current"><?= $i ?></span>
                                            <?php else: ?>
                                                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&filter=<?= $filter ?>" 
                                                   class="pagination-link"><?= $i ?></a>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&filter=<?= $filter ?>" 
                                               class="pagination-link">다음</a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="pagination-info">
                                        총 <?= number_format($total_posts) ?>건 중 <?= ($page-1)*$per_page+1 ?>-<?= min($page*$per_page, $total_posts) ?>건
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="no-data">
                                <p>등록된 게시글이 없습니다.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="/assets/js/main.js"></script>
    <script src="/assets/js/admin.js"></script>
    <script>
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="post_ids[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
        
        function confirmBulkAction() {
            const action = document.getElementById('bulkAction').value;
            const checked = document.querySelectorAll('input[name="post_ids[]"]:checked');
            
            if (!action) {
                alert('작업을 선택해주세요.');
                return false;
            }
            
            if (checked.length === 0) {
                alert('게시글을 선택해주세요.');
                return false;
            }
            
            const actionNames = {
                'notice_on': '공지로 설정',
                'notice_off': '공지 해제',
                'delete': '삭제',
                'restore': '복원'
            };
            
            return confirm(`선택한 ${checked.length}개 게시글을 ${actionNames[action]}하시겠습니까?`);
        }
    </script>
    
    <style>
        .row-deleted {
            opacity: 0.6;
        }
        
        .post-title-cell {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .post-title-link {
            color: #333;
            text-decoration: none;
            font-weight: 500;
        }
        
        .post-title-link:hover {
            color: #007bff;
        }
        
        .badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .badge-notice {
            background: #dc3545;
            color: white;
        }
        
        .badge-review {
            background: #28a745;
            color: white;
        }
        
        .post-type-general {
            color: #6c757d;
        }
        
        .post-type-review {
            color: #28a745;
            font-weight: 500;
        }
        
        .status-badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-deleted {
            background: #f8d7da;
            color: #721c24;
        }
        
        .action-buttons {
            display: flex;
            gap: 4px;
        }
        
        .bulk-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            align-items: center;
        }
    </style>
</body>
</html>
<?php
// Initialize session and auth before any output
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
$auth = Auth::getInstance();
$auth->requireAdmin();

require_once $base_path . '/classes/Database.php';

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;
$search = $_GET['search'] ?? '';

$customers = [];
$total_customers = 0;
$total_pages = 0;
$error = '';

try {
    $pdo = Database::getInstance()->getConnection();
    
    $where_conditions = ["user_level < 9"];
    $params = [];
    
    if ($search) {
        $where_conditions[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
        $params = ["%$search%", "%$search%", "%$search%"];
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // 총 고객 수 조회
    $count_sql = "SELECT COUNT(*) FROM users $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_customers = $stmt->fetchColumn();
    
    $total_pages = ceil($total_customers / $per_page);
    
    // 고객 목록 조회
    $per_page_int = (int) $per_page;
    $offset_int = (int) $offset;
    $sql = "SELECT id, name, email, phone, created_at, last_login, user_level, plant_analysis_permission, 
                   CASE WHEN last_login IS NOT NULL THEN 'active' ELSE 'inactive' END as status 
            FROM users $where_clause
            ORDER BY created_at DESC 
            LIMIT $per_page_int OFFSET $offset_int";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = "고객 정보를 불러오는데 실패했습니다: " . $e->getMessage();
    $customers = [];
    $total_customers = 0;
    $total_pages = 0;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>고객 관리 - 탄생 관리자</title>
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
                    <div class="page-title">
                        <h1>👥 고객 관리</h1>
                        <p>등록된 고객 정보를 관리합니다</p>
                    </div>
                </div>

                <div class="table-controls">
                    <div class="search-box">
                        <form method="get" class="search-form">
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                   placeholder="이름, 이메일, 전화번호로 검색..."
                                   class="form-control">
                            <button type="submit" class="btn btn-primary">검색</button>
                            <?php if ($search): ?>
                                <a href="?" class="btn btn-outline">초기화</a>
                            <?php endif; ?>
                        </form>
                    </div>
                    <div class="table-info">
                        <span>총 <?= number_format($total_customers) ?>명의 고객</span>
                    </div>
                </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <strong>오류:</strong> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
                <div class="table-container">
                    <?php if (empty($customers)): ?>
                        <div class="no-data">
                            <div class="no-data-icon">👥</div>
                            <div class="no-data-text">
                                <?= $search ? '검색 결과가 없습니다.' : '등록된 고객이 없습니다.' ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>이름</th>
                                    <th>이메일</th>
                                    <th>연락처</th>
                                    <th>사용자 레벨</th>
                                    <th>식물분석 권한</th>
                                    <th>가입일</th>
                                    <th>최근 로그인</th>
                                    <th>상태</th>
                                    <th>관리</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td><?= $customer['id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($customer['name']) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($customer['email']) ?></td>
                                    <td><?= htmlspecialchars($customer['phone'] ?? '-') ?></td>
                                    <td>
                                        <span class="user-level level-<?= $customer['user_level'] ?>">
                                            <?php
                                            switch($customer['user_level']) {
                                                case 1: echo '일반 사용자'; break;
                                                case 2: echo '식물분석 권한자'; break;
                                                case 9: echo '관리자'; break;
                                                default: echo '미정의';
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="permission-badge <?= $customer['plant_analysis_permission'] ? 'granted' : 'denied' ?>">
                                            <?= $customer['plant_analysis_permission'] ? '승인' : '미승인' ?>
                                        </span>
                                    </td>
                                    <td><?= date('Y-m-d', strtotime($customer['created_at'])) ?></td>
                                    <td><?= $customer['last_login'] ? date('Y-m-d H:i', strtotime($customer['last_login'])) : '없음' ?></td>
                                    <td>
                                        <span class="status-badge <?= $customer['status'] === 'active' ? 'active' : 'inactive' ?>">
                                            <?= $customer['status'] === 'active' ? '활성' : '비활성' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="detail.php?id=<?= $customer['id'] ?>" class="btn btn-sm btn-outline" title="상세보기">👁️</a>
                                            <a href="edit.php?id=<?= $customer['id'] ?>" class="btn btn-sm btn-secondary" title="수정">✏️</a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="btn btn-outline">이전</a>
                    <?php endif; ?>

                    <div class="page-numbers">
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);

                        for ($i = $start; $i <= $end; $i++):
                        ?>
                        <a href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?>"
                           class="btn <?= $i === $page ? 'btn-primary' : 'btn-outline' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                    </div>

                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="btn btn-outline">다음</a>
                    <?php endif; ?>
                </div>
                <div class="pagination-info">
                    <span><?= number_format($total_customers) ?>명 중 <?= number_format(($page - 1) * $per_page + 1) ?>-<?= number_format(min($page * $per_page, $total_customers)) ?>명 표시</span>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="/assets/js/main.js"></script>
    <script src="/assets/js/admin.js"></script>
</body>
</html>
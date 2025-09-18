<?php
// Initialize session and auth before any output
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
require_once $base_path . '/classes/Database.php';

$auth = Auth::getInstance();
$auth->requireAdmin();

$success = '';
$error = '';

// Handle permission updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_permission'])) {
    $user_id = intval($_POST['user_id']);
    $permission_value = isset($_POST['permission_value']) ? 1 : 0;
    
    try {
        $pdo = Database::getInstance()->getConnection();
        $sql = "UPDATE users SET plant_analysis_permission = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$permission_value, $user_id]);
        
        $success = '식물분석 권한이 업데이트되었습니다.';
    } catch (Exception $e) {
        $error = '권한 업데이트에 실패했습니다: ' . $e->getMessage();
    }
}

// Get users with pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;
$search = $_GET['search'] ?? '';
$permission_filter = $_GET['permission'] ?? 'all';

$users = [];
$total_users = 0;
$total_pages = 0;

try {
    $pdo = Database::getInstance()->getConnection();
    
    $where_conditions = ["user_level < 9"];
    $params = [];
    
    if ($search) {
        $where_conditions[] = "(name LIKE ? OR email LIKE ?)";
        $params = ["%$search%", "%$search%"];
    }
    
    if ($permission_filter === 'granted') {
        $where_conditions[] = "plant_analysis_permission = 1";
    } elseif ($permission_filter === 'denied') {
        $where_conditions[] = "(plant_analysis_permission = 0 OR plant_analysis_permission IS NULL)";
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Count users
    $count_sql = "SELECT COUNT(*) FROM users $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_users = $stmt->fetchColumn();
    
    $total_pages = ceil($total_users / $per_page);
    
    // Get users
    $per_page_int = (int) $per_page;
    $offset_int = (int) $offset;
    $sql = "SELECT id, name, email, user_level, plant_analysis_permission, created_at, last_login 
            FROM users $where_clause 
            ORDER BY created_at DESC 
            LIMIT $per_page_int OFFSET $offset_int";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = "사용자 정보를 불러오는데 실패했습니다: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>식물분석 권한 관리 - 탄생 관리자</title>
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
                        <h1>🔑 식물분석 권한 관리</h1>
                        <p>사용자별 식물분석 기능 접근 권한을 관리합니다</p>
                    </div>
                    <div class="page-actions">
                        <a href="index.php" class="btn btn-outline">분석 현황</a>
                        <a href="analysis_logs.php" class="btn btn-secondary">분석 로그</a>
                    </div>
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
                
                <div class="admin-card">
                    <div class="card-header">
                        <div class="search-form">
                            <form method="get" class="admin-search">
                                <select name="permission" class="form-select">
                                    <option value="all" <?= $permission_filter === 'all' ? 'selected' : '' ?>>모든 사용자</option>
                                    <option value="granted" <?= $permission_filter === 'granted' ? 'selected' : '' ?>>권한 허용</option>
                                    <option value="denied" <?= $permission_filter === 'denied' ? 'selected' : '' ?>>권한 거부</option>
                                </select>
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                       placeholder="이름, 이메일로 검색하세요..." class="form-input">
                                <button type="submit" class="btn btn-primary">🔍 검색</button>
                                <?php if ($search || $permission_filter !== 'all'): ?>
                                    <a href="user_permissions.php" class="btn btn-outline">전체보기</a>
                                <?php endif; ?>
                            </form>
                        </div>
                        <div class="table-info">
                            <span>총 <?= number_format($total_users) ?>명</span>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <?php if (empty($users)): ?>
                            <div class="no-data">
                                <div class="no-data-icon">🔑</div>
                                <div class="no-data-text">
                                    <?= $search ? '검색 결과가 없습니다.' : '권한을 관리할 사용자가 없습니다.' ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>사용자</th>
                                            <th>이메일</th>
                                            <th width="80">등급</th>
                                            <th width="120">식물분석 권한</th>
                                            <th width="120">가입일</th>
                                            <th width="120">최근 로그인</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($user['name']) ?></strong>
                                                </td>
                                                <td><?= htmlspecialchars($user['email']) ?></td>
                                                <td>
                                                    <span class="level-badge level-<?= $user['user_level'] ?>">
                                                        <?php
                                                        switch($user['user_level']) {
                                                            case 1: echo '일반'; break;
                                                            case 5: echo 'VIP'; break;
                                                            default: echo '기타';
                                                        }
                                                        ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <form method="post" class="inline-form">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <label class="permission-switch">
                                                            <input type="checkbox" name="permission_value" 
                                                                   <?= $user['plant_analysis_permission'] ? 'checked' : '' ?>
                                                                   onchange="this.form.submit()">
                                                            <span class="switch-slider"></span>
                                                            <span class="switch-label">
                                                                <?= $user['plant_analysis_permission'] ? '허용' : '거부' ?>
                                                            </span>
                                                        </label>
                                                        <input type="hidden" name="update_permission" value="1">
                                                    </form>
                                                </td>
                                                <td><?= date('Y-m-d', strtotime($user['created_at'])) ?></td>
                                                <td>
                                                    <?= $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : '없음' ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($total_pages > 1): ?>
                        <div class="card-footer">
                            <div class="pagination-wrapper">
                                <div class="pagination">
                                    <?php
                                    $page_group = ceil($page / 10);
                                    $start_page = ($page_group - 1) * 10 + 1;
                                    $end_page = min($start_page + 9, $total_pages);
                                    ?>
                                    
                                    <?php if ($start_page > 1): ?>
                                        <a href="?page=1<?= $search ? '&search=' . urlencode($search) : '' ?><?= $permission_filter !== 'all' ? '&permission=' . $permission_filter : '' ?>">처음</a>
                                        <a href="?page=<?= $start_page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $permission_filter !== 'all' ? '&permission=' . $permission_filter : '' ?>">이전</a>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                        <?php if ($i == $page): ?>
                                            <span class="current"><?= $i ?></span>
                                        <?php else: ?>
                                            <a href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $permission_filter !== 'all' ? '&permission=' . $permission_filter : '' ?>"><?= $i ?></a>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                    
                                    <?php if ($end_page < $total_pages): ?>
                                        <a href="?page=<?= $end_page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $permission_filter !== 'all' ? '&permission=' . $permission_filter : '' ?>">다음</a>
                                        <a href="?page=<?= $total_pages ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $permission_filter !== 'all' ? '&permission=' . $permission_filter : '' ?>">마지막</a>
                                    <?php endif; ?>
                                </div>
                                <div class="pagination-info">
                                    총 <?= number_format($total_users) ?>명 중 <?= ($page-1)*$per_page+1 ?>-<?= min($page*$per_page, $total_users) ?>번째
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script src="/assets/js/main.js"></script>
    
    <style>
        .inline-form {
            display: inline-block;
            margin: 0;
        }
        
        .level-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .level-1 {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .level-5 {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .permission-switch {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .permission-switch input[type="checkbox"] {
            position: relative;
            width: 50px;
            height: 24px;
            appearance: none;
            background: #ccc;
            border-radius: 12px;
            outline: none;
            transition: background 0.3s;
        }
        
        .permission-switch input[type="checkbox"]:checked {
            background: #4CAF50;
        }
        
        .permission-switch input[type="checkbox"]::before {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 20px;
            height: 20px;
            background: white;
            border-radius: 50%;
            transition: left 0.3s;
        }
        
        .permission-switch input[type="checkbox"]:checked::before {
            left: 28px;
        }
        
        .switch-label {
            font-size: 12px;
            font-weight: 500;
            min-width: 30px;
        }
        
        .permission-switch input[type="checkbox"]:checked + .switch-slider + .switch-label {
            color: #28a745;
        }
        
        .card-footer {
            padding: 20px;
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }
    </style>
</body>
</html>
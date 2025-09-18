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
    $permission_type = $_POST['permission_type'];
    $permission_value = isset($_POST['permission_value']) ? 1 : 0;
    
    try {
        $pdo = Database::getInstance()->getConnection();
        
        if ($permission_type === 'plant_analysis') {
            $sql = "UPDATE users SET plant_analysis_permission = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$permission_value, $user_id]);
            
            $success = '식물분석 권한이 업데이트되었습니다.';
        } elseif ($permission_type === 'user_level') {
            $new_level = intval($_POST['user_level']);
            $sql = "UPDATE users SET user_level = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$new_level, $user_id]);
            
            $success = '사용자 등급이 업데이트되었습니다.';
        }
        
    } catch (Exception $e) {
        $error = '권한 업데이트에 실패했습니다: ' . $e->getMessage();
    }
}

// Get users with pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;
$search = $_GET['search'] ?? '';

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
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Count users
    $count_sql = "SELECT COUNT(*) FROM users $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_users = $stmt->fetchColumn();
    
    $total_pages = ceil($total_users / $per_page);
    
    // Get users
    $per_page = (int) $per_page;
    $offset = (int) $offset;
    $sql = "SELECT id, name, email, user_level, plant_analysis_permission, created_at, last_login 
            FROM users $where_clause 
            ORDER BY created_at DESC 
            LIMIT $per_page OFFSET $offset";
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
    <title>권한 관리 - 탄생 관리자</title>
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
                    <h1>🔑 권한 관리</h1>
                    <p>사용자의 권한과 등급을 관리합니다</p>
                </div>
                
                <div class="content-wrapper">
                    <div class="search-section">
                        <form class="search-form" method="get">
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                   placeholder="이름, 이메일로 검색하세요..." class="search-input">
                            <button type="submit" class="btn btn-primary">🔍 검색</button>
                            <?php if ($search): ?>
                                <a href="permissions.php" class="btn btn-outline">전체보기</a>
                            <?php endif; ?>
                        </form>
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
                    
                    <div class="table-container">
                        <?php if (empty($users)): ?>
                            <div class="no-data">
                                <div class="no-data-icon">🔑</div>
                                <div class="no-data-text">
                                    <?= $search ? '검색 결과가 없습니다.' : '관리할 사용자가 없습니다.' ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>사용자</th>
                                        <th>이메일</th>
                                        <th>등급</th>
                                        <th>식물분석 권한</th>
                                        <th>가입일</th>
                                        <th>최근 로그인</th>
                                        <th>관리</th>
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
                                                <form method="post" class="inline-form">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <input type="hidden" name="permission_type" value="user_level">
                                                    <select name="user_level" onchange="this.form.submit()" class="form-select-small">
                                                        <option value="1" <?= $user['user_level'] == 1 ? 'selected' : '' ?>>일반</option>
                                                        <option value="5" <?= $user['user_level'] == 5 ? 'selected' : '' ?>>VIP</option>
                                                    </select>
                                                    <input type="hidden" name="update_permission" value="1">
                                                </form>
                                            </td>
                                            <td>
                                                <form method="post" class="inline-form">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <input type="hidden" name="permission_type" value="plant_analysis">
                                                    <label class="switch">
                                                        <input type="checkbox" name="permission_value" 
                                                               <?= $user['plant_analysis_permission'] ? 'checked' : '' ?>
                                                               onchange="this.form.submit()">
                                                        <span class="slider"></span>
                                                    </label>
                                                    <input type="hidden" name="update_permission" value="1">
                                                </form>
                                            </td>
                                            <td><?= date('Y-m-d', strtotime($user['created_at'])) ?></td>
                                            <td>
                                                <?= $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : '없음' ?>
                                            </td>
                                            <td>
                                                <a href="../users/?user_id=<?= $user['id'] ?>" class="btn btn-small btn-outline">상세</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination-wrapper">
                            <div class="pagination">
                                <?php
                                $page_group = ceil($page / 10);
                                $start_page = ($page_group - 1) * 10 + 1;
                                $end_page = min($start_page + 9, $total_pages);
                                ?>
                                
                                <?php if ($start_page > 1): ?>
                                    <a href="?page=1<?= $search ? '&search=' . urlencode($search) : '' ?>">처음</a>
                                    <a href="?page=<?= $start_page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>">이전</a>
                                <?php endif; ?>
                                
                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <?php if ($i == $page): ?>
                                        <span class="current"><?= $i ?></span>
                                    <?php else: ?>
                                        <a href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?>"><?= $i ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($end_page < $total_pages): ?>
                                    <a href="?page=<?= $end_page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>">다음</a>
                                    <a href="?page=<?= $total_pages ?><?= $search ? '&search=' . urlencode($search) : '' ?>">마지막</a>
                                <?php endif; ?>
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
        
        .form-select-small {
            padding: 5px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #4CAF50;
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
    </style>
</body>
</html>
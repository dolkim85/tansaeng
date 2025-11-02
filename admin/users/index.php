<?php
// Initialize session and auth before any output
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
require_once $base_path . '/classes/User.php';

$auth = Auth::getInstance();
$auth->requireAdmin();

$user = new User();
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$search = trim($_GET['search'] ?? '');

$users = $user->getAllUsers($page, $limit, $search);
$totalUsers = $user->getUserCount($search);
$totalPages = ceil($totalUsers / $limit);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>사용자 관리 - 탄생 관리자</title>
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
                        <h1>사용자 관리</h1>
                        <p>등록된 사용자 목록을 관리하고 권한을 설정할 수 있습니다</p>
                    </div>
                    <div class="page-actions">
                        <a href="send_email.php" class="btn btn-primary">📧 이메일 발송</a>
                        <a href="permissions.php" class="btn btn-secondary">권한 관리</a>
                        <a href="export.php" class="btn btn-outline">📊 데이터 내보내기</a>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="table-controls">
                    <div class="search-box">
                        <form method="get" class="search-form">
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="이름 또는 이메일로 검색...">
                            <button type="submit" class="btn btn-primary">검색</button>
                            <?php if ($search): ?>
                            <a href="?" class="btn btn-outline">초기화</a>
                            <?php endif; ?>
                        </form>
                    </div>
                    <div class="table-info">
                        <span>총 <?= number_format($totalUsers) ?>명의 사용자</span>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>이름</th>
                                <th>이메일</th>
                                <th>연락처</th>
                                <th>연령대</th>
                                <th>성별</th>
                                <th>가입유형</th>
                                <th>사용자 레벨</th>
                                <th>식물분석 권한</th>
                                <th>가입일</th>
                                <th>최근 로그인</th>
                                <th>상태</th>
                                <th>관리</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="13" class="no-data">
                                    <?= $search ? '검색 결과가 없습니다.' : '등록된 사용자가 없습니다.' ?>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($users as $userData): ?>
                            <tr>
                                <td><?= $userData['id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($userData['name']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($userData['email']) ?></td>
                                <td><?= htmlspecialchars($userData['phone'] ?? '-') ?></td>
                                <td>
                                    <?php if (!empty($userData['age_range'])): ?>
                                    <span class="info-badge"><?= htmlspecialchars($userData['age_range']) ?></span>
                                    <?php else: ?>
                                    <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($userData['gender'])): ?>
                                    <span class="info-badge"><?= htmlspecialchars($userData['gender']) ?></span>
                                    <?php else: ?>
                                    <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($userData['oauth_provider'])): ?>
                                    <span class="oauth-badge oauth-<?= $userData['oauth_provider'] ?>">
                                        <?php
                                        switch($userData['oauth_provider']) {
                                            case 'google': echo '구글'; break;
                                            case 'kakao': echo '카카오'; break;
                                            case 'naver': echo '네이버'; break;
                                            default: echo ucfirst($userData['oauth_provider']);
                                        }
                                        ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="oauth-badge oauth-email">이메일</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="user-level level-<?= $userData['user_level'] ?>">
                                        <?php
                                        switch($userData['user_level']) {
                                            case 1: echo '일반 사용자'; break;
                                            case 2: echo '식물분석 권한자'; break;
                                            case 9: echo '관리자'; break;
                                            default: echo '미정의';
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="permission-badge <?= $userData['plant_analysis_permission'] ? 'granted' : 'denied' ?>">
                                        <?= $userData['plant_analysis_permission'] ? '승인' : '미승인' ?>
                                    </span>
                                </td>
                                <td><?= date('Y-m-d', strtotime($userData['created_at'])) ?></td>
                                <td><?= $userData['last_login'] ? date('Y-m-d H:i', strtotime($userData['last_login'])) : '없음' ?></td>
                                <td>
                                    <span class="status-badge <?= $userData['is_active'] ? 'active' : 'inactive' ?>">
                                        <?= $userData['is_active'] ? '활성' : '비활성' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="detail.php?id=<?= $userData['id'] ?>" class="btn btn-sm btn-outline" title="상세보기">👁️</a>
                                        <a href="edit.php?id=<?= $userData['id'] ?>" class="btn btn-sm btn-secondary" title="수정">✏️</a>
                                        <?php if ($userData['plant_analysis_permission']): ?>
                                        <button onclick="togglePermission(<?= $userData['id'] ?>, 'revoke')" class="btn btn-sm btn-warning" title="권한 해제">🔒</button>
                                        <?php else: ?>
                                        <button onclick="togglePermission(<?= $userData['id'] ?>, 'grant')" class="btn btn-sm btn-success" title="권한 부여">🔓</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="btn btn-outline">이전</a>
                    <?php endif; ?>
                    
                    <div class="page-numbers">
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                        <a href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                           class="btn <?= $i === $page ? 'btn-primary' : 'btn-outline' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                    </div>
                    
                    <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="btn btn-outline">다음</a>
                    <?php endif; ?>
                </div>
                <div class="pagination-info">
                    <span><?= number_format($totalUsers) ?>명 중 <?= number_format(($page - 1) * $limit + 1) ?>-<?= number_format(min($page * $limit, $totalUsers)) ?>명 표시</span>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <style>
        .info-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.85rem;
            background: #e7f3ff;
            color: #0066cc;
            font-weight: 500;
        }

        .oauth-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .oauth-google {
            background: #4285f4;
            color: white;
        }

        .oauth-kakao {
            background: #fee500;
            color: #3c1e1e;
        }

        .oauth-naver {
            background: #03c75a;
            color: white;
        }

        .oauth-email {
            background: #6c757d;
            color: white;
        }

        .admin-table th {
            white-space: nowrap;
        }

        .admin-table td {
            white-space: nowrap;
        }
    </style>

    <script src="/assets/js/main.js"></script>
    <script src="/assets/js/admin.js"></script>
    <script>
        function togglePermission(userId, action) {
            const actionText = action === 'grant' ? '권한을 부여' : '권한을 해제';
            
            if (!confirm(`정말로 이 사용자의 식물분석 ${actionText}하시겠습니까?`)) {
                return;
            }
            
            TangsaengApp.showLoading();
            
            fetch('/admin/api/toggle_permission.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    user_id: userId,
                    action: action
                })
            })
            .then(response => response.json())
            .then(data => {
                TangsaengApp.hideLoading();
                
                if (data.success) {
                    TangsaengApp.showAlert('권한이 성공적으로 변경되었습니다.', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    TangsaengApp.showAlert(data.message || '권한 변경에 실패했습니다.', 'error');
                }
            })
            .catch(error => {
                TangsaengApp.hideLoading();
                TangsaengApp.showAlert('서버 오류가 발생했습니다.', 'error');
                console.error('Error:', error);
            });
        }
    </script>
    
    <!-- CSS는 /assets/css/admin.css에서 통합 관리 -->
</body>
</html>
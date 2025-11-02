<?php
// Initialize session and auth before any output
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
require_once $base_path . '/classes/User.php';

$auth = Auth::getInstance();
$auth->requireAdmin();

// Get user ID
$userId = intval($_GET['id'] ?? 0);
if (!$userId) {
    header('Location: /admin/users/index.php');
    exit;
}

$userModel = new User();
$userData = $userModel->getUserById($userId);

if (!$userData) {
    header('Location: /admin/users/index.php?error=' . urlencode('사용자를 찾을 수 없습니다.'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>사용자 상세보기 - 탄생 관리자</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        .detail-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .detail-header {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .detail-header h1 {
            font-size: 1.8rem;
            color: #333;
            margin-bottom: 10px;
        }

        .header-actions {
            display: flex;
            gap: 10px;
        }

        .detail-card {
            background: white;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 15px;
            align-items: start;
        }

        .info-label {
            font-weight: 600;
            color: #666;
        }

        .info-value {
            color: #333;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-badge.active {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .level-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .level-badge.level-1 {
            background: #e7f3ff;
            color: #0066cc;
        }

        .level-badge.level-2 {
            background: #fff3cd;
            color: #856404;
        }

        .level-badge.level-9 {
            background: #dc3545;
            color: white;
        }

        .oauth-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
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

        .permission-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .permission-badge.granted {
            background: #d4edda;
            color: #155724;
        }

        .permission-badge.denied {
            background: #f8d7da;
            color: #721c24;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
    </style>
</head>
<body class="admin-body">
    <?php include '../includes/admin_header.php'; ?>

    <div class="admin-container">
        <?php include '../includes/admin_sidebar.php'; ?>

        <main class="admin-main">
            <div class="admin-content">
                <div class="detail-container">
                    <div class="detail-header">
                        <h1>👤 사용자 상세 정보</h1>
                        <div class="header-actions">
                            <a href="edit.php?id=<?= $userData['id'] ?>" class="btn btn-primary">✏️ 수정</a>
                            <a href="index.php" class="btn btn-secondary">← 목록으로</a>
                        </div>
                    </div>

                    <!-- 기본 정보 -->
                    <div class="detail-card">
                        <h2 class="card-title">기본 정보</h2>
                        <div class="info-grid">
                            <div class="info-label">사용자 ID</div>
                            <div class="info-value"><?= $userData['id'] ?></div>

                            <div class="info-label">이름</div>
                            <div class="info-value"><?= htmlspecialchars($userData['name']) ?></div>

                            <div class="info-label">이메일</div>
                            <div class="info-value"><?= htmlspecialchars($userData['email']) ?></div>

                            <div class="info-label">연락처</div>
                            <div class="info-value"><?= htmlspecialchars($userData['phone'] ?? '-') ?></div>

                            <div class="info-label">연령대</div>
                            <div class="info-value"><?= htmlspecialchars($userData['age_range'] ?? '-') ?></div>

                            <div class="info-label">성별</div>
                            <div class="info-value"><?= htmlspecialchars($userData['gender'] ?? '-') ?></div>
                        </div>
                    </div>

                    <!-- 계정 정보 -->
                    <div class="detail-card">
                        <h2 class="card-title">계정 정보</h2>
                        <div class="info-grid">
                            <div class="info-label">가입 유형</div>
                            <div class="info-value">
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
                            </div>

                            <div class="info-label">사용자 레벨</div>
                            <div class="info-value">
                                <span class="level-badge level-<?= $userData['user_level'] ?>">
                                    <?php
                                    switch($userData['user_level']) {
                                        case 1: echo '일반 사용자'; break;
                                        case 2: echo '식물분석 권한자'; break;
                                        case 9: echo '관리자'; break;
                                        default: echo '미정의';
                                    }
                                    ?>
                                </span>
                            </div>

                            <div class="info-label">식물분석 권한</div>
                            <div class="info-value">
                                <span class="permission-badge <?= $userData['plant_analysis_permission'] ? 'granted' : 'denied' ?>">
                                    <?= $userData['plant_analysis_permission'] ? '승인됨' : '미승인' ?>
                                </span>
                            </div>

                            <div class="info-label">계정 상태</div>
                            <div class="info-value">
                                <span class="status-badge <?= $userData['is_active'] ? 'active' : 'inactive' ?>">
                                    <?= $userData['is_active'] ? '활성' : '비활성' ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- 활동 정보 -->
                    <div class="detail-card">
                        <h2 class="card-title">활동 정보</h2>
                        <div class="info-grid">
                            <div class="info-label">가입일</div>
                            <div class="info-value"><?= date('Y-m-d H:i:s', strtotime($userData['created_at'])) ?></div>

                            <div class="info-label">최근 로그인</div>
                            <div class="info-value">
                                <?= $userData['last_login'] ? date('Y-m-d H:i:s', strtotime($userData['last_login'])) : '없음' ?>
                            </div>

                            <div class="info-label">정보 수정일</div>
                            <div class="info-value">
                                <?= $userData['updated_at'] ? date('Y-m-d H:i:s', strtotime($userData['updated_at'])) : '-' ?>
                            </div>
                        </div>
                    </div>

                    <!-- OAuth 정보 -->
                    <?php if (!empty($userData['oauth_provider'])): ?>
                    <div class="detail-card">
                        <h2 class="card-title">OAuth 정보</h2>
                        <div class="info-grid">
                            <div class="info-label">OAuth 제공자</div>
                            <div class="info-value"><?= htmlspecialchars($userData['oauth_provider']) ?></div>

                            <div class="info-label">OAuth ID</div>
                            <div class="info-value"><?= htmlspecialchars($userData['oauth_id'] ?? '-') ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="/assets/js/main.js"></script>
    <script src="/assets/js/admin.js"></script>
</body>
</html>

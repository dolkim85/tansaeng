<?php
// Initialize session and auth before any output
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
$auth = Auth::getInstance();
$auth->requireAdmin();

require_once $base_path . '/classes/Database.php';

$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$customer = null;
$error = '';

if ($customer_id <= 0) {
    $error = '잘못된 고객 ID입니다.';
} else {
    try {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND user_level < 9");
        $stmt->execute([$customer_id]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$customer) {
            $error = '고객을 찾을 수 없습니다.';
        }
    } catch (Exception $e) {
        $error = "고객 정보를 불러오는데 실패했습니다: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>고객 상세정보 - 탄생 관리자</title>
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
                        <h1>👤 고객 상세정보</h1>
                        <p>고객의 상세 정보를 확인합니다</p>
                    </div>
                    <div class="page-actions">
                        <a href="index.php" class="btn btn-outline">목록으로</a>
                        <?php if ($customer): ?>
                            <a href="edit.php?id=<?= $customer['id'] ?>" class="btn btn-primary">정보 수정</a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <strong>오류:</strong> <?= htmlspecialchars($error) ?>
                    </div>
                <?php elseif ($customer): ?>
                    <div class="admin-form">
                        <div class="form-section">
                            <div class="section-header">
                                <span class="section-icon">👤</span>
                                <h3>기본 정보</h3>
                            </div>
                            <div class="section-body">
                                <div class="info-grid">
                                    <div class="info-item">
                                        <label>고객 ID</label>
                                        <span><?= htmlspecialchars($customer['id']) ?></span>
                                    </div>
                                    <div class="info-item">
                                        <label>이름</label>
                                        <span><?= htmlspecialchars($customer['name']) ?></span>
                                    </div>
                                    <div class="info-item">
                                        <label>이메일</label>
                                        <span><?= htmlspecialchars($customer['email']) ?></span>
                                    </div>
                                    <div class="info-item">
                                        <label>전화번호</label>
                                        <span><?= htmlspecialchars($customer['phone'] ?: '미등록') ?></span>
                                    </div>
                                    <div class="info-item">
                                        <label>사용자 레벨</label>
                                        <span class="badge <?= $customer['user_level'] >= 5 ? 'badge-success' : 'badge-secondary' ?>">
                                            <?= $customer['user_level'] >= 5 ? '우수고객' : '일반고객' ?>
                                            (Level <?= $customer['user_level'] ?>)
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <label>식물분석 권한</label>
                                        <span class="badge <?= $customer['plant_analysis_permission'] ? 'badge-success' : 'badge-secondary' ?>">
                                            <?= $customer['plant_analysis_permission'] ? '허용' : '제한' ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="section-header">
                                <span class="section-icon">📅</span>
                                <h3>활동 정보</h3>
                            </div>
                            <div class="section-body">
                                <div class="info-grid">
                                    <div class="info-item">
                                        <label>가입일</label>
                                        <span><?= date('Y-m-d H:i', strtotime($customer['created_at'])) ?></span>
                                    </div>
                                    <div class="info-item">
                                        <label>최근 로그인</label>
                                        <span><?= $customer['last_login'] ? date('Y-m-d H:i', strtotime($customer['last_login'])) : '로그인 기록 없음' ?></span>
                                    </div>
                                    <div class="info-item">
                                        <label>계정 상태</label>
                                        <span class="badge <?= $customer['last_login'] ? 'badge-success' : 'badge-warning' ?>">
                                            <?= $customer['last_login'] ? '활성' : '비활성' ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
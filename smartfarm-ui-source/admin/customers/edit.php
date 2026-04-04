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
$success = '';

// 고객 정보 수정 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $customer_id > 0) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $user_level = (int)($_POST['user_level'] ?? 1);
    $plant_analysis_permission = isset($_POST['plant_analysis_permission']) ? 1 : 0;

    if (empty($name) || empty($email)) {
        $error = '이름과 이메일은 필수 항목입니다.';
    } else {
        try {
            $pdo = Database::getInstance()->getConnection();

            // 이메일 중복 체크 (자신 제외)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $customer_id]);
            if ($stmt->fetch()) {
                $error = '이미 사용 중인 이메일 주소입니다.';
            } else {
                // 고객 정보 업데이트
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, user_level = ?, plant_analysis_permission = ? WHERE id = ? AND user_level < 9");
                $stmt->execute([$name, $email, $phone, $user_level, $plant_analysis_permission, $customer_id]);

                if ($stmt->rowCount() > 0) {
                    $success = '고객 정보가 성공적으로 수정되었습니다.';
                } else {
                    $error = '고객 정보 수정에 실패했습니다.';
                }
            }
        } catch (Exception $e) {
            $error = "고객 정보 수정 중 오류가 발생했습니다: " . $e->getMessage();
        }
    }
}

// 고객 정보 조회
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
    <title>고객 정보 수정 - 탄생 관리자</title>
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
                        <h1>✏️ 고객 정보 수정</h1>
                        <p>고객의 정보를 수정합니다</p>
                    </div>
                    <div class="page-actions">
                        <a href="index.php" class="btn btn-outline">목록으로</a>
                        <?php if ($customer): ?>
                            <a href="detail.php?id=<?= $customer['id'] ?>" class="btn btn-secondary">상세보기</a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <strong>성공:</strong> <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <strong>오류:</strong> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($customer): ?>
                    <form method="post" class="admin-form">
                        <div class="form-section">
                            <div class="section-header">
                                <span class="section-icon">👤</span>
                                <h3>기본 정보</h3>
                            </div>
                            <div class="section-body">
                                <div class="form-group">
                                    <label for="name">이름 <span class="required">*</span></label>
                                    <input type="text" id="name" name="name" class="form-control"
                                           value="<?= htmlspecialchars($customer['name']) ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="email">이메일 <span class="required">*</span></label>
                                    <input type="email" id="email" name="email" class="form-control"
                                           value="<?= htmlspecialchars($customer['email']) ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="phone">전화번호</label>
                                    <input type="tel" id="phone" name="phone" class="form-control"
                                           value="<?= htmlspecialchars($customer['phone'] ?: '') ?>"
                                           placeholder="010-0000-0000">
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="section-header">
                                <span class="section-icon">⚙️</span>
                                <h3>권한 설정</h3>
                            </div>
                            <div class="section-body">
                                <div class="form-group">
                                    <label for="user_level">사용자 레벨</label>
                                    <select id="user_level" name="user_level" class="form-control">
                                        <option value="1" <?= $customer['user_level'] == 1 ? 'selected' : '' ?>>Level 1 - 기본</option>
                                        <option value="3" <?= $customer['user_level'] == 3 ? 'selected' : '' ?>>Level 3 - 일반</option>
                                        <option value="5" <?= $customer['user_level'] == 5 ? 'selected' : '' ?>>Level 5 - 우수</option>
                                        <option value="7" <?= $customer['user_level'] == 7 ? 'selected' : '' ?>>Level 7 - VIP</option>
                                    </select>
                                    <small>사용자 레벨이 높을수록 더 많은 기능을 이용할 수 있습니다.</small>
                                </div>

                                <div class="form-checkbox">
                                    <input type="checkbox" id="plant_analysis_permission" name="plant_analysis_permission"
                                           <?= $customer['plant_analysis_permission'] ? 'checked' : '' ?>>
                                    <label for="plant_analysis_permission">식물분석 권한 부여</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="section-header">
                                <span class="section-icon">📅</span>
                                <h3>계정 정보 (읽기 전용)</h3>
                            </div>
                            <div class="section-body">
                                <div class="info-grid">
                                    <div class="info-item">
                                        <label>고객 ID</label>
                                        <span><?= htmlspecialchars($customer['id']) ?></span>
                                    </div>
                                    <div class="info-item">
                                        <label>가입일</label>
                                        <span><?= date('Y-m-d H:i', strtotime($customer['created_at'])) ?></span>
                                    </div>
                                    <div class="info-item">
                                        <label>최근 로그인</label>
                                        <span><?= $customer['last_login'] ? date('Y-m-d H:i', strtotime($customer['last_login'])) : '로그인 기록 없음' ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">정보 수정</button>
                            <a href="detail.php?id=<?= $customer['id'] ?>" class="btn btn-secondary">취소</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ||
          (isset($_SESSION['user_level']) && $_SESSION['user_level'] == 9);

if (!$isAdmin) {
    header('Location: /pages/auth/login.php?redirect=/admin/settings/support.php');
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = DatabaseConfig::getConnection();

        $settings = [
            'support_email' => trim($_POST['support_email'] ?? ''),
            'support_phone' => trim($_POST['support_phone'] ?? ''),
            'support_hours' => trim($_POST['support_hours'] ?? ''),
            'contact_form_email' => trim($_POST['contact_form_email'] ?? ''),
            'faq_content' => trim($_POST['faq_content'] ?? ''),
            'support_notice' => trim($_POST['support_notice'] ?? ''),
            'support_policy' => trim($_POST['support_policy'] ?? '')
        ];

        foreach ($settings as $key => $value) {
            $sql = "INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$key, $value]);
        }

        $success = '고객지원 설정이 성공적으로 저장되었습니다.';
    } catch (Exception $e) {
        $error = '저장 중 오류가 발생했습니다: ' . $e->getMessage();
    }
}

$currentSettings = [];
try {
    $pdo = DatabaseConfig::getConnection();
    $sql = "SELECT setting_key, setting_value FROM site_settings";
    $stmt = $pdo->query($sql);
    while ($row = $stmt->fetch()) {
        $currentSettings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    $error = '데이터 불러오기 중 오류가 발생했습니다.';
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>고객지원 관리 - 탄생 관리자</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-body">
    <?php include '../includes/admin_header.php'; ?>

    <div class="admin-container">
        <?php include '../includes/admin_sidebar.php'; ?>

        <main class="admin-main">
            <div class="admin-content settings-container">
                <div class="settings-header">
                    <h1>🎧 고객지원 관리</h1>
                    <p>고객지원 서비스 설정을 관리합니다</p>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post" class="admin-form">
                    <div class="form-section">
                        <div class="section-header">
                            <span class="section-icon">📞</span>
                            <h3>고객지원 연락처</h3>
                        </div>
                        <div class="section-body">
                            <div class="form-group">
                                <label for="support_email">지원 이메일</label>
                                <input type="email" id="support_email" name="support_email" class="form-control"
                                       value="<?= htmlspecialchars($currentSettings['support_email'] ?? 'support@tansaeng.com') ?>">
                            </div>

                            <div class="form-group">
                                <label for="support_phone">지원 전화번호</label>
                                <input type="text" id="support_phone" name="support_phone" class="form-control"
                                       value="<?= htmlspecialchars($currentSettings['support_phone'] ?? '1588-1234') ?>">
                            </div>

                            <div class="form-group">
                                <label for="contact_form_email">문의 폼 수신 이메일</label>
                                <input type="email" id="contact_form_email" name="contact_form_email" class="form-control"
                                       value="<?= htmlspecialchars($currentSettings['contact_form_email'] ?? 'contact@tansaeng.com') ?>">
                            </div>

                            <div class="form-group">
                                <label for="support_hours">지원 시간</label>
                                <input type="text" id="support_hours" name="support_hours" class="form-control"
                                       value="<?= htmlspecialchars($currentSettings['support_hours'] ?? '평일 09:00 - 18:00 (토,일,공휴일 휴무)') ?>"
                                       placeholder="예: 평일 09:00 - 18:00">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="section-header">
                            <span class="section-icon">❓</span>
                            <h3>FAQ 및 정책</h3>
                        </div>
                        <div class="section-body">
                            <div class="form-group">
                                <label for="faq_content">자주 묻는 질문 (FAQ)</label>
                                <textarea id="faq_content" name="faq_content" class="form-control large" rows="8"><?= htmlspecialchars($currentSettings['faq_content'] ?? '자주 묻는 질문들을 여기에 입력해주세요.') ?></textarea>
                                <small>HTML 태그를 사용할 수 있습니다.</small>
                            </div>

                            <div class="form-group">
                                <label for="support_notice">고객지원 공지사항</label>
                                <textarea id="support_notice" name="support_notice" class="form-control" rows="4"><?= htmlspecialchars($currentSettings['support_notice'] ?? '고객지원 관련 공지사항을 입력하세요.') ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="support_policy">지원 정책</label>
                                <textarea id="support_policy" name="support_policy" class="form-control" rows="6"><?= htmlspecialchars($currentSettings['support_policy'] ?? '고객지원 정책 및 절차를 입력하세요.') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">저장</button>
                        <button type="reset" class="btn btn-secondary">취소</button>
                    </div>
                </form>

                <div class="form-section">
                    <h3>고객지원 가이드</h3>
                    <div class="info-box">
                        <h4>고객지원 관리 가이드:</h4>
                        <ul>
                            <li><strong>연락처 정보:</strong> 고객이 쉽게 연락할 수 있는 정확한 정보</li>
                            <li><strong>지원 시간:</strong> 명확한 업무 시간과 휴무일 안내</li>
                            <li><strong>FAQ:</strong> 자주 묻는 질문과 답변으로 고객 만족도 향상</li>
                            <li><strong>공지사항:</strong> 고객지원 관련 중요 정보 안내</li>
                            <li><strong>지원 정책:</strong> 명확한 지원 절차와 정책 안내</li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
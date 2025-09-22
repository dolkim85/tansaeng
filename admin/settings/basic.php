<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ||
          (isset($_SESSION['user_level']) && $_SESSION['user_level'] == 9);

if (!$isAdmin) {
    header('Location: /pages/auth/login.php?redirect=/admin/settings/basic.php');
    exit;
}

$success = '';
$error = '';

// 설정 저장 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = DatabaseConfig::getConnection();

        $settings = [
            'site_name' => trim($_POST['site_name'] ?? ''),
            'site_description' => trim($_POST['site_description'] ?? ''),
            'site_keywords' => trim($_POST['site_keywords'] ?? ''),
            'admin_email' => trim($_POST['admin_email'] ?? ''),
            'contact_phone' => trim($_POST['contact_phone'] ?? ''),
            'contact_address' => trim($_POST['contact_address'] ?? ''),
            'business_hours' => trim($_POST['business_hours'] ?? ''),
            'site_meta_description' => trim($_POST['site_meta_description'] ?? ''),
        ];

        foreach ($settings as $key => $value) {
            $sql = "INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$key, $value]);
        }

        $success = '기본 설정이 성공적으로 저장되었습니다.';
    } catch (Exception $e) {
        $error = '저장 중 오류가 발생했습니다: ' . $e->getMessage();
    }
}

// 현재 설정 불러오기
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
    <title>기본 설정 - 탄생 관리자</title>
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
                    <h1>⚙️ 기본 설정</h1>
                    <p>사이트의 기본 정보와 연락처를 관리합니다</p>
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
                            <span class="section-icon">🌐</span>
                            <h3>사이트 기본 정보</h3>
                        </div>
                        <div class="section-body">
                            <div class="form-group">
                                <label for="site_name">사이트 이름</label>
                                <input type="text" id="site_name" name="site_name"
                                       value="<?= htmlspecialchars($currentSettings['site_name'] ?? '탄생 - 스마트팜 배지 전문업체') ?>"
                                       class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label for="site_description">사이트 설명</label>
                                <input type="text" id="site_description" name="site_description"
                                       value="<?= htmlspecialchars($currentSettings['site_description'] ?? '고품질 수경재배 배지와 AI 식물분석 서비스를 제공하는 스마트팜 전문업체입니다.') ?>"
                                       class="form-control">
                            </div>

                            <div class="form-group">
                                <label for="site_meta_description">메타 설명 (SEO)</label>
                                <textarea id="site_meta_description" name="site_meta_description"
                                          class="form-control" rows="3"><?= htmlspecialchars($currentSettings['site_meta_description'] ?? '스마트팜 배지 전문업체 탄생 - 코코피트, 펄라이트 등 고품질 수경재배 배지와 AI 기반 식물분석 서비스 제공') ?></textarea>
                                <small>검색엔진에서 표시되는 설명입니다 (150자 이내 권장)</small>
                            </div>

                            <div class="form-group">
                                <label for="site_keywords">사이트 키워드</label>
                                <input type="text" id="site_keywords" name="site_keywords"
                                       value="<?= htmlspecialchars($currentSettings['site_keywords'] ?? '스마트팜, 배지, 수경재배, 코코피트, 펄라이트, 식물분석, AI') ?>"
                                       class="form-control">
                                <small>콤마로 구분하여 입력하세요</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="section-header">
                            <span class="section-icon">📞</span>
                            <h3>연락처 정보</h3>
                        </div>
                        <div class="section-body">
                            <div class="form-group">
                                <label for="admin_email">관리자 이메일</label>
                                <input type="email" id="admin_email" name="admin_email"
                                       value="<?= htmlspecialchars($currentSettings['admin_email'] ?? 'contact@tansaeng.com') ?>"
                                       class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label for="contact_phone">연락처 전화번호</label>
                                <input type="tel" id="contact_phone" name="contact_phone"
                                       value="<?= htmlspecialchars($currentSettings['contact_phone'] ?? '1588-0000') ?>"
                                       class="form-control">
                            </div>

                            <div class="form-group">
                                <label for="contact_address">사업장 주소</label>
                                <input type="text" id="contact_address" name="contact_address"
                                       value="<?= htmlspecialchars($currentSettings['contact_address'] ?? '서울특별시 강남구 테헤란로 123') ?>"
                                       class="form-control">
                            </div>

                            <div class="form-group">
                                <label for="business_hours">영업시간</label>
                                <input type="text" id="business_hours" name="business_hours"
                                       value="<?= htmlspecialchars($currentSettings['business_hours'] ?? '평일 09:00 - 18:00') ?>"
                                       class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">저장</button>
                        <button type="reset" class="btn btn-secondary">취소</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>

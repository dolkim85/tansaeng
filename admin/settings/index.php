<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// 관리자 인증 확인
$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ||
          (isset($_SESSION['user_level']) && $_SESSION['user_level'] == 9);

if (!$isAdmin) {
    header('Location: /pages/auth/login.php?redirect=/admin/settings/');
    exit;
}

$success = '';
$error = '';

// 설정값 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = DatabaseConfig::getConnection();

        $settings = [
            'site_name' => trim($_POST['site_name'] ?? ''),
            'site_description' => trim($_POST['site_description'] ?? ''),
            'contact_email' => trim($_POST['contact_email'] ?? ''),
            'contact_phone' => trim($_POST['contact_phone'] ?? ''),
            'company_name' => trim($_POST['company_name'] ?? ''),
            'company_address' => trim($_POST['company_address'] ?? ''),
            'business_number' => trim($_POST['business_number'] ?? ''),
            'ceo_name' => trim($_POST['ceo_name'] ?? ''),
            'establishment_date' => trim($_POST['establishment_date'] ?? ''),
            'main_business' => trim($_POST['main_business'] ?? ''),
            'employee_count' => trim($_POST['employee_count'] ?? ''),
            'website_url' => trim($_POST['website_url'] ?? ''),
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0,
            'analytics_code' => trim($_POST['analytics_code'] ?? ''),
            'meta_keywords' => trim($_POST['meta_keywords'] ?? ''),
            'meta_description' => trim($_POST['meta_description'] ?? '')
        ];

        foreach ($settings as $key => $value) {
            $sql = "INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$key, $value]);
        }

        $success = '설정이 성공적으로 저장되었습니다.';
    } catch (Exception $e) {
        $error = '설정 저장 중 오류가 발생했습니다: ' . $e->getMessage();
    }
}

// 현재 설정값 가져오기
$currentSettings = [];
try {
    $pdo = DatabaseConfig::getConnection();
    $sql = "SELECT setting_key, setting_value FROM site_settings";
    $stmt = $pdo->query($sql);
    while ($row = $stmt->fetch()) {
        $currentSettings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    $error = '설정 불러오기 중 오류가 발생했습니다.';
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
                    <p>사이트의 기본 정보와 설정을 관리합니다</p>
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
                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label for="site_name">사이트명</label>
                                    <input type="text" id="site_name" name="site_name" class="form-control"
                                           value="<?= htmlspecialchars($currentSettings['site_name'] ?? '탄생') ?>"
                                           placeholder="예: 탄생 스마트팜" required>
                                </div>
                                <div class="form-group">
                                    <label for="website_url">웹사이트 URL</label>
                                    <input type="url" id="website_url" name="website_url" class="form-control"
                                           value="<?= htmlspecialchars($currentSettings['website_url'] ?? '') ?>"
                                           placeholder="https://www.tangsaeng.com">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="site_description">사이트 설명</label>
                                <textarea id="site_description" name="site_description" class="form-control" rows="3"
                                          placeholder="스마트팜 배지 제조 전문회사"><?= htmlspecialchars($currentSettings['site_description'] ?? '스마트팜 배지 제조 전문회사') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="section-header">
                            <span class="section-icon">📞</span>
                            <h3>연락처 정보</h3>
                        </div>
                        <div class="section-body">
                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label for="contact_email">대표 이메일</label>
                                    <input type="email" id="contact_email" name="contact_email" class="form-control"
                                           value="<?= htmlspecialchars($currentSettings['contact_email'] ?? 'info@tansaeng.com') ?>"
                                           placeholder="info@tangsaeng.com">
                                </div>
                                <div class="form-group">
                                    <label for="contact_phone">대표 전화번호</label>
                                    <input type="tel" id="contact_phone" name="contact_phone" class="form-control"
                                           value="<?= htmlspecialchars($currentSettings['contact_phone'] ?? '02-0000-0000') ?>"
                                           placeholder="02-1234-5678">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="section-header">
                            <span class="section-icon">🏢</span>
                            <h3>회사 정보</h3>
                        </div>
                        <div class="section-body">
                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label for="company_name">회사명</label>
                                    <input type="text" id="company_name" name="company_name" class="form-control"
                                           value="<?= htmlspecialchars($currentSettings['company_name'] ?? '탄생') ?>"
                                           placeholder="주식회사 탄생">
                                </div>
                                <div class="form-group">
                                    <label for="ceo_name">대표자명</label>
                                    <input type="text" id="ceo_name" name="ceo_name" class="form-control"
                                           value="<?= htmlspecialchars($currentSettings['ceo_name'] ?? '') ?>"
                                           placeholder="홍길동">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="company_address">회사 주소</label>
                                <input type="text" id="company_address" name="company_address" class="form-control"
                                       value="<?= htmlspecialchars($currentSettings['company_address'] ?? '') ?>"
                                       placeholder="서울시 강남구 테헤란로 123">
                            </div>

                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label for="business_number">사업자등록번호</label>
                                    <input type="text" id="business_number" name="business_number" class="form-control"
                                           value="<?= htmlspecialchars($currentSettings['business_number'] ?? '') ?>"
                                           placeholder="123-45-67890">
                                </div>
                                <div class="form-group">
                                    <label for="establishment_date">설립일</label>
                                    <input type="date" id="establishment_date" name="establishment_date" class="form-control"
                                           value="<?= htmlspecialchars($currentSettings['establishment_date'] ?? '') ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="main_business">주요 사업분야</label>
                                <input type="text" id="main_business" name="main_business"
                                       value="<?= htmlspecialchars($currentSettings['main_business'] ?? '스마트팜 배지 제조') ?>">
                            </div>
                            <div class="form-group">
                                <label for="employee_count">직원 수</label>
                                <input type="number" id="employee_count" name="employee_count"
                                       value="<?= htmlspecialchars($currentSettings['employee_count'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>SEO 설정</h3>

                        <div class="form-group">
                            <label for="meta_description">메타 설명</label>
                            <textarea id="meta_description" name="meta_description" rows="3"><?= htmlspecialchars($currentSettings['meta_description'] ?? '고품질 수경재배 배지와 AI 식물분석 서비스를 제공하는 스마트팜 전문업체입니다.') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="meta_keywords">메타 키워드</label>
                            <input type="text" id="meta_keywords" name="meta_keywords"
                                   value="<?= htmlspecialchars($currentSettings['meta_keywords'] ?? '스마트팜, 배지, 수경재배, 코코피트, 펄라이트, 식물분석, AI') ?>">
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>고급 설정</h3>

                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="maintenance_mode" name="maintenance_mode"
                                       <?= !empty($currentSettings['maintenance_mode']) ? 'checked' : '' ?>>
                                <label for="maintenance_mode">유지보수 모드</label>
                                <small>활성화 시 관리자만 사이트에 접근할 수 있습니다.</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="analytics_code">Google Analytics 코드</label>
                            <textarea id="analytics_code" name="analytics_code" rows="3" placeholder="GA 추적 코드를 입력하세요"><?= htmlspecialchars($currentSettings['analytics_code'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">설정 저장</button>
                        <button type="reset" class="btn btn-secondary">취소</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
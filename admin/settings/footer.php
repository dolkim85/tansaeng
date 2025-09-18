<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ||
          (isset($_SESSION['user_level']) && $_SESSION['user_level'] == 9);

if (!$isAdmin) {
    header('Location: /pages/auth/login.php?redirect=/admin/settings/footer.php');
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = DatabaseConfig::getConnection();

        $settings = [
            'footer_company_desc' => trim($_POST['footer_company_desc'] ?? ''),
            'footer_address' => trim($_POST['footer_address'] ?? ''),
            'footer_phone' => trim($_POST['footer_phone'] ?? ''),
            'footer_fax' => trim($_POST['footer_fax'] ?? ''),
            'footer_email' => trim($_POST['footer_email'] ?? ''),
            'footer_business_hours_weekday' => trim($_POST['footer_business_hours_weekday'] ?? ''),
            'footer_business_hours_saturday' => trim($_POST['footer_business_hours_saturday'] ?? ''),
            'footer_business_hours_holiday' => trim($_POST['footer_business_hours_holiday'] ?? ''),
            'footer_copyright' => trim($_POST['footer_copyright'] ?? ''),
            'footer_social_facebook' => trim($_POST['footer_social_facebook'] ?? ''),
            'footer_social_instagram' => trim($_POST['footer_social_instagram'] ?? ''),
            'footer_social_youtube' => trim($_POST['footer_social_youtube'] ?? ''),
            'footer_social_blog' => trim($_POST['footer_social_blog'] ?? ''),
            'footer_menu_products' => json_encode(array_filter(array_map('trim', explode("\n", $_POST['footer_menu_products'] ?? '')))),
            'footer_menu_services' => json_encode(array_filter(array_map('trim', explode("\n", $_POST['footer_menu_services'] ?? '')))),
            'footer_menu_company' => json_encode(array_filter(array_map('trim', explode("\n", $_POST['footer_menu_company'] ?? '')))),
            'footer_menu_legal' => json_encode(array_filter(array_map('trim', explode("\n", $_POST['footer_menu_legal'] ?? ''))))
        ];

        foreach ($settings as $key => $value) {
            $sql = "INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$key, $value]);
        }

        $success = '푸터 설정이 성공적으로 저장되었습니다.';
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

// JSON 배열을 텍스트로 변환하는 함수
function jsonToText($jsonString) {
    if (empty($jsonString)) return '';
    $array = json_decode($jsonString, true);
    return is_array($array) ? implode("\n", $array) : '';
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>푸터 관리 - 탄생 관리자</title>
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
                    <h1>🔗 푸터 관리</h1>
                    <p>웹사이트 하단 푸터 영역의 내용을 관리합니다</p>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post" class="admin-form">
                    <div class="form-section">
                        <h3>회사 정보</h3>

                        <div class="form-group">
                            <label for="footer_company_desc">회사 설명</label>
                            <textarea id="footer_company_desc" name="footer_company_desc" class="form-control" rows="3"><?= htmlspecialchars($currentSettings['footer_company_desc'] ?? '스마트팜 배지 제조 전문회사로서 최고 품질의 제품과 혁신적인 AI 기술을 통해 미래 농업을 선도합니다.') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="footer_address">주소</label>
                            <input type="text" id="footer_address" name="footer_address"
                                   value="<?= htmlspecialchars($currentSettings['footer_address'] ?? '서울특별시 강남구 테헤란로 123') ?>">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="footer_phone">전화번호</label>
                                <input type="tel" id="footer_phone" name="footer_phone"
                                       value="<?= htmlspecialchars($currentSettings['footer_phone'] ?? '02-0000-0000') ?>">
                            </div>

                            <div class="form-group">
                                <label for="footer_fax">팩스</label>
                                <input type="tel" id="footer_fax" name="footer_fax"
                                       value="<?= htmlspecialchars($currentSettings['footer_fax'] ?? '02-0000-0001') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="footer_email">이메일</label>
                            <input type="email" id="footer_email" name="footer_email"
                                   value="<?= htmlspecialchars($currentSettings['footer_email'] ?? 'info@tansaeng.com') ?>">
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>운영 시간</h3>

                        <div class="form-group">
                            <label for="footer_business_hours_weekday">평일</label>
                            <input type="text" id="footer_business_hours_weekday" name="footer_business_hours_weekday"
                                   value="<?= htmlspecialchars($currentSettings['footer_business_hours_weekday'] ?? '평일: 09:00 - 18:00') ?>">
                        </div>

                        <div class="form-group">
                            <label for="footer_business_hours_saturday">토요일</label>
                            <input type="text" id="footer_business_hours_saturday" name="footer_business_hours_saturday"
                                   value="<?= htmlspecialchars($currentSettings['footer_business_hours_saturday'] ?? '토요일: 09:00 - 13:00') ?>">
                        </div>

                        <div class="form-group">
                            <label for="footer_business_hours_holiday">일요일/공휴일</label>
                            <input type="text" id="footer_business_hours_holiday" name="footer_business_hours_holiday"
                                   value="<?= htmlspecialchars($currentSettings['footer_business_hours_holiday'] ?? '일요일/공휴일: 휴무') ?>">
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>소셜 미디어</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="footer_social_facebook">Facebook</label>
                                <input type="url" id="footer_social_facebook" name="footer_social_facebook"
                                       value="<?= htmlspecialchars($currentSettings['footer_social_facebook'] ?? 'https://facebook.com/tansaeng') ?>">
                            </div>

                            <div class="form-group">
                                <label for="footer_social_instagram">Instagram</label>
                                <input type="url" id="footer_social_instagram" name="footer_social_instagram"
                                       value="<?= htmlspecialchars($currentSettings['footer_social_instagram'] ?? 'https://instagram.com/tansaeng') ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="footer_social_youtube">YouTube</label>
                                <input type="url" id="footer_social_youtube" name="footer_social_youtube"
                                       value="<?= htmlspecialchars($currentSettings['footer_social_youtube'] ?? 'https://youtube.com/@tansaeng') ?>">
                            </div>

                            <div class="form-group">
                                <label for="footer_social_blog">블로그</label>
                                <input type="url" id="footer_social_blog" name="footer_social_blog"
                                       value="<?= htmlspecialchars($currentSettings['footer_social_blog'] ?? 'https://blog.tansaeng.com') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>푸터 메뉴</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="footer_menu_products">제품 메뉴</label>
                                <textarea id="footer_menu_products" name="footer_menu_products" class="form-control" rows="6" placeholder="한 줄에 하나씩 메뉴명을 입력하세요"><?= htmlspecialchars(jsonToText($currentSettings['footer_menu_products'] ?? '') ?: "배지소개\n코코피트 배지\n펄라이트 배지\n양액\n농업용품") ?></textarea>
                                <small>각 메뉴 항목을 새 줄에 입력하세요</small>
                            </div>

                            <div class="form-group">
                                <label for="footer_menu_services">서비스 메뉴</label>
                                <textarea id="footer_menu_services" name="footer_menu_services" class="form-control" rows="6" placeholder="한 줄에 하나씩 메뉴명을 입력하세요"><?= htmlspecialchars(jsonToText($currentSettings['footer_menu_services'] ?? '') ?: "식물분석\n기술정보\nFAQ\n기술지원\n공지사항") ?></textarea>
                                <small>각 메뉴 항목을 새 줄에 입력하세요</small>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="footer_menu_company">회사 메뉴</label>
                                <textarea id="footer_menu_company" name="footer_menu_company" class="form-control" rows="4" placeholder="한 줄에 하나씩 메뉴명을 입력하세요"><?= htmlspecialchars(jsonToText($currentSettings['footer_menu_company'] ?? '') ?: "회사소개\n연혁\n팀소개") ?></textarea>
                                <small>각 메뉴 항목을 새 줄에 입력하세요</small>
                            </div>

                            <div class="form-group">
                                <label for="footer_menu_legal">법적 정보</label>
                                <textarea id="footer_menu_legal" name="footer_menu_legal" class="form-control" rows="4" placeholder="한 줄에 하나씩 메뉴명을 입력하세요"><?= htmlspecialchars(jsonToText($currentSettings['footer_menu_legal'] ?? '') ?: "개인정보처리방침\n이용약관\n사이트맵") ?></textarea>
                                <small>각 메뉴 항목을 새 줄에 입력하세요</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>저작권 정보</h3>

                        <div class="form-group">
                            <label for="footer_copyright">저작권 표시</label>
                            <input type="text" id="footer_copyright" name="footer_copyright"
                                   value="<?= htmlspecialchars($currentSettings['footer_copyright'] ?? '© 2024 탄생(Tansaeng). All rights reserved.') ?>">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">저장</button>
                        <button type="reset" class="btn btn-secondary">취소</button>
                        <a href="/" target="_blank" class="btn btn-outline">사이트 미리보기</a>
                    </div>
                </form>

                <div class="form-section">
                    <h3>푸터 설정 가이드</h3>
                    <div class="info-box">
                        <h4>푸터 관리 가이드:</h4>
                        <ul>
                            <li><strong>회사 설명:</strong> 간결하고 명확한 회사 소개 문구</li>
                            <li><strong>연락처 정보:</strong> 정확한 주소, 전화번호, 이메일</li>
                            <li><strong>운영 시간:</strong> 고객이 연락 가능한 시간 명시</li>
                            <li><strong>소셜 미디어:</strong> 활성화된 소셜 미디어 계정만 입력</li>
                            <li><strong>메뉴 구성:</strong> 각 카테고리별로 관련 링크 정리</li>
                            <li><strong>저작권:</strong> 연도와 회사명을 정확히 기입</li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ||
          (isset($_SESSION['user_level']) && $_SESSION['user_level'] == 9);

if (!$isAdmin) {
    header('Location: /pages/auth/login.php?redirect=/admin/support/');
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
            'support_emergency_phone' => trim($_POST['support_emergency_phone'] ?? ''),
            'support_kakao_id' => trim($_POST['support_kakao_id'] ?? ''),
            'support_telegram' => trim($_POST['support_telegram'] ?? ''),
            'support_address' => trim($_POST['support_address'] ?? ''),
            'support_faq_categories' => json_encode(array_filter(array_map('trim', explode("\n", $_POST['support_faq_categories'] ?? '')))),
            'support_response_time' => trim($_POST['support_response_time'] ?? ''),
            'support_warranty_info' => trim($_POST['support_warranty_info'] ?? ''),
            'support_return_policy' => trim($_POST['support_return_policy'] ?? ''),
            'support_shipping_info' => trim($_POST['support_shipping_info'] ?? ''),
            'support_technical_docs' => trim($_POST['support_technical_docs'] ?? ''),
            'support_training_info' => trim($_POST['support_training_info'] ?? ''),
            'support_maintenance_schedule' => trim($_POST['support_maintenance_schedule'] ?? '')
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
    <title>고객지원 관리 - 탄생 관리자</title>
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
                        <h1>고객지원 관리</h1>
                        <p>고객지원 채널과 정책을 관리합니다</p>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post" class="admin-form">
                    <div class="form-section">
                        <h3>연락처 정보</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="support_email">고객지원 이메일</label>
                                <input type="email" id="support_email" name="support_email"
                                       value="<?= htmlspecialchars($currentSettings['support_email'] ?? 'support@tansaeng.com') ?>">
                            </div>

                            <div class="form-group">
                                <label for="support_phone">고객지원 전화번호</label>
                                <input type="tel" id="support_phone" name="support_phone"
                                       value="<?= htmlspecialchars($currentSettings['support_phone'] ?? '02-1234-5678') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="support_emergency_phone">긴급 연락처</label>
                            <input type="tel" id="support_emergency_phone" name="support_emergency_phone"
                                   value="<?= htmlspecialchars($currentSettings['support_emergency_phone'] ?? '010-1234-5678') ?>">
                        </div>

                        <div class="form-group">
                            <label for="support_hours">운영 시간</label>
                            <textarea id="support_hours" name="support_hours" rows="3"><?= htmlspecialchars($currentSettings['support_hours'] ?? '평일: 09:00 - 18:00
토요일: 09:00 - 13:00
일요일/공휴일: 휴무') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="support_address">고객센터 주소</label>
                            <input type="text" id="support_address" name="support_address"
                                   value="<?= htmlspecialchars($currentSettings['support_address'] ?? '서울특별시 강남구 테헤란로 123 탄생빌딩 5층') ?>">
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>온라인 채널</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="support_kakao_id">카카오톡 채널 ID</label>
                                <input type="text" id="support_kakao_id" name="support_kakao_id"
                                       value="<?= htmlspecialchars($currentSettings['support_kakao_id'] ?? '@tansaeng') ?>"
                                       placeholder="@tansaeng">
                            </div>

                            <div class="form-group">
                                <label for="support_telegram">텔레그램</label>
                                <input type="text" id="support_telegram" name="support_telegram"
                                       value="<?= htmlspecialchars($currentSettings['support_telegram'] ?? '@tansaeng_support') ?>"
                                       placeholder="@tansaeng_support">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>서비스 정보</h3>

                        <div class="form-group">
                            <label for="support_response_time">응답 시간</label>
                            <input type="text" id="support_response_time" name="support_response_time"
                                   value="<?= htmlspecialchars($currentSettings['support_response_time'] ?? '이메일: 24시간 이내, 전화: 즉시, 카카오톡: 30분 이내') ?>">
                        </div>

                        <div class="form-group">
                            <label for="support_faq_categories">FAQ 카테고리</label>
                            <textarea id="support_faq_categories" name="support_faq_categories" rows="6" placeholder="한 줄에 하나씩 카테고리를 입력하세요"><?= htmlspecialchars(jsonToText($currentSettings['support_faq_categories'] ?? '') ?: "제품 문의\n주문/배송\n기술 지원\n환불/교환\n회원 관리\n기타") ?></textarea>
                            <small>각 카테고리를 새 줄에 입력하세요</small>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>정책 및 약관</h3>

                        <div class="form-group">
                            <label for="support_warranty_info">품질보증 정책</label>
                            <textarea id="support_warranty_info" name="support_warranty_info" rows="4"><?= htmlspecialchars($currentSettings['support_warranty_info'] ?? '• 제품 하자 시 무상 교환/환불
• 품질보증 기간: 제조일로부터 1년
• 고객 과실로 인한 손상은 보증 대상 제외
• 보증서 지참 시 A/S 가능') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="support_return_policy">반품/교환 정책</label>
                            <textarea id="support_return_policy" name="support_return_policy" rows="4"><?= htmlspecialchars($currentSettings['support_return_policy'] ?? '• 제품 수령 후 7일 이내 반품 가능
• 미개봉 제품에 한해 교환 가능
• 반품 배송비는 고객 부담 (제품 하자 시 회사 부담)
• 사용한 제품은 반품 불가') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="support_shipping_info">배송 정보</label>
                            <textarea id="support_shipping_info" name="support_shipping_info" rows="4"><?= htmlspecialchars($currentSettings['support_shipping_info'] ?? '• 전국 택배 배송 (제주/도서산간 추가비용)
• 평일 오후 2시 이전 주문 시 당일 발송
• 배송기간: 1-2일 (도서산간 2-3일)
• 무료배송: 50,000원 이상 주문 시') ?></textarea>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>기술 지원</h3>

                        <div class="form-group">
                            <label for="support_technical_docs">기술 문서 링크</label>
                            <textarea id="support_technical_docs" name="support_technical_docs" rows="3"><?= htmlspecialchars($currentSettings['support_technical_docs'] ?? '/docs/manual.pdf
/docs/setup-guide.pdf
/docs/troubleshooting.pdf') ?></textarea>
                            <small>각 문서 링크를 새 줄에 입력하세요</small>
                        </div>

                        <div class="form-group">
                            <label for="support_training_info">교육/훈련 정보</label>
                            <textarea id="support_training_info" name="support_training_info" rows="3"><?= htmlspecialchars($currentSettings['support_training_info'] ?? '• 매월 첫째 주 화요일: 제품 교육 세미나
• 온라인 교육 과정 상시 운영
• 고객사 방문 교육 가능 (별도 문의)') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="support_maintenance_schedule">정기 점검 안내</label>
                            <textarea id="support_maintenance_schedule" name="support_maintenance_schedule" rows="2"><?= htmlspecialchars($currentSettings['support_maintenance_schedule'] ?? '시스템 점검: 매월 둘째 주 일요일 02:00-06:00
서버 업데이트: 분기별 토요일 20:00-24:00') ?></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">저장</button>
                        <button type="reset" class="btn btn-secondary">취소</button>
                        <a href="/pages/support/contact.php" target="_blank" class="btn btn-outline">고객지원 페이지 미리보기</a>
                    </div>
                </form>

                <div class="form-section">
                    <h3>고객지원 관리 가이드</h3>
                    <div class="info-box">
                        <h4>효과적인 고객지원 운영:</h4>
                        <ul>
                            <li><strong>신속한 응답:</strong> 고객 문의에 빠른 응답으로 만족도 향상</li>
                            <li><strong>다양한 채널:</strong> 이메일, 전화, 카카오톡 등 다양한 연락 방법 제공</li>
                            <li><strong>명확한 정책:</strong> 반품/교환 정책을 명확히 안내</li>
                            <li><strong>자주 묻는 질문:</strong> FAQ를 통해 고객의 궁금증 해결</li>
                            <li><strong>기술 지원:</strong> 제품 사용법과 문제 해결 방법 제공</li>
                            <li><strong>정기 업데이트:</strong> 연락처와 정책 정보를 최신으로 유지</li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
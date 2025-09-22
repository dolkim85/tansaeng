<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ||
          (isset($_SESSION['user_level']) && $_SESSION['user_level'] == 9);

if (!$isAdmin) {
    header('Location: /pages/auth/login.php?redirect=/admin/settings/company.php');
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = DatabaseConfig::getConnection();

        $settings = [
            'company_intro_title' => trim($_POST['company_intro_title'] ?? ''),
            'company_intro_subtitle' => trim($_POST['company_intro_subtitle'] ?? ''),
            'company_intro_content' => trim($_POST['company_intro_content'] ?? ''),
            'company_vision' => trim($_POST['company_vision'] ?? ''),
            'company_mission' => trim($_POST['company_mission'] ?? ''),
            'company_values' => trim($_POST['company_values'] ?? ''),
            'company_history' => trim($_POST['company_history'] ?? ''),
            'company_achievements' => trim($_POST['company_achievements'] ?? ''),
            'company_certifications' => trim($_POST['company_certifications'] ?? ''),
            'company_facilities' => trim($_POST['company_facilities'] ?? ''),
            'company_technology' => trim($_POST['company_technology'] ?? ''),
            'company_partners' => trim($_POST['company_partners'] ?? ''),
            'company_awards' => trim($_POST['company_awards'] ?? ''),
            'company_research' => trim($_POST['company_research'] ?? ''),
            'company_future_plans' => trim($_POST['company_future_plans'] ?? ''),
            // 연락처 정보도 회사소개에서 관리
            'company_address' => trim($_POST['company_address'] ?? ''),
            'contact_phone' => trim($_POST['contact_phone'] ?? ''),
            'contact_email' => trim($_POST['contact_email'] ?? ''),
            'business_hours' => trim($_POST['business_hours'] ?? '')
        ];

        foreach ($settings as $key => $value) {
            $sql = "INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$key, $value]);
        }

        $success = '회사 정보가 성공적으로 저장되었습니다.';
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
    <title>회사 소개 관리 - 탄생 관리자</title>
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
                    <h1>🏢 회사 소개 관리</h1>
                    <p>회사 소개 페이지에 표시될 내용을 관리합니다</p>
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
                            <span class="section-icon">🌟</span>
                            <h3>회사 소개 메인</h3>
                        </div>
                        <div class="section-body">
                            <div class="form-group">
                                <label for="company_intro_title">메인 제목</label>
                                <input type="text" id="company_intro_title" name="company_intro_title" class="form-control"
                                       value="<?= htmlspecialchars($currentSettings['company_intro_title'] ?? '스마트팜의 미래를 여는 탄생') ?>"
                                       placeholder="예: 스마트팜의 미래를 여는 탄생">
                            </div>

                            <div class="form-group">
                                <label for="company_intro_subtitle">부제목</label>
                                <input type="text" id="company_intro_subtitle" name="company_intro_subtitle" class="form-control"
                                       value="<?= htmlspecialchars($currentSettings['company_intro_subtitle'] ?? '혁신적인 배지 기술로 지속가능한 농업을 실현합니다') ?>"
                                       placeholder="예: 혁신적인 배지 기술로 지속가능한 농업을 실현합니다">

                        <div class="form-group">
                            <label for="company_intro_content">회사 소개 내용</label>
                            <textarea id="company_intro_content" name="company_intro_content" class="form-control large" rows="8"><?= htmlspecialchars($currentSettings['company_intro_content'] ?? '탄생은 스마트팜 분야의 선도기업으로, 최고 품질의 수경재배용 배지를 제조하고 있습니다. 우리는 지속가능한 농업의 미래를 만들어가며, 혁신적인 기술과 최고의 품질로 고객의 성공을 지원합니다.') ?></textarea>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>기업 철학</h3>

                        <div class="form-group">
                            <label for="company_vision">비전 (Vision)</label>
                            <textarea id="company_vision" name="company_vision" class="form-control" rows="4"><?= htmlspecialchars($currentSettings['company_vision'] ?? '스마트팜 기술의 글로벌 리더가 되어 지속가능한 농업 생태계를 구축하고, 인류의 식량 안보에 기여한다.') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="company_mission">미션 (Mission)</label>
                            <textarea id="company_mission" name="company_mission" class="form-control" rows="4"><?= htmlspecialchars($currentSettings['company_mission'] ?? '혁신적인 배지 기술과 AI 기반 식물분석 서비스를 통해 농업의 효율성을 극대화하고, 친환경적이며 지속가능한 농업 솔루션을 제공한다.') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="company_values">핵심 가치 (Values)</label>
                            <textarea id="company_values" name="company_values" rows="6"><?= htmlspecialchars($currentSettings['company_values'] ?? '혁신: 끊임없는 연구개발을 통한 기술 혁신
품질: 최고 품질의 제품과 서비스 제공
지속가능성: 환경을 생각하는 친환경 솔루션
신뢰: 고객과의 약속을 지키는 신뢰할 수 있는 파트너
성장: 고객과 함께 성장하는 상생의 관계') ?></textarea>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>회사 연혁</h3>

                        <div class="form-group">
                            <label for="company_history">주요 연혁</label>
                            <textarea id="company_history" name="company_history" rows="8"><?= htmlspecialchars($currentSettings['company_history'] ?? '2020년: 회사 설립, 코코피트 배지 생산 시작
2021년: 펄라이트 배지 개발 완료, 특허 출원
2022년: AI 기반 식물분석 시스템 도입
2023년: 수출 시작, 해외시장 진출
2024년: 연구개발센터 설립, 신제품 라인 확장
2025년: 스마트팜 통합 솔루션 출시 예정') ?></textarea>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>주요 성과</h3>

                        <div class="form-group">
                            <label for="company_achievements">주요 성과</label>
                            <textarea id="company_achievements" name="company_achievements" rows="6"><?= htmlspecialchars($currentSettings['company_achievements'] ?? '• 연간 배지 생산량 10,000톤 달성
• 국내 스마트팜 시장 점유율 25% 확보
• 수출액 100만 달러 돌파
• 고객 만족도 98% 달성
• 품질인증 ISO 9001 획득') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="company_certifications">보유 인증</label>
                            <textarea id="company_certifications" name="company_certifications" rows="4"><?= htmlspecialchars($currentSettings['company_certifications'] ?? 'ISO 9001 품질경영시스템
친환경 인증
농림축산식품부 우수농자재 인증
수출농업법인 등록') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="company_awards">수상 내역</label>
                            <textarea id="company_awards" name="company_awards" rows="4"><?= htmlspecialchars($currentSettings['company_awards'] ?? '2023년 우수 스마트팜 기업 대상
2024년 혁신기술 개발 우수상
중소벤처기업부 장관상 수상') ?></textarea>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>기술 및 시설</h3>

                        <div class="form-group">
                            <label for="company_facilities">주요 시설</label>
                            <textarea id="company_facilities" name="company_facilities" rows="4"><?= htmlspecialchars($currentSettings['company_facilities'] ?? '본사 및 생산시설: 경기도 화성시 (부지 5,000㎡)
연구개발센터: 서울시 강남구
품질관리실험실: 최신 분석장비 보유
물류센터: 전국 당일배송 시스템') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="company_technology">핵심 기술</label>
                            <textarea id="company_technology" name="company_technology" rows="4"><?= htmlspecialchars($currentSettings['company_technology'] ?? 'AI 기반 식물 생장 분석 시스템
최적 배지 배합 기술
자동화 생산 라인
품질관리 IoT 시스템') ?></textarea>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>협력 및 미래 계획</h3>

                        <div class="form-group">
                            <label for="company_partners">주요 파트너</label>
                            <textarea id="company_partners" name="company_partners" rows="4"><?= htmlspecialchars($currentSettings['company_partners'] ?? '농업기술실용화재단
한국농업기술진흥원
주요 스마트팜 운영업체
해외 농업 유통업체') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="company_research">연구개발 활동</label>
                            <textarea id="company_research" name="company_research" rows="4"><?= htmlspecialchars($currentSettings['company_research'] ?? '신소재 배지 개발 연구
식물 최적 생장환경 연구
IoT 기반 스마트팜 솔루션 개발
AI 식물질병 진단 시스템 연구') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="company_future_plans">미래 계획</label>
                            <textarea id="company_future_plans" name="company_future_plans" rows="4"><?= htmlspecialchars($currentSettings['company_future_plans'] ?? '2025년: 동남아시아 시장 진출
2026년: 스마트팜 통합 플랫폼 출시
2027년: 연구개발센터 확장
2030년: 글로벌 배지 시장 톱5 진입') ?></textarea>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>📞 연락처 정보</h3>

                        <div class="form-group">
                            <label for="company_address">회사 주소</label>
                            <input type="text" id="company_address" name="company_address"
                                   value="<?= htmlspecialchars($currentSettings['company_address'] ?? '') ?>"
                                   placeholder="회사 주소를 입력하세요">
                        </div>

                        <div class="form-group">
                            <label for="contact_phone">연락처 전화번호</label>
                            <input type="tel" id="contact_phone" name="contact_phone"
                                   value="<?= htmlspecialchars($currentSettings['contact_phone'] ?? '') ?>"
                                   placeholder="010-0000-0000">
                        </div>

                        <div class="form-group">
                            <label for="contact_email">연락처 이메일</label>
                            <input type="email" id="contact_email" name="contact_email"
                                   value="<?= htmlspecialchars($currentSettings['contact_email'] ?? '') ?>"
                                   placeholder="contact@company.com">
                        </div>

                        <div class="form-group">
                            <label for="business_hours">영업시간</label>
                            <input type="text" id="business_hours" name="business_hours"
                                   value="<?= htmlspecialchars($currentSettings['business_hours'] ?? '') ?>"
                                   placeholder="평일 09:00 - 18:00">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">저장</button>
                        <button type="reset" class="btn btn-secondary">취소</button>
                        <a href="/pages/company/about.php" target="_blank" class="btn btn-outline">미리보기</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
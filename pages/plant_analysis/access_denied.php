<?php
// 데이터베이스 연결을 선택적으로 처리
$currentUser = null;
try {
    require_once __DIR__ . '/../../classes/Auth.php';
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();
} catch (Exception $e) {
    // 데이터베이스 연결 실패시 계속 진행
    $currentUser = null;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>접근 권한 없음 - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/analysis.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main style="display: flex; justify-content: center; align-items: flex-start; min-height: calc(100vh - 200px); padding: 2rem 1rem;">
        <div class="access-denied-content" style="max-width: 900px; width: 100%; text-align: center;">
            <div class="access-denied-icon">🔒</div>
            <h1>식물분석 서비스 접근 권한이 없습니다</h1>
            
            <?php if ($currentUser): ?>
                <div class="access-denied-info">
                    <p><strong><?= htmlspecialchars($currentUser['name']) ?></strong>님은 현재 식물분석 서비스 이용 권한이 없습니다.</p>
                    <p>식물분석 서비스는 관리자의 승인이 필요한 특별 서비스입니다.</p>
                </div>
                
                <div class="access-request-section">
                    <h3>권한 신청 방법</h3>
                    <div class="request-steps">
                        <div class="step">
                            <div class="step-number">1</div>
                            <div class="step-content">
                                <h4>문의하기</h4>
                                <p>고객지원 > 문의하기를 통해 식물분석 권한 신청서를 작성해주세요</p>
                            </div>
                        </div>
                        <div class="step">
                            <div class="step-number">2</div>
                            <div class="step-content">
                                <h4>승인 대기</h4>
                                <p>관리자가 신청서를 검토한 후 승인 여부를 결정합니다</p>
                            </div>
                        </div>
                        <div class="step">
                            <div class="step-number">3</div>
                            <div class="step-content">
                                <h4>서비스 이용</h4>
                                <p>승인 완료 후 식물분석 서비스를 자유롭게 이용하실 수 있습니다</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="access-actions">
                    <a href="/pages/support/contact.php" class="btn btn-primary btn-lg">권한 신청하기</a>
                    <a href="/pages/support/faq.php" class="btn btn-outline btn-lg">FAQ 보기</a>
                </div>
                
            <?php else: ?>
                <div class="access-denied-info">
                    <p>식물분석 서비스를 이용하려면 먼저 로그인이 필요합니다.</p>
                    <p>로그인 후 관리자의 승인을 통해 서비스를 이용하실 수 있습니다.</p>
                </div>
                
                <div class="access-actions">
                    <a href="/pages/auth/login.php" class="btn btn-primary btn-lg">로그인</a>
                    <a href="/pages/auth/register.php" class="btn btn-outline btn-lg">회원가입</a>
                </div>
            <?php endif; ?>

            <div class="service-features">
                <h3>식물분석 서비스 기능</h3>
                <div class="features-grid">
                    <div class="feature-item">
                        <div class="feature-icon">📷</div>
                        <h4>실시간 촬영</h4>
                        <p>라즈베리파이 카메라를 통한 원격 식물 촬영</p>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">🤖</div>
                        <h4>AI 분석</h4>
                        <p>인공지능 기반 식물 건강상태 및 병충해 진단</p>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">📊</div>
                        <h4>환경 모니터링</h4>
                        <p>온도, 습도, pH, EC 등 실시간 환경 데이터 분석</p>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">📈</div>
                        <h4>데이터 내보내기</h4>
                        <p>분석 결과를 Excel, PDF 형태로 다운로드</p>
                    </div>
                </div>
            </div>

            <div class="contact-info">
                <h3>문의사항</h3>
                <p>식물분석 서비스에 대한 문의사항이 있으시면 언제든지 연락주세요.</p>
                <div class="contact-methods">
                    <div class="contact-method">
                        <span class="contact-icon">📞</span>
                        <span>02-0000-0000</span>
                    </div>
                    <div class="contact-method">
                        <span class="contact-icon">✉️</span>
                        <span>support@tangsaeng.com</span>
                    </div>
                    <div class="contact-method">
                        <span class="contact-icon">🕒</span>
                        <span>평일 09:00-18:00</span>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>
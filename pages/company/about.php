<?php
// 데이터베이스 연결을 선택적으로 처리
$currentUser = null;
$siteSettings = [];

try {
    require_once __DIR__ . '/../../classes/Auth.php';
    require_once __DIR__ . '/../../config/database.php';

    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();

    // Load site settings
    $pdo = DatabaseConfig::getConnection();
    $sql = "SELECT setting_key, setting_value FROM site_settings";
    $stmt = $pdo->query($sql);
    while ($row = $stmt->fetch()) {
        $siteSettings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    // 데이터베이스 연결 실패시 계속 진행
    error_log("Database connection failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>회사소개 - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main class="company-main">
        <div class="container">
            <div class="page-header">
                <h1>회사소개</h1>
                <p>스마트팜의 미래를 선도하는 탄생을 소개합니다</p>
            </div>

            <!-- Company Overview -->
            <section class="company-overview">
                <div class="overview-content">
                    <div class="overview-text">
                        <h2><?= htmlspecialchars($siteSettings['company_intro_title'] ?? '탄생 (Tangsaeng)') ?></h2>
                        <h3><?= htmlspecialchars($siteSettings['company_intro_subtitle'] ?? '스마트팜 배지 제조 전문회사') ?></h3>
                        <div class="intro-content">
                            <?= nl2br(htmlspecialchars($siteSettings['company_intro_content'] ?? '탄생은 수경재배용 배지 제조 분야의 선두주자로서, 최고 품질의 친환경 배지 제품을 생산하고 있습니다. 우리는 전통적인 농업 방식과 최첨단 기술을 결합하여 지속 가능한 농업 솔루션을 제공합니다.')) ?>
                        </div>
                    </div>
                    <div class="overview-image">
                        <img src="/assets/images/company/about-hero.jpg" alt="탄생 회사 전경" loading="lazy">
                    </div>
                </div>
            </section>

            <!-- Mission & Vision -->
            <section class="mission-vision">
                <div class="mission-vision-grid">
                    <div class="mission">
                        <div class="icon">🎯</div>
                        <h3>미션 (Mission)</h3>
                        <p>최고 품질의 배지와 혁신적인 기술을 통해 지속 가능한 농업 생태계를 구축하고, 전 세계 식량 안보에 기여합니다.</p>
                    </div>
                    <div class="vision">
                        <div class="icon">🚀</div>
                        <h3>비전 (Vision)</h3>
                        <p>스마트팜 기술의 글로벌 리더로서 농업의 미래를 선도하며, 모든 사람이 건강한 농산물을 접할 수 있는 세상을 만듭니다.</p>
                    </div>
                </div>
            </section>

            <!-- Core Values -->
            <section class="core-values">
                <h2>핵심 가치</h2>
                <div class="values-grid">
                    <div class="value-item">
                        <div class="value-icon">🌱</div>
                        <h3>지속가능성</h3>
                        <p>환경을 생각하는 친환경 제품 개발과 지속 가능한 농업 방식을 추구합니다.</p>
                    </div>
                    <div class="value-item">
                        <div class="value-icon">🔬</div>
                        <h3>혁신</h3>
                        <p>끊임없는 연구개발을 통해 농업 기술의 혁신을 이끌어갑니다.</p>
                    </div>
                    <div class="value-item">
                        <div class="value-icon">🤝</div>
                        <h3>신뢰</h3>
                        <p>고객과의 신뢰 관계를 바탕으로 최고의 품질과 서비스를 제공합니다.</p>
                    </div>
                    <div class="value-item">
                        <div class="value-icon">⚡</div>
                        <h3>효율성</h3>
                        <p>최적화된 솔루션으로 농업 생산성 향상에 기여합니다.</p>
                    </div>
                </div>
            </section>

            <!-- Company Stats -->
            <section class="company-stats">
                <h2>탄생의 성과</h2>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-number">10+</div>
                        <div class="stat-label">년간 업계 경험</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">1000+</div>
                        <div class="stat-label">만족한 고객</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">50+</div>
                        <div class="stat-label">제품 라인업</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">99%</div>
                        <div class="stat-label">고객 만족도</div>
                    </div>
                </div>
            </section>

            <!-- Technology -->
            <section class="technology-section">
                <h2>우리의 기술</h2>
                <div class="tech-grid">
                    <div class="tech-item">
                        <h3>🤖 AI 식물분석</h3>
                        <p>딥러닝 기반의 식물 건강상태 분석으로 정확한 진단과 처방을 제공합니다.</p>
                    </div>
                    <div class="tech-item">
                        <h3>📸 실시간 모니터링</h3>
                        <p>라즈베리파이와 고해상도 카메라를 통한 24시간 식물 관찰 시스템입니다.</p>
                    </div>
                    <div class="tech-item">
                        <h3>📊 데이터 분석</h3>
                        <p>온도, 습도, pH, EC 등 환경 데이터를 실시간으로 수집하고 분석합니다.</p>
                    </div>
                    <div class="tech-item">
                        <h3>🌡️ IoT 센서</h3>
                        <p>다양한 IoT 센서를 통해 최적의 성장 환경을 자동으로 조절합니다.</p>
                    </div>
                </div>
            </section>

            <!-- Contact Info -->
            <section class="contact-info">
                <h2>오시는 길</h2>
                <div class="contact-grid">
                    <div class="contact-details">
                        <h3>회사 정보</h3>
                        <div class="detail-item">
                            <strong>주소:</strong> 서울특별시 강남구 테헤란로 123
                        </div>
                        <div class="detail-item">
                            <strong>전화:</strong> 02-0000-0000
                        </div>
                        <div class="detail-item">
                            <strong>팩스:</strong> 02-0000-0001
                        </div>
                        <div class="detail-item">
                            <strong>이메일:</strong> info@tangsaeng.com
                        </div>
                        <div class="detail-item">
                            <strong>영업시간:</strong> 평일 09:00 - 18:00 (주말 및 공휴일 휴무)
                        </div>
                    </div>
                    <div class="map-placeholder">
                        <div style="width: 100%; height: 300px; background: #f0f0f0; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #666;">
                            <p>지도 영역<br><small>실제 구현시 Google Maps 등으로 교체</small></p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <?php include '../../includes/footer.php'; ?>

    <script src="/assets/js/main.js"></script>
</body>
</html>

<style>
.company-main {
    padding: 2rem 0;
}

.page-header {
    text-align: center;
    margin-bottom: 3rem;
    padding: 3rem 0;
    background: linear-gradient(135deg, #E8F5E8 0%, #C8E6C9 100%);
    border-radius: 12px;
}

.page-header h1 {
    font-size: 2.5rem;
    color: #2E7D32;
    margin-bottom: 1rem;
}

.company-overview {
    margin-bottom: 4rem;
}

.overview-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
    align-items: center;
}

.overview-text h2 {
    color: #2E7D32;
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.overview-text h3 {
    color: #4CAF50;
    margin-bottom: 1.5rem;
}

.overview-text p {
    line-height: 1.7;
    margin-bottom: 1.5rem;
    color: #333;
}

.mission-vision {
    margin-bottom: 4rem;
}

.mission-vision-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
}

.mission, .vision {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    text-align: center;
}

.mission .icon, .vision .icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.mission h3, .vision h3 {
    color: #2E7D32;
    margin-bottom: 1rem;
}

.core-values {
    margin-bottom: 4rem;
}

.core-values h2 {
    text-align: center;
    color: #2E7D32;
    margin-bottom: 2rem;
}

.values-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
}

.value-item {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    text-align: center;
}

.value-icon {
    font-size: 2.5rem;
    margin-bottom: 1rem;
}

.value-item h3 {
    color: #2E7D32;
    margin-bottom: 1rem;
}

.company-stats {
    background: #f8f9fa;
    padding: 3rem;
    border-radius: 12px;
    margin-bottom: 4rem;
}

.company-stats h2 {
    text-align: center;
    color: #2E7D32;
    margin-bottom: 2rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 2rem;
}

.stat-item {
    text-align: center;
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 700;
    color: #4CAF50;
    margin-bottom: 0.5rem;
}

.stat-label {
    font-size: 1rem;
    color: #666;
}

.technology-section {
    margin-bottom: 4rem;
}

.technology-section h2 {
    text-align: center;
    color: #2E7D32;
    margin-bottom: 2rem;
}

.tech-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 2rem;
}

.tech-item {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.tech-item h3 {
    color: #2E7D32;
    margin-bottom: 1rem;
}

.contact-info h2 {
    text-align: center;
    color: #2E7D32;
    margin-bottom: 2rem;
}

.contact-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
}

.contact-details h3 {
    color: #2E7D32;
    margin-bottom: 1.5rem;
}

.detail-item {
    margin-bottom: 1rem;
    line-height: 1.6;
}

@media (max-width: 768px) {
    .company-main {
        padding: 1rem 0 !important;
    }

    .page-header {
        height: 100px !important;
        padding: 1.5rem 1rem !important;
        margin-bottom: 1.5rem !important;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .page-header h1 {
        font-size: 1.2rem !important;
        margin-bottom: 0.5rem !important;
    }

    .page-header p {
        font-size: 0.9rem !important;
    }

    .company-overview,
    .mission-vision,
    .core-values,
    .technology-section {
        margin-bottom: 2rem !important;
    }

    .company-stats {
        padding: 1.5rem !important;
        margin-bottom: 2rem !important;
    }

    .overview-content,
    .mission-vision-grid,
    .contact-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem !important;
    }

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem !important;
    }

    .values-grid,
    .tech-grid {
        gap: 1rem !important;
    }

    .mission, .vision,
    .value-item,
    .tech-item {
        padding: 1rem !important;
    }

    .overview-text h2 {
        font-size: 1.2rem !important;
    }

    .overview-text h3 {
        font-size: 1rem !important;
        margin-bottom: 1rem !important;
    }

    .intro-content {
        font-size: 0.9rem !important;
        line-height: 1.5 !important;
    }

    .mission .icon, .vision .icon {
        font-size: 2rem !important;
        margin-bottom: 0.5rem !important;
    }

    .mission h3, .vision h3,
    .core-values h2,
    .company-stats h2,
    .technology-section h2,
    .contact-info h2 {
        font-size: 1.1rem !important;
        margin-bottom: 1rem !important;
    }

    .value-icon {
        font-size: 2rem !important;
        margin-bottom: 0.5rem !important;
    }

    .value-item h3,
    .tech-item h3 {
        font-size: 1rem !important;
        margin-bottom: 0.5rem !important;
    }

    .value-item p,
    .tech-item p,
    .mission p,
    .vision p {
        font-size: 0.9rem !important;
        line-height: 1.4 !important;
    }

    .stat-number {
        font-size: 1.8rem !important;
        margin-bottom: 0.3rem !important;
    }

    .stat-label {
        font-size: 0.8rem !important;
    }

    .contact-details h3 {
        font-size: 1rem !important;
        margin-bottom: 1rem !important;
    }

    .detail-item {
        margin-bottom: 0.8rem !important;
        font-size: 0.9rem !important;
    }

    .map-placeholder div {
        height: 200px !important;
        font-size: 0.8rem !important;
    }
}
</style>
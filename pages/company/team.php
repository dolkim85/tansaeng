<?php
// 데이터베이스 연결을 선택적으로 처리
$currentUser = null;
try {
    require_once __DIR__ . '/../../classes/Auth.php';
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();
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
    <title>팀소개 - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main class="team-main">
        <div class="container">
            <div class="page-header">
                <h1>팀소개</h1>
                <p>탄생의 혁신을 이끌어가는 전문가들을 만나보세요</p>
            </div>

            <!-- Leadership Team -->
            <section class="leadership-section">
                <h2>경영진</h2>
                <div class="team-grid">
                    <div class="team-member">
                        <div class="member-photo">
                            <img src="/assets/images/team/ceo.jpg" alt="대표이사" loading="lazy">
                        </div>
                        <div class="member-info">
                            <h3>김영수</h3>
                            <p class="member-position">대표이사 / CEO</p>
                            <p class="member-description">
                                농업 분야 20년 경력의 전문가로, 스마트팜 기술의 상용화를 선도하고 있습니다. 
                                서울대학교 농업생명과학대학을 졸업하고, 다양한 농업 관련 사업을 성공적으로 운영한 경험을 바탕으로 
                                탄생을 설립하여 혁신적인 농업 솔루션을 제공하고 있습니다.
                            </p>
                            <div class="member-contact">
                                <span>📧 ceo@tangsaeng.com</span>
                            </div>
                        </div>
                    </div>

                    <div class="team-member">
                        <div class="member-photo">
                            <img src="/assets/images/team/cto.jpg" alt="기술이사" loading="lazy">
                        </div>
                        <div class="member-info">
                            <h3>박지영</h3>
                            <p class="member-position">기술이사 / CTO</p>
                            <p class="member-description">
                                AI 및 IoT 분야의 권위자로서 탄생의 모든 기술 혁신을 주도하고 있습니다. 
                                KAIST에서 컴퓨터공학 박사학위를 취득했으며, 삼성전자와 네이버에서 
                                10년간 AI 연구개발을 담당한 경력을 바탕으로 농업과 IT의 융합을 실현하고 있습니다.
                            </p>
                            <div class="member-contact">
                                <span>📧 cto@tangsaeng.com</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- R&D Team -->
            <section class="rd-section">
                <h2>연구개발팀</h2>
                <div class="team-grid">
                    <div class="team-member">
                        <div class="member-photo">
                            <img src="/assets/images/team/rd1.jpg" alt="연구개발 팀장" loading="lazy">
                        </div>
                        <div class="member-info">
                            <h3>이민호</h3>
                            <p class="member-position">연구개발팀장</p>
                            <p class="member-description">
                                농업생명공학 전문가로서 새로운 배지 소재 연구와 제품 개발을 담당하고 있습니다.
                                10년간의 연구 경력을 바탕으로 친환경적이고 효율적인 배지 제품을 개발하고 있습니다.
                            </p>
                        </div>
                    </div>

                    <div class="team-member">
                        <div class="member-photo">
                            <img src="/assets/images/team/rd2.jpg" alt="AI 개발자" loading="lazy">
                        </div>
                        <div class="member-info">
                            <h3>정수현</h3>
                            <p class="member-position">AI 개발팀장</p>
                            <p class="member-description">
                                식물 영상 분석 AI 모델 개발을 담당하며, 딥러닝 기반 식물 건강 진단 시스템을 
                                구축하고 있습니다. 컴퓨터 비전과 머신러닝 분야의 전문성을 활용하고 있습니다.
                            </p>
                        </div>
                    </div>

                    <div class="team-member">
                        <div class="member-photo">
                            <img src="/assets/images/team/rd3.jpg" alt="하드웨어 엔지니어" loading="lazy">
                        </div>
                        <div class="member-info">
                            <h3>최동훈</h3>
                            <p class="member-position">하드웨어 엔지니어</p>
                            <p class="member-description">
                                라즈베리파이와 각종 센서를 활용한 IoT 시스템 구축 전문가입니다.
                                실시간 모니터링 하드웨어 설계와 최적화를 담당하고 있습니다.
                            </p>
                        </div>
                    </div>

                    <div class="team-member">
                        <div class="member-photo">
                            <img src="/assets/images/team/rd4.jpg" alt="소프트웨어 개발자" loading="lazy">
                        </div>
                        <div class="member-info">
                            <h3>김소영</h3>
                            <p class="member-position">소프트웨어 개발자</p>
                            <p class="member-description">
                                웹 기반 모니터링 플랫폼과 모바일 앱 개발을 담당합니다.
                                사용자 친화적인 인터페이스 설계와 시스템 안정성 확보에 집중하고 있습니다.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Production Team -->
            <section class="production-section">
                <h2>생산팀</h2>
                <div class="team-grid">
                    <div class="team-member">
                        <div class="member-photo">
                            <img src="/assets/images/team/prod1.jpg" alt="생산팀장" loading="lazy">
                        </div>
                        <div class="member-info">
                            <h3>장현우</h3>
                            <p class="member-position">생산팀장</p>
                            <p class="member-description">
                                15년간의 제조업 경험을 바탕으로 효율적인 생산 공정 관리와 품질 향상을 이끌고 있습니다.
                                ISO 9001 품질경영시스템 구축과 운영을 담당하고 있습니다.
                            </p>
                        </div>
                    </div>

                    <div class="team-member">
                        <div class="member-photo">
                            <img src="/assets/images/team/prod2.jpg" alt="품질관리 담당자" loading="lazy">
                        </div>
                        <div class="member-info">
                            <h3>윤미래</h3>
                            <p class="member-position">품질관리 담당자</p>
                            <p class="member-description">
                                모든 제품의 품질 검사와 관리를 담당하며, 고객 만족을 위한 
                                엄격한 품질 기준을 유지하고 있습니다.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Sales & Marketing -->
            <section class="sales-section">
                <h2>영업 & 마케팅</h2>
                <div class="team-grid">
                    <div class="team-member">
                        <div class="member-photo">
                            <img src="/assets/images/team/sales1.jpg" alt="영업팀장" loading="lazy">
                        </div>
                        <div class="member-info">
                            <h3>오성민</h3>
                            <p class="member-position">영업팀장</p>
                            <p class="member-description">
                                전국 농장과의 파트너십 구축과 신규 고객 개발을 담당합니다.
                                농업 현장의 실제 니즈를 파악하여 맞춤형 솔루션을 제공하고 있습니다.
                            </p>
                        </div>
                    </div>

                    <div class="team-member">
                        <div class="member-photo">
                            <img src="/assets/images/team/marketing1.jpg" alt="마케팅 담당자" loading="lazy">
                        </div>
                        <div class="member-info">
                            <h3>한지수</h3>
                            <p class="member-position">마케팅 담당자</p>
                            <p class="member-description">
                                브랜드 전략 수립과 디지털 마케팅을 담당하며, 
                                탄생의 혁신적인 기술과 제품을 시장에 알리는 역할을 하고 있습니다.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Join Us -->
            <section class="join-section">
                <div class="join-content">
                    <h2>함께 성장할 인재를 찾습니다</h2>
                    <p>탄생과 함께 농업의 미래를 만들어갈 열정적인 인재를 모집하고 있습니다.</p>
                    
                    <div class="open-positions">
                        <h3>🔍 현재 채용 중인 포지션</h3>
                        <div class="positions-grid">
                            <div class="position-item">
                                <h4>AI 개발자 (경력 3년 이상)</h4>
                                <p>식물 영상 분석 AI 모델 개발</p>
                            </div>
                            <div class="position-item">
                                <h4>IoT 하드웨어 엔지니어</h4>
                                <p>센서 시스템 설계 및 최적화</p>
                            </div>
                            <div class="position-item">
                                <h4>농업 전문가</h4>
                                <p>작물별 맞춤 배지 연구개발</p>
                            </div>
                            <div class="position-item">
                                <h4>영업 담당자</h4>
                                <p>전국 농장 영업 및 고객 관리</p>
                            </div>
                        </div>
                    </div>

                    <div class="contact-hr">
                        <h3>📧 채용 문의</h3>
                        <p>이메일: hr@tangsaeng.com</p>
                        <p>전화: 02-0000-0000 (내선 123)</p>
                        <p>담당자: 인사팀 김하늘</p>
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
.team-main {
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

section {
    margin-bottom: 4rem;
}

section h2 {
    color: #2E7D32;
    font-size: 2rem;
    margin-bottom: 2rem;
    text-align: center;
}

.team-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 2rem;
}

.team-member {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    overflow: hidden;
    transition: transform 0.3s ease;
}

.team-member:hover {
    transform: translateY(-5px);
}

.member-photo {
    height: 250px;
    overflow: hidden;
    background: #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #666;
}

.member-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.member-info {
    padding: 2rem;
}

.member-info h3 {
    color: #2E7D32;
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

.member-position {
    color: #4CAF50;
    font-weight: 600;
    margin-bottom: 1rem;
    font-size: 1.1rem;
}

.member-description {
    color: #333;
    line-height: 1.6;
    margin-bottom: 1rem;
}

.member-contact span {
    color: #666;
    font-size: 0.9rem;
}

.join-section {
    background: #f8f9fa;
    padding: 3rem;
    border-radius: 12px;
    text-align: center;
}

.join-content h2 {
    color: #2E7D32;
    margin-bottom: 1rem;
}

.join-content > p {
    color: #666;
    font-size: 1.1rem;
    margin-bottom: 2rem;
}

.open-positions {
    margin-bottom: 3rem;
}

.open-positions h3 {
    color: #2E7D32;
    margin-bottom: 1.5rem;
}

.positions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.position-item {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    text-align: left;
}

.position-item h4 {
    color: #2E7D32;
    margin-bottom: 0.5rem;
}

.position-item p {
    color: #666;
    font-size: 0.9rem;
}

.contact-hr {
    background: white;
    padding: 2rem;
    border-radius: 8px;
    display: inline-block;
}

.contact-hr h3 {
    color: #2E7D32;
    margin-bottom: 1rem;
}

.contact-hr p {
    margin-bottom: 0.5rem;
    color: #333;
}

@media (max-width: 768px) {
    .team-grid {
        grid-template-columns: 1fr;
    }
    
    .positions-grid {
        grid-template-columns: 1fr;
    }
    
    .member-photo {
        height: 200px;
    }
}
</style>
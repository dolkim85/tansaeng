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
    <title>회사연혁 - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main class="history-main">
        <div class="container">
            <div class="page-header">
                <h1>회사연혁</h1>
                <p>탄생의 성장 과정과 주요 성과를 소개합니다</p>
            </div>

            <div class="timeline">
                <div class="timeline-item">
                    <div class="timeline-year">2024</div>
                    <div class="timeline-content">
                        <h3>AI 식물분석 시스템 도입</h3>
                        <p>라즈베리파이 기반 실시간 식물 모니터링 및 AI 분석 시스템 정식 출시</p>
                        <ul>
                            <li>딥러닝 기반 식물 건강상태 진단 기능 개발</li>
                            <li>실시간 환경 센서 데이터 수집 시스템 구축</li>
                            <li>웹 기반 원격 모니터링 플랫폼 오픈</li>
                        </ul>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-year">2023</div>
                    <div class="timeline-content">
                        <h3>스마트팜 기술 연구개발</h3>
                        <p>IoT와 인공지능을 접목한 차세대 농업 기술 연구 시작</p>
                        <ul>
                            <li>농업기술실용화재단과 기술개발 협약 체결</li>
                            <li>스마트팜 배지 자동화 시설 구축</li>
                            <li>첫 번째 AI 프로토타입 개발 완료</li>
                        </ul>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-year">2021-2022</div>
                    <div class="timeline-content">
                        <h3>사업 확장 및 품질 인증</h3>
                        <p>국내외 시장 진출과 품질 관리 시스템 강화</p>
                        <ul>
                            <li>ISO 9001 품질경영시스템 인증 획득</li>
                            <li>친환경 농자재 품질인증 취득</li>
                            <li>온라인 직판 시스템 구축</li>
                            <li>연 매출 50억 원 돌파</li>
                        </ul>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-year">2019-2020</div>
                    <div class="timeline-content">
                        <h3>제품 라인업 확장</h3>
                        <p>다양한 작물별 맞춤형 배지 제품 개발</p>
                        <ul>
                            <li>토마토 전용 배지 시리즈 출시</li>
                            <li>딸기, 오이, 파프리카용 특수 배지 개발</li>
                            <li>유기농 인증 배지 제품군 확대</li>
                            <li>전국 200개 농장에 공급 계약 체결</li>
                        </ul>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-year">2017-2018</div>
                    <div class="timeline-content">
                        <h3>기술 혁신과 자동화</h3>
                        <p>생산 공정 자동화 및 품질 관리 체계 확립</p>
                        <ul>
                            <li>자동화 생산라인 도입으로 생산능력 3배 증가</li>
                            <li>품질관리 연구소 설립</li>
                            <li>특허 출원 5건 완료</li>
                            <li>우수 농업자재 선정 (농림축산식품부)</li>
                        </ul>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-year">2015-2016</div>
                    <div class="timeline-content">
                        <h3>본격적인 사업 성장</h3>
                        <p>안정적인 매출 기반 구축 및 브랜드 확립</p>
                        <ul>
                            <li>전국 유통망 구축 완료</li>
                            <li>대형 스마트팜 업체와 정식 공급계약</li>
                            <li>연구개발팀 확대 (10명 → 25명)</li>
                            <li>매출 10억 원 달성</li>
                        </ul>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-year">2013-2014</div>
                    <div class="timeline-content">
                        <h3>법인 설립 및 사업 확장</h3>
                        <p>개인사업에서 법인으로 전환하며 본격적인 성장 시작</p>
                        <ul>
                            <li>㈜탄생 법인 설립</li>
                            <li>경기도 화성시로 본사 이전</li>
                            <li>생산시설 확장 (월 1,000톤 생산 가능)</li>
                            <li>전문 영업팀 구성</li>
                        </ul>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-year">2010-2012</div>
                    <div class="timeline-content">
                        <h3>초기 제품 개발 및 시장 진입</h3>
                        <p>수경재배용 배지 전문 제조 사업 시작</p>
                        <ul>
                            <li>코코피트, 펄라이트 기반 혼합 배지 개발</li>
                            <li>지역 농장 대상 테스트 공급 시작</li>
                            <li>첫 정식 제품 '탄생 프리미엄' 출시</li>
                            <li>특허청 실용신안 등록</li>
                        </ul>
                    </div>
                </div>

                <div class="timeline-item first">
                    <div class="timeline-year">2010</div>
                    <div class="timeline-content">
                        <h3>탄생 창업</h3>
                        <p>스마트팜 배지 제조업 창업으로 첫 발을 내딛다</p>
                        <ul>
                            <li>서울 강남구에서 개인사업 시작</li>
                            <li>수경재배 농장 운영 경험을 바탕으로 사업 구상</li>
                            <li>초기 투자금 1억원으로 소규모 생산시설 구축</li>
                            <li>농업 전문가 3명과 팀 구성</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="achievements-section">
                <h2>주요 성과 및 인증</h2>
                <div class="achievements-grid">
                    <div class="achievement-item">
                        <div class="achievement-icon">🏆</div>
                        <h3>농림축산식품부 장관상</h3>
                        <p>2023년 우수 농업기술 혁신상 수상</p>
                    </div>
                    <div class="achievement-item">
                        <div class="achievement-icon">📜</div>
                        <h3>ISO 9001 인증</h3>
                        <p>국제 품질경영시스템 인증 획득</p>
                    </div>
                    <div class="achievement-item">
                        <div class="achievement-icon">🌱</div>
                        <h3>친환경 인증</h3>
                        <p>친환경 농자재 품질인증 취득</p>
                    </div>
                    <div class="achievement-item">
                        <div class="achievement-icon">⚗️</div>
                        <h3>특허 등록</h3>
                        <p>배지 제조 기술 관련 10건 특허 보유</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include '../../includes/footer.php'; ?>

    <script src="/assets/js/main.js"></script>
</body>
</html>

<style>
.history-main {
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

.timeline {
    position: relative;
    max-width: 1000px;
    margin: 0 auto;
    padding: 2rem 0;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 50%;
    top: 0;
    bottom: 0;
    width: 3px;
    background: #4CAF50;
    transform: translateX(-50%);
}

.timeline-item {
    position: relative;
    margin-bottom: 3rem;
    display: flex;
    align-items: flex-start;
}

.timeline-item:nth-child(odd) {
    flex-direction: row;
}

.timeline-item:nth-child(even) {
    flex-direction: row-reverse;
}

.timeline-year {
    width: 120px;
    background: #2E7D32;
    color: white;
    text-align: center;
    padding: 1rem;
    border-radius: 8px;
    font-weight: 700;
    font-size: 1.2rem;
    position: relative;
    z-index: 2;
}

.timeline-item:nth-child(odd) .timeline-year {
    margin-right: 2rem;
}

.timeline-item:nth-child(even) .timeline-year {
    margin-left: 2rem;
}

.timeline-year::before {
    content: '';
    position: absolute;
    top: 50%;
    width: 20px;
    height: 20px;
    background: #4CAF50;
    border: 3px solid white;
    border-radius: 50%;
    transform: translateY(-50%);
}

.timeline-item:nth-child(odd) .timeline-year::before {
    right: -41px;
}

.timeline-item:nth-child(even) .timeline-year::before {
    left: -41px;
}

.timeline-content {
    flex: 1;
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.timeline-item:nth-child(odd) .timeline-content {
    margin-left: 2rem;
}

.timeline-item:nth-child(even) .timeline-content {
    margin-right: 2rem;
}

.timeline-content h3 {
    color: #2E7D32;
    margin-bottom: 1rem;
    font-size: 1.5rem;
}

.timeline-content p {
    color: #666;
    margin-bottom: 1rem;
    line-height: 1.6;
}

.timeline-content ul {
    list-style: none;
    padding: 0;
}

.timeline-content li {
    padding: 0.5rem 0;
    padding-left: 1.5rem;
    position: relative;
    color: #333;
}

.timeline-content li::before {
    content: '✓';
    position: absolute;
    left: 0;
    color: #4CAF50;
    font-weight: bold;
}

.achievements-section {
    margin-top: 4rem;
    padding: 3rem;
    background: #f8f9fa;
    border-radius: 12px;
}

.achievements-section h2 {
    text-align: center;
    color: #2E7D32;
    margin-bottom: 2rem;
}

.achievements-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
}

.achievement-item {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.achievement-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.achievement-item h3 {
    color: #2E7D32;
    margin-bottom: 1rem;
}

@media (max-width: 768px) {
    .timeline::before {
        left: 30px;
    }
    
    .timeline-item {
        flex-direction: column !important;
        padding-left: 60px;
    }
    
    .timeline-year {
        width: 80px;
        position: absolute;
        left: 0;
        top: 0;
        margin: 0 !important;
    }
    
    .timeline-year::before {
        left: 90px !important;
        right: auto !important;
    }
    
    .timeline-content {
        margin: 0 !important;
        margin-top: 1rem !important;
    }
}
</style>
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
    <title>기술정보 - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main class="tech-main">
        <div class="container">
            <div class="page-header">
                <h1>기술정보</h1>
                <p>탄생의 혁신적인 배지 제조 기술과 스마트팜 솔루션을 소개합니다</p>
            </div>

            <!-- Technology Overview -->
            <section class="tech-overview">
                <div class="overview-content">
                    <div class="overview-text">
                        <h2>🚀 혁신적인 배지 기술</h2>
                        <p>
                            탄생은 10년간의 연구개발을 통해 최적의 수경재배용 배지 기술을 개발했습니다. 
                            우리의 특허받은 혼합 기술은 식물의 뿌리 발달과 영양분 흡수를 극대화하여 
                            기존 토양재배 대비 30% 이상의 수확량 증대를 실현합니다.
                        </p>
                    </div>
                    <div class="tech-stats">
                        <div class="stat-item">
                            <div class="stat-number">30%</div>
                            <div class="stat-label">수확량 증대</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">50%</div>
                            <div class="stat-label">물 절약</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">40%</div>
                            <div class="stat-label">비료 절약</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">99.8%</div>
                            <div class="stat-label">무균화 달성</div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Core Technologies -->
            <section class="core-tech">
                <h2>핵심 기술</h2>
                <div class="tech-grid">
                    <div class="tech-card">
                        <div class="tech-icon">🧪</div>
                        <h3>멀티레이어 혼합 기술</h3>
                        <p>
                            코코피트, 펄라이트, 버미큘라이트를 최적 비율로 혼합하여 
                            뿌리 환경에 맞는 다층 구조를 구현합니다.
                        </p>
                        <div class="tech-details">
                            <h4>주요 특징:</h4>
                            <ul>
                                <li>우수한 보수력과 배수성의 균형</li>
                                <li>뿌리 호흡을 위한 최적 공극률 15-20%</li>
                                <li>pH 6.0-6.8 안정적 유지</li>
                                <li>EC값 0.3-0.8 mS/cm 조절</li>
                            </ul>
                        </div>
                    </div>

                    <div class="tech-card">
                        <div class="tech-icon">🌿</div>
                        <h3>친환경 처리 공법</h3>
                        <p>
                            100% 천연 원료를 사용하며, 화학 처리 없이 
                            고온 스팀 살균으로 무균화를 달성합니다.
                        </p>
                        <div class="tech-details">
                            <h4>처리 과정:</h4>
                            <ul>
                                <li>1차: 고온 스팀 살균 (121°C, 30분)</li>
                                <li>2차: 자연 건조 및 수분 조절</li>
                                <li>3차: 미생물 검사 및 품질 검증</li>
                                <li>4차: 밀폐 포장 및 보관</li>
                            </ul>
                        </div>
                    </div>

                    <div class="tech-card">
                        <div class="tech-icon">📊</div>
                        <h3>품질 분석 시스템</h3>
                        <p>
                            첨단 분석 장비를 통해 모든 배치의 물리적, 
                            화학적 특성을 실시간으로 모니터링합니다.
                        </p>
                        <div class="tech-details">
                            <h4>분석 항목:</h4>
                            <ul>
                                <li>입자 크기 분포 분석</li>
                                <li>보수력 및 배수성 테스트</li>
                                <li>pH, EC, 영양분 함량 측정</li>
                                <li>미생물 및 중금속 검사</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <!-- AI & IoT Technology -->
            <section class="ai-iot-tech">
                <h2>AI & IoT 통합 기술</h2>
                <div class="ai-iot-content">
                    <div class="ai-section">
                        <h3>🤖 AI 식물 분석 시스템</h3>
                        <div class="ai-features">
                            <div class="feature-item">
                                <h4>실시간 영상 분석</h4>
                                <p>딥러닝 기반 컴퓨터 비전으로 식물의 건강상태를 실시간 분석</p>
                                <div class="tech-spec">
                                    <span>정확도: 95% 이상</span>
                                    <span>분석 시간: 2초 이내</span>
                                </div>
                            </div>
                            <div class="feature-item">
                                <h4>병충해 조기 탐지</h4>
                                <p>미세한 잎 변화까지 감지하여 병충해를 조기에 발견</p>
                                <div class="tech-spec">
                                    <span>감지 가능: 20여종 질병</span>
                                    <span>조기 탐지: 육안 발견보다 5-7일 빠름</span>
                                </div>
                            </div>
                            <div class="feature-item">
                                <h4>성장 예측 모델</h4>
                                <p>환경 데이터와 식물 상태를 종합하여 성장률과 수확 시기 예측</p>
                                <div class="tech-spec">
                                    <span>예측 정확도: 92%</span>
                                    <span>수확 시기: ±2일 오차</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="iot-section">
                        <h3>📡 IoT 센서 네트워크</h3>
                        <div class="sensor-grid">
                            <div class="sensor-item">
                                <div class="sensor-icon">🌡️</div>
                                <h4>온도 센서</h4>
                                <p>±0.1°C 정밀도</p>
                                <span class="range">측정 범위: -10~60°C</span>
                            </div>
                            <div class="sensor-item">
                                <div class="sensor-icon">💧</div>
                                <h4>습도 센서</h4>
                                <p>±2% 정밀도</p>
                                <span class="range">측정 범위: 0~100%</span>
                            </div>
                            <div class="sensor-item">
                                <div class="sensor-icon">💡</div>
                                <h4>조도 센서</h4>
                                <p>풀스펙트럼 측정</p>
                                <span class="range">범위: 0~200,000 lux</span>
                            </div>
                            <div class="sensor-item">
                                <div class="sensor-icon">⚗️</div>
                                <h4>pH/EC 센서</h4>
                                <p>±0.1 pH 정밀도</p>
                                <span class="range">pH: 0-14, EC: 0-20 mS/cm</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Research & Patents -->
            <section class="research-section">
                <h2>연구개발 & 특허</h2>
                <div class="research-content">
                    <div class="patents">
                        <h3>🏆 보유 특허</h3>
                        <div class="patent-list">
                            <div class="patent-item">
                                <h4>수경재배용 다층 혼합 배지 제조방법</h4>
                                <p>특허 제10-2023-0001234호</p>
                                <span class="patent-date">2023.03.15 등록</span>
                            </div>
                            <div class="patent-item">
                                <h4>AI 기반 식물 건강 진단 시스템</h4>
                                <p>특허 제10-2023-0005678호</p>
                                <span class="patent-date">2023.08.22 등록</span>
                            </div>
                            <div class="patent-item">
                                <h4>IoT 기반 스마트팜 자동 제어 장치</h4>
                                <p>특허 제10-2024-0001111호</p>
                                <span class="patent-date">2024.01.10 등록</span>
                            </div>
                        </div>
                    </div>

                    <div class="research-stats">
                        <h3>📈 연구개발 현황</h3>
                        <div class="research-grid">
                            <div class="research-item">
                                <div class="research-number">25명</div>
                                <div class="research-label">연구진</div>
                            </div>
                            <div class="research-item">
                                <div class="research-number">15억원</div>
                                <div class="research-label">연간 R&D 투자</div>
                            </div>
                            <div class="research-item">
                                <div class="research-number">10건</div>
                                <div class="research-label">보유 특허</div>
                            </div>
                            <div class="research-item">
                                <div class="research-number">5건</div>
                                <div class="research-label">진행 중인 연구</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Technical Specifications -->
            <section class="specifications">
                <h2>기술 사양</h2>
                <div class="spec-tabs">
                    <div class="tab-buttons">
                        <button class="tab-btn active" onclick="showTab('media-spec')">배지 규격</button>
                        <button class="tab-btn" onclick="showTab('ai-spec')">AI 시스템</button>
                        <button class="tab-btn" onclick="showTab('iot-spec')">IoT 하드웨어</button>
                    </div>

                    <div id="media-spec" class="tab-content active">
                        <table class="spec-table">
                            <tr><th>항목</th><th>규격</th><th>허용 오차</th></tr>
                            <tr><td>pH</td><td>6.0 - 6.8</td><td>±0.2</td></tr>
                            <tr><td>EC</td><td>0.3 - 0.8 mS/cm</td><td>±0.1</td></tr>
                            <tr><td>공극률</td><td>15 - 20%</td><td>±2%</td></tr>
                            <tr><td>보수력</td><td>60 - 70%</td><td>±5%</td></tr>
                            <tr><td>입자 크기</td><td>2-10mm (80%)</td><td>±5%</td></tr>
                        </table>
                    </div>

                    <div id="ai-spec" class="tab-content">
                        <table class="spec-table">
                            <tr><th>구성 요소</th><th>사양</th><th>성능</th></tr>
                            <tr><td>카메라</td><td>4K 해상도</td><td>30fps</td></tr>
                            <tr><td>AI 모델</td><td>YOLOv8 기반</td><td>95% 정확도</td></tr>
                            <tr><td>처리 시간</td><td>실시간 분석</td><td>2초 이내</td></tr>
                            <tr><td>지원 작물</td><td>토마토, 딸기, 오이 등</td><td>15종</td></tr>
                            <tr><td>병충해 탐지</td><td>20여종 질병</td><td>92% 정확도</td></tr>
                        </table>
                    </div>

                    <div id="iot-spec" class="tab-content">
                        <table class="spec-table">
                            <tr><th>하드웨어</th><th>모델</th><th>사양</th></tr>
                            <tr><td>메인보드</td><td>Raspberry Pi 4B</td><td>8GB RAM</td></tr>
                            <tr><td>온도센서</td><td>DS18B20</td><td>±0.1°C</td></tr>
                            <tr><td>습도센서</td><td>SHT30</td><td>±2%</td></tr>
                            <tr><td>pH센서</td><td>Atlas Scientific</td><td>±0.1 pH</td></tr>
                            <tr><td>무선통신</td><td>WiFi 6, Bluetooth 5.0</td><td>2.4/5GHz</td></tr>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <?php include '../../includes/footer.php'; ?>

    <script src="/assets/js/main.js"></script>
    <script>
        function showTab(tabId) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab and mark button as active
            document.getElementById(tabId).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>

<style>
.tech-main {
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

.overview-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
    align-items: center;
}

.overview-text h2 {
    text-align: left;
    margin-bottom: 1rem;
}

.overview-text p {
    line-height: 1.7;
    color: #333;
}

.tech-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.stat-item {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    text-align: center;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: #4CAF50;
    margin-bottom: 0.5rem;
}

.stat-label {
    color: #666;
    font-size: 0.9rem;
}

.tech-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 2rem;
}

.tech-card {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.tech-icon {
    font-size: 3rem;
    text-align: center;
    margin-bottom: 1rem;
}

.tech-card h3 {
    color: #2E7D32;
    margin-bottom: 1rem;
    text-align: center;
}

.tech-card p {
    color: #333;
    line-height: 1.6;
    margin-bottom: 1.5rem;
}

.tech-details h4 {
    color: #4CAF50;
    margin-bottom: 0.5rem;
    font-size: 1rem;
}

.tech-details ul {
    list-style: none;
    padding: 0;
}

.tech-details li {
    padding: 0.3rem 0;
    padding-left: 1.5rem;
    position: relative;
    color: #333;
    font-size: 0.9rem;
}

.tech-details li::before {
    content: '•';
    position: absolute;
    left: 0;
    color: #4CAF50;
    font-weight: bold;
}

.ai-iot-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
}

.ai-section, .iot-section {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.ai-section h3, .iot-section h3 {
    color: #2E7D32;
    margin-bottom: 1.5rem;
}

.feature-item {
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #eee;
}

.feature-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.feature-item h4 {
    color: #4CAF50;
    margin-bottom: 0.5rem;
}

.tech-spec {
    margin-top: 0.5rem;
}

.tech-spec span {
    display: inline-block;
    background: #f0f0f0;
    padding: 0.3rem 0.7rem;
    border-radius: 4px;
    font-size: 0.8rem;
    margin-right: 0.5rem;
    color: #666;
}

.sensor-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.sensor-item {
    text-align: center;
    padding: 1rem;
    border: 1px solid #eee;
    border-radius: 8px;
}

.sensor-icon {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.sensor-item h4 {
    color: #2E7D32;
    margin-bottom: 0.5rem;
}

.range {
    display: block;
    color: #666;
    font-size: 0.8rem;
    margin-top: 0.3rem;
}

.research-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
}

.patents, .research-stats {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.patents h3, .research-stats h3 {
    color: #2E7D32;
    margin-bottom: 1.5rem;
}

.patent-item {
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #eee;
}

.patent-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.patent-item h4 {
    color: #333;
    margin-bottom: 0.5rem;
}

.patent-item p {
    color: #4CAF50;
    margin-bottom: 0.3rem;
}

.patent-date {
    color: #666;
    font-size: 0.9rem;
}

.research-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.research-item {
    text-align: center;
    padding: 1rem;
    border: 1px solid #eee;
    border-radius: 8px;
}

.research-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: #4CAF50;
    margin-bottom: 0.5rem;
}

.research-label {
    color: #666;
    font-size: 0.9rem;
}

.spec-tabs {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    overflow: hidden;
}

.tab-buttons {
    display: flex;
    background: #f8f9fa;
}

.tab-btn {
    flex: 1;
    padding: 1rem 2rem;
    border: none;
    background: transparent;
    cursor: pointer;
    border-bottom: 3px solid transparent;
    transition: all 0.3s ease;
}

.tab-btn.active {
    background: white;
    border-bottom-color: #4CAF50;
    color: #2E7D32;
}

.tab-content {
    display: none;
    padding: 2rem;
}

.tab-content.active {
    display: block;
}

.spec-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.spec-table th,
.spec-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.spec-table th {
    background: #f8f9fa;
    color: #2E7D32;
    font-weight: 600;
}

@media (max-width: 768px) {
    .overview-content,
    .ai-iot-content,
    .research-content {
        grid-template-columns: 1fr;
    }
    
    .tech-stats,
    .sensor-grid,
    .research-grid {
        grid-template-columns: 1fr;
    }
    
    .tab-buttons {
        flex-direction: column;
    }
}
</style>
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
    <title>사용법 - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main class="usage-main">
        <div class="container">
            <div class="page-header">
                <h1>배지 사용법</h1>
                <p>탄생 배지의 올바른 사용법과 관리 방법을 안내해드립니다</p>
            </div>

            <!-- Quick Start Guide -->
            <section class="quick-start">
                <h2>🚀 빠른 시작 가이드</h2>
                <div class="steps-grid">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <h3>배지 준비</h3>
                            <p>포장을 개봉하고 배지를 충분히 물에 적셔 준비합니다</p>
                        </div>
                    </div>
                    <div class="step-card">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <h3>화분에 배치</h3>
                            <p>준비된 화분이나 재배 베드에 배지를 고르게 채웁니다</p>
                        </div>
                    </div>
                    <div class="step-card">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <h3>식물 이식</h3>
                            <p>묘목을 배지에 심고 뿌리가 잘 고정되도록 합니다</p>
                        </div>
                    </div>
                    <div class="step-card">
                        <div class="step-number">4</div>
                        <div class="step-content">
                            <h3>양액 공급</h3>
                            <p>작물에 맞는 양액을 정기적으로 공급합니다</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Detailed Instructions -->
            <section class="detailed-instructions">
                <h2>상세 사용법</h2>
                
                <div class="instruction-tabs">
                    <div class="tab-navigation">
                        <button class="tab-btn active" onclick="showInstructionTab('preparation')">배지 준비</button>
                        <button class="tab-btn" onclick="showInstructionTab('planting')">식물 심기</button>
                        <button class="tab-btn" onclick="showInstructionTab('maintenance')">관리 방법</button>
                        <button class="tab-btn" onclick="showInstructionTab('troubleshooting')">문제 해결</button>
                    </div>

                    <div id="preparation" class="tab-panel active">
                        <h3>🥄 배지 준비 과정</h3>
                        <div class="instruction-content">
                            <div class="instruction-item">
                                <h4>1. 포장 확인</h4>
                                <p>배지 포장이 손상되지 않았는지 확인하고, 유통기한을 체크합니다.</p>
                                <div class="tip">
                                    <span class="tip-icon">💡</span>
                                    <span>포장에 표시된 보관 조건을 준수하세요.</span>
                                </div>
                            </div>

                            <div class="instruction-item">
                                <h4>2. 충분한 수분 공급</h4>
                                <p>배지를 깨끗한 물에 10-15분간 담가 충분히 수분을 공급합니다.</p>
                                <ul>
                                    <li>물 온도: 20-25°C 권장</li>
                                    <li>pH: 6.0-6.5 조절</li>
                                    <li>EC: 0.5-1.0 mS/cm</li>
                                </ul>
                                <div class="warning">
                                    <span class="warning-icon">⚠️</span>
                                    <span>너무 뜨거운 물이나 차가운 물은 피하세요.</span>
                                </div>
                            </div>

                            <div class="instruction-item">
                                <h4>3. 과도한 물기 제거</h4>
                                <p>배지를 가볍게 짜서 과도한 물기를 제거합니다.</p>
                                <div class="tip">
                                    <span class="tip-icon">💡</span>
                                    <span>배지가 촉촉하지만 물이 뚝뚝 떨어지지 않을 정도가 적당합니다.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="planting" class="tab-panel">
                        <h3>🌱 식물 심기</h3>
                        <div class="instruction-content">
                            <div class="instruction-item">
                                <h4>1. 재배 용기 준비</h4>
                                <p>적절한 크기의 화분이나 재배 베드를 준비합니다.</p>
                                <ul>
                                    <li>배수구가 있는 용기 사용</li>
                                    <li>용기 크기: 식물 크기의 2-3배</li>
                                    <li>깊이: 최소 15cm 이상</li>
                                </ul>
                            </div>

                            <div class="instruction-item">
                                <h4>2. 배지 배치</h4>
                                <p>준비된 배지를 용기에 고르게 채웁니다.</p>
                                <div class="planting-diagram">
                                    <div class="layer">배수층 (5cm)</div>
                                    <div class="layer main">주 배지층 (60-70%)</div>
                                    <div class="layer">표층 배지 (3-5cm)</div>
                                </div>
                            </div>

                            <div class="instruction-item">
                                <h4>3. 묘목 이식</h4>
                                <p>건강한 묘목을 선택하여 배지에 심습니다.</p>
                                <ul>
                                    <li>뿌리를 손상시키지 않도록 주의</li>
                                    <li>식물 간격: 작물별 권장 간격 준수</li>
                                    <li>심는 깊이: 기존 화분과 동일하게</li>
                                </ul>
                                <div class="tip">
                                    <span class="tip-icon">💡</span>
                                    <span>이식 후 2-3일간은 직사광선을 피해주세요.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="maintenance" class="tab-panel">
                        <h3>🔧 관리 방법</h3>
                        <div class="instruction-content">
                            <div class="maintenance-section">
                                <h4>💧 물 관리</h4>
                                <div class="maintenance-grid">
                                    <div class="maintenance-item">
                                        <h5>급수 주기</h5>
                                        <p>배지 표면이 약간 마를 때 물을 공급합니다.</p>
                                        <ul>
                                            <li>여름: 1일 1-2회</li>
                                            <li>봄/가을: 2-3일에 1회</li>
                                            <li>겨울: 3-4일에 1회</li>
                                        </ul>
                                    </div>
                                    <div class="maintenance-item">
                                        <h5>급수량</h5>
                                        <p>배지가 충분히 촉촉해질 정도로 공급합니다.</p>
                                        <div class="tip">
                                            <span class="tip-icon">💡</span>
                                            <span>배수구에서 물이 약간 나올 때까지</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="maintenance-section">
                                <h4>🥤 양액 관리</h4>
                                <table class="nutrient-table">
                                    <thead>
                                        <tr>
                                            <th>작물</th>
                                            <th>EC (mS/cm)</th>
                                            <th>pH</th>
                                            <th>공급 주기</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>토마토</td>
                                            <td>2.0-2.5</td>
                                            <td>5.8-6.2</td>
                                            <td>매일</td>
                                        </tr>
                                        <tr>
                                            <td>딸기</td>
                                            <td>0.8-1.2</td>
                                            <td>5.5-6.0</td>
                                            <td>2일에 1회</td>
                                        </tr>
                                        <tr>
                                            <td>오이</td>
                                            <td>1.8-2.2</td>
                                            <td>5.8-6.2</td>
                                            <td>매일</td>
                                        </tr>
                                        <tr>
                                            <td>상추</td>
                                            <td>1.2-1.6</td>
                                            <td>6.0-6.5</td>
                                            <td>3일에 1회</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="maintenance-section">
                                <h4>🌡️ 환경 관리</h4>
                                <div class="environment-grid">
                                    <div class="env-item">
                                        <h5>온도</h5>
                                        <p>작물별 적정 온도 유지</p>
                                        <span class="range">일반적으로 18-25°C</span>
                                    </div>
                                    <div class="env-item">
                                        <h5>습도</h5>
                                        <p>과습을 피하고 적절한 습도 유지</p>
                                        <span class="range">60-70% 권장</span>
                                    </div>
                                    <div class="env-item">
                                        <h5>광량</h5>
                                        <p>충분한 광량 확보</p>
                                        <span class="range">작물별 요구 광량 준수</span>
                                    </div>
                                    <div class="env-item">
                                        <h5>환기</h5>
                                        <p>정기적인 환기로 공기 순환</p>
                                        <span class="range">하루 2-3회 환기</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="troubleshooting" class="tab-panel">
                        <h3>🔧 문제 해결</h3>
                        <div class="instruction-content">
                            <div class="problem-section">
                                <h4>자주 발생하는 문제들</h4>
                                <div class="problem-list">
                                    <div class="problem-item">
                                        <div class="problem-header">
                                            <span class="problem-icon">🟡</span>
                                            <h5>잎이 노랗게 변함</h5>
                                        </div>
                                        <div class="problem-content">
                                            <p><strong>원인:</strong> 과습 또는 양분 부족</p>
                                            <p><strong>해결책:</strong></p>
                                            <ul>
                                                <li>급수량과 주기 조절</li>
                                                <li>배수 상태 점검</li>
                                                <li>양액 농도 확인 (EC값 측정)</li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div class="problem-item">
                                        <div class="problem-header">
                                            <span class="problem-icon">🟤</span>
                                            <h5>뿌리가 갈색으로 변함</h5>
                                        </div>
                                        <div class="problem-content">
                                            <p><strong>원인:</strong> 뿌리 부패 (과습)</p>
                                            <p><strong>해결책:</strong></p>
                                            <ul>
                                                <li>즉시 급수 중단</li>
                                                <li>배지 교체 검토</li>
                                                <li>부패된 뿌리 제거</li>
                                                <li>배수 시설 개선</li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div class="problem-item">
                                        <div class="problem-header">
                                            <span class="problem-icon">⚪</span>
                                            <h5>배지 표면에 하얀 곰팡이</h5>
                                        </div>
                                        <div class="problem-content">
                                            <p><strong>원인:</strong> 과습 및 환기 부족</p>
                                            <p><strong>해결책:</strong></p>
                                            <ul>
                                                <li>환기 강화</li>
                                                <li>급수량 줄이기</li>
                                                <li>곰팡이 부분 제거</li>
                                                <li>습도 조절</li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div class="problem-item">
                                        <div class="problem-header">
                                            <span class="problem-icon">📉</span>
                                            <h5>생장이 느림</h5>
                                        </div>
                                        <div class="problem-content">
                                            <p><strong>원인:</strong> 양분 부족 또는 환경 요인</p>
                                            <p><strong>해결책:</strong></p>
                                            <ul>
                                                <li>양액 농도 증가</li>
                                                <li>온도 조건 확인</li>
                                                <li>광량 보충</li>
                                                <li>pH 조정</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="contact-support">
                                <h4>📞 기술 지원</h4>
                                <p>문제가 해결되지 않으시면 언제든지 연락주세요.</p>
                                <div class="support-info">
                                    <div class="support-item">
                                        <span class="support-icon">📞</span>
                                        <span>전화: 02-0000-0000 (기술지원팀)</span>
                                    </div>
                                    <div class="support-item">
                                        <span class="support-icon">✉️</span>
                                        <span>이메일: support@tangsaeng.com</span>
                                    </div>
                                    <div class="support-item">
                                        <span class="support-icon">🕒</span>
                                        <span>운영시간: 평일 09:00-18:00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Tips Section -->
            <section class="tips-section">
                <h2>💡 전문가 팁</h2>
                <div class="tips-grid">
                    <div class="tip-card">
                        <div class="tip-icon">🌱</div>
                        <h3>성장 단계별 관리</h3>
                        <p>발아기, 생장기, 개화기, 결실기에 따라 양액 농도와 급수 주기를 조절하세요.</p>
                    </div>
                    <div class="tip-card">
                        <div class="tip-icon">📊</div>
                        <h3>정기적인 모니터링</h3>
                        <p>pH, EC값을 주 2-3회 측정하여 최적 상태를 유지하세요.</p>
                    </div>
                    <div class="tip-card">
                        <div class="tip-icon">♻️</div>
                        <h3>배지 재활용</h3>
                        <p>사용한 배지는 적절한 처리 후 퇴비로 재활용할 수 있습니다.</p>
                    </div>
                    <div class="tip-card">
                        <div class="tip-icon">📝</div>
                        <h3>재배 일지 작성</h3>
                        <p>급수, 시비, 환경 조건을 기록하여 최적의 재배 조건을 찾아보세요.</p>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <?php include '../../includes/footer.php'; ?>

    <script src="/assets/js/main.js"></script>
    <script>
        function showInstructionTab(tabId) {
            // Hide all tab panels
            document.querySelectorAll('.tab-panel').forEach(panel => {
                panel.classList.remove('active');
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
.usage-main {
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

.steps-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
}

.step-card {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    text-align: center;
    position: relative;
}

.step-number {
    position: absolute;
    top: -15px;
    left: 50%;
    transform: translateX(-50%);
    background: #4CAF50;
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.2rem;
}

.step-content h3 {
    color: #2E7D32;
    margin-bottom: 1rem;
    margin-top: 1rem;
}

.instruction-tabs {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    overflow: hidden;
}

.tab-navigation {
    display: flex;
    background: #f8f9fa;
    border-bottom: 1px solid #eee;
}

.tab-btn {
    flex: 1;
    padding: 1rem;
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

.tab-panel {
    display: none;
    padding: 2rem;
}

.tab-panel.active {
    display: block;
}

.tab-panel h3 {
    color: #2E7D32;
    margin-bottom: 1.5rem;
}

.instruction-item {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid #eee;
}

.instruction-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.instruction-item h4 {
    color: #4CAF50;
    margin-bottom: 1rem;
}

.instruction-item ul {
    margin: 1rem 0;
    padding-left: 1.5rem;
}

.instruction-item li {
    margin-bottom: 0.5rem;
    color: #333;
}

.tip, .warning {
    display: flex;
    align-items: center;
    padding: 0.8rem;
    border-radius: 6px;
    margin-top: 1rem;
}

.tip {
    background: #E8F5E8;
    border-left: 4px solid #4CAF50;
}

.warning {
    background: #FFF3E0;
    border-left: 4px solid #FF9800;
}

.tip-icon, .warning-icon {
    margin-right: 0.5rem;
}

.planting-diagram {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    margin: 1rem 0;
}

.layer {
    padding: 0.5rem;
    margin: 0.3rem 0;
    border-radius: 4px;
    text-align: center;
    font-size: 0.9rem;
}

.layer:first-child {
    background: #E3F2FD;
}

.layer.main {
    background: #C8E6C9;
    font-weight: bold;
}

.layer:last-child {
    background: #FFF9C4;
}

.maintenance-section {
    margin-bottom: 2rem;
}

.maintenance-section h4 {
    color: #2E7D32;
    margin-bottom: 1rem;
}

.maintenance-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

.maintenance-item {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
}

.maintenance-item h5 {
    color: #4CAF50;
    margin-bottom: 0.5rem;
}

.nutrient-table {
    width: 100%;
    border-collapse: collapse;
    margin: 1rem 0;
}

.nutrient-table th,
.nutrient-table td {
    padding: 0.8rem;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.nutrient-table th {
    background: #f8f9fa;
    color: #2E7D32;
    font-weight: 600;
}

.environment-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.env-item {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    text-align: center;
}

.env-item h5 {
    color: #2E7D32;
    margin-bottom: 0.5rem;
}

.range {
    display: block;
    color: #4CAF50;
    font-size: 0.9rem;
    font-weight: 600;
    margin-top: 0.5rem;
}

.problem-item {
    border: 1px solid #eee;
    border-radius: 8px;
    margin-bottom: 1rem;
    overflow: hidden;
}

.problem-header {
    display: flex;
    align-items: center;
    padding: 1rem;
    background: #f8f9fa;
    cursor: pointer;
}

.problem-icon {
    font-size: 1.2rem;
    margin-right: 0.5rem;
}

.problem-header h5 {
    margin: 0;
    color: #2E7D32;
}

.problem-content {
    padding: 1rem;
}

.problem-content ul {
    margin-top: 0.5rem;
    padding-left: 1.5rem;
}

.contact-support {
    background: #f8f9fa;
    padding: 2rem;
    border-radius: 8px;
    margin-top: 2rem;
}

.contact-support h4 {
    color: #2E7D32;
    margin-bottom: 1rem;
}

.support-info {
    margin-top: 1rem;
}

.support-item {
    display: flex;
    align-items: center;
    margin-bottom: 0.5rem;
}

.support-icon {
    margin-right: 0.5rem;
}

.tips-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
}

.tip-card {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    text-align: center;
}

.tip-card .tip-icon {
    font-size: 2.5rem;
    margin-bottom: 1rem;
}

.tip-card h3 {
    color: #2E7D32;
    margin-bottom: 1rem;
}

@media (max-width: 768px) {
    .tab-navigation {
        flex-direction: column;
    }
    
    .maintenance-grid,
    .environment-grid {
        grid-template-columns: 1fr;
    }
    
    .steps-grid {
        grid-template-columns: 1fr;
    }
}
</style>
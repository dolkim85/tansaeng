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
    <title>배지소개 - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main >
        <div class="container">
            <div class="page-header">
                <h1>배지소개</h1>
                <p>탄생의 프리미엄 배지로 최상의 작물을 키워보세요</p>
            </div>

            <!-- What is Media -->
            <section class="media-intro">
                <div class="intro-content">
                    <div class="intro-text">
                        <h2>배지란 무엇인가요?</h2>
                        <p>배지(培地, Growing Medium)는 수경재배에서 식물의 뿌리를 지지하고 영양분과 수분을 공급하는 매개체입니다. 전통적인 토양을 대신하여 식물이 건강하게 성장할 수 있는 최적의 환경을 제공합니다.</p>
                        
                        <p>탄생의 배지는 엄선된 천연 원료와 과학적인 배합으로 제조되어 뛰어난 통기성, 보수성, 배수성을 자랑합니다.</p>
                        
                        <div class="benefits">
                            <h3>배지 사용의 장점</h3>
                            <ul>
                                <li>✓ 토양 병해충으로부터 안전</li>
                                <li>✓ 일정한 품질과 성분 보장</li>
                                <li>✓ 최적의 뿌리 환경 조성</li>
                                <li>✓ 재사용 가능한 친환경성</li>
                                <li>✓ 정확한 영양분 제어</li>
                            </ul>
                        </div>
                    </div>
                    <div class="intro-visual">
                        <img src="/assets/images/products/media-intro.jpg" alt="배지 소개" loading="lazy">
                    </div>
                </div>
            </section>

            <!-- Product Categories -->
            <section class="product-categories">
                <h2>탄생 배지 제품군</h2>
                <div class="categories-grid">
                    
                    <!-- 코코피트 배지 -->
                    <div class="category-card">
                        <div class="category-image">
                            <img src="/assets/images/products/coconut-fiber-detail.jpg" alt="코코피트 배지" loading="lazy">
                        </div>
                        <div class="category-content">
                            <h3>코코피트 배지</h3>
                            <p class="category-subtitle">Coconut Fiber Growing Medium</p>
                            
                            <div class="features">
                                <h4>특징</h4>
                                <ul>
                                    <li>코코넛 껍질에서 추출한 천연 섬유</li>
                                    <li>뛰어난 보수성과 통기성</li>
                                    <li>pH 5.5-6.5의 최적 산성도</li>
                                    <li>100% 천연 재료로 친환경적</li>
                                </ul>
                            </div>
                            
                            <div class="applications">
                                <h4>적합한 작물</h4>
                                <p>토마토, 오이, 파프리카, 딸기, 허브류</p>
                            </div>
                            
                            <a href="/pages/store/category.php?category=coconut" class="btn btn-primary">구매하기</a>
                        </div>
                    </div>

                    <!-- 펄라이트 배지 -->
                    <div class="category-card">
                        <div class="category-image">
                            <img src="/assets/images/products/perlite-detail.jpg" alt="펄라이트 배지" loading="lazy">
                        </div>
                        <div class="category-content">
                            <h3>펄라이트 배지</h3>
                            <p class="category-subtitle">Perlite Growing Medium</p>
                            
                            <div class="features">
                                <h4>특징</h4>
                                <ul>
                                    <li>화산암을 고온 처리한 무기질 배지</li>
                                    <li>우수한 배수성과 통기성</li>
                                    <li>가볍고 취급이 용이</li>
                                    <li>재사용이 가능한 내구성</li>
                                </ul>
                            </div>
                            
                            <div class="applications">
                                <h4>적합한 작물</h4>
                                <p>멜론, 수박, 상추, 시금치, 베리류</p>
                            </div>
                            
                            <a href="/pages/store/category.php?category=perlite" class="btn btn-primary">구매하기</a>
                        </div>
                    </div>

                    <!-- 혼합 배지 -->
                    <div class="category-card">
                        <div class="category-image">
                            <img src="/assets/images/products/mixed-media.jpg" alt="혼합 배지" loading="lazy">
                        </div>
                        <div class="category-content">
                            <h3>혼합 배지</h3>
                            <p class="category-subtitle">Mixed Growing Medium</p>
                            
                            <div class="features">
                                <h4>특징</h4>
                                <ul>
                                    <li>코코피트와 펄라이트의 과학적 배합</li>
                                    <li>균형잡힌 보수성과 배수성</li>
                                    <li>다양한 작물에 범용적 사용</li>
                                    <li>초보자도 쉽게 사용 가능</li>
                                </ul>
                            </div>
                            
                            <div class="applications">
                                <h4>적합한 작물</h4>
                                <p>대부분의 채소류, 화훼류, 관엽식물</p>
                            </div>
                            
                            <a href="/pages/store/category.php?category=mixed" class="btn btn-primary">구매하기</a>
                        </div>
                    </div>

                    <!-- 유기농 배지 -->
                    <div class="category-card">
                        <div class="category-image">
                            <img src="/assets/images/products/organic-media.jpg" alt="유기농 배지" loading="lazy">
                        </div>
                        <div class="category-content">
                            <h3>유기농 배지</h3>
                            <p class="category-subtitle">Organic Growing Medium</p>
                            
                            <div class="features">
                                <h4>특징</h4>
                                <ul>
                                    <li>유기농 인증을 받은 천연 재료</li>
                                    <li>미생물 활성화를 통한 자연 순환</li>
                                    <li>화학 비료 없이도 우수한 성장</li>
                                    <li>안전한 유기농 작물 생산</li>
                                </ul>
                            </div>
                            
                            <div class="applications">
                                <h4>적합한 작물</h4>
                                <p>유기농 채소, 허브, 약용식물, 베이비채소</p>
                            </div>
                            
                            <a href="/pages/store/category.php?category=organic" class="btn btn-primary">구매하기</a>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Comparison Table -->
            <section class="comparison-section">
                <h2>배지 비교표</h2>
                <div class="comparison-table-container">
                    <table class="comparison-table">
                        <thead>
                            <tr>
                                <th>특성</th>
                                <th>코코피트</th>
                                <th>펄라이트</th>
                                <th>혼합배지</th>
                                <th>유기농</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>보수성</td>
                                <td class="excellent">우수</td>
                                <td class="good">보통</td>
                                <td class="excellent">우수</td>
                                <td class="excellent">우수</td>
                            </tr>
                            <tr>
                                <td>배수성</td>
                                <td class="good">보통</td>
                                <td class="excellent">우수</td>
                                <td class="excellent">우수</td>
                                <td class="good">보통</td>
                            </tr>
                            <tr>
                                <td>통기성</td>
                                <td class="good">좋음</td>
                                <td class="excellent">우수</td>
                                <td class="excellent">우수</td>
                                <td class="good">좋음</td>
                            </tr>
                            <tr>
                                <td>pH 안정성</td>
                                <td class="excellent">안정</td>
                                <td class="excellent">안정</td>
                                <td class="excellent">안정</td>
                                <td class="good">양호</td>
                            </tr>
                            <tr>
                                <td>재사용성</td>
                                <td class="good">가능</td>
                                <td class="excellent">우수</td>
                                <td class="excellent">우수</td>
                                <td class="fair">제한적</td>
                            </tr>
                            <tr>
                                <td>초보자 적합성</td>
                                <td class="good">좋음</td>
                                <td class="fair">보통</td>
                                <td class="excellent">우수</td>
                                <td class="good">좋음</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Care Instructions -->
            <section class="care-instructions">
                <h2>배지 관리 방법</h2>
                <div class="instructions-grid">
                    <div class="instruction-card">
                        <div class="instruction-icon">💧</div>
                        <h3>급수 관리</h3>
                        <ul>
                            <li>배지 표면이 마르면 충분히 급수</li>
                            <li>과습을 피하고 배수가 잘 되도록 관리</li>
                            <li>급수 시간은 오전이 적합</li>
                        </ul>
                    </div>
                    <div class="instruction-card">
                        <div class="instruction-icon">🌡️</div>
                        <h3>온도 관리</h3>
                        <ul>
                            <li>적정 온도: 18-25°C 유지</li>
                            <li>급격한 온도 변화 방지</li>
                            <li>야간 온도는 2-3°C 낮게 설정</li>
                        </ul>
                    </div>
                    <div class="instruction-card">
                        <div class="instruction-icon">💡</div>
                        <h3>광량 관리</h3>
                        <ul>
                            <li>하루 12-16시간의 충분한 광량 제공</li>
                            <li>LED 또는 형광등 보조 조명 활용</li>
                            <li>작물별 최적 광량 조절</li>
                        </ul>
                    </div>
                    <div class="instruction-card">
                        <div class="instruction-icon">🧪</div>
                        <h3>영양 관리</h3>
                        <ul>
                            <li>작물별 전용 양액 사용</li>
                            <li>EC 1.2-2.0, pH 5.5-6.5 유지</li>
                            <li>정기적인 양액 교체</li>
                        </ul>
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
.products-main {
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

.media-intro {
    margin-bottom: 4rem;
}

.intro-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
    align-items: center;
}

.intro-text h2 {
    color: #2E7D32;
    font-size: 2rem;
    margin-bottom: 1.5rem;
}

.intro-text p {
    line-height: 1.7;
    margin-bottom: 1.5rem;
    color: #333;
}

.benefits h3 {
    color: #4CAF50;
    margin: 2rem 0 1rem 0;
}

.benefits ul {
    list-style: none;
    padding: 0;
}

.benefits li {
    margin-bottom: 0.5rem;
    color: #333;
}

.product-categories {
    margin-bottom: 4rem;
}

.product-categories h2 {
    text-align: center;
    color: #2E7D32;
    margin-bottom: 2rem;
}

.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
}

.category-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    overflow: hidden;
    transition: transform 0.3s ease;
}

.category-card:hover {
    transform: translateY(-5px);
}

.category-image {
    height: 200px;
    overflow: hidden;
}

.category-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.category-content {
    padding: 2rem;
}

.category-content h3 {
    color: #2E7D32;
    margin-bottom: 0.5rem;
}

.category-subtitle {
    color: #4CAF50;
    font-size: 0.9rem;
    margin-bottom: 1.5rem;
}

.features, .applications {
    margin-bottom: 1.5rem;
}

.features h4, .applications h4 {
    color: #333;
    margin-bottom: 0.5rem;
    font-size: 1rem;
}

.features ul {
    list-style: none;
    padding: 0;
}

.features li {
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
    color: #666;
}

.applications p {
    font-size: 0.9rem;
    color: #666;
}

.comparison-section {
    margin-bottom: 4rem;
}

.comparison-section h2 {
    text-align: center;
    color: #2E7D32;
    margin-bottom: 2rem;
}

.comparison-table-container {
    overflow-x: auto;
}

.comparison-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.comparison-table th {
    background: #4CAF50;
    color: white;
    padding: 1rem;
    text-align: center;
    font-weight: 600;
}

.comparison-table td {
    padding: 1rem;
    text-align: center;
    border-bottom: 1px solid #f0f0f0;
}

.comparison-table td:first-child {
    font-weight: 600;
    background: #f8f9fa;
}

.excellent { color: #2E7D32; font-weight: 600; }
.good { color: #4CAF50; font-weight: 600; }
.fair { color: #FF9800; font-weight: 600; }

.care-instructions h2 {
    text-align: center;
    color: #2E7D32;
    margin-bottom: 2rem;
}

.instructions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 2rem;
}

.instruction-card {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    text-align: center;
}

.instruction-icon {
    font-size: 2.5rem;
    margin-bottom: 1rem;
}

.instruction-card h3 {
    color: #2E7D32;
    margin-bottom: 1rem;
}

.instruction-card ul {
    list-style: none;
    padding: 0;
    text-align: left;
}

.instruction-card li {
    margin-bottom: 0.5rem;
    color: #666;
    position: relative;
    padding-left: 1rem;
}

.instruction-card li:before {
    content: '•';
    color: #4CAF50;
    position: absolute;
    left: 0;
}

@media (max-width: 768px) {
    .intro-content {
        grid-template-columns: 1fr;
    }
    
    .categories-grid {
        grid-template-columns: 1fr;
    }
}
</style>
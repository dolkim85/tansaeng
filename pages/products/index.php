<?php
/**
 * Products Main Page - 배지설명
 * 탄생 스마트팜 배지 제품 소개
 */

$currentUser = null;
try {
    require_once __DIR__ . '/../../classes/Auth.php';
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
}

$pageTitle = "배지설명 - 탄생";
$pageDescription = "고품질 수경재배 배지 제품군을 소개합니다. 코코피트, 펄라이트, 혼합배지 등 다양한 제품을 확인하세요.";
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <meta name="description" content="<?= $pageDescription ?>">
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
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
        .page-header p {
            font-size: 1.1rem;
            color: #555;
        }
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            padding: 60px 0;
        }
        .product-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 16px 40px rgba(0,0,0,0.15);
        }
        .product-image {
            height: 200px;
            background: linear-gradient(45deg, #4CAF50, #2E7D32);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
        }
        .product-content {
            padding: 30px;
        }
        .product-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2E7D32;
            margin-bottom: 15px;
        }
        .product-description {
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .product-features {
            list-style: none;
            padding: 0;
            margin-bottom: 25px;
        }
        .product-features li {
            padding: 5px 0;
            color: #333;
        }
        .product-features li:before {
            content: "✓ ";
            color: #4CAF50;
            font-weight: bold;
        }
        .btn-learn-more {
            background: #2E7D32;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            font-weight: 500;
            transition: background 0.3s ease;
        }
        .btn-learn-more:hover {
            background: #1B5E20;
            color: white;
        }
        .comparison-section {
            background: #f8f9fa;
            padding: 60px 0;
        }
        .comparison-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }
        .comparison-table table {
            width: 100%;
            border-collapse: collapse;
        }
        .comparison-table th {
            background: #2E7D32;
            color: white;
            padding: 20px;
            text-align: center;
            font-weight: 600;
        }
        .comparison-table td {
            padding: 15px 20px;
            text-align: center;
            border-bottom: 1px solid #eee;
        }
        .comparison-table tr:hover {
            background: #f5f5f5;
        }
        @media (max-width: 768px) {
            .page-header {
                padding: 1.5rem 1rem !important;
            }
            .page-header h1 {
                font-size: 1.5rem !important;
            }
            .page-header p {
                font-size: 0.9rem !important;
            }
            .products-grid {
                grid-template-columns: 1fr;
                padding: 40px 0;
            }
            .comparison-table {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main >
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>배지설명</h1>
            <p>수경재배의 성공을 위한 고품질 배지 솔루션</p>
        </div>
    </div>

    <!-- Products Grid -->
    <section class="products-grid">
        <div class="container">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px;">

                <!-- 코코피트 배지 -->
                <div class="product-card">
                    <div class="product-image">🥥</div>
                    <div class="product-content">
                        <h3 class="product-title">코코피트 배지</h3>
                        <p class="product-description">
                            코코넛 껍질에서 추출한 천연 친환경 배지로, 우수한 보수력과 배수성을 겸비하고 있습니다.
                        </p>
                        <ul class="product-features">
                            <li>뛰어난 보수력 및 배수성</li>
                            <li>친환경 천연 소재</li>
                            <li>pH 중성 유지</li>
                            <li>재사용 가능</li>
                            <li>병충해 저항성</li>
                        </ul>
                        <a href="/pages/store/?category=coco" class="btn-learn-more">제품 보기</a>
                    </div>
                </div>

                <!-- 펄라이트 배지 -->
                <div class="product-card">
                    <div class="product-image">⚪</div>
                    <div class="product-content">
                        <h3 class="product-title">펄라이트 배지</h3>
                        <p class="product-description">
                            화산암을 고온 처리하여 만든 경량 배지로, 탁월한 배수성과 통기성을 제공합니다.
                        </p>
                        <ul class="product-features">
                            <li>우수한 배수성 및 통기성</li>
                            <li>경량으로 작업 편의성</li>
                            <li>무균 상태 유지</li>
                            <li>pH 안정성</li>
                            <li>장기간 형태 유지</li>
                        </ul>
                        <a href="/pages/store/?category=perlite" class="btn-learn-more">제품 보기</a>
                    </div>
                </div>

                <!-- 혼합 배지 -->
                <div class="product-card">
                    <div class="product-image">🌿</div>
                    <div class="product-content">
                        <h3 class="product-title">혼합 배지</h3>
                        <p class="product-description">
                            코코피트와 펄라이트를 최적 비율로 혼합하여 각 소재의 장점을 극대화한 프리미엄 배지입니다.
                        </p>
                        <ul class="product-features">
                            <li>최적화된 배지 비율</li>
                            <li>균형잡힌 보수력/배수성</li>
                            <li>작물별 맞춤 조성</li>
                            <li>즉시 사용 가능</li>
                            <li>일관된 품질 보장</li>
                        </ul>
                        <a href="/pages/store/?category=mixed" class="btn-learn-more">제품 보기</a>
                    </div>
                </div>

                <!-- 특수 배지 -->
                <div class="product-card">
                    <div class="product-image">⭐</div>
                    <div class="product-content">
                        <h3 class="product-title">특수 배지</h3>
                        <p class="product-description">
                            특정 작물과 재배 환경에 특화된 맞춤형 배지 솔루션을 제공합니다.
                        </p>
                        <ul class="product-features">
                            <li>작물별 맞춤 설계</li>
                            <li>전문가 컨설팅</li>
                            <li>연구개발 기반 제품</li>
                            <li>성능 검증 완료</li>
                            <li>기술지원 서비스</li>
                        </ul>
                        <a href="/pages/store/?category=special" class="btn-learn-more">제품 보기</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Comparison Section -->
    <section class="comparison-section">
        <div class="container">
            <h2 style="text-align: center; margin-bottom: 40px; color: #2E7D32;">배지별 특성 비교</h2>
            <div class="comparison-table">
                <table>
                    <thead>
                        <tr>
                            <th>특성</th>
                            <th>코코피트</th>
                            <th>펄라이트</th>
                            <th>혼합배지</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>보수력</strong></td>
                            <td>높음</td>
                            <td>낮음</td>
                            <td>중간</td>
                        </tr>
                        <tr>
                            <td><strong>배수성</strong></td>
                            <td>보통</td>
                            <td>매우 높음</td>
                            <td>높음</td>
                        </tr>
                        <tr>
                            <td><strong>통기성</strong></td>
                            <td>보통</td>
                            <td>매우 높음</td>
                            <td>높음</td>
                        </tr>
                        <tr>
                            <td><strong>pH 안정성</strong></td>
                            <td>중성</td>
                            <td>안정</td>
                            <td>안정</td>
                        </tr>
                        <tr>
                            <td><strong>재사용성</strong></td>
                            <td>가능</td>
                            <td>가능</td>
                            <td>가능</td>
                        </tr>
                        <tr>
                            <td><strong>적합 작물</strong></td>
                            <td>엽채류, 과채류</td>
                            <td>다육식물, 허브</td>
                            <td>전체 작물</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section style="background: #2E7D32; color: white; padding: 60px 0; text-align: center;">
        <div class="container">
            <h2>지금 바로 최적의 배지를 선택하세요</h2>
            <p style="margin: 20px 0; font-size: 1.1rem;">전문가 상담을 통해 작물에 가장 적합한 배지를 추천받을 수 있습니다.</p>
            <div style="margin-top: 30px;">
                <a href="/pages/store/" class="btn-learn-more" style="background: white; color: #2E7D32; margin-right: 15px;">온라인 스토어</a>
                <a href="/pages/support/contact.php" class="btn-learn-more" style="background: transparent; border: 2px solid white;">전문가 상담</a>
            </div>
        </div>
    </section>

    </main>

    <?php include '../../includes/footer.php'; ?>

    <script src="/assets/js/main.js"></script>
</body>
</html>
<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>코코피트 배지 - 탄생</title>
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
        .product-content {
            max-width: 1200px;
            margin: 50px auto;
            padding: 0 20px;
        }
        .product-section {
            margin-bottom: 60px;
        }
        .product-section h2 {
            font-size: 2em;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 3px solid #2ecc71;
            padding-bottom: 10px;
        }
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        .feature-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .feature-card h3 {
            color: #2ecc71;
            font-size: 1.5em;
            margin-bottom: 15px;
        }
        .specs-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .specs-table th,
        .specs-table td {
            padding: 15px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .specs-table th {
            background: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        .cta-section {
            background: #f8f9fa;
            padding: 50px 20px;
            text-align: center;
            margin-top: 60px;
            border-radius: 10px;
        }
        .cta-section h3 {
            font-size: 2em;
            margin-bottom: 20px;
        }
        .btn-primary {
            display: inline-block;
            padding: 15px 40px;
            background: #2ecc71;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 1.1em;
            transition: background 0.3s;
        }
        .btn-primary:hover {
            background: #27ae60;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <main>
        <div class="container">
            <div class="page-header">
                <h1>코코피트 배지</h1>
                <p>지속 가능한 천연 배지, 코코넛 섬유로 만든 프리미엄 재배 솔루션</p>
            </div>
        </div>

    <div class="product-content">
        <div class="product-section">
            <h2>코코피트 배지란?</h2>
            <p>코코피트(Cocopeat)는 코코넛 껍질을 가공하여 만든 천연 유기질 배지입니다. 우수한 보수력과 통기성을 동시에 제공하여 식물의 건강한 성장을 돕습니다. 친환경적이며 재생 가능한 자원으로 만들어져 지속 가능한 농업을 실현합니다.</p>
        </div>

        <div class="product-section">
            <h2>주요 특징</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <h3>💧 우수한 보수력</h3>
                    <p>자체 무게의 8~9배에 달하는 물을 흡수하여 안정적인 수분 공급이 가능합니다.</p>
                </div>
                <div class="feature-card">
                    <h3>🌬️ 탁월한 통기성</h3>
                    <p>공극률이 높아 뿌리 호흡이 원활하며 뿌리 부패를 방지합니다.</p>
                </div>
                <div class="feature-card">
                    <h3>♻️ 친환경 소재</h3>
                    <p>100% 천연 코코넛 섬유로 만들어져 환경 친화적이며 재활용이 가능합니다.</p>
                </div>
                <div class="feature-card">
                    <h3>⚖️ 균형잡힌 pH</h3>
                    <p>pH 5.5~6.5의 약산성으로 대부분의 작물 재배에 적합합니다.</p>
                </div>
                <div class="feature-card">
                    <h3>🔄 재사용 가능</h3>
                    <p>적절한 관리 시 여러 번 재사용이 가능하여 경제적입니다.</p>
                </div>
                <div class="feature-card">
                    <h3>🦠 병해충 저항성</h3>
                    <p>자연적인 항균 성분이 포함되어 병해충 발생을 억제합니다.</p>
                </div>
            </div>
        </div>

        <div class="product-section">
            <h2>제품 사양</h2>
            <table class="specs-table">
                <tr>
                    <th>항목</th>
                    <th>사양</th>
                </tr>
                <tr>
                    <td>원료</td>
                    <td>100% 천연 코코넛 섬유</td>
                </tr>
                <tr>
                    <td>pH</td>
                    <td>5.5 ~ 6.5</td>
                </tr>
                <tr>
                    <td>EC (전기전도도)</td>
                    <td>0.5 mS/cm 이하</td>
                </tr>
                <tr>
                    <td>보수력</td>
                    <td>자체 무게의 8~9배</td>
                </tr>
                <tr>
                    <td>공극률</td>
                    <td>95% 이상</td>
                </tr>
                <tr>
                    <td>압축 비율</td>
                    <td>5:1 (압축 시)</td>
                </tr>
                <tr>
                    <td>포장 단위</td>
                    <td>5kg 블록, 650g 디스크</td>
                </tr>
            </table>
        </div>

        <div class="product-section">
            <h2>적용 작물</h2>
            <p>코코피트 배지는 다양한 작물 재배에 사용됩니다:</p>
            <ul style="line-height: 2; margin-left: 20px;">
                <li>🍅 토마토, 파프리카 등 과채류</li>
                <li>🥬 상추, 케일 등 엽채류</li>
                <li>🌹 장미, 국화 등 화훼류</li>
                <li>🍓 딸기</li>
                <li>🥒 오이, 호박 등 박과류</li>
            </ul>
        </div>

        <div class="cta-section">
            <h3>코코피트 배지 문의</h3>
            <p>제품에 대해 궁금하신 점이 있으신가요? 전문가가 상담해 드립니다.</p>
            <a href="/pages/support/contact.php" class="btn-primary">문의하기</a>
        </div>
    </div>
    </main>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>

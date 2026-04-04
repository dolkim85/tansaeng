<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>펄라이트 배지 - 탄생</title>
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
            border-bottom: 3px solid #3498db;
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
            color: #3498db;
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
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 1.1em;
            transition: background 0.3s;
        }
        .btn-primary:hover {
            background: #2980b9;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <main>
        <div class="container">
            <div class="page-header">
                <h1>펄라이트 배지</h1>
                <p>가볍고 통기성이 뛰어난 무기질 배지</p>
            </div>
        </div>

    <div class="product-content">
        <div class="product-section">
            <h2>펄라이트 배지란?</h2>
            <p>펄라이트(Perlite)는 화산암을 고온으로 가열하여 팽창시킨 무기질 배지입니다. 매우 가볍고 배수성과 통기성이 뛰어나며, 무균 상태로 제공되어 청결한 재배 환경을 만들어줍니다. 화학적으로 안정적이며 pH 중성을 유지합니다.</p>
        </div>

        <div class="product-section">
            <h2>주요 특징</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <h3>🪶 초경량 소재</h3>
                    <p>매우 가벼워 취급이 용이하고 운반 비용이 적게 듭니다.</p>
                </div>
                <div class="feature-card">
                    <h3>💨 최고의 배수성</h3>
                    <p>과습을 방지하고 뿌리가 항상 신선한 공기에 접촉할 수 있습니다.</p>
                </div>
                <div class="feature-card">
                    <h3>🧪 화학적 안정성</h3>
                    <p>무기질 소재로 pH 변화가 없고 영양분과 반응하지 않습니다.</p>
                </div>
                <div class="feature-card">
                    <h3>🦠 무균 배지</h3>
                    <p>고온 처리로 병원균과 잡초 씨앗이 없는 청결한 배지입니다.</p>
                </div>
                <div class="feature-card">
                    <h3>♻️ 반영구적 사용</h3>
                    <p>부패하지 않아 세척 후 반복 사용이 가능합니다.</p>
                </div>
                <div class="feature-card">
                    <h3>🌡️ 온도 안정성</h3>
                    <p>단열 효과가 있어 뿌리 온도를 안정적으로 유지합니다.</p>
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
                    <td>화산암 (팽창 처리)</td>
                </tr>
                <tr>
                    <td>pH</td>
                    <td>7.0 ~ 7.5 (중성)</td>
                </tr>
                <tr>
                    <td>입자 크기</td>
                    <td>2~5mm (중립), 5~10mm (대립)</td>
                </tr>
                <tr>
                    <td>밀도</td>
                    <td>80~120 kg/m³</td>
                </tr>
                <tr>
                    <td>보수력</td>
                    <td>40~60%</td>
                </tr>
                <tr>
                    <td>공극률</td>
                    <td>95% 이상</td>
                </tr>
                <tr>
                    <td>포장 단위</td>
                    <td>100L 대포장, 20L 소포장</td>
                </tr>
            </table>
        </div>

        <div class="product-section">
            <h2>적용 작물</h2>
            <p>펄라이트 배지는 특히 배수를 중요시하는 작물에 적합합니다:</p>
            <ul style="line-height: 2; margin-left: 20px;">
                <li>🌵 다육식물 및 선인장</li>
                <li>🌸 난초류</li>
                <li>🌿 허브류</li>
                <li>🥗 수경재배 엽채류</li>
                <li>🌱 육묘용 배지</li>
            </ul>
        </div>

        <div class="product-section">
            <h2>사용 방법</h2>
            <ol style="line-height: 2; margin-left: 20px;">
                <li>단독 사용 또는 다른 배지와 혼합하여 사용</li>
                <li>코코피트와 3:7 비율로 혼합 시 최적의 성능 발휘</li>
                <li>사용 전 물로 가볍게 세척하여 미세 분진 제거</li>
                <li>재사용 시 세척 및 소독 후 사용</li>
            </ol>
        </div>

        <div class="cta-section">
            <h3>펄라이트 배지 문의</h3>
            <p>제품에 대해 궁금하신 점이 있으신가요? 전문가가 상담해 드립니다.</p>
            <a href="/pages/support/contact.php" class="btn-primary">문의하기</a>
        </div>
    </div>
    </main>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>

<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>혼합 배지 - 탄생</title>
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
            border-bottom: 3px solid #e74c3c;
            padding-bottom: 10px;
        }
        .mix-types {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        .mix-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 5px solid #e74c3c;
        }
        .mix-card h3 {
            color: #e74c3c;
            font-size: 1.8em;
            margin-bottom: 15px;
        }
        .mix-card .ratio {
            font-size: 1.5em;
            color: #3498db;
            font-weight: bold;
            margin: 15px 0;
        }
        .benefits {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 15px;
        }
        .benefits h4 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .benefits ul {
            margin-left: 20px;
            line-height: 1.8;
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
            background: #e74c3c;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 1.1em;
            transition: background 0.3s;
        }
        .btn-primary:hover {
            background: #c0392b;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <main>
        <div class="container">
            <div class="page-header">
                <h1>혼합 배지</h1>
                <p>각 배지의 장점을 결합한 맞춤형 솔루션</p>
            </div>
        </div>

    <div class="product-content">
        <div class="product-section">
            <h2>혼합 배지란?</h2>
            <p>혼합 배지는 코코피트, 펄라이트 등 여러 배지를 최적의 비율로 혼합하여 각각의 장점을 극대화한 배지입니다. 작물의 특성과 재배 환경에 따라 맞춤 배합이 가능하며, 보수력과 배수성의 완벽한 균형을 제공합니다.</p>
        </div>

        <div class="product-section">
            <h2>추천 배합 비율</h2>
            <div class="mix-types">
                <div class="mix-card">
                    <h3>🥬 엽채류용 배지</h3>
                    <div class="ratio">코코피트 70% : 펄라이트 30%</div>
                    <div class="benefits">
                        <h4>특징</h4>
                        <ul>
                            <li>적당한 보수력과 통기성</li>
                            <li>빠른 성장 속도</li>
                            <li>균일한 수분 분포</li>
                        </ul>
                        <h4 style="margin-top: 15px;">적용 작물</h4>
                        <ul>
                            <li>상추, 케일, 청경채</li>
                        </ul>
                    </div>
                </div>

                <div class="mix-card">
                    <h3>🍅 과채류용 배지</h3>
                    <div class="ratio">코코피트 60% : 펄라이트 40%</div>
                    <div class="benefits">
                        <h4>특징</h4>
                        <ul>
                            <li>우수한 배수성</li>
                            <li>강한 뿌리 발달</li>
                            <li>과습 방지</li>
                        </ul>
                        <h4 style="margin-top: 15px;">적용 작물</h4>
                        <ul>
                            <li>토마토, 파프리카, 오이</li>
                        </ul>
                    </div>
                </div>

                <div class="mix-card">
                    <h3>🍓 딸기용 배지</h3>
                    <div class="ratio">코코피트 50% : 펄라이트 50%</div>
                    <div class="benefits">
                        <h4>특징</h4>
                        <ul>
                            <li>최적의 배수성</li>
                            <li>뿌리 호흡 원활</li>
                            <li>병해 예방</li>
                        </ul>
                        <h4 style="margin-top: 15px;">적용 작물</h4>
                        <ul>
                            <li>딸기</li>
                        </ul>
                    </div>
                </div>

                <div class="mix-card">
                    <h3>🌹 화훼류용 배지</h3>
                    <div class="ratio">코코피트 65% : 펄라이트 35%</div>
                    <div class="benefits">
                        <h4>특징</h4>
                        <ul>
                            <li>안정적인 수분 공급</li>
                            <li>뿌리 성장 촉진</li>
                            <li>개화 품질 향상</li>
                        </ul>
                        <h4 style="margin-top: 15px;">적용 작물</h4>
                        <ul>
                            <li>장미, 국화, 거베라</li>
                        </ul>
                    </div>
                </div>

                <div class="mix-card">
                    <h3>🌱 육묘용 배지</h3>
                    <div class="ratio">코코피트 80% : 펄라이트 20%</div>
                    <div class="benefits">
                        <h4>특징</h4>
                        <ul>
                            <li>높은 보수력</li>
                            <li>안정적인 발아</li>
                            <li>초기 성장 지원</li>
                        </ul>
                        <h4 style="margin-top: 15px;">용도</h4>
                        <ul>
                            <li>모든 작물의 육묘 단계</li>
                        </ul>
                    </div>
                </div>

                <div class="mix-card">
                    <h3>🎯 맞춤형 배지</h3>
                    <div class="ratio">고객 요구사항에 따라</div>
                    <div class="benefits">
                        <h4>특징</h4>
                        <ul>
                            <li>재배 환경 분석</li>
                            <li>작물 특성 고려</li>
                            <li>최적 배합 제안</li>
                        </ul>
                        <h4 style="margin-top: 15px;">서비스</h4>
                        <ul>
                            <li>전문가 상담 제공</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="product-section">
            <h2>혼합 배지의 장점</h2>
            <table class="specs-table">
                <tr>
                    <th>구분</th>
                    <th>코코피트 단독</th>
                    <th>펄라이트 단독</th>
                    <th>혼합 배지</th>
                </tr>
                <tr>
                    <td>보수력</td>
                    <td>⭐⭐⭐⭐⭐</td>
                    <td>⭐⭐</td>
                    <td>⭐⭐⭐⭐</td>
                </tr>
                <tr>
                    <td>배수성</td>
                    <td>⭐⭐⭐</td>
                    <td>⭐⭐⭐⭐⭐</td>
                    <td>⭐⭐⭐⭐</td>
                </tr>
                <tr>
                    <td>통기성</td>
                    <td>⭐⭐⭐</td>
                    <td>⭐⭐⭐⭐⭐</td>
                    <td>⭐⭐⭐⭐</td>
                </tr>
                <tr>
                    <td>무게</td>
                    <td>보통</td>
                    <td>매우 가벼움</td>
                    <td>가벼움</td>
                </tr>
                <tr>
                    <td>재사용성</td>
                    <td>⭐⭐⭐</td>
                    <td>⭐⭐⭐⭐⭐</td>
                    <td>⭐⭐⭐⭐</td>
                </tr>
            </table>
        </div>

        <div class="product-section">
            <h2>사용 가이드</h2>
            <ol style="line-height: 2; margin-left: 20px;">
                <li><strong>작물 선택:</strong> 재배하려는 작물의 특성을 파악합니다</li>
                <li><strong>배합 선택:</strong> 위의 추천 비율을 참고하거나 맞춤 상담을 받습니다</li>
                <li><strong>배지 준비:</strong> 각 배지를 정확한 비율로 혼합합니다</li>
                <li><strong>수분 조절:</strong> 충분히 물을 공급하여 배지를 적십니다</li>
                <li><strong>정식:</strong> 준비된 배지에 작물을 심습니다</li>
                <li><strong>관리:</strong> 정기적인 양분 공급과 수분 관리를 합니다</li>
            </ol>
        </div>

        <div class="cta-section">
            <h3>맞춤형 혼합 배지 상담</h3>
            <p>귀하의 재배 환경에 최적화된 배지 배합을 제안해 드립니다</p>
            <a href="/pages/support/contact.php" class="btn-primary">전문가 상담 신청</a>
        </div>
    </div>
    </main>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>

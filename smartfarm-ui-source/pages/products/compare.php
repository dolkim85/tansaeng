<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>제품 비교 - 탄생</title>
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
        .compare-content {
            max-width: 1400px;
            margin: 50px auto;
            padding: 0 20px;
        }
        .compare-table-wrapper {
            overflow-x: auto;
            margin-top: 30px;
        }
        .compare-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .compare-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .compare-table th {
            padding: 20px;
            text-align: center;
            font-size: 1.3em;
        }
        .compare-table td {
            padding: 15px;
            text-align: center;
            border: 1px solid #e0e0e0;
        }
        .compare-table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }
        .compare-table tbody tr:hover {
            background: #e3f2fd;
        }
        .category-header {
            background: #34495e !important;
            color: white;
            font-weight: bold;
            text-align: left !important;
            font-size: 1.1em;
        }
        .rating {
            font-size: 1.2em;
        }
        .pros-cons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }
        .pros-cons-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .pros-cons-card h3 {
            font-size: 1.8em;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 3px solid #2ecc71;
        }
        .pros-cons-card.coco h3 {
            border-color: #2ecc71;
            color: #2ecc71;
        }
        .pros-cons-card.perlite h3 {
            border-color: #3498db;
            color: #3498db;
        }
        .pros-cons-card.mixed h3 {
            border-color: #e74c3c;
            color: #e74c3c;
        }
        .pros, .cons {
            margin-top: 20px;
        }
        .pros h4 {
            color: #27ae60;
            margin-bottom: 10px;
        }
        .cons h4 {
            color: #e74c3c;
            margin-bottom: 10px;
        }
        .pros ul, .cons ul {
            list-style: none;
            padding: 0;
        }
        .pros li::before {
            content: "✅ ";
            margin-right: 8px;
        }
        .cons li::before {
            content: "⚠️ ";
            margin-right: 8px;
        }
        .pros li, .cons li {
            margin-bottom: 10px;
            line-height: 1.6;
        }
        .recommendation {
            background: #f8f9fa;
            padding: 40px;
            border-radius: 10px;
            margin-top: 50px;
        }
        .recommendation h2 {
            font-size: 2em;
            margin-bottom: 30px;
            text-align: center;
        }
        .rec-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .rec-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            border-left: 5px solid #3498db;
        }
        .rec-card h4 {
            color: #2c3e50;
            font-size: 1.3em;
            margin-bottom: 15px;
        }
        .btn-group {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 40px;
            flex-wrap: wrap;
        }
        .btn {
            padding: 15px 30px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 1.1em;
            transition: transform 0.3s;
        }
        .btn:hover {
            transform: translateY(-3px);
        }
        .btn-coco {
            background: #2ecc71;
            color: white;
        }
        .btn-perlite {
            background: #3498db;
            color: white;
        }
        .btn-mixed {
            background: #e74c3c;
            color: white;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <main>
        <div class="container">
            <div class="page-header">
                <h1>제품 비교</h1>
                <p>각 배지의 특성을 한눈에 비교하고 최적의 선택을 하세요</p>
            </div>
        </div>

    <div class="compare-content">
        <div class="compare-table-wrapper">
            <table class="compare-table">
                <thead>
                    <tr>
                        <th style="width: 25%;">비교 항목</th>
                        <th style="width: 25%;">🥥 코코피트</th>
                        <th style="width: 25%;">⚪ 펄라이트</th>
                        <th style="width: 25%;">🔄 혼합 배지</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="category-header">
                        <td colspan="4">기본 특성</td>
                    </tr>
                    <tr>
                        <td><strong>원료</strong></td>
                        <td>코코넛 껍질 (천연)</td>
                        <td>화산암 (무기질)</td>
                        <td>코코피트 + 펄라이트</td>
                    </tr>
                    <tr>
                        <td><strong>보수력</strong></td>
                        <td><span class="rating">⭐⭐⭐⭐⭐</span><br>매우 높음</td>
                        <td><span class="rating">⭐⭐</span><br>낮음</td>
                        <td><span class="rating">⭐⭐⭐⭐</span><br>높음</td>
                    </tr>
                    <tr>
                        <td><strong>배수성</strong></td>
                        <td><span class="rating">⭐⭐⭐</span><br>보통</td>
                        <td><span class="rating">⭐⭐⭐⭐⭐</span><br>매우 높음</td>
                        <td><span class="rating">⭐⭐⭐⭐</span><br>높음</td>
                    </tr>
                    <tr>
                        <td><strong>통기성</strong></td>
                        <td><span class="rating">⭐⭐⭐</span><br>양호</td>
                        <td><span class="rating">⭐⭐⭐⭐⭐</span><br>우수</td>
                        <td><span class="rating">⭐⭐⭐⭐</span><br>우수</td>
                    </tr>

                    <tr class="category-header">
                        <td colspan="4">물리적 특성</td>
                    </tr>
                    <tr>
                        <td><strong>무게</strong></td>
                        <td>보통 (물 흡수 시 무거움)</td>
                        <td>매우 가벼움</td>
                        <td>가벼움</td>
                    </tr>
                    <tr>
                        <td><strong>pH</strong></td>
                        <td>5.5 ~ 6.5 (약산성)</td>
                        <td>7.0 ~ 7.5 (중성)</td>
                        <td>6.0 ~ 7.0 (약산~중성)</td>
                    </tr>
                    <tr>
                        <td><strong>분해성</strong></td>
                        <td>시간 경과 시 분해됨</td>
                        <td>분해되지 않음</td>
                        <td>부분 분해</td>
                    </tr>

                    <tr class="category-header">
                        <td colspan="4">경제성</td>
                    </tr>
                    <tr>
                        <td><strong>가격</strong></td>
                        <td>저렴</td>
                        <td>보통</td>
                        <td>보통</td>
                    </tr>
                    <tr>
                        <td><strong>재사용성</strong></td>
                        <td>2~3회</td>
                        <td>5회 이상</td>
                        <td>3~4회</td>
                    </tr>
                    <tr>
                        <td><strong>운반 비용</strong></td>
                        <td>보통</td>
                        <td>낮음 (가벼움)</td>
                        <td>낮음</td>
                    </tr>

                    <tr class="category-header">
                        <td colspan="4">재배 적합성</td>
                    </tr>
                    <tr>
                        <td><strong>엽채류</strong></td>
                        <td>⭐⭐⭐⭐⭐ 매우 적합</td>
                        <td>⭐⭐⭐ 보통</td>
                        <td>⭐⭐⭐⭐⭐ 매우 적합</td>
                    </tr>
                    <tr>
                        <td><strong>과채류</strong></td>
                        <td>⭐⭐⭐⭐ 적합</td>
                        <td>⭐⭐⭐ 보통</td>
                        <td>⭐⭐⭐⭐⭐ 매우 적합</td>
                    </tr>
                    <tr>
                        <td><strong>딸기</strong></td>
                        <td>⭐⭐⭐ 보통</td>
                        <td>⭐⭐⭐⭐ 적합</td>
                        <td>⭐⭐⭐⭐⭐ 매우 적합</td>
                    </tr>
                    <tr>
                        <td><strong>화훼류</strong></td>
                        <td>⭐⭐⭐⭐ 적합</td>
                        <td>⭐⭐⭐⭐ 적합</td>
                        <td>⭐⭐⭐⭐⭐ 매우 적합</td>
                    </tr>
                    <tr>
                        <td><strong>육묘</strong></td>
                        <td>⭐⭐⭐⭐⭐ 매우 적합</td>
                        <td>⭐⭐ 부적합</td>
                        <td>⭐⭐⭐⭐ 적합</td>
                    </tr>

                    <tr class="category-header">
                        <td colspan="4">관리 편의성</td>
                    </tr>
                    <tr>
                        <td><strong>병해충 저항</strong></td>
                        <td>양호</td>
                        <td>우수 (무균)</td>
                        <td>우수</td>
                    </tr>
                    <tr>
                        <td><strong>염류 집적</strong></td>
                        <td>주의 필요</td>
                        <td>낮음</td>
                        <td>보통</td>
                    </tr>
                    <tr>
                        <td><strong>세척 용이성</strong></td>
                        <td>보통</td>
                        <td>쉬움</td>
                        <td>보통</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="pros-cons">
            <div class="pros-cons-card coco">
                <h3>🥥 코코피트</h3>
                <div class="pros">
                    <h4>장점</h4>
                    <ul>
                        <li>우수한 보수력으로 안정적인 수분 공급</li>
                        <li>친환경 천연 소재</li>
                        <li>가격이 저렴하고 구하기 쉬움</li>
                        <li>대부분의 작물에 적합</li>
                        <li>완충력이 좋아 pH 변화가 적음</li>
                    </ul>
                </div>
                <div class="cons">
                    <h4>단점</h4>
                    <ul>
                        <li>배수성이 다소 떨어짐</li>
                        <li>시간이 지나면 분해됨</li>
                        <li>초기 EC 관리 필요</li>
                        <li>재사용 횟수가 제한적</li>
                    </ul>
                </div>
            </div>

            <div class="pros-cons-card perlite">
                <h3>⚪ 펄라이트</h3>
                <div class="pros">
                    <h4>장점</h4>
                    <ul>
                        <li>최고의 배수성과 통기성</li>
                        <li>매우 가벼워 다루기 쉬움</li>
                        <li>무균 상태로 병해 걱정 없음</li>
                        <li>화학적으로 안정적</li>
                        <li>반영구적으로 재사용 가능</li>
                    </ul>
                </div>
                <div class="cons">
                    <h4>단점</h4>
                    <ul>
                        <li>보수력이 낮아 잦은 관수 필요</li>
                        <li>가벼워서 바람에 날릴 수 있음</li>
                        <li>단독 사용 시 영양분 보유력 낮음</li>
                        <li>미세 분진이 발생할 수 있음</li>
                    </ul>
                </div>
            </div>

            <div class="pros-cons-card mixed">
                <h3>🔄 혼합 배지</h3>
                <div class="pros">
                    <h4>장점</h4>
                    <ul>
                        <li>각 배지의 장점을 결합</li>
                        <li>보수력과 배수성의 균형</li>
                        <li>작물별 맞춤 배합 가능</li>
                        <li>안정적인 재배 환경</li>
                        <li>최적의 성능 발휘</li>
                    </ul>
                </div>
                <div class="cons">
                    <h4>단점</h4>
                    <ul>
                        <li>배합 비율 조정 필요</li>
                        <li>초기 혼합 작업 필요</li>
                        <li>단일 배지보다 관리가 복잡할 수 있음</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="recommendation">
            <h2>🎯 작물별 추천 배지</h2>
            <div class="rec-grid">
                <div class="rec-card">
                    <h4>🥬 엽채류 (상추, 케일, 청경채)</h4>
                    <p><strong>추천:</strong> 코코피트 또는 혼합배지 (7:3)</p>
                    <p>빠른 생장과 안정적인 수분 공급이 필요한 엽채류는 코코피트 위주의 배지가 적합합니다.</p>
                </div>

                <div class="rec-card">
                    <h4>🍅 과채류 (토마토, 파프리카)</h4>
                    <p><strong>추천:</strong> 혼합배지 (6:4)</p>
                    <p>장기 재배하는 과채류는 배수성이 좋은 혼합 배지로 뿌리 건강을 유지하세요.</p>
                </div>

                <div class="rec-card">
                    <h4>🍓 딸기</h4>
                    <p><strong>추천:</strong> 혼합배지 (5:5)</p>
                    <p>과습에 약한 딸기는 배수성이 우수한 동량 혼합 배지가 최적입니다.</p>
                </div>

                <div class="rec-card">
                    <h4>🌹 화훼류 (장미, 국화)</h4>
                    <p><strong>추천:</strong> 혼합배지 (6.5:3.5)</p>
                    <p>뿌리 발달이 중요한 화훼류는 적절한 보수력과 통기성이 필요합니다.</p>
                </div>

                <div class="rec-card">
                    <h4>🌱 육묘</h4>
                    <p><strong>추천:</strong> 코코피트 또는 혼합배지 (8:2)</p>
                    <p>초기 생육에는 안정적인 수분 공급이 중요하므로 코코피트 비율을 높입니다.</p>
                </div>

                <div class="rec-card">
                    <h4>🌿 허브류</h4>
                    <p><strong>추천:</strong> 혼합배지 (5:5) 또는 펄라이트</p>
                    <p>건조를 선호하는 허브류는 배수성이 좋은 배지가 적합합니다.</p>
                </div>
            </div>
        </div>

        <div class="btn-group">
            <a href="/pages/products/coco.php" class="btn btn-coco">🥥 코코피트 상세보기</a>
            <a href="/pages/products/perlite.php" class="btn btn-perlite">⚪ 펄라이트 상세보기</a>
            <a href="/pages/products/mixed.php" class="btn btn-mixed">🔄 혼합 배지 상세보기</a>
        </div>
    </div>
    </main>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>

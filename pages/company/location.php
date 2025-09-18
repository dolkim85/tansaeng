<?php
/**
 * Company Location Page - 오시는길
 * 탄생 본사 위치 및 찾아오는 방법
 */

$currentUser = null;
try {
    require_once __DIR__ . '/../../classes/Auth.php';
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
}

$pageTitle = "오시는길 - 탄생";
$pageDescription = "탄생 스마트팜 본사 위치와 대중교통 이용방법을 안내해드립니다.";
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
        .location-hero {
            background: linear-gradient(135deg, #2E7D32 0%, #1B5E20 100%);
            color: white;
            padding: 80px 0;
            text-align: center;
        }
        .location-content {
            padding: 60px 0;
        }
        .location-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 50px;
        }
        .address-card {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
        .address-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2E7D32;
            margin-bottom: 20px;
        }
        .address-info {
            font-size: 1.1rem;
            line-height: 1.8;
            color: #333;
        }
        .map-container {
            background: #f8f9fa;
            border-radius: 12px;
            height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            font-size: 1.1rem;
        }
        .transport-section {
            background: #f8f9fa;
            padding: 60px 0;
        }
        .transport-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
        }
        .transport-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }
        .transport-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        .transport-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2E7D32;
            margin-bottom: 15px;
        }
        .contact-section {
            padding: 60px 0;
            text-align: center;
        }
        .contact-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }
        .contact-item {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }
        @media (max-width: 768px) {
            .location-grid {
                grid-template-columns: 1fr;
            }
            .transport-grid, .contact-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <!-- Hero Section -->
    <section class="location-hero">
        <div class="container">
            <h1>🗺️ 오시는길</h1>
            <p>탄생 스마트팜 본사로 오시는 방법을 안내해드립니다</p>
        </div>
    </section>

    <!-- Location Content -->
    <section class="location-content">
        <div class="container">
            <div class="location-grid">
                <!-- Address Information -->
                <div class="address-card">
                    <h2 class="address-title">🏢 본사 주소</h2>
                    <div class="address-info">
                        <p><strong>주소:</strong><br>
                        서울특별시 강남구 테헤란로 123<br>
                        스마트팜빌딩 5층</p>

                        <p><strong>우편번호:</strong> 06234</p>

                        <p><strong>대표전화:</strong> 1588-0000</p>

                        <p><strong>팩스:</strong> 02-1234-5678</p>

                        <p><strong>이메일:</strong> contact@tansaeng.com</p>

                        <p><strong>운영시간:</strong><br>
                        평일: 09:00 - 18:00<br>
                        토요일: 09:00 - 12:00<br>
                        일요일/공휴일: 휴무</p>
                    </div>
                </div>

                <!-- Map Placeholder -->
                <div class="map-container">
                    <div>
                        <h3 style="color: #2E7D32; margin-bottom: 15px;">🗺️ 지도</h3>
                        <p>서울특별시 강남구 테헤란로 123<br>
                        스마트팜빌딩 5층</p>
                        <p style="margin-top: 20px; font-size: 0.9rem;">
                            * 실제 서비스에서는 네이버지도, 카카오맵,<br>
                            구글맵 등의 지도 서비스가 연동됩니다.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Transportation -->
    <section class="transport-section">
        <div class="container">
            <h2 style="text-align: center; margin-bottom: 40px; color: #2E7D32;">대중교통 이용안내</h2>

            <div class="transport-grid">
                <!-- Subway -->
                <div class="transport-card">
                    <div class="transport-icon">🚇</div>
                    <h3 class="transport-title">지하철</h3>
                    <p>
                        <strong>2호선 강남역</strong><br>
                        3번 출구에서 도보 5분<br><br>

                        <strong>9호선 선릉역</strong><br>
                        1번 출구에서 도보 8분
                    </p>
                </div>

                <!-- Bus -->
                <div class="transport-card">
                    <div class="transport-icon">🚌</div>
                    <h3 class="transport-title">버스</h3>
                    <p>
                        <strong>간선버스</strong><br>
                        146, 401, 741<br><br>

                        <strong>지선버스</strong><br>
                        2415, 3411, 4318<br><br>

                        강남역 정류장 하차
                    </p>
                </div>

                <!-- Car -->
                <div class="transport-card">
                    <div class="transport-icon">🚗</div>
                    <h3 class="transport-title">자가용</h3>
                    <p>
                        <strong>네비게이션 검색</strong><br>
                        "탄생 스마트팜" 또는<br>
                        "서울시 강남구 테헤란로 123"<br><br>

                        <strong>주차안내</strong><br>
                        건물 지하 1-3층 주차장<br>
                        (방문고객 2시간 무료)
                    </p>
                </div>

                <!-- Airport -->
                <div class="transport-card">
                    <div class="transport-icon">✈️</div>
                    <h3 class="transport-title">공항에서</h3>
                    <p>
                        <strong>인천공항</strong><br>
                        공항철도 → 홍대입구<br>
                        → 2호선 강남역<br>
                        (약 1시간 20분)<br><br>

                        <strong>김포공항</strong><br>
                        9호선 → 선릉역<br>
                        (약 50분)
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact-section">
        <div class="container">
            <h2 style="color: #2E7D32; margin-bottom: 20px;">방문 전 연락주세요</h2>
            <p style="color: #666; font-size: 1.1rem;">
                보다 자세한 상담과 원활한 업무 처리를 위해<br>
                방문 전에 미리 연락주시면 감사하겠습니다.
            </p>

            <div class="contact-grid">
                <div class="contact-item">
                    <div style="font-size: 2rem; margin-bottom: 10px;">📞</div>
                    <h4>전화 예약</h4>
                    <p><strong>1588-0000</strong></p>
                </div>

                <div class="contact-item">
                    <div style="font-size: 2rem; margin-bottom: 10px;">📧</div>
                    <h4>이메일 예약</h4>
                    <p><strong>visit@tansaeng.com</strong></p>
                </div>

                <div class="contact-item">
                    <div style="font-size: 2rem; margin-bottom: 10px;">💬</div>
                    <h4>카카오톡</h4>
                    <p><strong>@탄생스마트팜</strong></p>
                </div>

                <div class="contact-item">
                    <div style="font-size: 2rem; margin-bottom: 10px;">📝</div>
                    <h4>온라인 예약</h4>
                    <p><a href="/pages/support/contact.php" style="color: #2E7D32;">예약하기</a></p>
                </div>
            </div>
        </div>
    </section>

    <?php include '../../includes/footer.php'; ?>

    <script src="/assets/js/main.js"></script>
</body>
</html>
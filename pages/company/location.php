<?php
/**
 * Company Location Page - 오시는길
 * 탄생 본사 위치 및 찾아오는 방법
 * Google Maps API 사용
 */

$currentUser = null;
$siteSettings = [];
try {
    require_once __DIR__ . '/../../classes/Auth.php';
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../config/env.php';
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();

    $pdo = DatabaseConfig::getConnection();
    $sql = "SELECT setting_key, setting_value FROM site_settings";
    $stmt = $pdo->query($sql);
    while ($row = $stmt->fetch()) {
        $siteSettings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
}

$kakaoMapApiKey = env('KAKAO_MAP_API_KEY', '');
$companyAddress = $siteSettings['company_address'] ?? $siteSettings['footer_address'] ?? '울산광역시 울주군 웅촌면 서리길 81';
$companyPhone = $siteSettings['contact_phone'] ?? $siteSettings['footer_phone'] ?? '052-000-0000';
$companyEmail = $siteSettings['contact_email'] ?? $siteSettings['footer_email'] ?? 'contact@tansaeng.com';
$companyFax = $siteSettings['company_fax'] ?? '';
$ceoName = $siteSettings['ceo_name'] ?? '';

// 회사 좌표 (울산 웅촌면 서리)
$companyLat = 35.4676;
$companyLng = 129.1860;

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
            grid-template-columns: 1fr 1.2fr;
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
            font-size: 1.05rem;
            line-height: 1.8;
            color: #333;
        }
        .address-info p {
            margin-bottom: 12px;
        }
        .address-info strong {
            color: #2E7D32;
        }
        #kakao-map {
            width: 100%;
            height: 400px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
        .map-fallback {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 400px;
            background: #f0f0f0;
            border-radius: 12px;
            color: #666;
            flex-direction: column;
            gap: 15px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
        .map-fallback a {
            color: #2E7D32;
            text-decoration: none;
            font-weight: 600;
            padding: 10px 20px;
            border: 2px solid #2E7D32;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .map-fallback a:hover {
            background: #2E7D32;
            color: white;
        }
        .transport-section {
            background: #f8f9fa;
            padding: 60px 0;
        }
        .transport-section h2 {
            text-align: center;
            margin-bottom: 40px;
            color: #2E7D32;
            font-size: 1.5rem;
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
        .transport-card p {
            line-height: 1.7;
            color: #555;
        }
        .contact-section {
            padding: 60px 0;
            text-align: center;
        }
        .contact-section h2 {
            color: #2E7D32;
            margin-bottom: 20px;
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
        .contact-item h4 {
            margin: 10px 0 5px;
            color: #333;
        }

        @media (max-width: 768px) {
            .location-hero {
                height: 100px !important;
                padding: 0 !important;
            }
            .location-hero .container {
                padding: 1.5rem 1rem !important;
                display: flex;
                flex-direction: column;
                justify-content: center;
                height: 100%;
            }
            .location-hero h1 {
                font-size: 1.2rem !important;
            }
            .location-hero p {
                font-size: 0.85rem !important;
            }
            .location-content {
                padding: 30px 0 !important;
            }
            .location-grid {
                grid-template-columns: 1fr;
                gap: 20px !important;
            }
            #kakao-map, .map-fallback {
                height: 300px !important;
            }
            .address-card {
                padding: 20px !important;
            }
            .address-title {
                font-size: 1.2rem !important;
            }
            .address-info {
                font-size: 0.95rem !important;
            }
            .transport-section {
                padding: 30px 0 !important;
            }
            .transport-section h2 {
                font-size: 1.2rem !important;
                margin-bottom: 20px !important;
            }
            .transport-grid {
                grid-template-columns: 1fr;
                gap: 15px !important;
            }
            .transport-card {
                padding: 20px !important;
            }
            .contact-section {
                padding: 30px 0 !important;
            }
            .contact-grid {
                grid-template-columns: 1fr 1fr;
                gap: 15px !important;
                margin-top: 20px !important;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main>
    <!-- Hero Section -->
    <section class="location-hero">
        <div class="container">
            <h1>오시는길</h1>
            <p>탄생 스마트팜 본사로 오시는 방법을 안내해드립니다</p>
        </div>
    </section>

    <!-- Location Content -->
    <section class="location-content">
        <div class="container">
            <div class="location-grid">
                <!-- Address Information -->
                <div class="address-card">
                    <h2 class="address-title">본사 주소</h2>
                    <div class="address-info">
                        <p><strong>주소</strong><br>
                        <?= htmlspecialchars($companyAddress) ?></p>

                        <p><strong>대표전화</strong><br>
                        <?= htmlspecialchars($companyPhone) ?></p>

                        <?php if ($companyFax): ?>
                        <p><strong>팩스</strong><br>
                        <?= htmlspecialchars($companyFax) ?></p>
                        <?php endif; ?>

                        <p><strong>이메일</strong><br>
                        <?= htmlspecialchars($companyEmail) ?></p>

                        <p><strong>운영시간</strong><br>
                        <?= nl2br(htmlspecialchars($siteSettings['company_business_hours'] ?? "평일: 09:00 - 18:00\n토요일: 09:00 - 12:00\n일요일/공휴일: 휴무")) ?></p>
                    </div>
                </div>

                <!-- Kakao Map or Fallback -->
                <?php if ($kakaoMapApiKey): ?>
                <div id="kakao-map"></div>
                <?php else: ?>
                <div class="map-fallback">
                    <p style="text-align:center;">
                        <strong style="font-size:1.2rem;">📍 탄생 스마트팜</strong><br><br>
                        <?= htmlspecialchars($companyAddress) ?>
                    </p>
                    <a href="https://map.kakao.com/link/search/<?= urlencode($companyAddress) ?>" target="_blank">
                        카카오맵에서 보기 →
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Transportation -->
    <section class="transport-section">
        <div class="container">
            <h2>교통안내</h2>
            <div class="transport-grid">
                <div class="transport-card">
                    <div class="transport-icon">🚗</div>
                    <h3 class="transport-title">자가용</h3>
                    <p>
                        <strong>네비게이션 검색</strong><br>
                        "탄생" 또는<br>
                        "울주군 웅촌면 서리길 81"<br><br>
                        <strong>주차안내</strong><br>
                        건물 앞 주차 가능
                    </p>
                </div>

                <div class="transport-card">
                    <div class="transport-icon">🚌</div>
                    <h3 class="transport-title">버스</h3>
                    <p>
                        웅촌면 방면 시내버스 이용<br>
                        서리 정류장 하차
                    </p>
                </div>

                <div class="transport-card">
                    <div class="transport-icon">🚇</div>
                    <h3 class="transport-title">KTX / 기차</h3>
                    <p>
                        <strong>울산역(KTX)</strong> 하차 후<br>
                        택시 약 20분<br><br>
                        <strong>태화강역</strong> 하차 후<br>
                        택시 약 30분
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact-section">
        <div class="container">
            <h2>방문 전 연락주세요</h2>
            <p style="color: #666; font-size: 1.05rem;">
                원활한 상담을 위해 방문 전 미리 연락 부탁드립니다.
            </p>

            <div class="contact-grid">
                <div class="contact-item">
                    <div style="font-size: 2rem;">📞</div>
                    <h4>전화</h4>
                    <p><strong><?= htmlspecialchars($companyPhone) ?></strong></p>
                </div>
                <div class="contact-item">
                    <div style="font-size: 2rem;">📧</div>
                    <h4>이메일</h4>
                    <p><strong><?= htmlspecialchars($companyEmail) ?></strong></p>
                </div>
                <div class="contact-item">
                    <div style="font-size: 2rem;">💬</div>
                    <h4>카카오톡</h4>
                    <p><strong>@탄생스마트팜</strong></p>
                </div>
                <div class="contact-item">
                    <div style="font-size: 2rem;">📝</div>
                    <h4>온라인 문의</h4>
                    <p><a href="/pages/support/inquiry.php" style="color: #2E7D32; font-weight: 600;">문의하기</a></p>
                </div>
            </div>
        </div>
    </section>
    </main>

    <?php include '../../includes/footer.php'; ?>

    <script src="/assets/js/main.js"></script>
    <?php if ($kakaoMapApiKey): ?>
    <script>
    var companyAddress = <?= json_encode($companyAddress, JSON_UNESCAPED_UNICODE) ?>;
    var companyLat = <?= $companyLat ?>;
    var companyLng = <?= $companyLng ?>;

    function initKakaoMap() {
        var container = document.getElementById('kakao-map');
        if (!container) return;
        var position = new kakao.maps.LatLng(companyLat, companyLng);

        var options = {
            center: position,
            level: 3
        };

        var map = new kakao.maps.Map(container, options);

        // 지도 컨트롤 추가
        var zoomControl = new kakao.maps.ZoomControl();
        map.addControl(zoomControl, kakao.maps.ControlPosition.RIGHT);

        var mapTypeControl = new kakao.maps.MapTypeControl();
        map.addControl(mapTypeControl, kakao.maps.ControlPosition.TOPRIGHT);

        // 마커 생성
        var marker = new kakao.maps.Marker({
            position: position,
            map: map
        });

        // 인포윈도우 내용
        var infoContent =
            '<div style="padding:15px;min-width:200px;line-height:1.6;">' +
            '<strong style="font-size:16px;color:#2E7D32;">탄생 스마트팜</strong><br>' +
            '<span style="color:#555;font-size:14px;">' + companyAddress + '</span><br><br>' +
            '<a href="https://map.kakao.com/link/to/탄생스마트팜,' + companyLat + ',' + companyLng + '" target="_blank" style="color:#3396FF;text-decoration:none;font-weight:600;font-size:14px;">길찾기 →</a>' +
            '</div>';

        var infoWindow = new kakao.maps.InfoWindow({
            content: infoContent
        });

        // 마커 클릭 시 인포윈도우 열기
        kakao.maps.event.addListener(marker, 'click', function() {
            infoWindow.open(map, marker);
        });

        // 기본으로 인포윈도우 열기
        infoWindow.open(map, marker);
    }
    </script>
    <script src="https://dapi.kakao.com/v2/maps/sdk.js?appkey=<?= htmlspecialchars($kakaoMapApiKey) ?>&autoload=false" onload="kakao.maps.load(initKakaoMap)"></script>
    <?php endif; ?>
</body>
</html>

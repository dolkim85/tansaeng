<?php
// 데이터베이스 연결을 선택적으로 처리
$currentUser = null;
$dbConnected = false;
$featuredProducts = [];
$productCategories = [];
$latestNews = [];

try {
    require_once __DIR__ . '/classes/Auth.php';
    require_once __DIR__ . '/classes/Database.php';
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $dbConnected = true;
    
    // 관리자에서 등록한 추천 상품 가져오기 (최대 3개)
    $stmt = $pdo->query("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN product_categories c ON p.category_id = c.id 
        WHERE p.is_featured = 1 AND p.status = 'active' 
        ORDER BY p.created_at DESC 
        LIMIT 3
    ");
    $featuredProducts = $stmt->fetchAll();
    
    // 카테고리 정보 가져오기
    $stmt = $pdo->query("
        SELECT * FROM product_categories 
        WHERE status = 'active' 
        ORDER BY name 
        LIMIT 3
    ");
    $productCategories = $stmt->fetchAll();
    
    // 최신 공지사항/뉴스 가져오기
    $stmt = $pdo->query("
        SELECT id, title, content, created_at 
        FROM board_posts 
        WHERE status = 'active' AND (is_notice = 1 OR post_type = 'general')
        ORDER BY is_notice DESC, created_at DESC 
        LIMIT 3
    ");
    $latestNews = $stmt->fetchAll();
    
} catch (Exception $e) {
    // 데이터베이스 연결 실패시 계속 진행
    error_log("Database connection failed: " . $e->getMessage());
}

// 사이트 설정값 불러오기
$site_settings = [];
if ($dbConnected) {
    try {
        // 먼저 테이블이 존재하는지 확인
        $table_check = $pdo->query("SHOW TABLES LIKE 'site_settings'")->fetch();
        
        if ($table_check) {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $site_settings[$row['setting_key']] = $row['setting_value'];
            }
            error_log("메인페이지 설정 로드됨: " . count($site_settings) . "개");
        } else {
            error_log("site_settings 테이블이 존재하지 않음");
        }
    } catch (Exception $e) {
        error_log("Failed to load site settings: " . $e->getMessage());
    }
}

// 기본값 설정
$defaults = [
    'hero_1_title' => '탄생 스마트팜 배지',
    'hero_1_subtitle' => '최고 품질의 수경재배용 배지로 건강한 농작물을 키워보세요',
    'hero_1_cta_text' => '제품 보기',
    'hero_1_cta_link' => '/pages/products/media.php',
    'hero_image_1' => '/assets/images/banners/hero-1.jpg',
    'hero_2_title' => 'AI 식물분석 시스템',
    'hero_2_subtitle' => '첨단 기술로 식물의 건강상태를 정확하게 분석합니다',
    'hero_2_cta_text' => '분석하기',
    'hero_2_cta_link' => '/pages/plant_analysis/',
    'hero_image_2' => '/assets/images/banners/hero-2.jpg',
    'hero_3_title' => '스마트팜 솔루션',
    'hero_3_subtitle' => '라즈베리파이와 AI 기술이 결합된 스마트한 농업',
    'hero_3_cta_text' => '자세히 보기',
    'hero_3_cta_link' => '/pages/company/about.php',
    'hero_image_3' => '/assets/images/banners/hero-3.jpg',
    'plant_analysis_video' => '/uploads/media/plant_analysis_video.mp4',
    'plant_analysis_title' => 'AI 식물분석 서비스',
    'plant_analysis_description' => '라즈베리파이 카메라와 AI 기술을 활용하여 식물의 건강상태를 실시간으로 분석하고 관리할 수 있습니다.',
    'company_intro_video' => '/uploads/media/company_intro_video.mp4',
    'company_intro_title' => '탄생 소개 영상',
    'company_intro_description' => '우리의 기술과 비전을 영상으로 만나보세요'
];

// 설정값과 기본값 합치기
$settings = array_merge($defaults, $site_settings);

$heroSlides = [
    [
        'image' => $settings['hero_image_1'],
        'title' => $settings['hero_1_title'],
        'subtitle' => $settings['hero_1_subtitle'],
        'cta_text' => $settings['hero_1_cta_text'],
        'cta_link' => $settings['hero_1_cta_link']
    ],
    [
        'image' => $settings['hero_image_2'],
        'title' => $settings['hero_2_title'],
        'subtitle' => $settings['hero_2_subtitle'],
        'cta_text' => $settings['hero_2_cta_text'],
        'cta_link' => $settings['hero_2_cta_link']
    ],
    [
        'image' => $settings['hero_image_3'],
        'title' => $settings['hero_3_title'],
        'subtitle' => $settings['hero_3_subtitle'],
        'cta_text' => $settings['hero_3_cta_text'],
        'cta_link' => $settings['hero_3_cta_link']
    ]
];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>탄생 - 스마트팜 배지 제조회사</title>
    <meta name="description" content="탄생은 최고 품질의 수경재배용 배지를 제조하는 스마트팜 전문 회사입니다. AI 식물분석 시스템과 함께하는 혁신적인 농업 솔루션을 경험하세요.">
    <link rel="stylesheet" href="/assets/css/main-new-1757783087.css">
    <link rel="stylesheet" href="/assets/css/home.css">
    <!-- CACHE TEST: <?= time() ?> -->
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main>
        <!-- Hero Section -->
        <section class="hero-section">
            <div style="padding: 50px 0; text-align: center; background: linear-gradient(135deg, #E3F2FD 0%, #BBDEFB 100%);">
                <h1>🎯 CSS 테스트 - <?= date('H:i:s') ?></h1>
                <p>새로운 CSS가 적용되었다면 이 페이지의 메뉴에서:</p>
                <ul style="text-align: left; max-width: 500px; margin: 20px auto;">
                    <li><strong>데스크톱:</strong> 홈, 기업소개, 배지설명, 스토어, 게시판, 고객지원, 식물분석</li>
                    <li><strong>모바일:</strong> 🏠 홈, 🏢 회사, 🌱 제품, 🛒 스토어, 📋 게시판, 💬 지원, 🔬 분석</li>
                </ul>
                <p><strong>현재 시간:</strong> <?= date('Y-m-d H:i:s') ?></p>
            </div>
        </section>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="/assets/js/main.js"></script>
    <script src="/assets/js/home.js"></script>
</body>
</html>
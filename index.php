<?php
/**
 * Main Index Page
 * Tansaeng Smart Farm Website
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$currentUser = null;
$siteSettings = [];

try {
    require_once __DIR__ . '/classes/Auth.php';
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();
} catch (Exception $e) {
    // Continue without user
}

// Load site settings from database and get featured products
$featuredProducts = [];
try {
    $pdo = DatabaseConfig::getConnection();
    $sql = "SELECT setting_key, setting_value FROM site_settings";
    $stmt = $pdo->query($sql);
    while ($row = $stmt->fetch()) {
        $siteSettings[$row['setting_key']] = $row['setting_value'];
    }

    // Get featured products first (priority to featured products)
    $stmt = $pdo->query(
        "SELECT p.*, c.name as category_name
         FROM products p
         LEFT JOIN categories c ON p.category_id = c.id
         WHERE p.is_featured = 1 AND p.status = 'active'
         ORDER BY p.created_at DESC LIMIT 6"
    );
    $featuredProducts = $stmt->fetchAll();

    // If we have less than 6 featured products, fill with recent active products
    $featuredCount = count($featuredProducts);
    if ($featuredCount < 6) {
        $remainingSlots = 6 - $featuredCount;

        // Get product IDs we already have to exclude them
        $excludeIds = array_column($featuredProducts, 'id');
        $excludeClause = !empty($excludeIds) ? 'AND p.id NOT IN (' . implode(',', $excludeIds) . ')' : '';

        $stmt = $pdo->query(
            "SELECT p.*, c.name as category_name
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.status = 'active' $excludeClause
             ORDER BY p.created_at DESC LIMIT $remainingSlots"
        );
        $additionalProducts = $stmt->fetchAll();

        // Merge the arrays
        $featuredProducts = array_merge($featuredProducts, $additionalProducts);
    }

    // Debug log for troubleshooting
    error_log("Total products shown: " . count($featuredProducts) . " (Featured: $featuredCount)");
} catch (Exception $e) {
    // Continue with default settings and empty products
}

// Meta information with dynamic settings
$pageTitle = $siteSettings['site_title'] ?? "탄생 - 스마트팜 배지 전문업체";
$pageDescription = $siteSettings['site_description'] ?? "고품질 수경재배 배지와 AI 식물분석 서비스를 제공하는 스마트팜 전문업체입니다.";
$pageKeywords = $siteSettings['site_keywords'] ?? "스마트팜, 배지, 수경재배, 코코피트, 펄라이트, 식물분석, AI";
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <meta name="description" content="<?= $pageDescription ?>">
    <meta name="keywords" content="<?= $pageKeywords ?>">
    <meta name="robots" content="index,follow">

    <!-- Open Graph -->
    <meta property="og:title" content="<?= $pageTitle ?>">
    <meta property="og:description" content="<?= $pageDescription ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= APP_URL ?>">
    <meta property="og:image" content="<?= APP_URL ?>/assets/images/og-image.jpg">

    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/home.css">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($siteSettings['site_favicon'] ?? 'assets/images/favicon.ico') ?>">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <!-- Hero Section -->
    <section class="hero-section"<?= !empty($siteSettings['hero_background']) ? ' style="background-image: url(\'' . htmlspecialchars($siteSettings['hero_background']) . '\')"' : '' ?>>
        <div class="hero-container">
            <div class="hero-content">
                <h1 class="hero-title"><?= htmlspecialchars($siteSettings['hero_title'] ?? '스마트팜의 미래를 선도하는 탄생') ?></h1>
                <?php if (!empty($siteSettings['hero_subtitle'])): ?>
                    <p class="hero-subtitle"><?= htmlspecialchars($siteSettings['hero_subtitle']) ?></p>
                <?php endif; ?>
                <p class="hero-description">
                    <?= nl2br(htmlspecialchars($siteSettings['hero_description'] ?? '고품질 수경재배 배지와 AI 기반 식물분석 서비스로 여러분의 스마트팜을 더욱 스마트하게 만들어드립니다.')) ?>
                </p>
                <div class="hero-links">
                    <a href="/pages/products/" class="hero-link">제품 보기</a>
                    <a href="/pages/plant-analysis/" class="hero-link">AI 식물분석</a>
                </div>
            </div>
            <div class="hero-image">
                <div class="hero-slider">
                    <?php
                    // 여러 미디어 파일 지원 (콤마로 구분)
                    $heroMediaList = $siteSettings['hero_media_list'] ?? $siteSettings['hero_background'] ?? '/assets/images/hero-smart-farm.jpg';
                    // 줄바꿈으로 구분된 이미지 URL 처리
                    $mediaFiles = array_filter(array_map('trim', explode("\n", str_replace("\r", "", $heroMediaList))));

                    // 만약 배열이 비어있다면 기본 이미지 사용
                    if (empty($mediaFiles)) {
                        $mediaFiles = ['/assets/images/hero-smart-farm.jpg'];
                    }
                    $totalSlides = count($mediaFiles);

                    foreach ($mediaFiles as $index => $heroMedia):
                        $fileExt = strtolower(pathinfo($heroMedia, PATHINFO_EXTENSION));
                        $isActive = $index === 0 ? 'active' : '';
                    ?>
                        <div class="hero-slide <?= $isActive ?>" data-slide="<?= $index ?>">
                            <?php if (in_array($fileExt, ['mp4', 'webm', 'ogg'])): ?>
                                <video autoplay muted loop playsinline>
                                    <source src="<?= htmlspecialchars($heroMedia) ?>" type="video/<?= $fileExt ?>">
                                    <!-- 비디오 지원하지 않는 브라우저용 대체 이미지 -->
                                    <img src="/assets/images/hero-smart-farm.jpg" alt="스마트팜 이미지" loading="lazy">
                                </video>
                            <?php else: ?>
                                <img src="<?= htmlspecialchars($heroMedia) ?>" alt="스마트팜 이미지 <?= $index + 1 ?>" loading="<?= $index === 0 ? 'eager' : 'lazy' ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($totalSlides > 1): ?>
                        <!-- 슬라이더 컨트롤 -->
                        <div class="hero-slider-controls">
                            <button class="slider-prev" onclick="heroSlider.prev()">‹</button>
                            <button class="slider-next" onclick="heroSlider.next()">›</button>
                        </div>

                        <!-- 슬라이더 인디케이터 -->
                        <div class="hero-slider-indicators">
                            <?php for ($i = 0; $i < $totalSlides; $i++): ?>
                                <button class="slider-indicator <?= $i === 0 ? 'active' : '' ?>" onclick="heroSlider.goTo(<?= $i ?>)"></button>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
        <div class="container">
            <h2 class="section-title">왜 탄생을 선택해야 할까요?</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="icon-leaf"></i>
                    </div>
                    <h3>친환경 배지</h3>
                    <p>코코넛 섬유 기반의 100% 천연 친환경 배지로 안전하고 건강한 작물 재배가 가능합니다.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="icon-chart"></i>
                    </div>
                    <h3>AI 식물분석</h3>
                    <p>최신 AI 기술을 활용한 식물 건강 진단 및 맞춤형 관리 솔루션을 제공합니다.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="icon-quality"></i>
                    </div>
                    <h3>검증된 품질</h3>
                    <p>엄격한 품질 관리 시스템을 통해 일정한 품질의 배지를 공급합니다.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="icon-support"></i>
                    </div>
                    <h3>전문 지원</h3>
                    <p>스마트팜 전문가들이 재배부터 수확까지 전 과정을 지원합니다.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Products Carousel Section -->
    <section class="products-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">🌱 지금 이 상품이 필요하신가요?</h2>
                <a href="/pages/products/" class="section-link">전체 제품 보기 →</a>
            </div>

            <div class="product-carousel-container">
                <div class="product-carousel-wrapper">
                    <div class="product-carousel-scroller" id="productCarousel">
                        <?php if (!empty($featuredProducts)): ?>
                            <?php foreach ($featuredProducts as $product): ?>
                                <a href="/pages/store/product.php?id=<?= $product['id'] ?>" class="product-card">
                                    <div class="product-image-wrap">
                                        <?php
                                        $imageSrc = !empty($product['image_url'])
                                            ? $product['image_url'] . '?v=' . strtotime($product['updated_at'])
                                            : '/assets/images/products/default.jpg';
                                        ?>
                                        <img src="<?= htmlspecialchars($imageSrc) ?>"
                                             alt="<?= htmlspecialchars($product['name']) ?>"
                                             loading="lazy" class="product-image">
                                        <span class="product-quick-view">미리보기</span>
                                    </div>
                                    <div class="product-info">
                                        <h3><?= htmlspecialchars($product['name']) ?></h3>
                                        <div class="product-price-wrap">
                                            <?php if ($product['discount_percentage'] > 0): ?>
                                                <span class="product-price-original"><?= number_format($product['price']) ?>원</span>
                                                <span class="product-price"><?= number_format($product['price'] * (100 - $product['discount_percentage']) / 100) ?>원</span>
                                                <span class="product-discount"><?= $product['discount_percentage'] ?>% OFF</span>
                                            <?php else: ?>
                                                <span class="product-price"><?= number_format($product['price']) ?>원</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="product-review">
                                            <?php
                                            $rating = $product['rating_score'] ?? 4.5;
                                            $reviewCount = $product['review_count'] ?? 0;
                                            $stars = str_repeat('⭐', (int)round($rating));
                                            ?>
                                            <span class="review-stars"><?= $stars ?></span>
                                            <span class="review-count">(<?= $reviewCount ?>)</span>
                                        </div>
                                        <?php if (!empty($product['delivery_info'])): ?>
                                            <div class="product-delivery"><?= htmlspecialchars($product['delivery_info']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-products-message">
                                <p>등록된 상품이 없습니다.</p>
                                <a href="/admin/products/add.php" class="btn btn-primary">첫 상품 등록하기</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section class="about-section">
        <div class="container">
            <div class="about-content">
                <div class="about-text">
                    <h2>탄생 소개</h2>
                    <p class="about-description">
                        고품질 배지 제조와 AI 기반 식물분석 서비스를 제공하는 스마트팜 전문업체입니다.
                    </p>
                    <div class="about-features-mobile">
                        <span>✓ 15년+ 경험</span>
                        <span>✓ ISO 인증</span>
                        <span>✓ 친환경</span>
                        <span>✓ 전국배송</span>
                    </div>
                    <div class="about-buttons">
                        <a href="/pages/company/about.php" class="about-link">회사소개</a>
                        <a href="/pages/support/contact.php" class="about-link">문의하기</a>
                    </div>
                </div>
                <div class="about-image">
                    <img src="/assets/images/about-company.jpg" alt="회사 소개" loading="lazy">
                </div>
            </div>
        </div>
    </section>

    <!-- Latest News Section -->
    <section class="news-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">최신 소식</h2>
                <a href="/pages/board/" class="section-link">전체 소식 보기 →</a>
            </div>

            <div class="news-grid">
                <div class="news-card">
                    <div class="news-category">공지사항</div>
                    <h3 class="news-title">신제품 코코피트 배지 출시 안내</h3>
                    <div class="news-date">2024.12.15</div>
                </div>
                <div class="news-card">
                    <div class="news-category">기술지원</div>
                    <h3 class="news-title">AI 식물분석 서비스 업그레이드 완료</h3>
                    <div class="news-date">2024.12.10</div>
                </div>
                <div class="news-card">
                    <div class="news-category">공지사항</div>
                    <h3 class="news-title">연말연시 배송 일정 안내</h3>
                    <div class="news-date">2024.12.05</div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-content">
                <h2>지금 시작하세요!</h2>
                <p>탄생과 함께 더 스마트한 농업의 미래를 만들어보세요.</p>
                <div class="cta-buttons">
                    <?php if ($currentUser): ?>
                        <?php if ($currentUser['role'] === 'admin'): ?>
                            <a href="/admin/" class="btn btn-admin">⚙️ 관리자 페이지</a>
                        <?php endif; ?>
                        <a href="/pages/store/" class="btn btn-primary">쇼핑하기</a>
                        <a href="/pages/plant-analysis/" class="btn btn-secondary">식물분석</a>
                    <?php else: ?>
                        <a href="/pages/auth/register.php" class="btn btn-primary">회원가입</a>
                        <a href="/pages/auth/login.php" class="btn btn-secondary">로그인</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <script>
    // Hero Slider functionality
    const heroSlider = {
        currentSlide: 0,
        totalSlides: 0,
        autoSlideInterval: null,
        autoSlideDelay: 5000, // 5초마다 자동 슬라이드

        init() {
            const slides = document.querySelectorAll('.hero-slide');
            this.totalSlides = slides.length;

            console.log('슬라이더 초기화:', this.totalSlides, '개 슬라이드');

            if (this.totalSlides > 1) {
                this.startAutoSlide();
                console.log('자동 슬라이드 시작');

                // 슬라이더 영역에 마우스 hover 시 자동 슬라이드 일시정지
                const sliderContainer = document.querySelector('.hero-slider');
                if (sliderContainer) {
                    sliderContainer.addEventListener('mouseenter', () => {
                        this.stopAutoSlide();
                        console.log('자동 슬라이드 일시정지');
                    });
                    sliderContainer.addEventListener('mouseleave', () => {
                        this.startAutoSlide();
                        console.log('자동 슬라이드 재시작');
                    });
                }
            }
        },

        goTo(slideIndex) {
            if (slideIndex < 0 || slideIndex >= this.totalSlides) return;

            console.log('슬라이드 이동:', this.currentSlide, '→', slideIndex);

            // 현재 활성 슬라이드 비활성화
            const currentSlide = document.querySelector('.hero-slide.active');
            const currentIndicator = document.querySelector('.slider-indicator.active');

            if (currentSlide) currentSlide.classList.remove('active');
            if (currentIndicator) currentIndicator.classList.remove('active');

            // 새 슬라이드 활성화
            const newSlide = document.querySelector(`[data-slide="${slideIndex}"]`);
            const newIndicator = document.querySelectorAll('.slider-indicator')[slideIndex];

            if (newSlide) newSlide.classList.add('active');
            if (newIndicator) newIndicator.classList.add('active');

            this.currentSlide = slideIndex;
        },

        next() {
            const nextSlide = (this.currentSlide + 1) % this.totalSlides;
            this.goTo(nextSlide);
        },

        prev() {
            const prevSlide = this.currentSlide === 0 ? this.totalSlides - 1 : this.currentSlide - 1;
            this.goTo(prevSlide);
        },

        startAutoSlide() {
            if (this.totalSlides <= 1) return;

            this.stopAutoSlide(); // 기존 인터벌 제거
            this.autoSlideInterval = setInterval(() => {
                this.next();
            }, this.autoSlideDelay);
        },

        stopAutoSlide() {
            if (this.autoSlideInterval) {
                clearInterval(this.autoSlideInterval);
                this.autoSlideInterval = null;
            }
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        console.log('페이지 로드 완료, 슬라이더 초기화 시작');

        // Initialize hero slider
        heroSlider.init();

        // Initialize page functionality
        console.log('Tansaeng Smart Farm Website Loaded');
    });

    // 에러 캐치
    window.addEventListener('error', function(e) {
        console.error('JavaScript 에러:', e.error);
    });
    </script>

    <!-- Main JavaScript -->
    <script src="/assets/js/main.js"></script>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
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
        "SELECT p.id, p.name, p.description, p.price, p.image_url, p.discount_percentage,
                p.rating_score, p.review_count, p.delivery_info, p.updated_at,
                c.name as category_name
         FROM products p
         LEFT JOIN categories c ON p.category_id = c.id
         WHERE p.is_featured = 1 AND p.status = 'active'
         ORDER BY p.created_at DESC LIMIT 6"
    );
    $featuredProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If we have less than 6 featured products, fill with recent active products
    $featuredCount = count($featuredProducts);
    if ($featuredCount < 6) {
        $remainingSlots = 6 - $featuredCount;

        // Get product IDs we already have to exclude them
        $excludeIds = array_column($featuredProducts, 'id');
        $excludeClause = !empty($excludeIds) ? 'AND p.id NOT IN (' . implode(',', $excludeIds) . ')' : '';

        $stmt = $pdo->query(
            "SELECT p.id, p.name, p.description, p.price, p.image_url, p.discount_percentage,
                    p.rating_score, p.review_count, p.delivery_info, p.updated_at,
                    c.name as category_name
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.status = 'active' $excludeClause
             ORDER BY p.created_at DESC LIMIT $remainingSlots"
        );
        $additionalProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    <!-- Hero Section (풀너비 슬라이더 + 텍스트 오버레이) -->
    <section class="hero-section hero-fullwidth">
        <div class="hero-slider">
            <?php
            $heroMediaList = $siteSettings['hero_media_list'] ?? $siteSettings['hero_background'] ?? '/assets/images/hero-smart-farm.jpg';
            $mediaFiles = array_filter(array_map('trim', explode("\n", str_replace("\r", "", $heroMediaList))));
            if (empty($mediaFiles)) { $mediaFiles = ['/assets/images/hero-smart-farm.jpg']; }
            $totalSlides = count($mediaFiles);
            foreach ($mediaFiles as $index => $heroMedia):
                $fileExt = strtolower(pathinfo($heroMedia, PATHINFO_EXTENSION));
                $isActive = $index === 0 ? 'active' : '';
            ?>
                <div class="hero-slide <?= $isActive ?>" data-slide="<?= $index ?>">
                    <?php if (in_array($fileExt, ['mp4', 'webm', 'ogg'])): ?>
                        <video autoplay muted loop playsinline>
                            <source src="<?= htmlspecialchars($heroMedia) ?>" type="video/<?= $fileExt ?>">
                        </video>
                    <?php else: ?>
                        <img src="<?= htmlspecialchars($heroMedia) ?>" alt="스마트팜 이미지 <?= $index + 1 ?>" loading="<?= $index === 0 ? 'eager' : 'lazy' ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <!-- 텍스트 오버레이 -->
            <div class="hero-overlay">
                <div class="hero-overlay-content">
                    <h1 class="hero-title"><?= htmlspecialchars($siteSettings['hero_title'] ?? '스마트팜의 미래를 여는 탄생') ?></h1>
                    <?php if (!empty($siteSettings['hero_subtitle'])): ?>
                        <p class="hero-subtitle"><?= htmlspecialchars($siteSettings['hero_subtitle']) ?></p>
                    <?php endif; ?>
                    <div class="hero-links">
                        <a href="/pages/store/" class="hero-link">제품 보기</a>
                        <a href="/pages/plant_analysis/" class="hero-link">AI 식물분석</a>
                    </div>
                </div>
            </div>

            <?php if ($totalSlides > 1): ?>
                <div class="hero-slider-controls">
                    <button class="slider-prev" onclick="heroSlider.prev()">‹</button>
                    <button class="slider-next" onclick="heroSlider.next()">›</button>
                </div>
                <div class="hero-slider-indicators">
                    <?php for ($i = 0; $i < $totalSlides; $i++): ?>
                        <button class="slider-indicator <?= $i === 0 ? 'active' : '' ?>" onclick="heroSlider.goTo(<?= $i ?>)"></button>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
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
                <a href="/pages/store/" class="section-link">전체 제품 보기 →</a>
            </div>

            <div class="product-carousel-container">
                <!-- 캐러셀 컨트롤 버튼 -->
                <div class="product-carousel-controls">
                    <button class="carousel-btn carousel-prev" onclick="productCarousel.prev()" aria-label="이전 상품">‹</button>
                    <button class="carousel-btn carousel-next" onclick="productCarousel.next()" aria-label="다음 상품">›</button>
                </div>

                <div class="product-carousel-wrapper">
                    <div class="product-carousel-scroller" id="productCarousel">
                        <?php if (!empty($featuredProducts)): ?>
                            <?php foreach ($featuredProducts as $product): ?>
                                <?php
                                // 기본값 설정
                                $productId = $product['id'] ?? 0;
                                $productName = $product['name'] ?? '상품명 없음';
                                $productDescription = $product['description'] ?? '';
                                $productPrice = $product['price'] ?? 0;
                                $discountPercent = $product['discount_percentage'] ?? 0;
                                $ratingScore = $product['rating_score'] ?? 4.5;
                                $reviewCount = $product['review_count'] ?? 0;
                                $deliveryInfo = $product['delivery_info'] ?? '무료배송';
                                $imageUrl = $product['image_url'] ?? '/assets/images/products/default.jpg';
                                $updatedAt = $product['updated_at'] ?? date('Y-m-d H:i:s');

                                // 할인가 계산
                                $discountedPrice = $discountPercent > 0
                                    ? $productPrice * (100 - $discountPercent) / 100
                                    : $productPrice;
                                ?>
                                <a href="/pages/store/product.php?id=<?= $productId ?>" class="product-card">
                                    <div class="product-image-wrap">
                                        <img src="<?= htmlspecialchars($imageUrl) ?>?v=<?= strtotime($updatedAt) ?>"
                                             alt="<?= htmlspecialchars($productName) ?>"
                                             loading="lazy" class="product-image">
                                        <span class="product-quick-view">미리보기</span>
                                    </div>
                                    <div class="product-info">
                                        <h3><?= htmlspecialchars($productName) ?></h3>
                                        <div class="product-price-wrap">
                                            <?php if ($discountPercent > 0): ?>
                                                <span class="product-price-original"><?= number_format($productPrice) ?>원</span>
                                                <span class="product-price"><?= number_format($discountedPrice) ?>원</span>
                                                <span class="product-discount"><?= $discountPercent ?>% OFF</span>
                                            <?php else: ?>
                                                <span class="product-price"><?= number_format($productPrice) ?>원</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="product-review">
                                            <span class="review-stars"><?= str_repeat('⭐', (int)round($ratingScore)) ?></span>
                                            <span class="review-count">(<?= number_format($reviewCount) ?>)</span>
                                        </div>
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
                    <div>
                        <div class="news-category">공지사항</div>
                        <h3 class="news-title">신제품 코코피트 배지 출시 안내</h3>
                    </div>
                    <div class="news-date">2024.12.15</div>
                </div>
                <div class="news-card">
                    <div>
                        <div class="news-category">기술지원</div>
                        <h3 class="news-title">AI 식물분석 서비스 업그레이드 완료</h3>
                    </div>
                    <div class="news-date">2024.12.10</div>
                </div>
                <div class="news-card">
                    <div>
                        <div class="news-category">공지사항</div>
                        <h3 class="news-title">연말연시 배송 일정 안내</h3>
                    </div>
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
                        <a href="/pages/plant_analysis/" class="btn btn-secondary">식물분석</a>
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

    // Product Carousel functionality
    const productCarousel = {
        currentPosition: 0,
        cardWidth: 0,
        gap: 20,
        visibleCards: 0,
        totalCards: 0,
        scroller: null,
        prevBtn: null,
        nextBtn: null,

        init() {
            this.scroller = document.getElementById('productCarousel');
            this.prevBtn = document.querySelector('.carousel-prev');
            this.nextBtn = document.querySelector('.carousel-next');

            if (!this.scroller) return;

            const cards = this.scroller.querySelectorAll('.product-card');
            this.totalCards = cards.length;

            if (this.totalCards === 0) return;

            // Calculate card width and visible cards
            this.updateDimensions();
            this.updateButtons();

            // Update on window resize
            window.addEventListener('resize', () => {
                this.updateDimensions();
                this.updateButtons();
            });

            console.log('Product carousel initialized:', this.totalCards, 'cards');
        },

        updateDimensions() {
            const cards = this.scroller.querySelectorAll('.product-card');
            if (cards.length === 0) return;

            // Get card width from first card
            const firstCard = cards[0];
            this.cardWidth = firstCard.offsetWidth;

            // Calculate how many cards are visible
            const containerWidth = this.scroller.parentElement.offsetWidth;
            this.visibleCards = Math.floor(containerWidth / (this.cardWidth + this.gap));
        },

        prev() {
            if (this.currentPosition > 0) {
                this.currentPosition--;
                this.slide();
            }
        },

        next() {
            const maxPosition = Math.max(0, this.totalCards - this.visibleCards);
            if (this.currentPosition < maxPosition) {
                this.currentPosition++;
                this.slide();
            }
        },

        slide() {
            const translateX = -(this.currentPosition * (this.cardWidth + this.gap));
            this.scroller.style.transform = `translateX(${translateX}px)`;
            this.updateButtons();
        },

        updateButtons() {
            if (!this.prevBtn || !this.nextBtn) return;

            // Disable prev button at start
            if (this.currentPosition === 0) {
                this.prevBtn.disabled = true;
            } else {
                this.prevBtn.disabled = false;
            }

            // Disable next button at end
            const maxPosition = Math.max(0, this.totalCards - this.visibleCards);
            if (this.currentPosition >= maxPosition) {
                this.nextBtn.disabled = true;
            } else {
                this.nextBtn.disabled = false;
            }
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        console.log('페이지 로드 완료, 슬라이더 초기화 시작');

        // Initialize hero slider
        heroSlider.init();

        // Initialize product carousel
        productCarousel.init();

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
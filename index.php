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

// Load site settings from database
try {
    $pdo = DatabaseConfig::getConnection();
    $sql = "SELECT setting_key, setting_value FROM site_settings";
    $stmt = $pdo->query($sql);
    while ($row = $stmt->fetch()) {
        $siteSettings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    // Continue with default settings
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
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/home.css">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($siteSettings['site_favicon'] ?? '/assets/images/favicon.ico') ?>">
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
                <?php
                $heroMedia = $siteSettings['hero_background'] ?? '/assets/images/hero-smart-farm.jpg';
                $fileExt = strtolower(pathinfo($heroMedia, PATHINFO_EXTENSION));

                if (in_array($fileExt, ['mp4', 'webm', 'ogg'])): ?>
                    <video autoplay muted loop>
                        <source src="<?= htmlspecialchars($heroMedia) ?>" type="video/<?= $fileExt ?>">
                        <!-- 비디오 지원하지 않는 브라우저용 대체 이미지 -->
                        <img src="/assets/images/hero-smart-farm.jpg" alt="스마트팜 이미지" loading="lazy">
                    </video>
                <?php else: ?>
                    <img src="<?= htmlspecialchars($heroMedia) ?>" alt="스마트팜 이미지" loading="lazy">
                <?php endif; ?>
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

    <!-- Products Section -->
    <section class="products-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">인기 제품</h2>
                <a href="/pages/products/" class="section-link">전체 제품 보기 →</a>
            </div>

            <div class="products-grid" id="featured-products">
                <a href="/pages/store/product.php?id=1" class="product-card">
                    <div class="product-image">
                        <img src="/assets/images/products/coco-peat.jpg" alt="코코피트 배지" loading="lazy">
                    </div>
                    <div class="product-info">
                        <h3>코코피트</h3>
                        <div class="product-price">15,000원</div>
                    </div>
                </a>
                <a href="/pages/store/product.php?id=2" class="product-card">
                    <div class="product-image">
                        <img src="/assets/images/products/perlite.jpg" alt="펄라이트 배지" loading="lazy">
                    </div>
                    <div class="product-info">
                        <h3>펄라이트</h3>
                        <div class="product-price">12,000원</div>
                    </div>
                </a>
                <a href="/pages/store/product.php?id=3" class="product-card">
                    <div class="product-image">
                        <img src="/assets/images/products/mixed.jpg" alt="혼합 배지" loading="lazy">
                    </div>
                    <div class="product-info">
                        <h3>혼합배지</h3>
                        <div class="product-price">18,000원</div>
                    </div>
                </a>
                <a href="/pages/store/product.php?id=4" class="product-card">
                    <div class="product-image">
                        <img src="/assets/images/products/organic.jpg" alt="유기질 배지" loading="lazy">
                    </div>
                    <div class="product-info">
                        <h3>유기질배지</h3>
                        <div class="product-price">16,000원</div>
                    </div>
                </a>
                <a href="/pages/store/product.php?id=5" class="product-card">
                    <div class="product-image">
                        <img src="/assets/images/products/hydro.jpg" alt="수경재배 키트" loading="lazy">
                    </div>
                    <div class="product-info">
                        <h3>수경키트</h3>
                        <div class="product-price">25,000원</div>
                    </div>
                </a>
                <a href="/pages/store/product.php?id=6" class="product-card">
                    <div class="product-image">
                        <img src="/assets/images/products/nutrients.jpg" alt="영양액" loading="lazy">
                    </div>
                    <div class="product-info">
                        <h3>영양액</h3>
                        <div class="product-price">8,000원</div>
                    </div>
                </a>
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
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize page functionality
        console.log('Tansaeng Smart Farm Website Loaded');
    });
    </script>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
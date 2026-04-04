<?php
// Load footer settings from database
if (!isset($siteSettings)) {
    $siteSettings = [];
    try {
        require_once __DIR__ . '/../config/database.php';
        $pdo = DatabaseConfig::getConnection();
        $sql = "SELECT setting_key, setting_value FROM site_settings";
        $stmt = $pdo->query($sql);
        while ($row = $stmt->fetch()) {
            $siteSettings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {
        // Continue with defaults
    }
}

// Footer configuration with database values
$company_name = $siteSettings['site_title'] ?? $siteSettings['company_name'] ?? $siteSettings['site_name'] ?? '탄생';
$company_desc = $siteSettings['footer_company_desc'] ?? $siteSettings['site_description'] ?? '스마트팜 배지 전문업체';
$company_address = $siteSettings['footer_address'] ?? $siteSettings['company_address'] ?? '서울특별시 강남구 테헤란로 123';
$company_phone = $siteSettings['footer_phone'] ?? $siteSettings['contact_phone'] ?? '1588-0000';
$company_email = $siteSettings['footer_email'] ?? $siteSettings['contact_email'] ?? 'contact@tansaeng.com';
$business_number = $siteSettings['business_number'] ?? $siteSettings['footer_business_number'] ?? '304-64-00700';
$business_type = $siteSettings['business_type'] ?? '도매 및 소매업';
$business_category = $siteSettings['business_category'] ?? '전자상거래 소매업';
$ceo_name = $siteSettings['ceo_name'] ?? $siteSettings['footer_ceo_name'] ?? '홍길동';
$current_year = date('Y');

// Parse JSON menu arrays - 새 형식: [{"name": "메뉴명", "url": "/path"}] 또는 구 형식: ["메뉴명"]
function parseMenuItems($jsonString) {
    if (empty($jsonString)) return [];
    $items = json_decode($jsonString, true);
    if (!is_array($items)) return [];

    // 새 형식인지 확인 (associative array)
    $newFormat = [];
    foreach ($items as $item) {
        if (is_array($item) && isset($item['name'])) {
            // 새 형식: {"name": "메뉴명", "url": "/path"}
            $newFormat[] = $item;
        } else if (is_string($item)) {
            // 구 형식: "메뉴명" -> {"name": "메뉴명", "url": "#"}로 변환
            $newFormat[] = ['name' => $item, 'url' => '#'];
        }
    }
    return $newFormat;
}

$productMenu = parseMenuItems($siteSettings['footer_menu_products'] ?? '');
$serviceMenu = parseMenuItems($siteSettings['footer_menu_services'] ?? '');
$companyMenu = parseMenuItems($siteSettings['footer_menu_company'] ?? '');
?>
<footer class="footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-section">
                <h3><?= htmlspecialchars($company_name) ?></h3>
                <p class="footer-company-desc"><?= htmlspecialchars($company_desc) ?></p>
                <div class="footer-contact">
                    <p>📍 <?= htmlspecialchars($company_address) ?></p>
                    <p>📞 <?= htmlspecialchars($company_phone) ?></p>
                    <p>✉️ <?= htmlspecialchars($company_email) ?></p>
                </div>
                <div class="footer-social">
                    <?php if (!empty($siteSettings['social_youtube'])): ?>
                        <a href="<?= htmlspecialchars($siteSettings['social_youtube']) ?>" target="_blank" title="YouTube">📺</a>
                    <?php endif; ?>
                    <?php if (!empty($siteSettings['social_instagram'])): ?>
                        <a href="<?= htmlspecialchars($siteSettings['social_instagram']) ?>" target="_blank" title="Instagram">📸</a>
                    <?php endif; ?>
                    <?php if (!empty($siteSettings['social_facebook'])): ?>
                        <a href="<?= htmlspecialchars($siteSettings['social_facebook']) ?>" target="_blank" title="Facebook">👥</a>
                    <?php endif; ?>
                    <?php if (!empty($siteSettings['social_blog'])): ?>
                        <a href="<?= htmlspecialchars($siteSettings['social_blog']) ?>" target="_blank" title="블로그">📝</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="footer-section">
                <h3>제품</h3>
                <ul>
                    <?php if (!empty($productMenu)): ?>
                        <?php foreach ($productMenu as $item): ?>
                            <li><a href="<?= htmlspecialchars($item['url'] ?? '#') ?>"><?= htmlspecialchars($item['name'] ?? '') ?></a></li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li><a href="/pages/products/coco.php">코코피트 배지</a></li>
                        <li><a href="/pages/products/perlite.php">펄라이트 배지</a></li>
                        <li><a href="/pages/products/mixed.php">혼합 배지</a></li>
                        <li><a href="/pages/products/compare.php">제품 비교</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="footer-section">
                <h3>서비스</h3>
                <ul>
                    <?php if (!empty($serviceMenu)): ?>
                        <?php foreach ($serviceMenu as $item): ?>
                            <li><a href="<?= htmlspecialchars($item['url'] ?? '#') ?>"><?= htmlspecialchars($item['name'] ?? '') ?></a></li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li><a href="/pages/plant_analysis/">AI 식물분석</a></li>
                        <li><a href="/pages/support/faq.php">FAQ</a></li>
                        <li><a href="/pages/support/technical.php">기술지원</a></li>
                        <li><a href="/pages/support/inquiry.php">1:1 문의</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="footer-section">
                <h3>회사정보</h3>
                <ul>
                    <?php if (!empty($companyMenu)): ?>
                        <?php foreach ($companyMenu as $item): ?>
                            <li><a href="<?= htmlspecialchars($item['url'] ?? '#') ?>"><?= htmlspecialchars($item['name'] ?? '') ?></a></li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li><a href="/pages/company/about.php">회사소개</a></li>
                        <li><a href="/pages/company/history.php">연혁</a></li>
                        <li><a href="/pages/company/location.php">오시는길</a></li>
                        <li><a href="/pages/board/">공지사항</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?= $current_year ?> <?= htmlspecialchars($company_name) ?>. All rights reserved.</p>
            <p>사업자등록번호: <?= htmlspecialchars($business_number) ?> | 업태: <?= htmlspecialchars($business_type) ?> | 업종: <?= htmlspecialchars($business_category) ?></p>
            <p>대표: <?= htmlspecialchars($ceo_name) ?></p>
        </div>
    </div>
</footer>

<style>
/* 모바일에서 푸터 회사 설명 숨김 */
@media (max-width: 768px) {
    .footer-company-desc {
        display: none !important;
    }
}
</style>
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
$company_name = $siteSettings['site_title'] ?? '탄생';
$company_desc = $siteSettings['footer_company_desc'] ?? '스마트팜 배지 전문업체';
$company_address = $siteSettings['footer_address'] ?? '서울특별시 강남구 테헤란로 123';
$company_phone = $siteSettings['footer_phone'] ?? '1588-0000';
$company_email = $siteSettings['footer_email'] ?? 'contact@tansaeng.com';
$current_year = date('Y');

// Parse JSON menu arrays
function parseMenuItems($jsonString) {
    if (empty($jsonString)) return [];
    $items = json_decode($jsonString, true);
    return is_array($items) ? $items : [];
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
                <p><?= htmlspecialchars($company_desc) ?></p>
                <div class="footer-contact">
                    <p>📍 <?= htmlspecialchars($company_address) ?></p>
                    <p>📞 <?= htmlspecialchars($company_phone) ?></p>
                    <p>✉️ <?= htmlspecialchars($company_email) ?></p>
                </div>
                <div class="footer-social">
                    <?php if (!empty($siteSettings['footer_social_youtube'])): ?>
                        <a href="<?= htmlspecialchars($siteSettings['footer_social_youtube']) ?>" target="_blank" title="YouTube">📺</a>
                    <?php endif; ?>
                    <?php if (!empty($siteSettings['footer_social_instagram'])): ?>
                        <a href="<?= htmlspecialchars($siteSettings['footer_social_instagram']) ?>" target="_blank" title="Instagram">📸</a>
                    <?php endif; ?>
                    <?php if (!empty($siteSettings['footer_social_facebook'])): ?>
                        <a href="<?= htmlspecialchars($siteSettings['footer_social_facebook']) ?>" target="_blank" title="Facebook">👥</a>
                    <?php endif; ?>
                    <?php if (!empty($siteSettings['footer_social_blog'])): ?>
                        <a href="<?= htmlspecialchars($siteSettings['footer_social_blog']) ?>" target="_blank" title="블로그">📝</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="footer-section">
                <h3>제품</h3>
                <ul>
                    <?php if (!empty($productMenu)): ?>
                        <?php foreach ($productMenu as $item): ?>
                            <li><a href="#"><?= htmlspecialchars($item) ?></a></li>
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
                            <li><a href="#"><?= htmlspecialchars($item) ?></a></li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li><a href="/pages/plant-analysis/">AI 식물분석</a></li>
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
                            <li><a href="#"><?= htmlspecialchars($item) ?></a></li>
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
            <p>사업자등록번호: 123-45-67890 | 대표: 홍길동</p>
        </div>
    </div>
</footer>
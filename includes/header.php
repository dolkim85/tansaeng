<?php
// Initialize current user if not already set
if (!isset($currentUser)) {
    $currentUser = null;
    try {
        if (!isset($auth)) {
            require_once __DIR__ . '/../classes/Auth.php';
            $auth = Auth::getInstance();
        }
        $currentUser = $auth->getCurrentUser();
    } catch (Exception $e) {
        $currentUser = null;
    }
}

// Load header settings from database
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

// Site settings with database values
$site_name = $siteSettings['site_title'] ?? '탄생';
$site_logo = $siteSettings['site_logo'] ?? null;
$header_phone = $siteSettings['company_phone'] ?? '1588-0000';
$header_email = $siteSettings['company_email'] ?? 'contact@tansaeng.com';

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<header class="header">
    <nav class="navbar container">
        <a href="/" class="logo">
            <?php if ($site_logo): ?>
                <img src="<?= htmlspecialchars($site_logo) ?>" alt="<?= htmlspecialchars($site_name) ?>" class="logo-image">
            <?php else: ?>
                <?= htmlspecialchars($site_name) ?>
            <?php endif; ?>
        </a>

        <ul class="nav-menu" id="nav-menu">
            <li><a href="/">홈</a></li>
            <li><a href="/pages/company/about.php">기업소개</a></li>
            <li><a href="/pages/products/">배지설명</a></li>
            <li><a href="/pages/store/">스토어</a></li>
            <li><a href="/pages/board/">게시판</a></li>
            <li><a href="/pages/support/">고객지원</a></li>
            <li><a href="/pages/plant_analysis/">식물분석</a></li>
        </ul>

        <div class="nav-auth">
            <?php if ($currentUser): ?>
                <span><?= htmlspecialchars($currentUser['name']) ?>님</span>
                <?php if ($currentUser['role'] === 'admin'): ?>
                    <a href="/admin/" class="admin-link">관리자</a>
                <?php endif; ?>
                <a href="/pages/auth/logout.php" class="logout-link">로그아웃</a>
            <?php else: ?>
                <a href="/pages/auth/login.php" class="login-link">로그인</a>
                <a href="/pages/auth/register.php" class="register-link">회원가입</a>
            <?php endif; ?>
        </div>

        <button class="mobile-menu-toggle" id="mobile-menu-toggle">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </nav>
</header>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mobileToggle = document.getElementById('mobile-menu-toggle');
    const navMenu = document.getElementById('nav-menu');

    if (mobileToggle) {
        mobileToggle.addEventListener('click', function() {
            navMenu.classList.toggle('active');
            mobileToggle.classList.toggle('active');
        });
    }
});
</script>
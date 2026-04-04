<?php
// Use Auth class for consistency
require_once __DIR__ . '/../../classes/Auth.php';
$auth = Auth::getInstance();

if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    header('Location: /pages/auth/login.php?redirect=/admin/');
    exit;
}

$currentUser = $auth->getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<header class="admin-header">
    <div class="admin-header-content">
        <div class="admin-brand">
            <a href="/admin/">
                <span class="brand-icon">🌱</span>
                <span class="brand-text">탄생 관리자</span>
            </a>
        </div>
        
        <nav class="admin-nav">
            <ul>
                <li class="<?= $currentPage === 'index' ? 'active' : '' ?>">
                    <a href="/admin/">대시보드</a>
                </li>
                <li class="dropdown <?= strpos($_SERVER['REQUEST_URI'], '/admin/users/') !== false ? 'active' : '' ?>">
                    <a href="/admin/users/">사용자 관리</a>
                    <ul class="dropdown-menu">
                        <li><a href="/admin/users/">사용자 목록</a></li>
                        <li><a href="/admin/users/permissions.php">권한 관리</a></li>
                    </ul>
                </li>
                <li class="<?= strpos($_SERVER['REQUEST_URI'], '/admin/products/') !== false ? 'active' : '' ?>">
                    <a href="/admin/products/">상품 관리</a>
                </li>
                <li class="<?= strpos($_SERVER['REQUEST_URI'], '/admin/orders/') !== false ? 'active' : '' ?>">
                    <a href="/admin/orders/">주문 관리</a>
                </li>
                <li class="<?= strpos($_SERVER['REQUEST_URI'], '/admin/plant_analysis/') !== false ? 'active' : '' ?>">
                    <a href="/admin/plant_analysis/">식물분석</a>
                </li>
            </ul>
        </nav>
        
        <div class="admin-user">
            <div class="admin-user-info">
                <span class="admin-name"><?= htmlspecialchars($currentUser['name']) ?></span>
                <span class="admin-role">관리자</span>
            </div>
            <div class="admin-actions">
                <a href="/" target="_blank" class="admin-action" title="사이트 보기">🌐</a>
                <a href="/admin/settings/" class="admin-action" title="설정">⚙️</a>
                <a href="/admin/logout.php" class="admin-action" title="로그아웃">🚪</a>
            </div>
        </div>
    </div>
</header>
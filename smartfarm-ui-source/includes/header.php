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
                <span class="logo-text">탄생</span>
            <?php else: ?>
                <span class="logo-text">탄생</span>
            <?php endif; ?>
        </a>

        <ul class="nav-menu" id="nav-menu">
            <li><a href="/">홈</a></li>
            <li class="dropdown">
                <a href="#" class="dropbtn">기업소개</a>
                <ul class="dropdown-content">
                    <li><a href="/pages/company/about.php">회사소개</a></li>
                    <li><a href="/pages/company/history.php">연혁</a></li>
                    <li><a href="/pages/company/location.php">오시는길</a></li>
                    <li><a href="/pages/support/notice.php">공지사항</a></li>
                </ul>
            </li>
            <li><a href="/pages/products/">배지설명</a></li>
            <li><a href="/pages/store/">스토어</a></li>
            <li><a href="/pages/board/">게시판</a></li>
            <li><a href="/pages/support/">고객지원</a></li>
            <li><a href="/pages/plant_analysis/">식물분석</a></li>
        </ul>

        <div class="nav-auth">
            <?php if ($currentUser): ?>
                <a href="/pages/store/cart.php" class="cart-link" id="cartLink">
                    🛒 장바구니 <span class="cart-count" id="cartCount" style="display: none;">0</span>
                </a>
                <a href="/pages/auth/profile.php" class="user-name-link" title="내 정보">
                    <span class="user-name"><?= htmlspecialchars($currentUser['name'] ?? '') ?>님</span>
                </a>
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

<!-- 모바일 메뉴 오버레이 -->
<div class="nav-menu-overlay" id="nav-menu-overlay" onclick="closeMobileMenu()"></div>

<!-- 모바일 하단 네비게이션 바 -->
<div class="mobile-bottom-nav">
    <button class="mobile-nav-item" id="mobile-nav-menu" onclick="toggleMobileMenu(event)">
        <div class="mobile-nav-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </div>
        <div class="mobile-nav-label">메뉴</div>
    </button>

    <a href="/pages/store/" class="mobile-nav-item">
        <div class="mobile-nav-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.35-4.35"></path>
            </svg>
        </div>
        <div class="mobile-nav-label">검색</div>
    </a>

    <a href="/" class="mobile-nav-item <?= $currentPage === 'index' ? 'active' : '' ?>">
        <div class="mobile-nav-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
        </div>
        <div class="mobile-nav-label">홈</div>
    </a>

    <?php if ($currentUser): ?>
        <a href="/pages/auth/profile.php" class="mobile-nav-item">
            <div class="mobile-nav-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <span class="mobile-nav-badge logged-in"></span>
            </div>
            <div class="mobile-nav-label">내정보</div>
        </a>
    <?php else: ?>
        <a href="/pages/auth/login.php" class="mobile-nav-item">
            <div class="mobile-nav-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
            </div>
            <div class="mobile-nav-label">로그인</div>
        </a>
    <?php endif; ?>

    <a href="/pages/store/cart.php" class="mobile-nav-item" id="mobile-nav-cart">
        <div class="mobile-nav-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="8" cy="21" r="1"></circle>
                <circle cx="19" cy="21" r="1"></circle>
                <path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path>
            </svg>
            <span class="mobile-nav-badge" id="mobileCartCount" style="display: none;">0</span>
        </div>
        <div class="mobile-nav-label">장바구니</div>
    </a>
</div>

<style>
/* 하단 네비게이션 - 기본 숨김 */
.mobile-bottom-nav {
    display: none;
}

/* 메뉴 오버레이 - 기본 숨김 */
.nav-menu-overlay {
    display: none;
}

/* 로고 스타일 */
.logo {
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.logo-text {
    font-family: 'Noto Sans KR', 'Apple SD Gothic Neo', 'Malgun Gothic', sans-serif;
    font-size: 20px;
    font-weight: 600;
    color: #2E7D32;
    letter-spacing: -0.5px;
}

.logo-image {
    height: 32px;
    width: auto;
}

/* 장바구니 링크 스타일 */
.cart-link {
    display: inline-flex;
    align-items: center;
    text-decoration: none;
    color: #2E7D32;
    font-weight: 500;
    padding: 6px 10px;
    border-radius: 15px;
    background: #E8F5E8;
    margin-right: 10px;
    transition: all 0.3s ease;
    position: relative;
    height: auto;
    font-size: 14px;
}

.cart-link:hover {
    background: #C8E6C9;
    transform: translateY(-1px);
}

.cart-count {
    background: #FFC107;
    color: #2E7D32;
    border-radius: 50%;
    padding: 2px 6px;
    font-size: 12px;
    font-weight: bold;
    margin-left: 5px;
    min-width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: none;
    border: 1px solid #FF8F00;
}

.cart-count.updated {
    animation: cartBounce 0.5s ease;
}

@keyframes cartBounce {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.3); }
}

.user-name-link {
    text-decoration: none;
    margin-right: 10px;
    display: inline-flex;
    align-items: center;
    position: relative;
    cursor: pointer;
    transition: all 0.3s ease;
}

.user-name-link:hover {
    transform: translateY(-1px);
}

.user-name-link::after {
    content: attr(title);
    position: absolute;
    bottom: -35px;
    left: 50%;
    transform: translateX(-50%) scale(0);
    background: #333;
    color: white;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 12px;
    white-space: nowrap;
    opacity: 0;
    transition: all 0.3s ease;
    pointer-events: none;
    z-index: 1000;
}

.user-name-link::before {
    content: '';
    position: absolute;
    bottom: -8px;
    left: 50%;
    transform: translateX(-50%) scale(0);
    width: 0;
    height: 0;
    border-left: 6px solid transparent;
    border-right: 6px solid transparent;
    border-bottom: 6px solid #333;
    opacity: 0;
    transition: all 0.3s ease;
    pointer-events: none;
    z-index: 1000;
}

.user-name-link:hover::after,
.user-name-link:hover::before {
    transform: translateX(-50%) scale(1);
    opacity: 1;
}

.user-name {
    color: #333;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    height: auto;
    font-size: 14px;
    padding: 6px 12px;
    border-radius: 15px;
    background: #f0f7ff;
    transition: all 0.3s ease;
}

.user-name-link:hover .user-name {
    background: #d4e7ff;
    color: #007bff;
}

/* nav-auth 컨테이너 정렬 */
.nav-auth {
    display: flex;
    align-items: center;
    gap: 5px;
}

.nav-auth > * {
    display: inline-flex;
    align-items: center;
    margin: 0;
}

/* 드롭다운 메뉴 스타일 */
.dropdown {
    position: relative;
    display: inline-block;
}

.dropdown-content {
    display: none;
    position: absolute;
    background-color: #ffffff;
    min-width: 160px;
    box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
    z-index: 1000;
    border-radius: 4px;
    padding: 8px 0;
    top: 100%;
    left: 0;
    border: 1px solid #e0e0e0;
}

.dropdown-content li {
    list-style: none;
    margin: 0;
    padding: 0;
}

.dropdown-content li a {
    color: #333;
    padding: 12px 16px;
    text-decoration: none;
    display: block;
    transition: all 0.3s ease;
    font-size: 14px;
    border-bottom: 1px solid #f0f0f0;
}

.dropdown-content li:last-child a {
    border-bottom: none;
}

.dropdown-content li a:hover {
    background-color: #f8f9fa;
    color: #007bff;
    padding-left: 20px;
}

.dropdown:hover .dropdown-content,
.dropdown.active .dropdown-content {
    display: block;
}

.dropbtn {
    cursor: pointer;
    position: relative;
    color: inherit;
    text-decoration: none;
    display: inline-block;
}

/* 데스크톱에서 호버 시 화살표 표시 */
.dropbtn::after {
    content: ' ▼';
    font-size: 10px;
    opacity: 0;
    transition: opacity 0.3s ease;
    margin-left: 5px;
}

.dropdown:hover .dropbtn::after {
    opacity: 1;
}

/* 모바일 헤더 - 완전히 새로운 디자인 */
@media (max-width: 768px) {
    /* 상단 헤더 (로고 영역) */
    .header {
        position: fixed !important;
        top: 0;
        left: 0;
        width: 100%;
        height: 50px;
        z-index: 1000;
        background-color: white !important;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1) !important;
        display: flex !important;
        align-items: center;
        justify-content: center;
    }

    .navbar {
        width: 100%;
        height: 100%;
        display: flex !important;
        align-items: center;
        justify-content: center;
        padding: 0;
    }

    /* 로고 중앙 배치 */
    .logo {
        flex-direction: row;
        align-items: center;
        gap: 8px;
        justify-content: center;
    }

    .logo-image {
        height: 28px;
    }

    .logo-text {
        font-size: 18px;
        font-weight: 600;
        line-height: 1;
    }

    /* 기존 햄버거 메뉴 숨김 */
    .mobile-menu-toggle {
        display: none !important;
    }

    /* 하단 고정 네비게이션 바 */
    .mobile-bottom-nav {
        position: fixed !important;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 60px;
        background: #FFFFFF;
        box-shadow: 0 -1px 3px rgba(0,0,0,0.08);
        display: flex !important;
        justify-content: space-around;
        align-items: center;
        z-index: 1000;
        padding: 0 4px;
        border-top: 1px solid rgba(0,0,0,0.06);
    }

    .mobile-nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        color: #757575;
        font-size: 11px;
        padding: 8px 10px;
        min-width: 64px;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        border-radius: 12px;
        background: none;
        border: none;
        cursor: pointer;
        -webkit-tap-highlight-color: transparent;
    }

    .mobile-nav-item.active {
        color: #2E7D32;
        background: rgba(46, 125, 50, 0.12);
    }

    .mobile-nav-item:active {
        transform: scale(0.92);
        background: rgba(0, 0, 0, 0.08);
    }

    .mobile-nav-item:hover {
        background: rgba(0, 0, 0, 0.04);
    }

    /* 햄버거 메뉴 버튼 활성화 상태 */
    #mobile-nav-menu.active {
        color: #2E7D32;
        background: rgba(46, 125, 50, 0.12);
    }

    #mobile-nav-menu.active .mobile-nav-icon svg line:nth-child(1) {
        transform: rotate(45deg) translateY(8px) translateX(6px);
        transition: transform 0.3s ease;
    }

    #mobile-nav-menu.active .mobile-nav-icon svg line:nth-child(2) {
        opacity: 0;
        transition: opacity 0.2s ease;
    }

    #mobile-nav-menu.active .mobile-nav-icon svg line:nth-child(3) {
        transform: rotate(-45deg) translateY(-8px) translateX(6px);
        transition: transform 0.3s ease;
    }

    #mobile-nav-menu .mobile-nav-icon svg line {
        transition: all 0.3s ease;
        transform-origin: center;
    }

    /* SVG 아이콘 스타일 */
    .mobile-nav-icon {
        width: 24px;
        height: 24px;
        margin-bottom: 4px;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .mobile-nav-icon svg {
        width: 24px;
        height: 24px;
        stroke-width: 2;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .mobile-nav-item.active .mobile-nav-icon svg {
        stroke: #2E7D32;
        stroke-width: 2.5;
    }

    .mobile-nav-label {
        font-size: 11px;
        font-weight: 500;
        letter-spacing: 0.01em;
        margin-top: 2px;
    }

    /* 배지 (장바구니 개수, 로그인 상태) */
    .mobile-nav-badge {
        position: absolute;
        top: 4px;
        right: 6px;
        background: #FF3B30;
        color: white;
        border-radius: 10px;
        min-width: 18px;
        height: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 600;
        padding: 0 5px;
        border: 2px solid white;
        box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }

    .mobile-nav-badge.logged-in {
        background: #34C759;
        width: 8px;
        height: 8px;
        min-width: 8px;
        padding: 0;
        top: 6px;
        right: 8px;
        border: 2px solid white;
    }

    /* 메뉴 오버레이 배경 */
    .nav-menu-overlay {
        position: fixed !important;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 998;
        display: block !important;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s ease, visibility 0.3s ease;
        pointer-events: none;
    }

    .nav-menu-overlay.active {
        opacity: 1;
        visibility: visible;
        pointer-events: all;
    }

    /* 햄버거 메뉴 패널 */
    .nav-menu {
        position: fixed !important;
        top: 50px !important;
        left: 0 !important;
        width: 80% !important;
        max-width: 320px !important;
        height: calc(100vh - 110px) !important; /* 상단(50px) + 하단(60px) */
        background-color: #ffffff !important;
        flex-direction: column !important;
        align-items: flex-start !important;
        justify-content: flex-start !important;
        display: flex !important;
        padding: 20px !important;
        box-shadow: 2px 0 10px rgba(0,0,0,0.15) !important;
        z-index: 999 !important;
        overflow-y: auto !important;
        visibility: hidden !important;
        opacity: 0 !important;
        transform: translateX(-100%) !important;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
        pointer-events: none !important;
    }

    .nav-menu.active {
        visibility: visible !important;
        opacity: 1 !important;
        transform: translateX(0) !important;
        pointer-events: all !important;
    }

    .nav-menu li {
        width: 100%;
        margin: 0;
        padding: 0;
        border-bottom: 1px solid #eee;
    }

    .nav-menu li a {
        display: block;
        padding: 15px 10px;
        color: #333;
        text-decoration: none;
        font-size: 16px;
        border-radius: 4px;
        transition: background-color 0.3s ease;
    }

    .nav-menu li a:hover {
        background-color: #f5f5f5;
    }

    /* 모바일에서 드롭다운 */
    .dropdown {
        width: 100%;
        border-bottom: 1px solid #eee;
    }

    .dropbtn::after {
        content: ' +';
        float: right;
        font-size: 16px;
        opacity: 1;
        transition: transform 0.3s ease;
    }

    .dropdown.active .dropbtn::after {
        content: ' −';
        transform: rotate(180deg);
    }

    .dropdown:hover .dropbtn::after {
        opacity: 1;
    }

    .dropdown-content {
        position: static;
        display: none;
        box-shadow: none;
        background-color: #f8f9fa;
        border-radius: 0;
        border: none;
        margin-left: 20px;
        padding: 0;
        margin-top: 5px;
        margin-bottom: 10px;
        border-left: 3px solid #007bff;
    }

    .dropdown-content li a {
        padding: 10px 15px;
        font-size: 14px;
        color: #666;
        border-bottom: 1px solid #e9ecef;
        background-color: transparent;
    }

    .dropdown-content li a:hover {
        background-color: #e3f2fd;
        color: #1976d2;
        padding-left: 15px;
    }

    .dropdown.active .dropdown-content {
        display: block;
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            max-height: 0;
        }
        to {
            opacity: 1;
            max-height: 200px;
        }
    }

    /* 모바일에서 nav-auth 완전히 숨김 (하단 네비게이션 사용) */
    .nav-auth {
        display: none !important;
    }

    /* 하단 네비게이션 표시 */
    .mobile-bottom-nav {
        display: flex !important;
    }

    .nav-menu .cart-link {
        background: #E8F5E8;
        padding: 12px 16px;
        border-radius: 8px;
        margin: 0;
        text-align: center;
        font-size: 0.9rem;
    }

    .nav-menu .user-name-link {
        margin: 0;
        width: 100%;
    }

    .nav-menu .user-name-link::after,
    .nav-menu .user-name-link::before {
        display: none;
    }

    .nav-menu .user-name {
        width: 100%;
        text-align: center;
        font-size: 0.9rem;
        justify-content: center;
        padding: 10px 14px;
    }

    .nav-menu .admin-link,
    .nav-menu .logout-link,
    .nav-menu .login-link,
    .nav-menu .register-link {
        padding: 12px 16px;
        margin: 0;
        background: #f8f9fa;
        border-radius: 8px;
        text-decoration: none;
        color: #333;
        transition: background-color 0.3s ease;
        text-align: center;
        font-size: 0.9rem;
    }

    .nav-menu .admin-link:hover,
    .nav-menu .logout-link:hover,
    .nav-menu .login-link:hover,
    .nav-menu .register-link:hover {
        background: #e9ecef;
    }
}
</style>

<script>
// 모바일 메뉴 토글 함수 - 즉시 정의하여 모든 페이지에서 사용 가능
window.toggleMobileMenu = function(e) {
    console.log('=== toggleMobileMenu CALLED ===');

    // 이벤트 객체가 있으면 기본 동작 방지
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }

    const navMenu = document.querySelector('.nav-menu');
    const menuButton = document.getElementById('mobile-nav-menu');
    const overlay = document.getElementById('nav-menu-overlay');

    console.log('Mobile menu elements:', {
        navMenu: !!navMenu,
        menuButton: !!menuButton,
        overlay: !!overlay,
        currentlyActive: navMenu ? navMenu.classList.contains('active') : false
    });

    if (navMenu) {
        const isActive = navMenu.classList.contains('active');

        if (isActive) {
            // 메뉴 닫기
            console.log('Closing mobile menu');
            navMenu.classList.remove('active');
            if (menuButton) menuButton.classList.remove('active');
            if (overlay) overlay.classList.remove('active');
            document.body.style.overflow = '';
        } else {
            // 메뉴 열기
            console.log('Opening mobile menu');
            navMenu.classList.add('active');
            if (menuButton) menuButton.classList.add('active');
            if (overlay) overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        console.log('Menu state after toggle:', navMenu.classList.contains('active') ? 'OPEN' : 'CLOSED');
    } else {
        console.error('nav-menu element not found!');
    }
};

// 모바일 메뉴 닫기 함수 - 즉시 정의
window.closeMobileMenu = function() {
    console.log('=== closeMobileMenu CALLED ===');

    const navMenu = document.querySelector('.nav-menu');
    const menuButton = document.getElementById('mobile-nav-menu');
    const overlay = document.getElementById('nav-menu-overlay');

    if (navMenu) {
        navMenu.classList.remove('active');
        if (menuButton) menuButton.classList.remove('active');
        if (overlay) overlay.classList.remove('active');
        document.body.style.overflow = '';
        console.log('Mobile menu closed');
    }
};

// 페이지 로드 후 즉시 확인
console.log('Mobile menu functions loaded:', {
    toggleMobileMenu: typeof window.toggleMobileMenu,
    closeMobileMenu: typeof window.closeMobileMenu
});

document.addEventListener('DOMContentLoaded', function() {

    // 드롭다운 메뉴 처리
    const dropdowns = document.querySelectorAll('.dropdown');
    dropdowns.forEach(dropdown => {
        const dropbtn = dropdown.querySelector('.dropbtn');
        if (dropbtn) {
            dropbtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                // 다른 드롭다운 닫기
                dropdowns.forEach(otherDropdown => {
                    if (otherDropdown !== dropdown) {
                        otherDropdown.classList.remove('active');
                    }
                });

                // 현재 드롭다운 토글
                dropdown.classList.toggle('active');
            });
        }
    });

    // 문서 클릭 시 드롭다운 닫기
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            dropdowns.forEach(dropdown => {
                dropdown.classList.remove('active');
            });
        }
    });

    // 윈도우 리사이즈 시 드롭다운 초기화
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            dropdowns.forEach(dropdown => {
                dropdown.classList.remove('active');
            });
        }
    });

    // 장바구니 아이템 수 업데이트
    let previousCartCount = -1; // 이전 카운트 저장

    function updateCartCount() {
        const cartCount = document.getElementById('cartCount');
        const mobileCartCount = document.getElementById('mobileCartCount');

        fetch('/api/cart.php?action=count', {
            method: 'GET',
            cache: 'no-cache' // 캐시 방지
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const count = data.data?.count || data.count || 0;

                // 데스크톱 장바구니 카운트 업데이트
                if (cartCount) {
                    // 카운트가 실제로 변경되었을 때만 애니메이션
                    if (previousCartCount !== -1 && previousCartCount !== count) {
                        cartCount.classList.add('updated');
                        setTimeout(() => {
                            cartCount.classList.remove('updated');
                        }, 500);
                    }

                    cartCount.textContent = count;

                    // 카운트가 0이면 숨기기, 0이 아니면 보이기
                    if (count > 0) {
                        cartCount.style.display = 'flex';
                    } else {
                        cartCount.style.display = 'none';
                    }
                }

                // 모바일 하단 네비게이션 장바구니 배지 업데이트
                if (mobileCartCount) {
                    mobileCartCount.textContent = count;
                    if (count > 0) {
                        mobileCartCount.style.display = 'flex';
                    } else {
                        mobileCartCount.style.display = 'none';
                    }
                }

                previousCartCount = count;
            }
        })
        .catch(error => {
            console.error('장바구니 카운트 업데이트 오류:', error);
        });
    }

    // 페이지 로드시 장바구니 카운트 업데이트
    updateCartCount();

    // 5초마다 자동으로 장바구니 카운트 업데이트 (실시간 반영)
    setInterval(updateCartCount, 5000);

    // 페이지가 다시 포커스될 때도 업데이트
    window.addEventListener('focus', updateCartCount);

    // 전역 함수로 등록 (다른 페이지에서 호출할 수 있도록)
    window.updateCartCount = updateCartCount;
});
</script>
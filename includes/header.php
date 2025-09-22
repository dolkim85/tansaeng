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
            <li class="dropdown">
                <a href="/pages/company/about.php" class="dropbtn">기업소개</a>
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

<style>
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

/* 모바일 헤더 고정 */
@media (max-width: 768px) {
    .header {
        position: fixed !important;
        top: 0;
        left: 0;
        width: 100%;
        height: 60px;
        z-index: 1000;
        background-color: white !important;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1) !important;
        display: flex !important;
        align-items: center;
    }

    .navbar {
        width: 100%;
        height: 100%;
        display: flex !important;
        align-items: center;
        justify-content: space-between;
        padding: 0 15px;
    }

    .mobile-menu-toggle {
        display: flex !important;
        background: none !important;
        border: none !important;
        cursor: pointer !important;
        position: relative !important;
        width: 30px !important;
        height: 30px !important;
        flex-direction: column !important;
        justify-content: space-around !important;
        align-items: center !important;
        padding: 5px !important;
        z-index: 10000 !important;
        visibility: visible !important;
        opacity: 1 !important;
        left: auto !important;
        right: auto !important;
        top: auto !important;
        bottom: auto !important;
        transform: none !important;
        margin: 0 !important;
    }

    .mobile-menu-toggle span {
        display: block !important;
        width: 20px !important;
        height: 2px !important;
        background-color: #2E7D32 !important;
        border-radius: 1px !important;
        transition: all 0.3s ease !important;
        visibility: visible !important;
        opacity: 1 !important;
    }

    .mobile-menu-toggle.active span:nth-child(1) {
        transform: rotate(45deg) translate(5px, 5px);
    }

    .mobile-menu-toggle.active span:nth-child(2) {
        opacity: 0;
    }

    .mobile-menu-toggle.active span:nth-child(3) {
        transform: rotate(-45deg) translate(7px, -6px);
    }

    .nav-menu {
        position: fixed !important;
        top: 60px !important;
        left: 0 !important;
        width: 100% !important;
        height: calc(100vh - 60px) !important;
        background-color: #ffffff !important;
        flex-direction: column !important;
        align-items: flex-start !important;
        justify-content: flex-start !important;
        display: none !important;
        padding: 20px !important;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1) !important;
        z-index: 9999 !important;
        overflow-y: auto !important;
        visibility: hidden !important;
        opacity: 0 !important;
        transform: translateX(-100%) !important;
        transition: all 0.3s ease !important;
    }

    .nav-menu.active {
        display: flex !important;
        visibility: visible !important;
        opacity: 1 !important;
        transform: translateX(0) !important;
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
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {

    // 드롭다운 메뉴 처리만 유지 (모바일 메뉴는 main.js에서 처리)
    const dropdowns = document.querySelectorAll('.dropdown');
    dropdowns.forEach(dropdown => {
        const dropbtn = dropdown.querySelector('.dropbtn');
        if (dropbtn) {
            dropbtn.addEventListener('click', function(e) {
                // 모바일과 데스크톱에서 모두 토글 가능
                if (window.innerWidth <= 768) {
                    e.preventDefault();
                    e.stopPropagation();
                }

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
});
</script>
<?php
/**
 * Admin Dashboard
 * Main admin interface
 */

session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Check admin session - check both role and user_level for compatibility
$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ||
          (isset($_SESSION['user_level']) && $_SESSION['user_level'] == USER_LEVEL_ADMIN);

if (!$isAdmin) {
    header('Location: /pages/auth/login.php?redirect=/admin/');
    exit;
}

$currentUser = [
    'id' => $_SESSION['user_id'],
    'name' => $_SESSION['user_name'] ?? '관리자',
    'email' => $_SESSION['user_email'] ?? 'tansaeng@naver.com'
];

// Get basic statistics
$stats = [
    'total_users' => 0,
    'total_products' => 0,
    'total_orders' => 0,
    'total_analyses' => 0
];

try {
    $db = DatabaseConfig::getConnection();

    // Get user count
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE status = 'active'");
    $stmt->execute();
    $stats['total_users'] = $stmt->fetchColumn();

    // Get product count
    $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE status = 'active'");
    $stmt->execute();
    $stats['total_products'] = $stmt->fetchColumn();

    // Get order count
    $stmt = $db->prepare("SELECT COUNT(*) FROM orders");
    $stmt->execute();
    $stats['total_orders'] = $stmt->fetchColumn();

    // Get analysis count
    $stmt = $db->prepare("SELECT COUNT(*) FROM plant_analysis");
    $stmt->execute();
    $stats['total_analyses'] = $stmt->fetchColumn();

} catch (Exception $e) {
    // Use default values if database error
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>관리자 대시보드 - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-body">
    <?php include 'includes/admin_header.php'; ?>
    
    <div class="admin-container">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-content">
                <div class="page-header">
                    <h1>관리자 대시보드</h1>
                    <p>탄생 스마트팜 시스템 현황을 한눈에 확인하세요</p>
                </div>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">👥</div>
                        <div class="stat-info">
                            <h3><?= number_format($stats['total_users']) ?>명</h3>
                            <p>전체 사용자</p>
                            <small>활성 사용자</small>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">🌱</div>
                        <div class="stat-info">
                            <h3><?= number_format($stats['total_analyses']) ?>건</h3>
                            <p>식물분석 건수</p>
                            <small>분석 완료</small>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">🛒</div>
                        <div class="stat-info">
                            <h3><?= number_format($stats['total_orders']) ?>건</h3>
                            <p>전체 주문</p>
                            <small>주문 관리</small>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">📦</div>
                        <div class="stat-info">
                            <h3><?= number_format($stats['total_products']) ?>개</h3>
                            <p>등록 상품</p>
                            <small>상품 관리</small>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">📸</div>
                        <div class="stat-info">
                            <h3><?= number_format($stats['total_analyses']) ?>장</h3>
                            <p>식물 이미지</p>
                            <small>분석완료</small>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="dashboard-grid">
                    <!-- Recent User Registrations -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>최근 회원가입</h3>
                            <a href="/admin/users/" class="btn btn-outline btn-sm">전체보기</a>
                        </div>
                        <div class="card-body">
                                <p class="no-data">최근 가입한 사용자가 없습니다.</p>
                        </div>
                    </div>

                    <!-- Recent Orders -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>최근 주문</h3>
                            <a href="/admin/orders/" class="btn btn-outline btn-sm">전체보기</a>
                        </div>
                        <div class="card-body">
                                <p class="no-data">최근 주문이 없습니다.</p>
                        </div>
                    </div>

                    <!-- Recent Plant Analysis -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>최근 식물분석</h3>
                            <a href="/admin/plant_analysis/" class="btn btn-outline btn-sm">전체보기</a>
                        </div>
                        <div class="card-body">
                                <p class="no-data">최근 분석 결과가 없습니다.</p>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>빠른 작업</h3>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions">
                            <a href="/admin/users/" class="quick-action">
                                <div class="action-icon">👥</div>
                                <span>사용자 관리</span>
                            </a>
                            <a href="/admin/users/permissions.php" class="quick-action">
                                <div class="action-icon">🔑</div>
                                <span>권한 관리</span>
                            </a>
                            <a href="/admin/products/" class="quick-action">
                                <div class="action-icon">📦</div>
                                <span>상품 관리</span>
                            </a>
                            <a href="/admin/orders/" class="quick-action">
                                <div class="action-icon">🛒</div>
                                <span>주문 관리</span>
                            </a>
                            <a href="/admin/plant_analysis/" class="quick-action">
                                <div class="action-icon">🌱</div>
                                <span>식물분석 관리</span>
                            </a>
                            <a href="/admin/board/" class="quick-action">
                                <div class="action-icon">📝</div>
                                <span>게시판 관리</span>
                            </a>
                            <a href="/admin/settings/" class="quick-action">
                                <div class="action-icon">⚙️</div>
                                <span>시스템 설정</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="/assets/js/main.js"></script>
    <script src="/assets/js/admin.js"></script>
</body>
</html>
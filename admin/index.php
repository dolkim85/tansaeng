<?php
/**
 * Admin Dashboard
 * Main admin interface with real-time statistics
 */

$base_path = dirname(__DIR__);
require_once $base_path . '/classes/Auth.php';
require_once $base_path . '/classes/Database.php';

$auth = Auth::getInstance();
$auth->requireAdmin();

$currentUser = $auth->getCurrentUser();

// Initialize statistics
$stats = [
    'total_users' => 0,
    'active_users' => 0,
    'total_products' => 0,
    'active_products' => 0,
    'total_orders' => 0,
    'pending_orders' => 0,
    'total_analyses' => 0,
    'total_inquiries' => 0,
    'pending_inquiries' => 0,
    'total_posts' => 0,
    'plant_permission_users' => 0
];

// Recent data
$recent_users = [];
$recent_orders = [];
$recent_analyses = [];
$recent_inquiries = [];

try {
    $pdo = Database::getInstance()->getConnection();

    // User statistics
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE user_level < 9");
    $stats['total_users'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE user_level < 9 AND status = 'active'");
    $stats['active_users'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE plant_analysis_permission = 1");
    $stats['plant_permission_users'] = $stmt->fetchColumn();

    // Product statistics
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    $stats['total_products'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'");
    $stats['active_products'] = $stmt->fetchColumn();

    // Order statistics
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders");
    $stats['total_orders'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
    $stats['pending_orders'] = $stmt->fetchColumn();

    // Plant analysis statistics
    $stmt = $pdo->query("SELECT COUNT(*) FROM plant_analysis");
    $stats['total_analyses'] = $stmt->fetchColumn();

    // Inquiry statistics
    $stmt = $pdo->query("SELECT COUNT(*) FROM contact_inquiries");
    $stats['total_inquiries'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM contact_inquiries WHERE status = 'pending'");
    $stats['pending_inquiries'] = $stmt->fetchColumn();

    // Board posts statistics
    $stmt = $pdo->query("SELECT COUNT(*) FROM board_posts");
    $stats['total_posts'] = $stmt->fetchColumn();

    // Recent users (last 5)
    $stmt = $pdo->query("SELECT id, name, email, created_at FROM users WHERE user_level < 9 ORDER BY created_at DESC LIMIT 5");
    $recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent orders (last 5)
    $stmt = $pdo->query("SELECT o.id, o.total_amount, o.status, o.created_at, u.name as user_name
                         FROM orders o
                         LEFT JOIN users u ON o.user_id = u.id
                         ORDER BY o.created_at DESC LIMIT 5");
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent plant analyses (last 5)
    $stmt = $pdo->query("SELECT pa.id, pa.created_at, pa.status, u.name as user_name
                         FROM plant_analysis pa
                         LEFT JOIN users u ON pa.user_id = u.id
                         ORDER BY pa.created_at DESC LIMIT 5");
    $recent_analyses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent inquiries (last 5)
    $stmt = $pdo->query("SELECT id, name, subject, status, created_at
                         FROM contact_inquiries
                         ORDER BY created_at DESC LIMIT 5");
    $recent_inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Dashboard statistics error: " . $e->getMessage());
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
                    <h1>📊 관리자 대시보드</h1>
                    <p>탄생 스마트팜 시스템 현황을 한눈에 확인하세요</p>
                </div>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <a href="/admin/users/" class="stat-card stat-card-link">
                        <div class="stat-icon">👥</div>
                        <div class="stat-info">
                            <h3><?= number_format($stats['total_users']) ?>명</h3>
                            <p>전체 사용자</p>
                            <small>활성: <?= number_format($stats['active_users']) ?>명</small>
                        </div>
                    </a>

                    <a href="/admin/plant_analysis/" class="stat-card stat-card-link">
                        <div class="stat-icon">🌱</div>
                        <div class="stat-info">
                            <h3><?= number_format($stats['total_analyses']) ?>건</h3>
                            <p>식물분석 건수</p>
                            <small>권한 보유: <?= number_format($stats['plant_permission_users']) ?>명</small>
                        </div>
                    </a>

                    <a href="/admin/orders/" class="stat-card stat-card-link">
                        <div class="stat-icon">🛒</div>
                        <div class="stat-info">
                            <h3><?= number_format($stats['total_orders']) ?>건</h3>
                            <p>전체 주문</p>
                            <small>대기: <?= number_format($stats['pending_orders']) ?>건</small>
                        </div>
                    </a>

                    <a href="/admin/products/" class="stat-card stat-card-link">
                        <div class="stat-icon">📦</div>
                        <div class="stat-info">
                            <h3><?= number_format($stats['total_products']) ?>개</h3>
                            <p>등록 상품</p>
                            <small>판매중: <?= number_format($stats['active_products']) ?>개</small>
                        </div>
                    </a>

                    <a href="/admin/pages/inquiries.php" class="stat-card stat-card-link">
                        <div class="stat-icon">📬</div>
                        <div class="stat-info">
                            <h3><?= number_format($stats['total_inquiries']) ?>건</h3>
                            <p>고객 문의</p>
                            <small>미답변: <?= number_format($stats['pending_inquiries']) ?>건</small>
                        </div>
                    </a>

                    <a href="/admin/board/" class="stat-card stat-card-link">
                        <div class="stat-icon">📝</div>
                        <div class="stat-info">
                            <h3><?= number_format($stats['total_posts']) ?>개</h3>
                            <p>게시글</p>
                            <small>전체 게시글</small>
                        </div>
                    </a>
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
                            <?php if (empty($recent_users)): ?>
                                <p class="no-data">최근 가입한 사용자가 없습니다.</p>
                            <?php else: ?>
                                <div class="activity-list">
                                    <?php foreach ($recent_users as $user): ?>
                                        <div class="activity-item">
                                            <div class="activity-icon">👤</div>
                                            <div class="activity-info">
                                                <strong><?= htmlspecialchars($user['name']) ?></strong>
                                                <small><?= htmlspecialchars($user['email']) ?></small>
                                            </div>
                                            <div class="activity-time">
                                                <?= date('m/d H:i', strtotime($user['created_at'])) ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Inquiries -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>최근 문의</h3>
                            <a href="/admin/pages/inquiries.php" class="btn btn-outline btn-sm">전체보기</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_inquiries)): ?>
                                <p class="no-data">최근 문의가 없습니다.</p>
                            <?php else: ?>
                                <div class="activity-list">
                                    <?php foreach ($recent_inquiries as $inquiry): ?>
                                        <div class="activity-item">
                                            <div class="activity-icon">📬</div>
                                            <div class="activity-info">
                                                <strong><?= htmlspecialchars($inquiry['subject']) ?></strong>
                                                <small><?= htmlspecialchars($inquiry['name']) ?></small>
                                            </div>
                                            <div class="activity-time">
                                                <span class="status-badge status-<?= $inquiry['status'] ?>">
                                                    <?= $inquiry['status'] === 'pending' ? '대기' : '답변' ?>
                                                </span>
                                                <?= date('m/d H:i', strtotime($inquiry['created_at'])) ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Orders -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>최근 주문</h3>
                            <a href="/admin/orders/" class="btn btn-outline btn-sm">전체보기</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_orders)): ?>
                                <p class="no-data">최근 주문이 없습니다.</p>
                            <?php else: ?>
                                <div class="activity-list">
                                    <?php foreach ($recent_orders as $order): ?>
                                        <div class="activity-item">
                                            <div class="activity-icon">🛒</div>
                                            <div class="activity-info">
                                                <strong><?= number_format($order['total_amount']) ?>원</strong>
                                                <small><?= htmlspecialchars($order['user_name'] ?? '비회원') ?></small>
                                            </div>
                                            <div class="activity-time">
                                                <?= date('m/d H:i', strtotime($order['created_at'])) ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Plant Analysis -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>최근 식물분석</h3>
                            <a href="/admin/plant_analysis/" class="btn btn-outline btn-sm">전체보기</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_analyses)): ?>
                                <p class="no-data">최근 분석 결과가 없습니다.</p>
                            <?php else: ?>
                                <div class="activity-list">
                                    <?php foreach ($recent_analyses as $analysis): ?>
                                        <div class="activity-item">
                                            <div class="activity-icon">🌱</div>
                                            <div class="activity-info">
                                                <strong>분석 #<?= $analysis['id'] ?></strong>
                                                <small><?= htmlspecialchars($analysis['user_name'] ?? '알 수 없음') ?></small>
                                            </div>
                                            <div class="activity-time">
                                                <?= date('m/d H:i', strtotime($analysis['created_at'])) ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
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
                            <a href="/admin/pages/inquiries.php" class="quick-action">
                                <div class="action-icon">📬</div>
                                <span>문의 관리</span>
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
    <style>
        .stat-card-link {
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .stat-card-link:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            transition: background 0.3s ease;
        }

        .activity-item:hover {
            background: #e9ecef;
        }

        .activity-icon {
            font-size: 1.5em;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            border-radius: 50%;
        }

        .activity-info {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .activity-info strong {
            color: #2c3e50;
            font-size: 0.95em;
        }

        .activity-info small {
            color: #7f8c8d;
            font-size: 0.85em;
            margin-top: 2px;
        }

        .activity-time {
            color: #95a5a6;
            font-size: 0.85em;
            white-space: nowrap;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 4px;
        }

        .status-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: bold;
        }

        .status-pending {
            background: #fee;
            color: #e74c3c;
        }

        .status-answered {
            background: #efe;
            color: #27ae60;
        }

        .no-data {
            text-align: center;
            color: #95a5a6;
            padding: 30px;
            font-size: 0.95em;
        }
    </style>
</body>
</html>

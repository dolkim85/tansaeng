<?php
// Initialize session and auth before any output
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
require_once $base_path . '/classes/Database.php';

$auth = Auth::getInstance();
$auth->requireAdmin();

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';

try {
    $pdo = Database::getInstance()->getConnection();
    
    // 주문 테이블이 없으면 생성
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        order_number VARCHAR(50) UNIQUE NOT NULL,
        customer_name VARCHAR(100) NOT NULL,
        customer_email VARCHAR(100),
        customer_phone VARCHAR(20),
        shipping_address TEXT,
        total_amount DECIMAL(10,2) NOT NULL,
        payment_method ENUM('card', 'bank_transfer', 'virtual_account') DEFAULT 'card',
        payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
        order_status ENUM('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_order_number (order_number),
        INDEX idx_order_status (order_status),
        INDEX idx_created_at (created_at)
    )");
    
    // 주문 상품 테이블
    $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT,
        product_name VARCHAR(255) NOT NULL,
        product_price DECIMAL(10,2) NOT NULL,
        quantity INT NOT NULL,
        total_price DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
    )");

    // 주문 데이터가 없으면 샘플 데이터 생성
    $count_check = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    if ($count_check == 0) {
        // 샘플 주문 데이터 삽입
        $sample_orders = [
            [
                'order_number' => 'ORD-' . date('Ymd') . '-001',
                'customer_name' => '김철수',
                'customer_email' => 'kim@example.com',
                'customer_phone' => '010-1234-5678',
                'shipping_address' => '서울시 강남구 테헤란로 123',
                'total_amount' => 45000,
                'payment_method' => 'card',
                'payment_status' => 'paid',
                'order_status' => 'shipped'
            ],
            [
                'order_number' => 'ORD-' . date('Ymd') . '-002',
                'customer_name' => '이영희',
                'customer_email' => 'lee@example.com',
                'customer_phone' => '010-9876-5432',
                'shipping_address' => '부산시 해운대구 센텀로 456',
                'total_amount' => 32000,
                'payment_method' => 'bank_transfer',
                'payment_status' => 'paid',
                'order_status' => 'delivered'
            ],
            [
                'order_number' => 'ORD-' . date('Ymd') . '-003',
                'customer_name' => '박민수',
                'customer_email' => 'park@example.com',
                'customer_phone' => '010-5555-6666',
                'shipping_address' => '대구시 중구 동성로 789',
                'total_amount' => 28500,
                'payment_method' => 'virtual_account',
                'payment_status' => 'pending',
                'order_status' => 'pending'
            ],
            [
                'order_number' => 'ORD-' . date('Ymd') . '-004',
                'customer_name' => '최지영',
                'customer_email' => 'choi@example.com',
                'customer_phone' => '010-7777-8888',
                'shipping_address' => '인천시 연수구 송도로 321',
                'total_amount' => 67500,
                'payment_method' => 'card',
                'payment_status' => 'paid',
                'order_status' => 'confirmed'
            ],
            [
                'order_number' => 'ORD-' . date('Ymd') . '-005',
                'customer_name' => '장성민',
                'customer_email' => 'jang@example.com',
                'customer_phone' => '010-3333-4444',
                'shipping_address' => '광주시 서구 상무로 654',
                'total_amount' => 19800,
                'payment_method' => 'card',
                'payment_status' => 'paid',
                'order_status' => 'processing'
            ]
        ];

        $insert_sql = "INSERT INTO orders (order_number, customer_name, customer_email, customer_phone, shipping_address, total_amount, payment_method, payment_status, order_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($insert_sql);

        foreach ($sample_orders as $order) {
            $stmt->execute([
                $order['order_number'],
                $order['customer_name'],
                $order['customer_email'],
                $order['customer_phone'],
                $order['shipping_address'],
                $order['total_amount'],
                $order['payment_method'],
                $order['payment_status'],
                $order['order_status']
            ]);
        }
    }
    
    $where_conditions = [];
    $params = [];
    
    if ($search) {
        $where_conditions[] = "(order_number LIKE ? OR customer_name LIKE ? OR customer_email LIKE ? OR customer_phone LIKE ?)";
        $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
    }
    
    if ($status_filter !== 'all') {
        $where_conditions[] = "order_status = ?";
        $params[] = $status_filter;
    }
    
    $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);
    
    $count_sql = "SELECT COUNT(*) FROM orders $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_orders = $stmt->fetchColumn();
    
    $total_pages = ceil($total_orders / $per_page);
    
    // Fix LIMIT/OFFSET binding issue by using direct integer values
    $per_page = (int) $per_page;
    $offset = (int) $offset;
    $sql = "SELECT * FROM orders $where_clause ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    
    // 통계 정보
    $stats_sql = "SELECT 
        COUNT(*) as total_count,
        SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN order_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_count,
        SUM(CASE WHEN order_status = 'shipped' THEN 1 ELSE 0 END) as shipped_count,
        SUM(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END) as delivered_count,
        SUM(total_amount) as total_revenue
        FROM orders";
    $stats = $pdo->query($stats_sql)->fetch();
    
} catch (Exception $e) {
    $error = "주문 정보를 불러오는데 실패했습니다.";
    $orders = [];
    $total_orders = 0;
    $total_pages = 0;
    $stats = ['total_count' => 0, 'pending_count' => 0, 'confirmed_count' => 0, 'shipped_count' => 0, 'delivered_count' => 0, 'total_revenue' => 0];
}

// 주문 상태 변경 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $order_id = $_POST['order_id'] ?? 0;
    
    try {
        if ($action === 'update_status') {
            $new_status = $_POST['new_status'];
            $sql = "UPDATE orders SET order_status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$new_status, $order_id]);
            $success = "주문 상태가 변경되었습니다.";
        }
        
        header("Location: ?success=1");
        exit;
        
    } catch (Exception $e) {
        $error = "상태 변경에 실패했습니다.";
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>주문 관리 - 탄생 관리자</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-body">
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="admin-container">
        <?php include '../includes/admin_sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-content">
                <div class="page-header">
                    <div class="page-title">
                        <h1>주문 관리</h1>
                        <p>고객 주문을 관리하고 배송 상태를 업데이트합니다</p>
                    </div>
                </div>

                <!-- 통계 카드 -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">📊</div>
                        <div class="stat-content">
                            <div class="stat-number"><?= number_format($stats['total_count'] ?? 0) ?></div>
                            <div class="stat-label">전체 주문</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">⏳</div>
                        <div class="stat-content">
                            <div class="stat-number"><?= number_format($stats['pending_count'] ?? 0) ?></div>
                            <div class="stat-label">대기중</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">📦</div>
                        <div class="stat-content">
                            <div class="stat-number"><?= number_format($stats['shipped_count'] ?? 0) ?></div>
                            <div class="stat-label">배송중</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">✅</div>
                        <div class="stat-content">
                            <div class="stat-number"><?= number_format($stats['delivered_count'] ?? 0) ?></div>
                            <div class="stat-label">배송완료</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">💰</div>
                        <div class="stat-content">
                            <div class="stat-number"><?= number_format($stats['total_revenue'] ?? 0) ?>원</div>
                            <div class="stat-label">총 매출</div>
                        </div>
                    </div>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success">주문 상태가 성공적으로 변경되었습니다.</div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="admin-card">
                    <div class="card-header">
                        <div class="search-form">
                            <form method="get" class="admin-search">
                                <select name="status" class="form-select">
                                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>전체 상태</option>
                                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>대기중</option>
                                    <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>확인됨</option>
                                    <option value="processing" <?= $status_filter === 'processing' ? 'selected' : '' ?>>처리중</option>
                                    <option value="shipped" <?= $status_filter === 'shipped' ? 'selected' : '' ?>>배송중</option>
                                    <option value="delivered" <?= $status_filter === 'delivered' ? 'selected' : '' ?>>배송완료</option>
                                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>취소됨</option>
                                </select>
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                       placeholder="주문번호, 고객명, 이메일, 전화번호로 검색" class="form-input">
                                <button type="submit" class="btn btn-primary">검색</button>
                                <a href="?" class="btn btn-outline">전체</a>
                            </form>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <?php if (!empty($orders)): ?>
                            <div class="table-responsive">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th width="120">주문번호</th>
                                            <th width="100">고객명</th>
                                            <th width="120">연락처</th>
                                            <th width="100">주문금액</th>
                                            <th width="80">결제상태</th>
                                            <th width="80">주문상태</th>
                                            <th width="120">주문일시</th>
                                            <th width="100">관리</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($order['order_number']) ?></strong>
                                                </td>
                                                <td>
                                                    <div class="customer-info">
                                                        <div class="customer-name"><?= htmlspecialchars($order['customer_name']) ?></div>
                                                        <?php if ($order['customer_email']): ?>
                                                            <div class="customer-email"><?= htmlspecialchars($order['customer_email']) ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($order['customer_phone'] ?? '-') ?></td>
                                                <td class="amount-cell">
                                                    <?= number_format($order['total_amount'] ?? 0) ?>원
                                                </td>
                                                <td>
                                                    <span class="payment-status-badge payment-<?= $order['payment_status'] ?>">
                                                        <?php
                                                        switch($order['payment_status']) {
                                                            case 'pending': echo '대기중'; break;
                                                            case 'paid': echo '결제완료'; break;
                                                            case 'failed': echo '결제실패'; break;
                                                            case 'refunded': echo '환불완료'; break;
                                                        }
                                                        ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="order-status-badge status-<?= $order['order_status'] ?>">
                                                        <?php
                                                        switch($order['order_status']) {
                                                            case 'pending': echo '대기중'; break;
                                                            case 'confirmed': echo '확인됨'; break;
                                                            case 'processing': echo '처리중'; break;
                                                            case 'shipped': echo '배송중'; break;
                                                            case 'delivered': echo '배송완료'; break;
                                                            case 'cancelled': echo '취소됨'; break;
                                                        }
                                                        ?>
                                                    </span>
                                                </td>
                                                <td><?= date('m-d H:i', strtotime($order['created_at'])) ?></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="detail.php?id=<?= $order['id'] ?>" 
                                                           class="btn btn-sm btn-outline" title="상세보기">👁️</a>
                                                        <button onclick="changeOrderStatus(<?= $order['id'] ?>, '<?= $order['order_status'] ?>')"
                                                                class="btn btn-sm btn-primary" title="상태변경">🔄</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php if ($total_pages > 1): ?>
                                <div class="pagination-wrapper">
                                    <div class="pagination">
                                        <?php if ($page > 1): ?>
                                            <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>" 
                                               class="pagination-link">이전</a>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page-5); $i <= min($total_pages, $page+5); $i++): ?>
                                            <?php if ($i == $page): ?>
                                                <span class="pagination-current"><?= $i ?></span>
                                            <?php else: ?>
                                                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>" 
                                                   class="pagination-link"><?= $i ?></a>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>" 
                                               class="pagination-link">다음</a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="pagination-info">
                                        총 <?= number_format($total_orders ?? 0) ?>건 중 <?= ($page-1)*$per_page+1 ?>-<?= min($page*$per_page, $total_orders ?? 0) ?>건
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="no-data">
                                <div class="no-data-icon">🛒</div>
                                <div class="no-data-text">
                                    <?= $search ? '검색 조건에 맞는 주문이 없습니다.' : '등록된 주문이 없습니다.' ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- 주문 상태 변경 모달 -->
    <div id="statusModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3>주문 상태 변경</h3>
            <p>주문 상태를 변경하시겠습니까?</p>
            <form method="post" id="statusForm">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="order_id" id="statusOrderId">
                <div class="form-group">
                    <label>새 상태:</label>
                    <select name="new_status" id="newStatus" class="form-input">
                        <option value="pending">대기중</option>
                        <option value="confirmed">확인됨</option>
                        <option value="processing">처리중</option>
                        <option value="shipped">배송중</option>
                        <option value="delivered">배송완료</option>
                        <option value="cancelled">취소됨</option>
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="closeModal('statusModal')" class="btn btn-outline">취소</button>
                    <button type="submit" class="btn btn-primary">변경</button>
                </div>
            </form>
        </div>
    </div>

    <script src="/assets/js/main.js"></script>
    <script src="/assets/js/admin.js"></script>
    <script>
        function changeOrderStatus(orderId, currentStatus) {
            document.getElementById('statusOrderId').value = orderId;
            document.getElementById('newStatus').value = currentStatus;
            showModal('statusModal');
        }
        
        function showModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = '';
        }
        
        // 모달 외부 클릭시 닫기
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeModal(modal.id);
                }
            });
        });
    </script>
    
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stat-icon {
            font-size: 2rem;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            border-radius: 50%;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .customer-info {
            min-width: 120px;
        }
        
        .customer-name {
            font-weight: 500;
            margin-bottom: 2px;
        }
        
        .customer-email {
            font-size: 12px;
            color: #666;
        }
        
        .amount-cell {
            font-weight: bold;
            color: #28a745;
        }
        
        .payment-status-badge, .order-status-badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .payment-pending { background: #fff3cd; color: #856404; }
        .payment-paid { background: #d4edda; color: #155724; }
        .payment-failed { background: #f8d7da; color: #721c24; }
        .payment-refunded { background: #cce4f4; color: #004085; }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #cce4f4; color: #004085; }
        .status-processing { background: #e2e3e5; color: #383d41; }
        .status-shipped { background: #b8daff; color: #004085; }
        .status-delivered { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 400px;
            width: 90%;
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
    </style>
</body>
</html>
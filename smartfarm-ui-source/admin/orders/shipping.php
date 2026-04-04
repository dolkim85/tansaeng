<?php
// Initialize session and auth before any output
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
require_once $base_path . '/classes/Database.php';

$auth = Auth::getInstance();
$auth->requireAdmin();

$success = '';
$error = '';

// Handle shipping update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        $pdo = Database::getInstance()->getConnection();

        if ($action === 'update_tracking') {
            $order_id = (int)($_POST['order_id'] ?? 0);
            $tracking_number = trim($_POST['tracking_number'] ?? '');
            $courier = trim($_POST['courier'] ?? '');

            if (empty($tracking_number)) {
                throw new Exception('송장번호를 입력해주세요.');
            }

            $pdo->beginTransaction();

            // Update order with tracking info
            $sql = "UPDATE orders SET
                    shipping_tracking_number = ?,
                    shipping_courier = ?,
                    status = 'shipping',
                    updated_at = NOW()
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tracking_number, $courier, $order_id]);

            $pdo->commit();
            $success = '배송 정보가 업데이트되었습니다.';

        } elseif ($action === 'update_status') {
            $order_id = (int)($_POST['order_id'] ?? 0);
            $status = $_POST['status'] ?? '';

            if (!in_array($status, ['preparing', 'shipping', 'delivered', 'cancelled'])) {
                throw new Exception('유효하지 않은 상태입니다.');
            }

            $sql = "UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$status, $order_id]);

            $success = '주문 상태가 업데이트되었습니다.';
        }

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Get orders for shipping
$filter = $_GET['filter'] ?? 'pending';
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

try {
    $pdo = Database::getInstance()->getConnection();

    $where_conditions = [];
    $params = [];

    if ($filter === 'pending') {
        $where_conditions[] = "o.status IN ('paid', 'preparing')";
    } elseif ($filter === 'shipping') {
        $where_conditions[] = "o.status = 'shipping'";
    } elseif ($filter === 'delivered') {
        $where_conditions[] = "o.status = 'delivered'";
    }

    if ($search) {
        $where_conditions[] = "(o.order_number LIKE ? OR u.name LIKE ? OR o.shipping_name LIKE ? OR o.shipping_tracking_number LIKE ?)";
        $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
    }

    $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

    // Get total count
    $count_sql = "SELECT COUNT(*) FROM orders o
                  LEFT JOIN users u ON o.user_id = u.id
                  $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_orders = $stmt->fetchColumn();

    $total_pages = ceil($total_orders / $per_page);

    // Get orders
    $per_page = (int) $per_page;
    $offset = (int) $offset;
    $sql = "SELECT o.*, u.name as user_name, u.email as user_email
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            $where_clause
            ORDER BY o.created_at DESC
            LIMIT $per_page OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = '주문을 불러오는데 실패했습니다.';
    $orders = [];
    $total_orders = 0;
    $total_pages = 0;
}

// Courier companies
$couriers = [
    'cj' => 'CJ대한통운',
    'hanjin' => '한진택배',
    'lotte' => '롯데택배',
    'logen' => '로젠택배',
    'kdexp' => '경동택배',
    'epost' => '우체국택배',
    'chunil' => '천일택배',
    'hanjin' => '대한통운'
];

function getStatusBadge($status) {
    $badges = [
        'pending' => '<span class="status-badge status-pending">입금대기</span>',
        'paid' => '<span class="status-badge status-paid">결제완료</span>',
        'preparing' => '<span class="status-badge status-preparing">배송준비</span>',
        'shipping' => '<span class="status-badge status-shipping">배송중</span>',
        'delivered' => '<span class="status-badge status-delivered">배송완료</span>',
        'cancelled' => '<span class="status-badge status-cancelled">취소</span>',
    ];
    return $badges[$status] ?? $status;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>배송 관리 - 탄생 관리자</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        .shipping-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 20px;
        }

        .filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 8px 16px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-btn.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        .search-box {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .search-input {
            flex: 1;
            max-width: 400px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-primary {
            background-color: #007bff;
            color: white;
        }

        .btn-success {
            background-color: #28a745;
            color: white;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .orders-table {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow-x: auto;
        }

        .orders-table table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        .orders-table th {
            background-color: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }

        .orders-table td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        .orders-table tr:hover {
            background-color: #f8f9fa;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-paid { background-color: #d1ecf1; color: #0c5460; }
        .status-preparing { background-color: #cfe2ff; color: #084298; }
        .status-shipping { background-color: #d1e7dd; color: #0a3622; }
        .status-delivered { background-color: #d4edda; color: #155724; }
        .status-cancelled { background-color: #f8d7da; color: #721c24; }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: bold;
        }

        .close {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
        }

        .close:hover {
            color: #000;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-control:focus {
            border-color: #007bff;
            outline: none;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }

        .pagination a {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }

        .pagination a.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        .order-info {
            font-size: 13px;
            color: #666;
        }

        @media (max-width: 768px) {
            .shipping-container {
                padding: 10px;
            }

            .modal-content {
                margin: 20px;
                width: calc(100% - 40px);
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>

    <div class="admin-layout">
        <?php include '../includes/admin_sidebar.php'; ?>

        <main class="admin-main">
            <div class="shipping-container">
                <div class="page-header">
                    <h1 class="page-title">배송 관리</h1>

                    <div class="filters">
                        <a href="?filter=pending" class="filter-btn <?= $filter === 'pending' ? 'active' : '' ?>">
                            배송대기
                        </a>
                        <a href="?filter=shipping" class="filter-btn <?= $filter === 'shipping' ? 'active' : '' ?>">
                            배송중
                        </a>
                        <a href="?filter=delivered" class="filter-btn <?= $filter === 'delivered' ? 'active' : '' ?>">
                            배송완료
                        </a>
                    </div>

                    <div class="search-box">
                        <form method="GET" style="display: flex; gap: 10px; width: 100%;">
                            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                            <input type="text" name="search" class="search-input"
                                   placeholder="주문번호, 수령인, 송장번호로 검색..."
                                   value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn btn-primary">검색</button>
                        </form>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="orders-table">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 120px;">주문번호</th>
                                <th>수령인</th>
                                <th>배송지</th>
                                <th style="width: 100px;">택배사</th>
                                <th style="width: 120px;">송장번호</th>
                                <th style="width: 100px;">상태</th>
                                <th style="width: 100px;">주문일</th>
                                <th style="width: 120px;">관리</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px;">
                                        주문 내역이 없습니다.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($order['order_number']) ?></strong>
                                            <div class="order-info">
                                                <?= htmlspecialchars($order['user_name'] ?? '비회원') ?>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($order['shipping_name']) ?></td>
                                        <td>
                                            <?= htmlspecialchars($order['shipping_address']) ?><br>
                                            <small style="color: #666;"><?= htmlspecialchars($order['shipping_phone']) ?></small>
                                        </td>
                                        <td>
                                            <?php if ($order['shipping_courier']): ?>
                                                <?= htmlspecialchars($couriers[$order['shipping_courier']] ?? $order['shipping_courier']) ?>
                                            <?php else: ?>
                                                <span style="color: #999;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($order['shipping_tracking_number']): ?>
                                                <strong><?= htmlspecialchars($order['shipping_tracking_number']) ?></strong>
                                            <?php else: ?>
                                                <span style="color: #999;">미등록</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= getStatusBadge($order['status']) ?></td>
                                        <td><?= date('Y-m-d', strtotime($order['created_at'])) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-success"
                                                    onclick='openTrackingModal(<?= json_encode($order) ?>)'>
                                                배송등록
                                            </button>
                                            <a href="/admin/orders/detail.php?id=<?= $order['id'] ?>"
                                               class="btn btn-sm btn-primary">
                                                상세
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?filter=<?= $filter ?>&search=<?= urlencode($search) ?>&page=<?= $i ?>"
                               class="<?= $i === $page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Tracking Modal -->
    <div id="trackingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">배송 정보 등록</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST" id="trackingForm">
                <input type="hidden" name="action" value="update_tracking">
                <input type="hidden" name="order_id" id="orderId">

                <div class="form-group">
                    <label class="form-label">주문번호</label>
                    <input type="text" class="form-control" id="orderNumber" readonly>
                </div>

                <div class="form-group">
                    <label class="form-label">택배사 *</label>
                    <select name="courier" id="courier" class="form-control" required>
                        <option value="">택배사를 선택하세요</option>
                        <?php foreach ($couriers as $code => $name): ?>
                            <option value="<?= $code ?>"><?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">송장번호 *</label>
                    <input type="text" name="tracking_number" id="trackingNumber"
                           class="form-control" required placeholder="송장번호를 입력하세요">
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">취소</button>
                    <button type="submit" class="btn btn-primary">등록</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openTrackingModal(order) {
            document.getElementById('orderId').value = order.id;
            document.getElementById('orderNumber').value = order.order_number;
            document.getElementById('courier').value = order.shipping_courier || '';
            document.getElementById('trackingNumber').value = order.shipping_tracking_number || '';
            document.getElementById('trackingModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('trackingModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('trackingModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>

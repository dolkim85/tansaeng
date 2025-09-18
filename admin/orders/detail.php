<?php
// Initialize session and auth before any output
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
require_once $base_path . '/classes/Database.php';

$auth = Auth::getInstance();
$auth->requireAdmin();

$success = '';
$error = '';
$order = null;
$order_items = [];

// Get order ID
$order_id = $_GET['id'] ?? 0;
if (!$order_id) {
    header('Location: index.php');
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();
    
    // Get order details
    $sql = "SELECT * FROM orders WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        header('Location: index.php');
        exit;
    }
    
    // Get order items
    $sql = "SELECT oi.*, p.name as current_product_name, p.image_url 
            FROM order_items oi 
            LEFT JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = '주문 정보를 불러올 수 없습니다.';
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $order) {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_order_status':
                $new_status = $_POST['order_status'];
                $sql = "UPDATE orders SET order_status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$new_status, $order_id]);
                $order['order_status'] = $new_status;
                $success = '주문 상태가 변경되었습니다.';
                break;
                
            case 'update_payment_status':
                $new_status = $_POST['payment_status'];
                $sql = "UPDATE orders SET payment_status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$new_status, $order_id]);
                $order['payment_status'] = $new_status;
                $success = '결제 상태가 변경되었습니다.';
                break;
                
            case 'update_shipping_address':
                $shipping_address = $_POST['shipping_address'];
                $sql = "UPDATE orders SET shipping_address = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$shipping_address, $order_id]);
                $order['shipping_address'] = $shipping_address;
                $success = '배송 주소가 변경되었습니다.';
                break;
        }
    } catch (Exception $e) {
        $error = '정보 업데이트에 실패했습니다.';
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>주문 상세 - 탄생 관리자</title>
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
                        <h1>📋 주문 상세</h1>
                        <p><?= htmlspecialchars($order['order_number'] ?? '') ?></p>
                    </div>
                    <div class="page-actions">
                        <a href="index.php" class="btn btn-outline">목록으로</a>
                        <button onclick="window.print()" class="btn btn-secondary">🖨️ 인쇄</button>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <strong>성공:</strong> <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <strong>오류:</strong> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($order): ?>
                <div class="detail-grid">
                    <!-- 주문 정보 -->
                    <div class="detail-card">
                        <div class="card-header">
                            <h3>📋 주문 정보</h3>
                        </div>
                        <div class="card-body">
                            <div class="info-row">
                                <label>주문번호:</label>
                                <span class="order-number"><?= htmlspecialchars($order['order_number']) ?></span>
                            </div>
                            <div class="info-row">
                                <label>주문일시:</label>
                                <span><?= date('Y-m-d H:i:s', strtotime($order['created_at'])) ?></span>
                            </div>
                            <div class="info-row">
                                <label>최근 수정:</label>
                                <span><?= date('Y-m-d H:i:s', strtotime($order['updated_at'])) ?></span>
                            </div>
                            <div class="info-row">
                                <label>총 주문금액:</label>
                                <span class="amount"><?= number_format($order['total_amount']) ?>원</span>
                            </div>
                        </div>
                    </div>

                    <!-- 고객 정보 -->
                    <div class="detail-card">
                        <div class="card-header">
                            <h3>👤 고객 정보</h3>
                        </div>
                        <div class="card-body">
                            <div class="info-row">
                                <label>고객명:</label>
                                <span><?= htmlspecialchars($order['customer_name']) ?></span>
                            </div>
                            <?php if ($order['customer_email']): ?>
                            <div class="info-row">
                                <label>이메일:</label>
                                <span>
                                    <a href="mailto:<?= htmlspecialchars($order['customer_email']) ?>"><?= htmlspecialchars($order['customer_email']) ?></a>
                                </span>
                            </div>
                            <?php endif; ?>
                            <?php if ($order['customer_phone']): ?>
                            <div class="info-row">
                                <label>연락처:</label>
                                <span>
                                    <a href="tel:<?= htmlspecialchars($order['customer_phone']) ?>"><?= htmlspecialchars($order['customer_phone']) ?></a>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- 상태 관리 -->
                    <div class="detail-card">
                        <div class="card-header">
                            <h3>📊 상태 관리</h3>
                        </div>
                        <div class="card-body">
                            <form method="post" class="status-form">
                                <input type="hidden" name="action" value="update_order_status">
                                <div class="form-group">
                                    <label>주문 상태:</label>
                                    <select name="order_status" class="form-select" onchange="this.form.submit()">
                                        <option value="pending" <?= $order['order_status'] === 'pending' ? 'selected' : '' ?>>대기중</option>
                                        <option value="confirmed" <?= $order['order_status'] === 'confirmed' ? 'selected' : '' ?>>확인됨</option>
                                        <option value="processing" <?= $order['order_status'] === 'processing' ? 'selected' : '' ?>>처리중</option>
                                        <option value="shipped" <?= $order['order_status'] === 'shipped' ? 'selected' : '' ?>>배송중</option>
                                        <option value="delivered" <?= $order['order_status'] === 'delivered' ? 'selected' : '' ?>>배송완료</option>
                                        <option value="cancelled" <?= $order['order_status'] === 'cancelled' ? 'selected' : '' ?>>취소됨</option>
                                    </select>
                                </div>
                            </form>
                            
                            <form method="post" class="status-form">
                                <input type="hidden" name="action" value="update_payment_status">
                                <div class="form-group">
                                    <label>결제 상태:</label>
                                    <select name="payment_status" class="form-select" onchange="this.form.submit()">
                                        <option value="pending" <?= $order['payment_status'] === 'pending' ? 'selected' : '' ?>>대기중</option>
                                        <option value="paid" <?= $order['payment_status'] === 'paid' ? 'selected' : '' ?>>결제완료</option>
                                        <option value="failed" <?= $order['payment_status'] === 'failed' ? 'selected' : '' ?>>결제실패</option>
                                        <option value="refunded" <?= $order['payment_status'] === 'refunded' ? 'selected' : '' ?>>환불완료</option>
                                    </select>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- 결제 정보 -->
                    <div class="detail-card">
                        <div class="card-header">
                            <h3>💳 결제 정보</h3>
                        </div>
                        <div class="card-body">
                            <div class="info-row">
                                <label>결제 방법:</label>
                                <span>
                                    <?php
                                    switch($order['payment_method']) {
                                        case 'card': echo '신용카드'; break;
                                        case 'bank_transfer': echo '계좌이체'; break;
                                        case 'virtual_account': echo '가상계좌'; break;
                                        default: echo $order['payment_method'];
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <label>결제 상태:</label>
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
                            </div>
                            <div class="info-row">
                                <label>결제 금액:</label>
                                <span class="amount"><?= number_format($order['total_amount']) ?>원</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 배송 정보 -->
                <?php if ($order['shipping_address']): ?>
                <div class="admin-card">
                    <div class="card-header">
                        <h3>🚚 배송 정보</h3>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="update_shipping_address">
                            <div class="form-group">
                                <label>배송 주소:</label>
                                <textarea name="shipping_address" class="form-input" rows="3"><?= htmlspecialchars($order['shipping_address']) ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">주소 변경</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- 주문 상품 목록 -->
                <div class="admin-card">
                    <div class="card-header">
                        <h3>🛒 주문 상품</h3>
                        <div class="table-info">
                            <span><?= count($order_items) ?>개 상품</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($order_items)): ?>
                            <div class="table-responsive">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th width="80">이미지</th>
                                            <th>상품명</th>
                                            <th width="100">단가</th>
                                            <th width="80">수량</th>
                                            <th width="100">합계</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($order_items as $item): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($item['image_url']): ?>
                                                        <img src="<?= htmlspecialchars($item['image_url']) ?>" 
                                                             alt="<?= htmlspecialchars($item['product_name']) ?>"
                                                             class="product-thumb">
                                                    <?php else: ?>
                                                        <div class="no-image">📦</div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="product-info">
                                                        <div class="product-name"><?= htmlspecialchars($item['product_name']) ?></div>
                                                        <?php if ($item['current_product_name'] && $item['current_product_name'] !== $item['product_name']): ?>
                                                            <small class="text-muted">현재명: <?= htmlspecialchars($item['current_product_name']) ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td class="price-cell"><?= number_format($item['product_price']) ?>원</td>
                                                <td class="quantity-cell"><?= number_format($item['quantity']) ?>개</td>
                                                <td class="total-cell"><?= number_format($item['total_price']) ?>원</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="total-row">
                                            <td colspan="4"><strong>총 주문금액</strong></td>
                                            <td><strong><?= number_format($order['total_amount']) ?>원</strong></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="no-data">
                                <div class="no-data-icon">🛒</div>
                                <div class="no-data-text">주문 상품 정보가 없습니다.</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="/assets/js/main.js"></script>
    
    <style>
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .detail-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .card-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
        }
        
        .card-header h3 {
            margin: 0;
            color: #333;
            font-size: 1.1rem;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 12px;
            align-items: flex-start;
        }
        
        .info-row label {
            min-width: 100px;
            font-weight: 600;
            color: #666;
            margin-right: 15px;
        }
        
        .info-row span {
            flex: 1;
        }
        
        .order-number {
            font-family: monospace;
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .amount {
            font-weight: bold;
            color: #28a745;
            font-size: 1.1rem;
        }
        
        .status-form {
            margin-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #666;
            margin-bottom: 5px;
        }
        
        .form-select, .form-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }
        
        .payment-status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .payment-pending { background: #fff3cd; color: #856404; }
        .payment-paid { background: #d4edda; color: #155724; }
        .payment-failed { background: #f8d7da; color: #721c24; }
        .payment-refunded { background: #cce4f4; color: #004085; }
        
        .product-thumb {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .no-image {
            width: 50px;
            height: 50px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            color: #666;
            font-size: 20px;
        }
        
        .product-info .product-name {
            font-weight: 500;
            color: #333;
        }
        
        .text-muted {
            color: #666;
            font-size: 12px;
        }
        
        .price-cell, .quantity-cell, .total-cell {
            text-align: right;
            font-weight: 500;
        }
        
        .total-row {
            background: #f8f9fa;
            font-weight: bold;
        }
        
        .total-row td {
            border-top: 2px solid #dee2e6;
        }
        
        @media (max-width: 768px) {
            .detail-grid {
                grid-template-columns: 1fr;
            }
            
            .info-row {
                flex-direction: column;
            }
            
            .info-row label {
                margin-bottom: 5px;
            }
        }
        
        @media print {
            .admin-sidebar, .page-actions, .btn {
                display: none !important;
            }
            
            .admin-content {
                margin-left: 0 !important;
            }
        }
    </style>
</body>
</html>
<?php
// 주문 상세 페이지
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentUser = null;
try {
    require_once __DIR__ . '/../../classes/Auth.php';
    require_once __DIR__ . '/../../classes/Database.php';
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();
    if (!$currentUser) {
        header('Location: /pages/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
} catch (Exception $e) {
    error_log("order_detail auth failed: " . $e->getMessage());
    header('Location: /pages/auth/login.php');
    exit;
}

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) {
    header('Location: /pages/store/order.php');
    exit;
}

$order = null;
$items = [];
$errorMessage = null;

try {
    $db = Database::getInstance()->getConnection();

    // 주문 조회 (본인 주문 또는 관리자만)
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $errorMessage = '주문을 찾을 수 없습니다.';
    } elseif ($order['user_id'] != $currentUser['id'] && ($currentUser['user_level'] ?? 0) < 9) {
        $errorMessage = '본인의 주문만 조회할 수 있습니다.';
        $order = null;
    } else {
        // 주문 상품 조회
        $stmtItems = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmtItems->execute([$orderId]);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("order_detail query error: " . $e->getMessage());
    $errorMessage = '주문 정보를 불러오는 중 오류가 발생했습니다.';
}

// 결제수단/상태 한글 매핑
$paymentNames = [
    'card' => '신용/체크카드', 'bank_transfer' => '무통장 입금',
    'virtual_account' => '가상계좌', 'naverpay' => '네이버페이', 'kakao' => '카카오페이',
];
$paymentStatusNames = [
    'pending' => '결제 대기', 'paid' => '결제 완료', 'failed' => '결제 실패', 'refunded' => '환불 완료',
];
$orderStatusNames = [
    'pending' => '주문 접수', 'confirmed' => '주문 확인', 'processing' => '상품 준비중',
    'shipped' => '배송중', 'delivered' => '배송 완료', 'cancelled' => '주문 취소',
];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>주문 상세 - 탄생</title>
    <link rel="stylesheet" href="../../assets/css/main.css?v=<?= date('YmdHis') ?>">
    <style>
        body { font-family: 'Noto Sans KR', sans-serif; background: #f8f9fa; padding-top: 80px; }
        .order-detail-wrap { max-width: 800px; margin: 0 auto; padding: 40px 15px; }
        .page-title { font-size: 1.6rem; font-weight: 700; color: #333; margin-bottom: 24px; }
        .order-number { background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 15px; text-align: center; margin-bottom: 20px; }
        .order-number strong { font-size: 1.2rem; color: #856404; }
        .status-badges { display: flex; gap: 10px; margin-bottom: 20px; }
        .badge { padding: 8px 16px; border-radius: 20px; font-size: 0.9rem; font-weight: 600; }
        .badge-paid { background: #d4edda; color: #155724; }
        .badge-order { background: #cce5ff; color: #004085; }
        .section { background: white; padding: 25px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.1); border: 1px solid #e0e0e0; }
        .section-title { font-size: 1.2rem; font-weight: 700; color: #333; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; }
        .info-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f5f5f5; }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #666; font-weight: 500; }
        .info-value { color: #333; font-weight: 600; }
        .order-item { display: flex; align-items: center; padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
        .order-item:last-child { border-bottom: none; }
        .item-name { flex: 1; font-size: 0.95rem; color: #333; }
        .item-quantity { color: #666; margin: 0 15px; font-size: 0.9rem; }
        .item-price { font-weight: 600; color: #333; }
        .total-amount { background: #f8f9fa; padding: 15px; border-radius: 6px; margin-top: 15px; }
        .total-row { display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: 700; color: #007bff; }
        .action-buttons { display: flex; gap: 15px; margin-top: 30px; }
        .btn { flex: 1; padding: 15px; border: none; border-radius: 6px; font-size: 1rem; font-weight: 600; cursor: pointer; text-align: center; text-decoration: none; display: inline-block; transition: all 0.2s; }
        .btn-primary { background: #007bff; color: white; }
        .btn-primary:hover { background: #0056b3; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        .error-box { background: white; padding: 60px 25px; border-radius: 8px; text-align: center; color: #666; }
        @media (max-width: 768px) {
            .order-detail-wrap { padding: 20px 10px; }
            .action-buttons { flex-direction: column; }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <div class="order-detail-wrap">
        <h1 class="page-title">📋 주문 상세</h1>

        <?php if ($errorMessage): ?>
            <div class="error-box">
                <p><?= htmlspecialchars($errorMessage) ?></p>
                <div class="action-buttons" style="max-width:400px;margin:24px auto 0;">
                    <a href="/pages/store/order.php" class="btn btn-primary">주문 내역으로</a>
                </div>
            </div>
        <?php else: ?>
            <!-- 주문번호 -->
            <div class="order-number">
                주문번호: <strong><?= htmlspecialchars($order['order_number']) ?></strong>
            </div>

            <!-- 상태 배지 -->
            <div class="status-badges">
                <span class="badge badge-paid"><?= $paymentStatusNames[$order['payment_status']] ?? $order['payment_status'] ?></span>
                <span class="badge badge-order"><?= $orderStatusNames[$order['order_status']] ?? $order['order_status'] ?></span>
            </div>

            <!-- 주문 상품 -->
            <div class="section">
                <h2 class="section-title">주문 상품 (<?= count($items) ?>개)</h2>
                <?php foreach ($items as $item): ?>
                <div class="order-item">
                    <div class="item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                    <div class="item-quantity"><?= $item['quantity'] ?>개</div>
                    <div class="item-price"><?= number_format($item['total_price']) ?>원</div>
                </div>
                <?php endforeach; ?>
                <div class="total-amount">
                    <div class="total-row">
                        <span>결제 금액</span>
                        <span><?= number_format($order['total_amount']) ?>원</span>
                    </div>
                </div>
            </div>

            <!-- 배송 정보 -->
            <div class="section">
                <h2 class="section-title">배송 정보</h2>
                <div class="info-row">
                    <span class="info-label">받는 사람</span>
                    <span class="info-value"><?= htmlspecialchars($order['customer_name']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">연락처</span>
                    <span class="info-value"><?= htmlspecialchars($order['customer_phone'] ?? '-') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">배송지</span>
                    <span class="info-value"><?= htmlspecialchars($order['shipping_address'] ?? '-') ?></span>
                </div>
            </div>

            <!-- 결제 정보 -->
            <div class="section">
                <h2 class="section-title">결제 정보</h2>
                <div class="info-row">
                    <span class="info-label">결제 수단</span>
                    <span class="info-value"><?= $paymentNames[$order['payment_method']] ?? $order['payment_method'] ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">주문 일시</span>
                    <span class="info-value"><?= htmlspecialchars($order['created_at']) ?></span>
                </div>
            </div>

            <!-- 액션 버튼 -->
            <div class="action-buttons">
                <a href="/pages/store/order.php" class="btn btn-secondary">주문 내역</a>
                <a href="/pages/store/" class="btn btn-primary">쇼핑 계속하기</a>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>

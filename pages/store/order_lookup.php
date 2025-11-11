<?php
// 주문 조회 페이지 (비회원용)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../classes/Database.php';

$orderInfo = null;
$errorMessage = null;

// 조회 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderNumber = trim($_POST['order_number'] ?? '');
    $buyerEmail = trim($_POST['buyer_email'] ?? '');

    if (empty($orderNumber) || empty($buyerEmail)) {
        $errorMessage = '주문번호와 이메일을 모두 입력해주세요.';
    } else {
        try {
            $db = Database::getInstance()->getConnection();

            // 주문 조회
            $stmt = $db->prepare("
                SELECT o.*,
                    (SELECT GROUP_CONCAT(
                        CONCAT(oi.product_name, ' (', oi.quantity, '개)')
                        SEPARATOR ', '
                    ) FROM order_items oi WHERE oi.order_id = o.id) as items_summary
                FROM orders o
                WHERE o.order_number = ? AND o.buyer_email = ?
            ");

            $stmt->execute([$orderNumber, $buyerEmail]);
            $orderInfo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$orderInfo) {
                $errorMessage = '주문 정보를 찾을 수 없습니다. 주문번호와 이메일을 확인해주세요.';
            }
        } catch (Exception $e) {
            $errorMessage = '조회 중 오류가 발생했습니다: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>비회원 주문 조회 - 탄생</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <style>
        body { background: #f8f9fa; }
        .lookup-container {
            max-width: 600px;
            margin: 100px auto;
            padding: 20px;
        }
        .lookup-box {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.1);
        }
        .lookup-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .lookup-header h1 {
            font-size: 1.8rem;
            color: #333;
            margin-bottom: 10px;
        }
        .lookup-header p {
            color: #666;
            font-size: 0.95rem;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }
        .form-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        .form-input:focus {
            outline: none;
            border-color: #007bff;
        }
        .btn-lookup {
            width: 100%;
            padding: 14px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-lookup:hover {
            background: #0056b3;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        .order-result {
            background: #d4edda;
            padding: 25px;
            border-radius: 8px;
            margin-top: 30px;
            border: 1px solid #c3e6cb;
        }
        .order-result h2 {
            color: #155724;
            font-size: 1.3rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #28a745;
        }
        .order-info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #c3e6cb;
        }
        .order-info-row:last-child {
            border-bottom: none;
        }
        .order-label {
            color: #155724;
            font-weight: 600;
        }
        .order-value {
            color: #333;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .status-paid {
            background: #28a745;
            color: white;
        }
        .status-pending {
            background: #ffc107;
            color: #333;
        }
        .btn-back {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #007bff;
            text-decoration: none;
            font-weight: 600;
        }
        .btn-back:hover {
            text-decoration: underline;
        }
        .helper-text {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 6px;
            margin-top: 20px;
            font-size: 0.9rem;
            color: #004085;
            border: 1px solid #b8daff;
        }
        .helper-text strong {
            display: block;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <div class="lookup-container">
        <div class="lookup-box">
            <div class="lookup-header">
                <h1>📦 비회원 주문 조회</h1>
                <p>주문번호와 이메일로 주문 내역을 확인하세요</p>
            </div>

            <?php if ($errorMessage): ?>
            <div class="error-message">
                ⚠️ <?= htmlspecialchars($errorMessage) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">주문번호</label>
                    <input type="text" name="order_number" class="form-input"
                           placeholder="예: ORD_20250111120000_1234"
                           value="<?= htmlspecialchars($_POST['order_number'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">주문자 이메일</label>
                    <input type="email" name="buyer_email" class="form-input"
                           placeholder="예: example@email.com"
                           value="<?= htmlspecialchars($_POST['buyer_email'] ?? '') ?>" required>
                </div>

                <button type="submit" class="btn-lookup">주문 조회</button>
            </form>

            <?php if ($orderInfo): ?>
            <div class="order-result">
                <h2>✅ 주문 정보</h2>
                <div class="order-info-row">
                    <span class="order-label">주문번호</span>
                    <span class="order-value"><?= htmlspecialchars($orderInfo['order_number']) ?></span>
                </div>
                <div class="order-info-row">
                    <span class="order-label">주문일시</span>
                    <span class="order-value"><?= date('Y-m-d H:i', strtotime($orderInfo['created_at'])) ?></span>
                </div>
                <div class="order-info-row">
                    <span class="order-label">주문자</span>
                    <span class="order-value"><?= htmlspecialchars($orderInfo['buyer_name']) ?></span>
                </div>
                <div class="order-info-row">
                    <span class="order-label">연락처</span>
                    <span class="order-value"><?= htmlspecialchars($orderInfo['buyer_phone']) ?></span>
                </div>
                <div class="order-info-row">
                    <span class="order-label">주문상품</span>
                    <span class="order-value"><?= htmlspecialchars($orderInfo['items_summary']) ?></span>
                </div>
                <div class="order-info-row">
                    <span class="order-label">결제금액</span>
                    <span class="order-value" style="font-weight: 700; color: #007bff; font-size: 1.1rem;">
                        <?= number_format($orderInfo['total_amount']) ?>원
                    </span>
                </div>
                <div class="order-info-row">
                    <span class="order-label">결제방법</span>
                    <span class="order-value">
                        <?php
                        $paymentMethods = [
                            'naverpay' => '네이버페이',
                            'card' => '신용카드',
                            'transfer' => '무통장입금'
                        ];
                        echo $paymentMethods[$orderInfo['payment_method']] ?? $orderInfo['payment_method'];
                        ?>
                    </span>
                </div>
                <div class="order-info-row">
                    <span class="order-label">주문상태</span>
                    <span class="order-value">
                        <?php
                        $statusLabels = [
                            'paid' => '결제완료',
                            'pending' => '입금대기',
                            'shipping' => '배송중',
                            'completed' => '배송완료',
                            'cancelled' => '취소'
                        ];
                        $statusClass = $orderInfo['status'] === 'paid' ? 'status-paid' : 'status-pending';
                        ?>
                        <span class="status-badge <?= $statusClass ?>">
                            <?= $statusLabels[$orderInfo['status']] ?? $orderInfo['status'] ?>
                        </span>
                    </span>
                </div>
            </div>
            <?php endif; ?>

            <div class="helper-text">
                <strong>💡 주문번호를 찾을 수 없나요?</strong>
                주문 완료 시 입력하신 이메일로 주문번호가 발송됩니다.
                이메일을 확인해주세요.
            </div>

            <a href="/pages/store/" class="btn-back">← 쇼핑 계속하기</a>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>

<?php
// 데이터베이스 연결 및 사용자 인증
$currentUser = null;

// 세션이 시작되지 않았으면 시작
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    require_once __DIR__ . '/../../classes/Auth.php';
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();

    if (!$currentUser) {
        header('Location: /pages/auth/login.php');
        exit;
    }
} catch (Exception $e) {
    error_log("Auth failed: " . $e->getMessage());
    header('Location: /pages/auth/login.php');
    exit;
}

// 주문 정보 가져오기
$orderInfo = $_SESSION['last_order'] ?? null;

if (!$orderInfo) {
    header('Location: /pages/store/');
    exit;
}

$orderId = $_GET['order_id'] ?? $orderInfo['order_id'];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>주문 완료 - 탄생</title>
    <link rel="stylesheet" href="../../assets/css/main.css?v=<?= date('YmdHis') ?>">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Noto Sans KR', sans-serif;
            background: #f8f9fa;
        }

        .order-complete-wrap {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 15px;
        }

        .success-icon {
            text-align: center;
            margin-bottom: 30px;
        }

        .success-icon .icon {
            display: inline-block;
            width: 100px;
            height: 100px;
            background: #28a745;
            border-radius: 50%;
            color: white;
            font-size: 50px;
            line-height: 100px;
            margin-bottom: 20px;
            animation: scaleIn 0.5s ease;
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .success-message {
            text-align: center;
            margin-bottom: 40px;
        }

        .success-message h1 {
            font-size: 2rem;
            color: #333;
            margin-bottom: 10px;
        }

        .success-message p {
            font-size: 1.1rem;
            color: #666;
        }

        .order-number {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            margin-bottom: 30px;
        }

        .order-number strong {
            font-size: 1.2rem;
            color: #856404;
        }

        .section {
            background: white;
            padding: 25px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f5f5f5;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #666;
            font-weight: 500;
        }

        .info-value {
            color: #333;
            font-weight: 600;
        }

        .order-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .item-name {
            flex: 1;
            font-size: 0.95rem;
            color: #333;
        }

        .item-quantity {
            color: #666;
            margin: 0 15px;
            font-size: 0.9rem;
        }

        .item-price {
            font-weight: 600;
            color: #333;
        }

        .total-amount {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-top: 15px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 1.2rem;
            font-weight: 700;
            color: #007bff;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 15px;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .notice-box {
            background: #e7f3ff;
            border: 1px solid #007bff;
            border-radius: 6px;
            padding: 15px;
            margin-top: 20px;
        }

        .notice-title {
            font-weight: 600;
            color: #007bff;
            margin-bottom: 8px;
        }

        .notice-text {
            font-size: 0.9rem;
            color: #333;
            line-height: 1.6;
        }

        @media (max-width: 768px) {
            .order-complete-wrap {
                padding: 20px 10px;
            }

            .success-message h1 {
                font-size: 1.5rem;
            }

            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <div class="order-complete-wrap">
        <!-- 성공 아이콘 -->
        <div class="success-icon">
            <div class="icon">✓</div>
        </div>

        <!-- 성공 메시지 -->
        <div class="success-message">
            <h1>주문이 완료되었습니다!</h1>
            <p>소중한 주문 감사드립니다.</p>
        </div>

        <!-- 주문 번호 -->
        <div class="order-number">
            주문번호: <strong><?= htmlspecialchars($orderInfo['order_number']) ?></strong>
        </div>

        <!-- 주문 상품 -->
        <div class="section">
            <h2 class="section-title">주문 상품 (<?= count($orderInfo['items']) ?>개)</h2>
            <?php foreach ($orderInfo['items'] as $item): ?>
            <div class="order-item">
                <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                <div class="item-quantity"><?= $item['quantity'] ?>개</div>
                <div class="item-price"><?= number_format($item['price'] * $item['quantity']) ?>원</div>
            </div>
            <?php endforeach; ?>

            <div class="total-amount">
                <div class="total-row">
                    <span>결제 금액</span>
                    <span><?= number_format($orderInfo['total_amount']) ?>원</span>
                </div>
            </div>
        </div>

        <!-- 배송 정보 -->
        <div class="section">
            <h2 class="section-title">배송 정보</h2>
            <div class="info-row">
                <span class="info-label">받는 사람</span>
                <span class="info-value"><?= htmlspecialchars($orderInfo['delivery_address']['name']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">연락처</span>
                <span class="info-value"><?= htmlspecialchars($orderInfo['delivery_address']['phone']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">배송지</span>
                <span class="info-value">
                    [<?= htmlspecialchars($orderInfo['delivery_address']['zipCode']) ?>]
                    <?= htmlspecialchars($orderInfo['delivery_address']['address']) ?>
                    <?= htmlspecialchars($orderInfo['delivery_address']['addressDetail'] ?? '') ?>
                </span>
            </div>
        </div>

        <!-- 결제 정보 -->
        <div class="section">
            <h2 class="section-title">결제 정보</h2>
            <div class="info-row">
                <span class="info-label">결제 수단</span>
                <span class="info-value">
                    <?php
                    $paymentNames = [
                        'card' => '신용/체크카드',
                        'transfer' => '무통장 입금',
                        'kakao' => '카카오페이',
                        'naver' => '네이버페이'
                    ];
                    echo $paymentNames[$orderInfo['payment_method']] ?? $orderInfo['payment_method'];
                    ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">주문 일시</span>
                <span class="info-value"><?= $orderInfo['created_at'] ?></span>
            </div>
        </div>

        <!-- 안내 사항 -->
        <div class="notice-box">
            <div class="notice-title">💡 배송 안내</div>
            <div class="notice-text">
                • 주문하신 상품은 영업일 기준 2-3일 내 배송됩니다.<br>
                • 배송 관련 문의사항은 고객센터(1588-0000)로 연락 주시기 바랍니다.<br>
                • 주문 내역은 마이페이지에서 확인하실 수 있습니다.
            </div>
        </div>

        <!-- 액션 버튼 -->
        <div class="action-buttons">
            <a href="/pages/store/" class="btn btn-secondary">쇼핑 계속하기</a>
            <?php if ($currentUser): ?>
                <a href="/pages/store/order.php" class="btn btn-primary">📋 주문 내역 확인</a>
            <?php else: ?>
                <a href="/pages/store/order_lookup.php" class="btn btn-primary">📋 주문 내역 확인</a>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>

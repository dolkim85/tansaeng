<?php
/**
 * 나이스페이먼츠 결제 테스트 페이지
 */

$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
require_once $base_path . '/classes/Order.php';
require_once $base_path . '/classes/Payment.php';
require_once $base_path . '/config/env.php';

$auth = Auth::getInstance();
$currentUser = $auth->getCurrentUser();

// 로그인 체크
if (!$currentUser) {
    header('Location: /pages/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// 테스트 주문 정보
$testOrderData = [
    'order_number' => 'TEST-' . date('YmdHis'),
    'product_name' => '탄생 스마트팜 상품 (테스트)',
    'amount' => 10000, // 10,000원 (테스트 금액)
    'customer_name' => $currentUser['name'],
    'customer_email' => $currentUser['email'],
    'customer_phone' => $currentUser['phone'] ?? '01012345678'
];

$payment = new Payment();
$clientId = $payment->getClientId();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>나이스페이먼츠 결제 테스트 - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        .payment-test-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .payment-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .payment-info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .payment-info-row:last-child {
            border-bottom: none;
        }
        .payment-info-label {
            font-weight: 600;
            color: #495057;
        }
        .payment-info-value {
            color: #212529;
        }
        .payment-amount {
            font-size: 24px;
            font-weight: bold;
            color: #28a745;
        }
        .btn-payment {
            width: 100%;
            padding: 15px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-payment:hover {
            background: #218838;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
    </style>
</head>
<body>
    <div class="payment-test-container">
        <h1>💳 나이스페이먼츠 결제 테스트</h1>

        <div class="alert alert-info">
            <strong>ℹ️ 테스트 환경</strong><br>
            실제 결제가 진행됩니다. 테스트 카드를 사용하거나 소액으로 테스트해주세요.
        </div>

        <div class="payment-info">
            <h3>주문 정보</h3>
            <div class="payment-info-row">
                <span class="payment-info-label">주문번호</span>
                <span class="payment-info-value"><?= htmlspecialchars($testOrderData['order_number']) ?></span>
            </div>
            <div class="payment-info-row">
                <span class="payment-info-label">상품명</span>
                <span class="payment-info-value"><?= htmlspecialchars($testOrderData['product_name']) ?></span>
            </div>
            <div class="payment-info-row">
                <span class="payment-info-label">주문자</span>
                <span class="payment-info-value"><?= htmlspecialchars($testOrderData['customer_name']) ?></span>
            </div>
            <div class="payment-info-row">
                <span class="payment-info-label">이메일</span>
                <span class="payment-info-value"><?= htmlspecialchars($testOrderData['customer_email']) ?></span>
            </div>
            <div class="payment-info-row">
                <span class="payment-info-label">연락처</span>
                <span class="payment-info-value"><?= htmlspecialchars($testOrderData['customer_phone']) ?></span>
            </div>
            <div class="payment-info-row">
                <span class="payment-info-label">결제금액</span>
                <span class="payment-info-value payment-amount"><?= number_format($testOrderData['amount']) ?>원</span>
            </div>
        </div>

        <button onclick="requestPay()" class="btn-payment">
            결제하기
        </button>

        <div style="margin-top: 20px; text-align: center;">
            <a href="/index.php" style="color: #6c757d;">메인으로 돌아가기</a>
        </div>
    </div>

    <!-- 나이스페이먼츠 JavaScript SDK -->
    <script src="https://pay.nicepay.co.kr/v1/js/"></script>
    <script>
        // 결제 요청
        function requestPay() {
            // NICEPAY 결제창 호출
            NICEPAY.requestPay({
                clientId: '<?= $clientId ?>',
                method: 'card',
                orderId: '<?= $testOrderData['order_number'] ?>',
                amount: <?= $testOrderData['amount'] ?>,
                goodsName: '<?= addslashes($testOrderData['product_name']) ?>',
                returnUrl: 'https://www.tansaeng.com/api/payment/nicepay_callback.php',

                // 구매자 정보
                buyerName: '<?= addslashes($testOrderData['customer_name']) ?>',
                buyerTel: '<?= $testOrderData['customer_phone'] ?>',
                buyerEmail: '<?= $testOrderData['customer_email'] ?>',

                // 결제 수단 (전체)
                payMethod: 'CARD,BANK,VBANK,CELLPHONE',

                // 기타 옵션
                reservedMsg: 'TEST',
                fnError: function(result) {
                    console.error('결제 오류:', result);
                    alert('결제 처리 중 오류가 발생했습니다.\n' + (result.errorMsg || result.msg || '알 수 없는 오류'));
                }
            });
        }
    </script>
</body>
</html>

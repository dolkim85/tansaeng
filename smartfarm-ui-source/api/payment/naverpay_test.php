<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>네이버페이 결제 테스트</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Noto Sans KR', sans-serif;
            background: linear-gradient(135deg, #03C75A 0%, #02b350 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo-text {
            font-size: 2.5rem;
            font-weight: 700;
            color: #03C75A;
        }
        h1 {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 10px;
            text-align: center;
        }
        .subtitle {
            text-align: center;
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 30px;
        }
        .info-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            color: #666;
            font-size: 0.9rem;
        }
        .info-value {
            color: #333;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .total-amount {
            background: #03C75A;
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
        }
        .total-label {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        .total-value {
            font-size: 2rem;
            font-weight: 700;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        .form-input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        .form-input:focus {
            outline: none;
            border-color: #03C75A;
        }
        .btn-container {
            display: flex;
            gap: 10px;
        }
        .btn {
            flex: 1;
            padding: 16px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-success {
            background: #03C75A;
            color: white;
        }
        .btn-success:hover {
            background: #02b350;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(3, 199, 90, 0.4);
        }
        .btn-cancel {
            background: #dc3545;
            color: white;
        }
        .btn-cancel:hover {
            background: #c82333;
        }
        .notice {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <div class="logo-text">N</div>
        </div>

        <h1>네이버페이 결제</h1>
        <div class="subtitle">테스트 환경 (실제 결제되지 않음)</div>

        <div class="notice">
            ⚠️ 이것은 개발/테스트 환경입니다. 실제 결제가 이루어지지 않습니다.
        </div>

        <div class="info-box">
            <div class="info-row">
                <span class="info-label">주문번호</span>
                <span class="info-value"><?= htmlspecialchars($_GET['merchant_pay_key'] ?? 'N/A') ?></span>
            </div>
        </div>

        <div class="total-amount">
            <div class="total-label">결제 금액</div>
            <div class="total-value"><?= number_format($_GET['amount'] ?? 0) ?>원</div>
        </div>

        <form id="paymentForm">
            <div class="form-group">
                <label class="form-label">이름</label>
                <input type="text" class="form-input" id="buyerName" placeholder="구매자 이름" required>
            </div>

            <div class="form-group">
                <label class="form-label">이메일</label>
                <input type="email" class="form-input" id="buyerEmail" placeholder="test@example.com" required>
            </div>

            <div class="form-group">
                <label class="form-label">연락처</label>
                <input type="tel" class="form-input" id="buyerPhone" placeholder="010-0000-0000" required>
            </div>

            <div class="btn-container">
                <button type="button" class="btn btn-cancel" onclick="cancelPayment()">취소</button>
                <button type="submit" class="btn btn-success">결제하기</button>
            </div>
        </form>
    </div>

    <script>
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const merchantPayKey = '<?= htmlspecialchars($_GET['merchant_pay_key'] ?? '') ?>';
            const amount = '<?= htmlspecialchars($_GET['amount'] ?? 0) ?>';
            const buyerName = document.getElementById('buyerName').value;
            const buyerEmail = document.getElementById('buyerEmail').value;
            const buyerPhone = document.getElementById('buyerPhone').value;

            if (!buyerName || !buyerEmail || !buyerPhone) {
                alert('모든 정보를 입력해주세요.');
                return;
            }

            // 결제 성공 시뮬레이션
            const paymentId = 'TEST_' + Date.now();

            // 콜백 페이지로 이동
            window.location.href = '/api/payment/naverpay_callback.php?' +
                'merchant_pay_key=' + merchantPayKey +
                '&payment_id=' + paymentId +
                '&amount=' + amount +
                '&buyer_name=' + encodeURIComponent(buyerName) +
                '&buyer_email=' + encodeURIComponent(buyerEmail) +
                '&buyer_phone=' + encodeURIComponent(buyerPhone) +
                '&status=SUCCESS';
        });

        function cancelPayment() {
            if (confirm('결제를 취소하시겠습니까?')) {
                window.location.href = '/pages/store/cart.php';
            }
        }
    </script>
</body>
</html>

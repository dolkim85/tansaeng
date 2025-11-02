<?php
/**
 * 결제 결과 페이지
 */

$success = isset($_GET['success']) && $_GET['success'] == '1';
$orderId = $_GET['orderId'] ?? '';
$amount = $_GET['amount'] ?? 0;
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>결제 <?= $success ? '완료' : '실패' ?> - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        .result-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .result-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        .result-icon.success {
            color: #28a745;
        }
        .result-icon.error {
            color: #dc3545;
        }
        .result-title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #212529;
        }
        .result-message {
            font-size: 16px;
            color: #6c757d;
            margin-bottom: 30px;
        }
        .result-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            text-align: left;
        }
        .result-info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .result-info-row:last-child {
            border-bottom: none;
        }
        .result-info-label {
            font-weight: 600;
            color: #495057;
        }
        .result-info-value {
            color: #212529;
        }
        .btn {
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            margin: 5px;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="result-container">
        <?php if ($success): ?>
            <!-- 결제 성공 -->
            <div class="result-icon success">✅</div>
            <h1 class="result-title">결제가 완료되었습니다</h1>
            <p class="result-message">결제가 정상적으로 처리되었습니다.</p>

            <div class="result-info">
                <div class="result-info-row">
                    <span class="result-info-label">주문번호</span>
                    <span class="result-info-value"><?= htmlspecialchars($orderId) ?></span>
                </div>
                <div class="result-info-row">
                    <span class="result-info-label">결제금액</span>
                    <span class="result-info-value" style="color: #28a745; font-weight: bold; font-size: 20px;">
                        <?= number_format($amount) ?>원
                    </span>
                </div>
                <div class="result-info-row">
                    <span class="result-info-label">결제일시</span>
                    <span class="result-info-value"><?= date('Y-m-d H:i:s') ?></span>
                </div>
            </div>

            <div>
                <a href="/index.php" class="btn btn-primary">메인으로</a>
                <a href="/pages/payment/test.php" class="btn btn-secondary">다시 테스트</a>
            </div>

        <?php else: ?>
            <!-- 결제 실패 -->
            <div class="result-icon error">❌</div>
            <h1 class="result-title">결제에 실패했습니다</h1>
            <p class="result-message">
                결제 처리 중 문제가 발생했습니다.<br>
                <?= $error ? '<strong>' . htmlspecialchars($error) . '</strong>' : '다시 시도해주세요.' ?>
            </p>

            <div style="margin-top: 30px;">
                <a href="/pages/payment/test.php" class="btn btn-primary">다시 시도</a>
                <a href="/index.php" class="btn btn-secondary">메인으로</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

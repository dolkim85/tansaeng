<?php
/**
 * 나이스페이먼츠 결제 승인 콜백 API
 * 결제 완료 후 나이스페이먼츠가 호출하는 endpoint
 */

$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Database.php';
require_once $base_path . '/classes/Order.php';
require_once $base_path . '/classes/Payment.php';

// 로그 기록 함수
function logPayment($message, $data = null) {
    $logDir = dirname(dirname(__DIR__)) . '/logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/payment_' . date('Y-m-d') . '.log';
    $logMessage = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if ($data) {
        $logMessage .= ' | Data: ' . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND);
}

try {
    // POST 데이터 받기
    $authResultCode = $_POST['authResultCode'] ?? '';
    $authResultMsg = $_POST['authResultMsg'] ?? '';
    $tid = $_POST['tid'] ?? '';
    $clientId = $_POST['clientId'] ?? '';
    $orderId = $_POST['orderId'] ?? '';
    $amount = $_POST['amount'] ?? 0;
    $mallReserved = $_POST['mallReserved'] ?? '';
    $authToken = $_POST['authToken'] ?? '';
    $signature = $_POST['signature'] ?? '';

    logPayment('결제 콜백 수신', $_POST);

    // 인증 실패 체크
    if ($authResultCode !== '0000') {
        logPayment('결제 인증 실패', ['code' => $authResultCode, 'msg' => $authResultMsg]);
        throw new Exception($authResultMsg ?: '결제 인증에 실패했습니다.');
    }

    // tid가 없으면 오류
    if (empty($tid)) {
        throw new Exception('거래 ID(tid)가 없습니다.');
    }

    // 결제 승인 요청
    $payment = new Payment();
    $approveResult = $payment->approve($tid, $amount);

    logPayment('결제 승인 결과', $approveResult);

    if (!$approveResult['success']) {
        throw new Exception($approveResult['message'] ?? '결제 승인에 실패했습니다.');
    }

    // 승인 성공 - DB에 저장
    $paymentData = $approveResult['data'];

    // 주문 조회 (order_number로)
    $order = new Order();
    $orderInfo = $order->getOrderByNumber($orderId);

    if (!$orderInfo) {
        // 테스트 주문인 경우 임시 주문 생성
        logPayment('주문 정보 없음 - 테스트 주문 생성', ['orderId' => $orderId]);

        $db = Database::getInstance();
        $pdo = $db->getConnection();

        // 간단한 테스트 주문 생성
        $stmt = $pdo->prepare("
            INSERT INTO orders (
                user_id, order_number, customer_name, customer_email,
                total_amount, payment_method, payment_status, order_status
            ) VALUES (1, ?, '테스트', 'test@test.com', ?, 'card', 'pending', 'pending')
        ");
        $stmt->execute([$orderId, $amount]);
        $orderDbId = $pdo->lastInsertId();

        logPayment('테스트 주문 생성 완료', ['order_id' => $orderDbId]);
    } else {
        $orderDbId = $orderInfo['id'];
    }

    // 결제 정보 저장
    $savePaymentData = [
        'tid' => $tid,
        'method' => $paymentData['payMethod'] ?? 'CARD',
        'amount' => $amount,
        'status' => 'approved',
        'result_code' => $paymentData['resultCode'] ?? '0000',
        'result_message' => $paymentData['resultMsg'] ?? '승인 성공',
        'card_company' => $paymentData['cardName'] ?? null,
        'card_number' => $paymentData['cardNum'] ?? null,
        'installment' => $paymentData['installment'] ?? 0,
        'approve_no' => $paymentData['authCode'] ?? null,
        'paid_at' => date('Y-m-d H:i:s'),
        'pg_raw_data' => json_encode($paymentData, JSON_UNESCAPED_UNICODE)
    ];

    $saveResult = $order->savePayment($orderDbId, $savePaymentData);

    if ($saveResult['success']) {
        // 주문 상태 업데이트
        $order->updatePaymentStatus($orderDbId, 'paid');
        logPayment('결제 정보 저장 성공', ['payment_id' => $saveResult['payment_id']]);
    } else {
        logPayment('결제 정보 저장 실패', $saveResult);
    }

    // 성공 페이지로 리다이렉트
    header('Location: /pages/payment/result.php?success=1&orderId=' . urlencode($orderId) . '&amount=' . $amount);
    exit;

} catch (Exception $e) {
    logPayment('결제 처리 오류', ['error' => $e->getMessage()]);

    // 실패 페이지로 리다이렉트
    header('Location: /pages/payment/result.php?success=0&error=' . urlencode($e->getMessage()));
    exit;
}

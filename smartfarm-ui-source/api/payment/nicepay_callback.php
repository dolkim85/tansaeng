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
    // 나이스페이먼츠 v3 POST 데이터 받기
    $resultCode = $_POST['ResultCode'] ?? '';
    $resultMsg = $_POST['ResultMsg'] ?? '';
    $tid = $_POST['TID'] ?? '';
    $moid = $_POST['Moid'] ?? '';  // 주문번호
    $amt = $_POST['Amt'] ?? 0;
    $authDate = $_POST['AuthDate'] ?? '';
    $authCode = $_POST['AuthCode'] ?? '';
    $payMethod = $_POST['PayMethod'] ?? '';
    $cardName = $_POST['CardName'] ?? '';
    $cardNum = $_POST['CardQuota'] ?? '';

    logPayment('결제 콜백 수신 (v3)', $_POST);

    // 결제 실패 체크
    if ($resultCode !== '3001') {  // 3001 = 결제 성공
        logPayment('결제 실패', ['code' => $resultCode, 'msg' => $resultMsg]);
        throw new Exception($resultMsg ?: '결제에 실패했습니다.');
    }

    // TID가 없으면 오류
    if (empty($tid)) {
        throw new Exception('거래 ID(TID)가 없습니다.');
    }

    // v3는 결제창에서 이미 승인 완료된 상태
    // DB에 결제 정보만 저장하면 됨

    // 주문 조회 (order_number로)
    $order = new Order();
    $orderInfo = $order->getOrderByNumber($moid);

    if (!$orderInfo) {
        // 주문 정보 없음
        logPayment('주문 정보 없음', ['moid' => $moid]);
        throw new Exception('주문 정보를 찾을 수 없습니다.');
    }

    $orderDbId = $orderInfo['id'];

    // 결제 정보 저장
    $savePaymentData = [
        'tid' => $tid,
        'method' => $payMethod,
        'amount' => $amt,
        'status' => 'approved',
        'result_code' => $resultCode,
        'result_message' => $resultMsg,
        'card_company' => $cardName,
        'card_number' => $cardNum,
        'installment' => 0,
        'approve_no' => $authCode,
        'paid_at' => date('Y-m-d H:i:s'),
        'pg_raw_data' => json_encode($_POST, JSON_UNESCAPED_UNICODE)
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
    header('Location: /pages/payment/result.php?success=1&orderId=' . urlencode($moid) . '&amount=' . $amt);
    exit;

} catch (Exception $e) {
    logPayment('결제 처리 오류', ['error' => $e->getMessage()]);

    // 실패 페이지로 리다이렉트
    header('Location: /pages/payment/result.php?success=0&error=' . urlencode($e->getMessage()));
    exit;
}

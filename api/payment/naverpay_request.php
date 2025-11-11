<?php
/**
 * 네이버페이 결제 요청 API
 * 장바구니에서 네이버페이 결제 시작
 */

header('Content-Type: application/json; charset=utf-8');

// 세션 시작
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    require_once __DIR__ . '/../../classes/NaverPay.php';
    require_once __DIR__ . '/../../classes/Database.php';

    // POST 데이터 받기
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['items']) || empty($input['items'])) {
        throw new Exception('주문 상품 정보가 없습니다.');
    }

    $items = $input['items'];

    // 총 금액 계산
    $totalAmount = 0;
    foreach ($items as $item) {
        $subtotal = $item['price'] * $item['quantity'];
        $totalAmount += $subtotal;

        // 배송비 계산
        $shippingCost = $item['shipping_cost'] ?? 0;
        $shippingUnitCount = $item['shipping_unit_count'] ?? 1;
        if ($shippingCost > 0 && $shippingUnitCount > 0) {
            $shippingTimes = ceil($item['quantity'] / $shippingUnitCount);
            $totalAmount += $shippingCost * $shippingTimes;
        }
    }

    // 네이버페이 결제 요청
    $naverPay = new NaverPay();
    $result = $naverPay->requestPayment([
        'items' => $items
    ]);

    if ($result['success']) {
        // 세션에 임시 주문 정보 저장 (결제 완료 후 사용)
        $_SESSION['naverpay_temp_order'] = [
            'merchant_pay_key' => $result['merchant_pay_key'],
            'items' => $items,
            'total_amount' => $totalAmount,
            'created_at' => time()
        ];

        echo json_encode([
            'success' => true,
            'payment_url' => $result['payment_url'],
            'merchant_pay_key' => $result['merchant_pay_key']
        ], JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception($result['message'] ?? '결제 요청에 실패했습니다.');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

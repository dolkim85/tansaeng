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
    require_once __DIR__ . '/../../classes/Auth.php';

    // 로그인 상태 확인 (회원/비회원 구분용)
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();
    $userId = $currentUser['id'] ?? null;

    error_log("NaverPay Request - User ID: " . ($userId ?? 'guest'));

    // POST 데이터 또는 세션에서 주문 정보 가져오기
    $input = json_decode(file_get_contents('php://input'), true);
    $items = null;

    // 1. POST 데이터에서 items 확인
    if (isset($input['items']) && !empty($input['items'])) {
        $items = $input['items'];
    }
    // 2. 세션에서 order_items 확인 (바로구매용)
    elseif (isset($_SESSION['order_items']) && !empty($_SESSION['order_items'])) {
        $items = $_SESSION['order_items'];
    }

    if (empty($items)) {
        throw new Exception('주문 상품 정보가 없습니다.');
    }

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
            'user_id' => $userId, // 로그인 사용자 ID (비회원은 null)
            'user_info' => $currentUser, // 회원 정보 (비회원은 null)
            'is_member' => !empty($userId), // 회원 여부
            'created_at' => time()
        ];

        error_log("NaverPay 세션 저장 완료 - 회원: " . ($userId ? 'Yes' : 'No'));

        echo json_encode([
            'success' => true,
            'payment_url' => $result['payment_url'],
            'merchant_pay_key' => $result['merchant_pay_key'],
            'is_member' => !empty($userId)
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

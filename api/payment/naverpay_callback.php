<?php
/**
 * 네이버페이 결제 완료 콜백
 * 결제 완료 후 승인 및 주문 생성
 */

// 세션 시작
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_log("=== NaverPay Callback Start ===");
error_log("GET params: " . json_encode($_GET));

try {
    require_once __DIR__ . '/../../classes/Database.php';
    require_once __DIR__ . '/../../classes/NaverPay.php';
    require_once __DIR__ . '/../../classes/Auth.php';

    // 네이버페이 콜백 파라미터
    $resultCode = $_GET['resultCode'] ?? '';
    $paymentId = $_GET['paymentId'] ?? null;
    $reserveId = $_GET['reserveId'] ?? null;

    error_log("NaverPay Callback - resultCode: $resultCode, paymentId: $paymentId");

    // 결제 실패 처리
    if ($resultCode !== 'Success') {
        $message = $_GET['message'] ?? '결제가 취소되었습니다.';
        error_log("NaverPay Callback - Failed: $message");
        header('Location: /pages/store/cart.php?error=' . urlencode($message));
        exit;
    }

    if (!$paymentId) {
        throw new Exception('결제 ID가 누락되었습니다.');
    }

    // 세션에서 임시 주문 정보 가져오기
    if (!isset($_SESSION['naverpay_temp_order'])) {
        error_log("NaverPay Callback - No temp order in session");
        throw new Exception('주문 정보를 찾을 수 없습니다. 다시 시도해주세요.');
    }

    $tempOrder = $_SESSION['naverpay_temp_order'];
    error_log("NaverPay Callback - Temp order: " . json_encode($tempOrder));

    // 결제 승인 요청
    $naverPay = new NaverPay();
    $approveResult = $naverPay->approvePayment($paymentId);

    error_log("NaverPay Approve Result: " . json_encode($approveResult));

    if (!$approveResult['success']) {
        throw new Exception('결제 승인 실패: ' . ($approveResult['message'] ?? '알 수 없는 오류'));
    }

    // 승인 결과에서 정보 추출
    $paymentData = $approveResult['data'] ?? [];
    $approvedAmount = $paymentData['totalPayAmount'] ?? $tempOrder['total_amount'];

    // 데이터베이스 연결
    $db = Database::getInstance()->getConnection();

    // 사용자 정보
    $userId = $tempOrder['user_id'] ?? null;
    $isMember = $tempOrder['is_member'] ?? false;
    $userInfo = $tempOrder['user_info'] ?? [];

    // 주문 정보
    $orderNumber = $tempOrder['merchant_pay_key'];
    $items = $tempOrder['items'];
    $totalAmount = $tempOrder['total_amount'];

    // 구매자 정보 (회원이면 회원정보, 아니면 네이버페이 정보)
    $buyerName = $userInfo['name'] ?? $paymentData['buyerName'] ?? '네이버페이 구매자';
    $buyerEmail = $userInfo['email'] ?? $paymentData['buyerEmail'] ?? '';
    $buyerPhone = $userInfo['phone'] ?? $paymentData['buyerTel'] ?? '';

    // 트랜잭션 시작
    $db->beginTransaction();

    try {
        // orders 테이블에 주문 생성
        $stmt = $db->prepare("
            INSERT INTO orders (
                order_number,
                user_id,
                buyer_name,
                buyer_email,
                buyer_phone,
                total_amount,
                payment_method,
                payment_id,
                status,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $orderNumber,
            $userId,
            $buyerName,
            $buyerEmail,
            $buyerPhone,
            $approvedAmount,
            'naverpay',
            $paymentId,
            'paid'
        ]);

        $orderId = $db->lastInsertId();
        error_log("NaverPay Callback - Order created: $orderId");

        // order_items 테이블에 주문 상품 추가
        $stmtItem = $db->prepare("
            INSERT INTO order_items (
                order_id,
                product_id,
                product_name,
                quantity,
                price,
                shipping_cost
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($items as $item) {
            $shippingCost = $item['shipping_cost'] ?? 0;
            $shippingUnitCount = $item['shipping_unit_count'] ?? 1;
            $itemShippingCost = 0;

            if ($shippingCost > 0 && $shippingUnitCount > 0) {
                $shippingTimes = ceil($item['quantity'] / $shippingUnitCount);
                $itemShippingCost = $shippingCost * $shippingTimes;
            }

            $stmtItem->execute([
                $orderId,
                $item['product_id'],
                $item['name'],
                $item['quantity'],
                $item['price'],
                $itemShippingCost
            ]);
        }

        // 장바구니에서 주문한 상품 삭제 (로그인한 경우만)
        if ($userId) {
            $productIds = array_column($items, 'product_id');
            if (!empty($productIds)) {
                $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
                $stmtDelete = $db->prepare("
                    DELETE FROM cart WHERE user_id = ? AND product_id IN ($placeholders)
                ");
                $stmtDelete->execute(array_merge([$userId], $productIds));
            }
        }

        // 트랜잭션 커밋
        $db->commit();

        // 세션 정리
        unset($_SESSION['naverpay_temp_order']);
        unset($_SESSION['order_items']);

        error_log("NaverPay Callback - Success, redirecting to order_complete");

        // 주문 완료 페이지로 이동
        header('Location: /pages/store/order_complete.php?order_id=' . $orderId . '&payment=naverpay');
        exit;

    } catch (Exception $e) {
        $db->rollBack();
        error_log("NaverPay Callback - DB Error: " . $e->getMessage());
        throw $e;
    }

} catch (Exception $e) {
    error_log('NaverPay Callback Error: ' . $e->getMessage());
    header('Location: /pages/store/cart.php?error=' . urlencode($e->getMessage()));
    exit;
}

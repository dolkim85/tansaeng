<?php
/**
 * 네이버페이 결제 완료 콜백
 * 결제 완료 후 주문 생성 및 처리
 */

// 세션 시작
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    require_once __DIR__ . '/../../classes/Database.php';
    require_once __DIR__ . '/../../classes/Auth.php';

    // 파라미터 받기
    $merchantPayKey = $_GET['merchant_pay_key'] ?? null;
    $paymentId = $_GET['payment_id'] ?? null;
    $status = $_GET['status'] ?? 'FAIL';
    $amount = $_GET['amount'] ?? 0;

    // 구매자 정보
    $buyerName = $_GET['buyer_name'] ?? '';
    $buyerEmail = $_GET['buyer_email'] ?? '';
    $buyerPhone = $_GET['buyer_phone'] ?? '';

    if (!$merchantPayKey || !$paymentId) {
        throw new Exception('필수 파라미터가 누락되었습니다.');
    }

    // 결제 실패 시
    if ($status !== 'SUCCESS') {
        header('Location: /pages/store/cart.php?error=payment_failed');
        exit;
    }

    // 세션에서 임시 주문 정보 가져오기
    if (!isset($_SESSION['naverpay_temp_order'])) {
        throw new Exception('주문 정보를 찾을 수 없습니다.');
    }

    $tempOrder = $_SESSION['naverpay_temp_order'];

    // 주문번호 확인
    if ($tempOrder['merchant_pay_key'] !== $merchantPayKey) {
        throw new Exception('주문번호가 일치하지 않습니다.');
    }

    // 데이터베이스 연결
    $db = Database::getInstance()->getConnection();

    // 현재 사용자 확인 (로그인 여부)
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();
    $userId = $currentUser ? $currentUser['id'] : null;

    // 주문 생성
    $orderNumber = $merchantPayKey;
    $items = $tempOrder['items'];
    $totalAmount = $tempOrder['total_amount'];

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
            $totalAmount,
            'naverpay',
            $paymentId,
            'paid'
        ]);

        $orderId = $db->lastInsertId();

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
            // 배송비 계산
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
            $placeholders = str_repeat('?,', count($productIds) - 1) . '?';

            $stmtDelete = $db->prepare("
                DELETE FROM cart WHERE user_id = ? AND product_id IN ($placeholders)
            ");

            $stmtDelete->execute(array_merge([$userId], $productIds));
        }

        // 트랜잭션 커밋
        $db->commit();

        // 세션 정리
        unset($_SESSION['naverpay_temp_order']);

        // 주문 완료 페이지로 이동
        header('Location: /pages/store/order_complete.php?order_id=' . $orderId);
        exit;

    } catch (Exception $e) {
        // 롤백
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log('NaverPay Callback Error: ' . $e->getMessage());

    // 에러 페이지로 이동
    header('Location: /pages/store/cart.php?error=' . urlencode($e->getMessage()));
    exit;
}

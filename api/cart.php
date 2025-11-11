<?php
/**
 * 새로운 장바구니 API - 깔끔하고 간단한 구조
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();

// 요청 로깅
error_log("=== Cart API Request ===");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("URI: " . $_SERVER['REQUEST_URI']);
error_log("Action: " . ($_GET['action'] ?? 'none'));
error_log("Session ID: " . session_id());
error_log("User ID: " . ($_SESSION['user_id'] ?? 'none'));

// 기본 응답 함수
function sendResponse($success, $data = null, $message = '', $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 오류 처리
function sendError($message, $code = 400) {
    sendResponse(false, null, $message, $code);
}

try {
    require_once __DIR__ . '/../classes/Cart.php';

    $cart = new Cart();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    switch ($method) {
        case 'GET':
            handleGet($cart, $action);
            break;
        case 'POST':
            handlePost($cart, $action);
            break;
        case 'PUT':
            handlePut($cart, $action);
            break;
        case 'DELETE':
            handleDelete($cart, $action);
            break;
        default:
            sendError('지원하지 않는 HTTP 메서드입니다.', 405);
    }

} catch (Exception $e) {
    error_log("Cart API Error: " . $e->getMessage());
    error_log("Cart API Trace: " . $e->getTraceAsString());
    sendError('서버 오류가 발생했습니다.', 500);
}

// GET 요청 처리
function handleGet($cart, $action) {
    switch ($action) {
        case 'items':
            // 장바구니 모든 항목 조회
            $items = $cart->getItems();
            $summary = $cart->getSummary();
            sendResponse(true, $items, '장바구니 조회 성공', 200);
            break;

        case 'count':
            // 장바구니 개수만 조회
            $count = $cart->getItemCount();
            sendResponse(true, ['count' => $count], '카운트 조회 성공', 200);
            break;

        case 'summary':
            // 요약 정보 조회
            $summary = $cart->getSummary();
            sendResponse(true, $summary, '요약 조회 성공', 200);
            break;

        default:
            // 기본: 전체 정보 조회
            $items = $cart->getItems();
            $summary = $cart->getSummary();
            sendResponse(true, [
                'items' => $items,
                'summary' => $summary,
                'count' => count($items)
            ], '장바구니 전체 조회 성공', 200);
    }
}

// POST 요청 처리 (상품 추가, 주문 준비, 바로구매)
function handlePost($cart, $action) {
    switch ($action) {
        case 'add':
            handleAddItem($cart);
            break;
        case 'prepare_order':
            handlePrepareOrder();
            break;
        case 'buy_now':
            handleBuyNow();
            break;
        default:
            sendError('잘못된 액션입니다.');
    }
}

// 상품 추가 처리
function handleAddItem($cart) {

    // 입력 데이터 받기
    $input = json_decode(file_get_contents('php://input'), true);

    // GET 파라미터로도 받기 (fallback)
    $productId = $input['product_id'] ?? $_GET['product_id'] ?? null;
    $quantity = (int)($input['quantity'] ?? $_GET['quantity'] ?? 1);

    // 유효성 검사
    if (!$productId) {
        sendError('상품 ID가 필요합니다.');
    }

    if ($quantity < 1) {
        sendError('수량은 1개 이상이어야 합니다.');
    }

    // 장바구니에 추가
    $result = $cart->addItem($productId, $quantity);

    if ($result['success']) {
        $summary = $cart->getSummary();
        sendResponse(true, [
            'cart' => $summary,
            'item_count' => $summary['item_count'],
            'final_total' => $summary['final_total']
        ], $result['message'], 200);
    } else {
        // 로그인이 필요한 경우 require_login 플래그 추가
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $result['message'],
            'require_login' => $result['require_login'] ?? false,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// PUT 요청 처리 (수량 변경)
function handlePut($cart, $action) {
    if ($action !== 'update') {
        sendError('잘못된 액션입니다.');
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $productId = $input['product_id'] ?? null;
    $quantity = (int)($input['quantity'] ?? 0);

    if (!$productId) {
        sendError('상품 ID가 필요합니다.');
    }

    if ($quantity < 1) {
        sendError('수량은 1개 이상이어야 합니다.');
    }

    $result = $cart->updateQuantity($productId, $quantity);

    if ($result['success']) {
        $summary = $cart->getSummary();
        sendResponse(true, $summary, $result['message'], 200);
    } else {
        sendError($result['message']);
    }
}

// DELETE 요청 처리
function handleDelete($cart, $action) {
    switch ($action) {
        case 'item':
            // 특정 상품 제거
            $productId = $_GET['product_id'] ?? null;

            if (!$productId) {
                sendError('상품 ID가 필요합니다.');
            }

            $result = $cart->removeItem($productId);

            if ($result['success']) {
                $summary = $cart->getSummary();
                sendResponse(true, $summary, $result['message'], 200);
            } else {
                sendError($result['message']);
            }
            break;

        case 'clear':
            // 장바구니 전체 비우기
            $result = $cart->clearCart();

            if ($result['success']) {
                sendResponse(true, [], $result['message'], 200);
            } else {
                sendError($result['message']);
            }
            break;

        default:
            sendError('잘못된 액션입니다.');
    }
}

// 주문 준비 처리 (세션에 저장)
function handlePrepareOrder() {
    $input = json_decode(file_get_contents('php://input'), true);
    $items = $input['items'] ?? [];

    if (empty($items)) {
        sendError('주문할 상품이 없습니다.');
    }

    // 세션에 주문 상품 저장
    $_SESSION['order_items'] = $items;

    sendResponse(true, ['item_count' => count($items)], '주문 준비가 완료되었습니다.', 200);
}

// 바로구매 처리 (장바구니 거치지 않고 바로 주문)
function handleBuyNow() {
    require_once __DIR__ . '/../classes/Database.php';

    $input = json_decode(file_get_contents('php://input'), true);
    $productId = $input['product_id'] ?? null;
    $quantity = (int)($input['quantity'] ?? 1);

    if (!$productId) {
        sendError('상품 ID가 필요합니다.');
    }

    if ($quantity < 1) {
        sendError('수량은 1개 이상이어야 합니다.');
    }

    try {
        // 상품 정보 조회
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT id, name, price, shipping_cost, shipping_unit_count, stock, image_url
            FROM products
            WHERE id = ? AND status = 'active'
        ");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            sendError('상품을 찾을 수 없습니다.');
        }

        // 재고 확인
        if ($product['stock'] < $quantity) {
            sendError('재고가 부족합니다.');
        }

        // 주문 아이템 생성
        $orderItem = [
            'product_id' => $product['id'],
            'name' => $product['name'],
            'price' => $product['price'],
            'quantity' => $quantity,
            'shipping_cost' => $product['shipping_cost'] ?? 0,
            'shipping_unit_count' => $product['shipping_unit_count'] ?? 1,
            'image' => $product['image_url'] ?? ''
        ];

        // 세션에 저장 (주문 페이지에서 사용)
        $_SESSION['order_items'] = [$orderItem];

        sendResponse(true, ['product_id' => $productId, 'quantity' => $quantity], '바로구매 준비가 완료되었습니다.', 200);

    } catch (Exception $e) {
        error_log("Buy Now Error: " . $e->getMessage());
        sendError('바로구매 처리 중 오류가 발생했습니다.');
    }
}
?>
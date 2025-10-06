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

// POST 요청 처리 (상품 추가)
function handlePost($cart, $action) {
    if ($action !== 'add') {
        sendError('잘못된 액션입니다.');
    }

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
        sendError($result['message']);
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
?>
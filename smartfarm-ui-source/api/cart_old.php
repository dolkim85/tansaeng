<?php
/**
 * Cart API Endpoint
 * Handles shopping cart operations
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();

// 디버그 정보 로깅
error_log("Cart API - Session ID: " . session_id());
error_log("Cart API - Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Cart API - Action: " . ($_GET['action'] ?? 'none'));
error_log("Cart API - Session Data: " . json_encode($_SESSION));
error_log("Cart API - User ID: " . ($_SESSION['user_id'] ?? 'null'));
error_log("Cart API - Headers: " . json_encode(getallheaders()));

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Cart.php';

try {
    $cart = new Cart();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    // 요청 전 세션 상태 로깅
    error_log("Cart API - Session before: " . json_encode($_SESSION['cart'] ?? []));

    switch ($method) {
        case 'GET':
            handleGetRequest($cart, $action);
            break;

        case 'POST':
            handlePostRequest($cart, $action);
            break;

        case 'PUT':
            handlePutRequest($cart, $action);
            break;

        case 'DELETE':
            handleDeleteRequest($cart, $action);
            break;

        default:
            throw new Exception('Method not allowed', 405);
    }

} catch (Exception $e) {
    $statusCode = $e->getCode() ?: 500;
    http_response_code($statusCode);

    error_log("Cart API Exception: " . $e->getMessage());
    error_log("Cart API Exception Trace: " . $e->getTraceAsString());
    error_log("Cart API Exception Context: " . json_encode([
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
        'action' => $_GET['action'] ?? 'NONE',
        'user_id' => $_SESSION['user_id'] ?? null,
        'session_id' => session_id()
    ]));

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => $statusCode,
        'debug_info' => [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            'action' => $_GET['action'] ?? 'NONE',
            'session_id' => session_id(),
            'user_id' => $_SESSION['user_id'] ?? null,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'UNKNOWN'
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function handleGetRequest($cart, $action) {
    switch ($action) {
        case 'items':
            getCartItems($cart);
            break;

        case 'count':
            getCartCount($cart);
            break;

        case 'summary':
            getCartSummary($cart);
            break;

        default:
            getCartItems($cart);
            break;
    }
}

function getCartItems($cart) {
    $items = $cart->getItems();
    $summary = $cart->getSummary();

    echo json_encode([
        'success' => true,
        'data' => $items,
        'count' => count($items),
        'summary' => $summary,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function getCartCount($cart) {
    $count = $cart->getItemCount();

    echo json_encode([
        'success' => true,
        'count' => $count,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function getCartSummary($cart) {
    $summary = $cart->getSummary();

    echo json_encode([
        'success' => true,
        'data' => $summary,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function handlePostRequest($cart, $action) {
    $rawInput = file_get_contents('php://input');
    error_log("Cart API - Raw POST data: " . $rawInput);

    $input = json_decode($rawInput, true);
    error_log("Cart API - Decoded JSON: " . json_encode($input));

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data: ' . json_last_error_msg(), 400);
    }

    switch ($action) {
        case 'add':
            addToCart($cart, $input);
            break;

        case 'validate':
            validateCart($cart);
            break;

        default:
            throw new Exception('Invalid action', 400);
    }
}

function addToCart($cart, $input) {
    error_log("Cart API - Received input data: " . json_encode($input));
    error_log("Cart API - GET parameters: " . json_encode($_GET));

    // POST 데이터 우선, GET 파라미터로 fallback
    $productId = $input['product_id'] ?? $_GET['product_id'] ?? null;
    $quantity = (int)($input['quantity'] ?? $_GET['quantity'] ?? 1);

    error_log("Cart API - addToCart called with productId: $productId, quantity: $quantity");
    error_log("Cart API - Session ID: " . session_id());
    error_log("Cart API - Session cart before: " . json_encode($_SESSION['cart'] ?? []));

    if (!$productId) {
        throw new Exception('Product ID is required. Received: ' . json_encode($input), 400);
    }

    if ($quantity < 1) {
        throw new Exception('Quantity must be at least 1. Received: ' . $quantity, 400);
    }

    $result = $cart->addItem($productId, $quantity);
    error_log("Cart API - addItem result: " . json_encode($result));
    error_log("Cart API - Session cart after: " . json_encode($_SESSION['cart'] ?? []));

    if ($result['success']) {
        $summary = $cart->getSummary();
        error_log("Cart API - cart summary: " . json_encode($summary));

        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'cart' => $summary,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        throw new Exception($result['message'], 400);
    }
}

function validateCart($cart) {
    $errors = $cart->validateCart();

    if (empty($errors)) {
        echo json_encode([
            'success' => true,
            'message' => 'Cart is valid',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Cart validation failed',
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}

function handlePutRequest($cart, $action) {
    $input = json_decode(file_get_contents('php://input'), true);

    switch ($action) {
        case 'update':
            updateCartItem($cart, $input);
            break;

        default:
            throw new Exception('Invalid action', 400);
    }
}

function updateCartItem($cart, $input) {
    $productId = $input['product_id'] ?? null;
    $quantity = (int)($input['quantity'] ?? 0);

    if (!$productId) {
        throw new Exception('Product ID is required', 400);
    }

    $result = $cart->updateQuantity($productId, $quantity);

    if ($result['success']) {
        $summary = $cart->getSummary();
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'cart' => $summary,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        throw new Exception($result['message'], 400);
    }
}

function handleDeleteRequest($cart, $action) {
    switch ($action) {
        case 'item':
            removeCartItem($cart);
            break;

        case 'clear':
            clearCart($cart);
            break;

        default:
            throw new Exception('Invalid action', 400);
    }
}

function removeCartItem($cart) {
    $productId = $_GET['product_id'] ?? null;

    if (!$productId) {
        throw new Exception('Product ID is required', 400);
    }

    $result = $cart->removeItem($productId);

    if ($result['success']) {
        $summary = $cart->getSummary();
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'cart' => $summary,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        throw new Exception($result['message'], 400);
    }
}

function clearCart($cart) {
    $result = $cart->clearCart();

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        throw new Exception($result['message'], 400);
    }
}
?>
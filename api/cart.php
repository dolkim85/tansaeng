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

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Cart.php';

try {
    $cart = new Cart();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

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
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('c')
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

    echo json_encode([
        'success' => true,
        'data' => $items,
        'count' => count($items),
        'timestamp' => date('c')
    ]);
}

function getCartCount($cart) {
    $count = $cart->getItemCount();

    echo json_encode([
        'success' => true,
        'count' => $count,
        'timestamp' => date('c')
    ]);
}

function getCartSummary($cart) {
    $summary = $cart->getSummary();

    echo json_encode([
        'success' => true,
        'data' => $summary,
        'timestamp' => date('c')
    ]);
}

function handlePostRequest($cart, $action) {
    $input = json_decode(file_get_contents('php://input'), true);

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
    $productId = $input['product_id'] ?? null;
    $quantity = (int)($input['quantity'] ?? 1);

    if (!$productId) {
        throw new Exception('Product ID is required', 400);
    }

    if ($quantity < 1) {
        throw new Exception('Quantity must be at least 1', 400);
    }

    $result = $cart->addItem($productId, $quantity);

    if ($result['success']) {
        $summary = $cart->getSummary();
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'cart' => $summary,
            'timestamp' => date('c')
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
            'timestamp' => date('c')
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Cart validation failed',
            'errors' => $errors,
            'timestamp' => date('c')
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
            'timestamp' => date('c')
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
            'timestamp' => date('c')
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
            'timestamp' => date('c')
        ]);
    } else {
        throw new Exception($result['message'], 400);
    }
}
?>
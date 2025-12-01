<?php
/**
 * 주문 처리 API
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 세션이 시작되지 않았으면 시작
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

function sendError($message, $code = 400) {
    sendResponse(false, null, $message, $code);
}

try {
    require_once __DIR__ . '/../classes/Auth.php';
    require_once __DIR__ . '/../classes/Database.php';

    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();

    if (!$currentUser) {
        sendError('로그인이 필요합니다.', 401);
    }

    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    switch ($method) {
        case 'POST':
            handlePost($pdo, $action, $currentUser);
            break;
        case 'GET':
            handleGet($pdo, $action, $currentUser);
            break;
        default:
            sendError('지원하지 않는 HTTP 메서드입니다.', 405);
    }

} catch (Exception $e) {
    error_log("Order API Error: " . $e->getMessage());
    error_log("Order API Trace: " . $e->getTraceAsString());
    sendError('서버 오류가 발생했습니다.', 500);
}

// POST 요청 처리
function handlePost($pdo, $action, $currentUser) {
    if ($action !== 'create') {
        sendError('잘못된 액션입니다.');
    }

    $input = json_decode(file_get_contents('php://input'), true);

    // 주문 데이터 검증
    $paymentMethod = $input['payment_method'] ?? null;
    $deliveryAddress = $input['delivery_address'] ?? null;
    $deliveryRequest = $input['delivery_request'] ?? '';
    $totalAmount = $input['total_amount'] ?? 0;

    if (!$paymentMethod || !$deliveryAddress || $totalAmount <= 0) {
        sendError('주문 정보가 올바르지 않습니다.');
    }

    // 주문 상품 가져오기 (세션에서)
    $orderItems = $_SESSION['order_items'] ?? [];

    if (empty($orderItems)) {
        sendError('주문할 상품이 없습니다.');
    }

    // 트랜잭션 시작
    $pdo->beginTransaction();

    try {
        // 주문 번호 생성
        $orderNumber = 'ORD' . date('YmdHis') . rand(1000, 9999);

        // orders 테이블에 주문 기록 (테이블이 없으면 임시로 세션에만 저장)
        $orderId = 'order_' . time();

        // 실제 서비스에서는 DB에 저장
        /*
        $stmt = $pdo->prepare("
            INSERT INTO orders (
                order_number, user_id, payment_method,
                recipient_name, recipient_phone,
                address, address_detail, zip_code,
                delivery_request, total_amount, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");

        $stmt->execute([
            $orderNumber,
            $currentUser['id'],
            $paymentMethod,
            $deliveryAddress['name'],
            $deliveryAddress['phone'],
            $deliveryAddress['address'],
            $deliveryAddress['addressDetail'] ?? '',
            $deliveryAddress['zipCode'],
            $deliveryRequest,
            $totalAmount
        ]);

        $orderId = $pdo->lastInsertId();
        */

        // 주문 상품 저장
        /*
        $stmtItems = $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, quantity, price)
            VALUES (?, ?, ?, ?)
        ");

        foreach ($orderItems as $item) {
            $stmtItems->execute([
                $orderId,
                $item['product_id'],
                $item['quantity'],
                $item['price']
            ]);
        }
        */

        $pdo->commit();

        // 세션에 주문 정보 저장 (주문 완료 페이지에서 사용)
        $_SESSION['last_order'] = [
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'items' => $orderItems,
            'total_amount' => $totalAmount,
            'delivery_address' => $deliveryAddress,
            'payment_method' => $paymentMethod,
            'created_at' => date('Y-m-d H:i:s')
        ];

        // 주문한 상품은 세션에서 제거
        unset($_SESSION['order_items']);

        sendResponse(true, [
            'order_id' => $orderId,
            'order_number' => $orderNumber
        ], '주문이 완료되었습니다.', 200);

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Order creation error: " . $e->getMessage());
        sendError('주문 처리 중 오류가 발생했습니다.');
    }
}

// GET 요청 처리
function handleGet($pdo, $action, $currentUser) {
    switch ($action) {
        case 'detail':
            $orderId = $_GET['order_id'] ?? null;
            if (!$orderId) {
                sendError('주문 ID가 필요합니다.');
            }

            // 세션에서 주문 정보 가져오기
            $orderInfo = $_SESSION['last_order'] ?? null;

            if ($orderInfo && $orderInfo['order_id'] == $orderId) {
                sendResponse(true, $orderInfo, '주문 조회 성공', 200);
            } else {
                sendError('주문 정보를 찾을 수 없습니다.', 404);
            }
            break;

        default:
            sendError('잘못된 액션입니다.');
    }
}
?>

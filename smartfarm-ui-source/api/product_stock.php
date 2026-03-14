<?php
/**
 * 상품 재고 정보 조회 API
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 기본 응답 함수
function sendResponse($success, $data = null, $message = '', $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'stock' => $data, // 호환성을 위해 추가
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
    require_once __DIR__ . '/../config/database.php';

    $productId = $_GET['id'] ?? null;

    if (!$productId) {
        sendError('상품 ID가 필요합니다.');
    }

    $pdo = DatabaseConfig::getConnection();

    // 상품 재고 정보 조회
    $sql = "SELECT stock_quantity, stock FROM products WHERE id = ? AND status = 'active'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        sendError('상품을 찾을 수 없습니다.', 404);
    }

    // stock_quantity 또는 stock 컬럼 사용
    $stock = $product['stock_quantity'] ?? $product['stock'] ?? 0;

    sendResponse(true, $stock, '재고 정보 조회 성공');

} catch (Exception $e) {
    error_log("Product stock API Error: " . $e->getMessage());
    sendError('서버 오류가 발생했습니다.', 500);
}
?>
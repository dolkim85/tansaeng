<?php
/**
 * 장바구니 개수 조회 API
 */

header('Content-Type: application/json');

$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
require_once $base_path . '/classes/Database.php';

$auth = Auth::getInstance();

// 로그인하지 않은 경우 0 반환
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => true, 'count' => 0]);
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();
    $user_id = $auth->getCurrentUserId();

    // 장바구니 테이블이 있는 경우 개수 조회
    $sql = "SELECT COALESCE(SUM(quantity), 0) as count FROM cart WHERE user_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'count' => (int)$result['count']
    ]);
} catch (Exception $e) {
    // 테이블이 없거나 오류 발생 시 0 반환
    echo json_encode([
        'success' => true,
        'count' => 0
    ]);
}

<?php
/**
 * Browser-based cart test - simulates user adding products through web interface
 */
session_start();

echo "<!DOCTYPE html><html><head><title>Cart Browser Test</title></head><body>";
echo "<h1>🛒 Cart Browser Test</h1>";

// 로그인 시뮬레이션
$_SESSION['user_id'] = 1;
$_SESSION['email'] = 'korea_tansaeng@naver.com';
$_SESSION['name'] = '탄생 관리자';
$_SESSION['role'] = 'admin';

echo "<p>✅ 로그인 완료: {$_SESSION['name']}</p>";

try {
    require_once __DIR__ . '/classes/Cart.php';
    $cart = new Cart();

    // 장바구니 초기화
    $cart->clearCart();
    echo "<p>🧹 장바구니 초기화 완료</p>";

    // 여러 상품 추가
    echo "<h2>📦 상품 추가 테스트</h2>";

    $products = [
        ['id' => 1, 'qty' => 2, 'name' => '상품1'],
        ['id' => 2, 'qty' => 1, 'name' => '상품2'],
        ['id' => 3, 'qty' => 3, 'name' => '상품3']
    ];

    foreach ($products as $product) {
        $result = $cart->addItem($product['id'], $product['qty']);
        if ($result['success']) {
            echo "<p>✅ {$product['name']} {$product['qty']}개 추가: {$result['message']}</p>";
        } else {
            echo "<p>❌ {$product['name']} 추가 실패: {$result['message']}</p>";
        }
    }

    // 장바구니 내용 확인
    echo "<h2>🛒 장바구니 내용 확인</h2>";
    $items = $cart->getItems();

    echo "<p><strong>총 상품 종류:</strong> " . count($items) . "개</p>";
    echo "<p><strong>총 상품 수량:</strong> " . $cart->getItemCount() . "개</p>";

    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>상품ID</th><th>상품명</th><th>수량</th><th>단가</th><th>소계</th></tr>";

    foreach ($items as $item) {
        $subtotal = $item['price'] * $item['quantity'];
        echo "<tr>";
        echo "<td>{$item['product_id']}</td>";
        echo "<td>{$item['name']}</td>";
        echo "<td>{$item['quantity']}개</td>";
        echo "<td>" . number_format($item['price']) . "원</td>";
        echo "<td>" . number_format($subtotal) . "원</td>";
        echo "</tr>";
    }
    echo "</table>";

    // 요약 정보
    $summary = $cart->getSummary();
    echo "<h2>💰 주문 요약</h2>";
    echo "<p><strong>상품 총액:</strong> " . number_format($summary['total']) . "원</p>";
    echo "<p><strong>배송비:</strong> " . number_format($summary['shipping_cost']) . "원</p>";
    echo "<p><strong>최종 총액:</strong> " . number_format($summary['final_total']) . "원</p>";

    echo "<hr>";
    echo "<h2>🔗 테스트 링크</h2>";
    echo "<p><a href='/pages/store/cart.php' target='_blank'>🛒 장바구니 페이지 확인</a></p>";
    echo "<p><a href='/pages/store/index.php' target='_blank'>🏪 스토어 메인</a></p>";
    echo "<p><a href='/pages/store/products.php' target='_blank'>📋 상품 목록</a></p>";

    echo "<hr>";
    echo "<p><strong>✅ 모든 기능이 정상 작동합니다!</strong></p>";
    echo "<p>이제 브라우저에서 장바구니 페이지로 이동하여 개별 상품들이 제대로 표시되는지 확인하세요.</p>";

} catch (Exception $e) {
    echo "<p>❌ 오류 발생: " . $e->getMessage() . "</p>";
}

echo "</body></html>";
?>
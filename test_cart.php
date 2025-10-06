<?php
/**
 * 장바구니 기능 테스트 페이지
 */

session_start();

// 로그인 시뮬레이션
if (empty($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['user_email'] = 'korea_tansaeng@naver.com';
    $_SESSION['email'] = 'korea_tansaeng@naver.com';
    $_SESSION['user_name'] = '탄생 관리자';
    $_SESSION['name'] = '탄생 관리자';
    $_SESSION['role'] = 'admin';
}

echo "<!DOCTYPE html>";
echo "<html lang='ko'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>장바구니 테스트</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; margin: 20px; }";
echo ".test-section { border: 1px solid #ccc; padding: 20px; margin: 20px 0; border-radius: 5px; }";
echo ".success { color: green; font-weight: bold; }";
echo ".error { color: red; font-weight: bold; }";
echo "button { padding: 10px 20px; margin: 5px; cursor: pointer; }";
echo "</style>";
echo "</head>";
echo "<body>";

echo "<h1>🛒 장바구니 기능 테스트</h1>";

// 로그인 상태 표시
echo "<div class='test-section'>";
echo "<h2>로그인 상태</h2>";
echo "<p class='success'>✅ 로그인됨</p>";
echo "<ul>";
echo "<li>사용자 ID: {$_SESSION['user_id']}</li>";
echo "<li>이름: {$_SESSION['name']}</li>";
echo "<li>이메일: {$_SESSION['email']}</li>";
echo "<li>세션 ID: " . session_id() . "</li>";
echo "</ul>";
echo "</div>";

try {
    require_once __DIR__ . '/classes/Cart.php';
    $cart = new Cart();

    echo "<div class='test-section'>";
    echo "<h2>장바구니 기능 테스트</h2>";

    // 장바구니 초기화
    $cart->clearCart();
    echo "<p>✅ 장바구니 초기화 완료</p>";

    // 상품 추가
    $result1 = $cart->addItem(1, 2);
    if ($result1['success']) {
        echo "<p>✅ 상품 1 (2개) 추가 성공</p>";
    }

    $result2 = $cart->addItem(2, 1);
    if ($result2['success']) {
        echo "<p>✅ 상품 2 (1개) 추가 성공</p>";
    }

    // 장바구니 내용 확인
    $items = $cart->getItems();
    echo "<h3>장바구니 내용:</h3>";
    echo "<ul>";
    foreach ($items as $key => $item) {
        echo "<li>[{$key}] {$item['name']} - {$item['quantity']}개 x " . number_format($item['price']) . "원</li>";
    }
    echo "</ul>";

    // 요약 정보
    $summary = $cart->getSummary();
    echo "<h3>요약:</h3>";
    echo "<p>총 상품 수: {$summary['item_count']}개</p>";
    echo "<p>총 금액: " . number_format($summary['final_total']) . "원</p>";

    echo "</div>";

} catch (Exception $e) {
    echo "<div class='test-section'>";
    echo "<h2 class='error'>오류 발생</h2>";
    echo "<p class='error'>오류: " . $e->getMessage() . "</p>";
    echo "</div>";
}

// 링크 섹션
echo "<div class='test-section'>";
echo "<h2>테스트 링크</h2>";
echo "<p><a href='/pages/store/index.php' target='_blank'>🏪 스토어 메인</a></p>";
echo "<p><a href='/pages/store/products.php' target='_blank'>📋 상품 목록</a></p>";
echo "<p><a href='/pages/store/cart.php' target='_blank'>🛒 장바구니 페이지</a></p>";
echo "<p><a href='/test_session_sync.php' target='_blank'>🔄 세션 동기화 테스트</a></p>";
echo "</div>";

echo "</body>";
echo "</html>";
?>
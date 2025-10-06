<?php
/**
 * API 직접 테스트 - 로그인 상태에서 장바구니 추가 테스트
 */

session_start();

// 로그인 시뮬레이션
$_SESSION['user_id'] = 1;
$_SESSION['email'] = 'korea_tansaeng@naver.com';
$_SESSION['name'] = '탄생 관리자';
$_SESSION['role'] = 'admin';

echo "=== API 직접 테스트 ===\n";
echo "세션 ID: " . session_id() . "\n";
echo "로그인 사용자: {$_SESSION['name']} (ID: {$_SESSION['user_id']})\n\n";

// API 요청 시뮬레이션
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = 'add';

// POST 데이터 시뮬레이션
$postData = json_encode([
    'product_id' => 1,
    'quantity' => 2
]);

// php://input 스트림 시뮬레이션을 위해 임시 파일 생성
$tempFile = tmpfile();
fwrite($tempFile, $postData);
rewind($tempFile);

// 백업
$originalInput = 'php://input';

echo "요청 데이터: $postData\n";
echo "요청 메서드: {$_SERVER['REQUEST_METHOD']}\n";
echo "액션: {$_GET['action']}\n\n";

try {
    // Cart API 직접 호출
    require_once __DIR__ . '/classes/Cart.php';

    $cart = new Cart();
    $input = json_decode($postData, true);

    echo "파싱된 입력 데이터:\n";
    print_r($input);

    $result = $cart->addItem($input['product_id'], $input['quantity']);

    echo "\n장바구니 추가 결과:\n";
    print_r($result);

    if ($result['success']) {
        $summary = $cart->getSummary();
        echo "\n장바구니 요약:\n";
        print_r($summary);

        echo "\n현재 장바구니 아이템들:\n";
        $items = $cart->getItems();
        print_r($items);
    }

} catch (Exception $e) {
    echo "오류 발생: " . $e->getMessage() . "\n";
    echo "스택 트레이스:\n" . $e->getTraceAsString() . "\n";
}

fclose($tempFile);

echo "\n=== 테스트 완료 ===\n";
?>
<?php
/**
 * TIMESTAMP 오류 디버깅
 */

session_start();

echo "=== TIMESTAMP 오류 디버깅 ===\n";

// 로그인 설정
$_SESSION['user_id'] = 1;
$_SESSION['email'] = 'korea_tansaeng@naver.com';
$_SESSION['name'] = '탄생 관리자';

echo "1. 로그인 설정 완료 (User ID: {$_SESSION['user_id']})\n";

try {
    require_once __DIR__ . '/classes/Cart.php';

    echo "2. Cart 클래스 로드 완료\n";

    $cart = new Cart();
    echo "3. Cart 인스턴스 생성 완료\n";

    // 장바구니 초기화
    echo "\n4. 장바구니 초기화 테스트\n";
    $result = $cart->clearCart();
    if ($result['success']) {
        echo "   ✅ 초기화 성공\n";
    } else {
        echo "   ❌ 초기화 실패: {$result['message']}\n";
    }

    // 상품 추가 테스트
    echo "\n5. 상품 추가 테스트\n";
    $addResult = $cart->addItem(1, 2);
    if ($addResult['success']) {
        echo "   ✅ 상품 추가 성공: {$addResult['message']}\n";
    } else {
        echo "   ❌ 상품 추가 실패: {$addResult['message']}\n";
    }

    // 장바구니 조회
    echo "\n6. 장바구니 내용 조회\n";
    $items = $cart->getItems();
    echo "   📦 상품 수: " . count($items) . "개\n";

    // 요약 정보
    echo "\n7. 요약 정보 조회\n";
    $summary = $cart->getSummary();
    echo "   📊 총 수량: {$summary['item_count']}개\n";
    echo "   💰 총 금액: " . number_format($summary['final_total']) . "원\n";

    // 데이터베이스 직접 확인
    echo "\n8. 데이터베이스 직접 조회\n";
    require_once __DIR__ . '/config/database.php';
    $pdo = DatabaseConfig::getConnection();

    $stmt = $pdo->prepare("SELECT * FROM cart WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$_SESSION['user_id']]);
    $dbCart = $stmt->fetchAll();

    echo "   🗄️ DB 레코드 수: " . count($dbCart) . "개\n";
    foreach ($dbCart as $record) {
        echo "     - 상품 {$record['product_id']}: {$record['quantity']}개 (생성: {$record['created_at']})\n";
    }

    echo "\n✅ 모든 테스트 완료 - TIMESTAMP 오류 없음\n";

} catch (Exception $e) {
    echo "\n❌ 오류 발생: " . $e->getMessage() . "\n";
    echo "오류 타입: " . get_class($e) . "\n";
    echo "파일: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "스택 트레이스:\n" . $e->getTraceAsString() . "\n";

    // TIMESTAMP 관련 오류인지 확인
    if (strpos($e->getMessage(), 'timestamp') !== false ||
        strpos($e->getMessage(), 'TIMESTAMP') !== false) {
        echo "\n🔍 TIMESTAMP 관련 오류 감지!\n";

        // MySQL 설정 확인
        try {
            require_once __DIR__ . '/config/database.php';
            $pdo = DatabaseConfig::getConnection();

            $sqlMode = $pdo->query('SELECT @@sql_mode')->fetchColumn();
            echo "SQL 모드: $sqlMode\n";

            $timezone = $pdo->query('SELECT @@time_zone')->fetchColumn();
            echo "타임존: $timezone\n";

        } catch (Exception $dbE) {
            echo "DB 연결 실패: " . $dbE->getMessage() . "\n";
        }
    }
}

echo "\n=== 디버깅 완료 ===\n";
?>
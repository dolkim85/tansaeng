<?php
/**
 * 테스트용 로그인 시뮬레이션 페이지
 */

session_start();

// 로그인 처리
if (isset($_POST['login'])) {
    try {
        require_once '/home/spinmoll/tansaeng_new/config/database.php';
        $pdo = DatabaseConfig::getConnection();

        $stmt = $pdo->prepare("SELECT id, email, name, role FROM users WHERE role = 'admin' LIMIT 1");
        $stmt->execute();
        $admin = $stmt->fetch();

        if ($admin) {
            $_SESSION['user_id'] = $admin['id'];
            $_SESSION['email'] = $admin['email'];
            $_SESSION['name'] = $admin['name'];
            $_SESSION['role'] = $admin['role'];

            $message = "✅ 로그인 성공! 이제 장바구니 기능을 사용할 수 있습니다.";
        } else {
            $message = "❌ 관리자 계정을 찾을 수 없습니다.";
        }
    } catch (Exception $e) {
        $message = "❌ 오류: " . $e->getMessage();
    }
}

// 로그아웃 처리
if (isset($_POST['logout'])) {
    session_destroy();
    session_start();
    $message = "✅ 로그아웃 되었습니다.";
}

// 장바구니에 테스트 상품 추가
if (isset($_POST['add_to_cart'])) {
    if (empty($_SESSION['user_id'])) {
        $message = "❌ 로그인이 필요합니다.";
    } else {
        try {
            require_once '/home/spinmoll/tansaeng_new/classes/Cart.php';
            $cart = new Cart();
            $result = $cart->addItem(1, 1);
            $message = $result['success'] ? "✅ " . $result['message'] : "❌ " . $result['message'];
        } catch (Exception $e) {
            $message = "❌ 장바구니 추가 오류: " . $e->getMessage();
        }
    }
}

// 현재 장바구니 상태 확인
$cartSummary = null;
if (!empty($_SESSION['user_id'])) {
    try {
        require_once '/home/spinmoll/tansaeng_new/classes/Cart.php';
        $cart = new Cart();
        $cartSummary = $cart->getSummary();
    } catch (Exception $e) {
        // 무시
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>테스트용 로그인 시뮬레이션</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .status {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .status.success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .status.error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .user-info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .cart-info {
            background: #fff3cd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }
        button:hover { background: #0056b3; }
        button.danger { background: #dc3545; }
        button.danger:hover { background: #c82333; }
        button.success { background: #28a745; }
        button.success:hover { background: #218838; }
        .links {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .links a {
            display: inline-block;
            padding: 10px 15px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px;
        }
        .links a:hover { background: #545b62; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 장바구니 기능 테스트 페이지</h1>

        <?php if (isset($message)): ?>
            <div class="status <?= strpos($message, '✅') !== false ? 'success' : 'error' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($_SESSION['user_id'])): ?>
            <!-- 로그인 전 -->
            <div class="user-info">
                <h3>🔐 로그인 상태: 로그인 안됨 (게스트)</h3>
                <p>장바구니는 세션에만 저장됩니다.</p>
            </div>

            <form method="POST">
                <button type="submit" name="login">🔑 테스트 로그인 (관리자)</button>
            </form>

        <?php else: ?>
            <!-- 로그인 후 -->
            <div class="user-info">
                <h3>✅ 로그인 상태: 로그인됨</h3>
                <p><strong>사용자 ID:</strong> <?= $_SESSION['user_id'] ?></p>
                <p><strong>이메일:</strong> <?= htmlspecialchars($_SESSION['email']) ?></p>
                <p><strong>이름:</strong> <?= htmlspecialchars($_SESSION['name']) ?></p>
                <p><strong>권한:</strong> <?= $_SESSION['role'] ?></p>
                <p>장바구니는 데이터베이스에 저장됩니다.</p>
            </div>

            <?php if ($cartSummary): ?>
                <div class="cart-info">
                    <h3>🛒 현재 장바구니 상태</h3>
                    <p><strong>총 아이템:</strong> <?= $cartSummary['item_count'] ?>개</p>
                    <p><strong>총 금액:</strong> <?= number_format($cartSummary['total']) ?>원</p>
                    <p><strong>최종 총액:</strong> <?= number_format($cartSummary['final_total']) ?>원</p>
                </div>
            <?php endif; ?>

            <form method="POST">
                <button type="submit" name="add_to_cart" class="success">🛒 테스트 상품 장바구니 추가</button>
                <button type="submit" name="logout" class="danger">🚪 로그아웃</button>
            </form>
        <?php endif; ?>

        <div class="links">
            <h3>🔗 테스트 링크</h3>
            <a href="/pages/store/index.php" target="_blank">📦 스토어 페이지</a>
            <a href="/pages/store/cart.php" target="_blank">🛒 장바구니 페이지</a>
            <a href="/test_cart.php" target="_blank">🧪 장바구니 테스트</a>
            <a href="/api/cart.php" target="_blank">📡 Cart API</a>
        </div>

        <div style="margin-top: 30px; padding: 15px; background: #e9ecef; border-radius: 5px; font-size: 14px;">
            <h4>📋 테스트 시나리오</h4>
            <ol>
                <li><strong>로그인:</strong> "테스트 로그인" 버튼 클릭</li>
                <li><strong>상품 추가:</strong> "테스트 상품 장바구니 추가" 버튼 클릭</li>
                <li><strong>장바구니 확인:</strong> "장바구니 페이지" 링크 클릭하여 상품이 표시되는지 확인</li>
                <li><strong>스토어 테스트:</strong> "스토어 페이지"에서 실제 "장바구니 담기" 버튼 테스트</li>
                <li><strong>로그아웃/로그인:</strong> 로그아웃 후 다시 로그인해도 장바구니 데이터가 유지되는지 확인</li>
            </ol>
        </div>
    </div>
</body>
</html>
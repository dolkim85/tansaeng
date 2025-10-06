<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>전체 장바구니 기능 테스트</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { border: 1px solid #ccc; padding: 20px; margin: 20px 0; border-radius: 5px; background: #f9f9f9; }
        .success { color: #008000; font-weight: bold; }
        .error { color: #ff0000; font-weight: bold; }
        .info { color: #0066cc; }
        button { padding: 10px 20px; margin: 5px; cursor: pointer; background: #007cba; color: white; border: none; border-radius: 4px; }
        button:hover { background: #005a87; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; }
        .cart-count { background: #ff4444; color: white; padding: 2px 8px; border-radius: 50%; font-size: 12px; }
    </style>
</head>
<body>
    <h1>🛒 전체 장바구니 기능 테스트</h1>

    <div class="test-section">
        <h2>1. 로그인 상태 확인</h2>
        <?php
        session_start();
        $isLoggedIn = !empty($_SESSION['user_id']);

        if ($isLoggedIn) {
            echo "<p class='success'>✅ 로그인됨</p>";
            echo "<ul>";
            echo "<li><strong>사용자 ID:</strong> {$_SESSION['user_id']}</li>";
            echo "<li><strong>이름:</strong> " . ($_SESSION['user_name'] ?? $_SESSION['name'] ?? 'N/A') . "</li>";
            echo "<li><strong>이메일:</strong> " . ($_SESSION['user_email'] ?? $_SESSION['email'] ?? 'N/A') . "</li>";
            echo "<li><strong>권한:</strong> " . ($_SESSION['role'] ?? 'N/A') . "</li>";
            echo "<li><strong>세션 ID:</strong> " . session_id() . "</li>";
            echo "</ul>";
        } else {
            echo "<p class='error'>❌ 비로그인 상태</p>";
            echo "<button onclick=\"window.location.href='/test_login_simulation.php'\">테스트 로그인</button>";
        }
        ?>
    </div>

    <div class="test-section">
        <h2>2. 장바구니 카운트 표시</h2>
        <p>현재 카운트: <span class="cart-count" id="cartCount">0</span></p>
        <button onclick="updateCartCount()">카운트 새로고침</button>
        <div id="countResult"></div>
    </div>

    <div class="test-section">
        <h2>3. 장바구니 추가 테스트</h2>
        <button onclick="testAddProduct(1, 2)">상품 1 (2개) 추가</button>
        <button onclick="testAddProduct(2, 1)">상품 2 (1개) 추가</button>
        <button onclick="testAddProduct(3, 3)">상품 3 (3개) 추가</button>
        <div id="addResult"></div>
    </div>

    <div class="test-section">
        <h2>4. 장바구니 내용 확인</h2>
        <button onclick="checkCartContents()">장바구니 내용 확인</button>
        <div id="cartContents"></div>
    </div>

    <div class="test-section">
        <h2>5. 실제 페이지 링크</h2>
        <p><a href="/pages/store/index.php" target="_blank">🏪 스토어 메인 (카운트 확인)</a></p>
        <p><a href="/pages/store/products.php" target="_blank">📋 상품 목록 (버튼 테스트)</a></p>
        <p><a href="/pages/store/cart.php" target="_blank">🛒 장바구니 페이지 (제품 리스트)</a></p>
    </div>

    <script>
        function log(elementId, message, type = 'info') {
            const element = document.getElementById(elementId);
            const className = type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info');
            element.innerHTML += `<p class="${className}">${message}</p>`;
        }

        function clearLog(elementId) {
            document.getElementById(elementId).innerHTML = '';
        }

        async function updateCartCount() {
            try {
                const response = await fetch('/api/cart.php?action=count');
                const data = await response.json();

                if (data.success) {
                    document.getElementById('cartCount').textContent = data.count;
                    log('countResult', `✅ 카운트 업데이트: ${data.count}개`, 'success');
                } else {
                    log('countResult', `❌ 카운트 로드 실패: ${data.message}`, 'error');
                }
            } catch (error) {
                log('countResult', `❌ 오류: ${error.message}`, 'error');
            }
        }

        async function testAddProduct(productId, quantity) {
            clearLog('addResult');

            try {
                const response = await fetch(`/api/cart.php?action=add&product_id=${productId}&quantity=${quantity}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        product_id: productId,
                        quantity: quantity
                    })
                });

                const data = await response.json();

                if (data.success) {
                    log('addResult', `✅ 상품 ${productId} ${quantity}개 추가 성공: ${data.message}`, 'success');

                    // 카운트 자동 업데이트
                    setTimeout(updateCartCount, 500);
                } else {
                    log('addResult', `❌ 추가 실패: ${data.message}`, 'error');
                }
            } catch (error) {
                log('addResult', `❌ 오류: ${error.message}`, 'error');
            }
        }

        async function checkCartContents() {
            clearLog('cartContents');

            try {
                const response = await fetch('/api/cart.php?action=items');
                const data = await response.json();

                if (data.success) {
                    log('cartContents', `✅ 장바구니 로드 성공`, 'success');

                    const items = data.data;
                    const summary = data.summary;

                    if (Object.keys(items).length === 0) {
                        log('cartContents', '장바구니가 비어있습니다.', 'info');
                        return;
                    }

                    let itemsHtml = '<h4>상품 목록:</h4><ul>';
                    Object.values(items).forEach(item => {
                        itemsHtml += `<li>상품 ${item.product_id}: ${item.name} - ${item.quantity}개 (${item.price.toLocaleString()}원)</li>`;
                    });
                    itemsHtml += '</ul>';

                    itemsHtml += `<h4>요약:</h4>`;
                    itemsHtml += `<p>총 상품 수: ${summary.item_count}개</p>`;
                    itemsHtml += `<p>총 금액: ${summary.final_total.toLocaleString()}원</p>`;

                    document.getElementById('cartContents').innerHTML += itemsHtml;
                } else {
                    log('cartContents', `❌ 장바구니 로드 실패: ${data.message}`, 'error');
                }
            } catch (error) {
                log('cartContents', `❌ 오류: ${error.message}`, 'error');
            }
        }

        // 페이지 로드시 초기 카운트 로드
        window.onload = function() {
            updateCartCount();
        };
    </script>
</body>
</html>
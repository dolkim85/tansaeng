<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>세션 동기화 테스트</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { border: 1px solid #ccc; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { color: blue; }
        button { padding: 10px 20px; margin: 5px; cursor: pointer; background: #007cba; color: white; border: none; border-radius: 4px; }
        button:hover { background: #005a87; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔄 세션 동기화 테스트</h1>

    <div class="test-section">
        <h2>현재 세션 상태</h2>
        <?php
        session_start();
        echo "<p><strong>세션 ID:</strong> " . session_id() . "</p>";
        echo "<p><strong>로그인 여부:</strong> " . (!empty($_SESSION['user_id']) ? '✅ 로그인됨' : '❌ 비로그인') . "</p>";

        if (!empty($_SESSION['user_id'])) {
            echo "<p><strong>사용자 ID:</strong> {$_SESSION['user_id']}</p>";
            echo "<p><strong>이름:</strong> " . ($_SESSION['name'] ?? $_SESSION['user_name'] ?? 'N/A') . "</p>";
        } else {
            echo '<p class="error">로그인이 필요합니다. <a href="/test_login_simulation.php">로그인하기</a></p>';
        }
        ?>
    </div>

    <div class="test-section">
        <h2>1단계: 세션 새로고침</h2>
        <button onclick="refreshSession()">세션 정보 새로고침</button>
        <div id="sessionResult"></div>
    </div>

    <div class="test-section">
        <h2>2단계: 장바구니 초기화</h2>
        <button onclick="clearCart()">장바구니 초기화</button>
        <div id="clearResult"></div>
    </div>

    <div class="test-section">
        <h2>3단계: 상품 추가 (단계별)</h2>
        <button onclick="addProduct(1, 1)">상품 1 추가</button>
        <button onclick="addProduct(2, 2)">상품 2 추가</button>
        <button onclick="addProduct(3, 1)">상품 3 추가</button>
        <div id="addResult"></div>
    </div>

    <div class="test-section">
        <h2>4단계: 동기화 확인</h2>
        <button onclick="checkSync()">세션-DB 동기화 확인</button>
        <div id="syncResult"></div>
    </div>

    <script>
        function log(elementId, message, type = 'info') {
            const element = document.getElementById(elementId);
            const className = type;
            const timestamp = new Date().toLocaleTimeString();
            element.innerHTML += `<p class="${className}">[${timestamp}] ${message}</p>`;
        }

        function clearLog(elementId) {
            document.getElementById(elementId).innerHTML = '';
        }

        async function refreshSession() {
            clearLog('sessionResult');
            try {
                const response = await fetch('/debug_session.php');
                const data = await response.json();

                log('sessionResult', `✅ 세션 새로고침 완료`, 'success');
                log('sessionResult', `세션 ID: ${data.session_id}`, 'info');
                log('sessionResult', `로그인 상태: ${data.is_logged_in ? '✅' : '❌'}`, data.is_logged_in ? 'success' : 'error');

                if (data.is_logged_in) {
                    log('sessionResult', `사용자 ID: ${data.user_id}`, 'info');
                }
            } catch (error) {
                log('sessionResult', `❌ 세션 새로고침 실패: ${error.message}`, 'error');
            }
        }

        async function clearCart() {
            clearLog('clearResult');
            try {
                const response = await fetch('/api/cart.php?action=clear', {
                    method: 'DELETE'
                });

                const data = await response.json();

                if (data.success) {
                    log('clearResult', `✅ 장바구니 초기화 성공: ${data.message}`, 'success');
                } else {
                    log('clearResult', `❌ 장바구니 초기화 실패: ${data.message}`, 'error');
                }
            } catch (error) {
                log('clearResult', `❌ 요청 실패: ${error.message}`, 'error');
            }
        }

        async function addProduct(productId, quantity) {
            try {
                log('addResult', `상품 ${productId} ${quantity}개 추가 중...`, 'info');

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

                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`HTTP ${response.status}: ${errorText}`);
                }

                const data = await response.json();

                if (data.success) {
                    log('addResult', `✅ 상품 ${productId} 추가 성공`, 'success');
                    log('addResult', `현재 총 수량: ${data.cart.item_count}개`, 'info');
                } else {
                    log('addResult', `❌ 상품 ${productId} 추가 실패: ${data.message}`, 'error');
                }
            } catch (error) {
                log('addResult', `❌ 상품 ${productId} 추가 오류: ${error.message}`, 'error');
            }
        }

        async function checkSync() {
            clearLog('syncResult');
            try {
                // 1. API를 통한 장바구니 조회
                log('syncResult', '1. API를 통한 장바구니 조회...', 'info');
                const apiResponse = await fetch('/api/cart.php?action=items');
                const apiData = await apiResponse.json();

                if (apiData.success) {
                    const itemCount = Object.keys(apiData.data).length;
                    const totalQuantity = apiData.summary.item_count;
                    log('syncResult', `✅ API 조회 성공: ${itemCount}종류, ${totalQuantity}개`, 'success');

                    // 상품별 상세 정보
                    Object.values(apiData.data).forEach(item => {
                        log('syncResult', `  - 상품 ${item.product_id}: ${item.name} ${item.quantity}개`, 'info');
                    });
                } else {
                    log('syncResult', `❌ API 조회 실패: ${apiData.message}`, 'error');
                    return;
                }

                // 2. 세션 정보 확인
                log('syncResult', '2. 세션 정보 확인...', 'info');
                const sessionResponse = await fetch('/debug_session.php');
                const sessionData = await sessionResponse.json();

                if (sessionData.session_data.cart) {
                    const sessionItemCount = Object.keys(sessionData.session_data.cart).length;
                    log('syncResult', `✅ 세션 장바구니: ${sessionItemCount}종류`, 'success');

                    Object.values(sessionData.session_data.cart).forEach(item => {
                        log('syncResult', `  - 상품 ${item.product_id}: ${item.name} ${item.quantity}개`, 'info');
                    });
                } else {
                    log('syncResult', `❌ 세션에 장바구니 데이터 없음`, 'error');
                }

                // 3. 동기화 상태 비교
                log('syncResult', '3. 동기화 상태 분석...', 'info');
                const apiItems = Object.keys(apiData.data).length;
                const sessionItems = sessionData.session_data.cart ? Object.keys(sessionData.session_data.cart).length : 0;

                if (apiItems === sessionItems) {
                    log('syncResult', `✅ 동기화 정상: API(${apiItems}) = 세션(${sessionItems})`, 'success');
                } else {
                    log('syncResult', `⚠️ 동기화 불일치: API(${apiItems}) ≠ 세션(${sessionItems})`, 'error');
                }

            } catch (error) {
                log('syncResult', `❌ 동기화 확인 실패: ${error.message}`, 'error');
            }
        }

        // 페이지 로드시 자동 세션 확인
        window.onload = function() {
            refreshSession();
        };
    </script>
</body>
</html>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>로그인 상태 실시간 확인</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .status-box { background: white; padding: 20px; border-radius: 8px; margin: 10px 0; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { color: blue; }
        button { padding: 10px 20px; margin: 5px; cursor: pointer; background: #007bff; color: white; border: none; border-radius: 4px; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 로그인 상태 실시간 확인</h1>

    <div class="status-box">
        <h2>현재 PHP 세션 상태</h2>
        <?php
        session_start();

        echo "<p><strong>세션 ID:</strong> " . session_id() . "</p>";
        echo "<p><strong>현재 시간:</strong> " . date('Y-m-d H:i:s') . "</p>";

        if (!empty($_SESSION['user_id'])) {
            echo "<p class='success'>✅ 로그인 상태: 로그인됨</p>";
            echo "<ul>";
            echo "<li><strong>사용자 ID:</strong> {$_SESSION['user_id']}</li>";
            echo "<li><strong>이름:</strong> " . ($_SESSION['name'] ?? $_SESSION['user_name'] ?? 'N/A') . "</li>";
            echo "<li><strong>이메일:</strong> " . ($_SESSION['email'] ?? $_SESSION['user_email'] ?? 'N/A') . "</li>";
            echo "<li><strong>권한:</strong> " . ($_SESSION['role'] ?? 'N/A') . "</li>";
            echo "</ul>";
        } else {
            echo "<p class='error'>❌ 로그인 상태: 비로그인</p>";
        }

        echo "<h3>전체 세션 데이터:</h3>";
        echo "<pre>" . json_encode($_SESSION, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        ?>
    </div>

    <div class="status-box">
        <h2>API 테스트</h2>
        <button onclick="testLoginStatus()">로그인 상태 API 확인</button>
        <button onclick="testCartAPI()">장바구니 API 테스트</button>
        <button onclick="addToCartTest()">장바구니 추가 테스트</button>
        <div id="apiResult"></div>
    </div>

    <div class="status-box">
        <h2>실시간 장바구니 테스트</h2>
        <button onclick="realTimeCartTest()">실시간 장바구니 버튼 시뮬레이션</button>
        <div id="cartTestResult"></div>
    </div>

    <script>
        function log(message, type = 'info') {
            const resultDiv = document.getElementById('apiResult');
            const className = type;
            const timestamp = new Date().toLocaleTimeString();
            resultDiv.innerHTML += `<p class="${className}">[${timestamp}] ${message}</p>`;
        }

        function clearLog() {
            document.getElementById('apiResult').innerHTML = '';
        }

        async function testLoginStatus() {
            clearLog();
            try {
                const response = await fetch('/debug_session.php');
                const data = await response.json();

                log('세션 API 응답 성공', 'success');
                log(`세션 ID: ${data.session_id}`, 'info');
                log(`로그인 상태: ${data.is_logged_in ? '✅ 로그인됨' : '❌ 비로그인'}`, data.is_logged_in ? 'success' : 'error');

                if (data.is_logged_in) {
                    log(`사용자 ID: ${data.user_id}`, 'info');
                }
            } catch (error) {
                log('세션 API 오류: ' + error.message, 'error');
            }
        }

        async function testCartAPI() {
            try {
                log('장바구니 API 테스트 시작...', 'info');

                const response = await fetch('/api/cart.php?action=count');

                if (!response.ok) {
                    const errorText = await response.text();
                    log(`HTTP ${response.status}: ${errorText}`, 'error');
                    return;
                }

                const data = await response.json();
                log('장바구니 API 응답 성공', 'success');
                log(`카운트: ${data.data.count}개`, 'info');

            } catch (error) {
                log('장바구니 API 오류: ' + error.message, 'error');
            }
        }

        async function addToCartTest() {
            try {
                log('장바구니 추가 API 테스트...', 'info');

                const response = await fetch('/api/cart.php?action=add', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        product_id: 1,
                        quantity: 1
                    })
                });

                const data = await response.json();

                if (data.success) {
                    log('✅ 장바구니 추가 성공: ' + data.message, 'success');
                    log(`현재 총 수량: ${data.data.item_count}개`, 'info');
                } else {
                    log('❌ 장바구니 추가 실패: ' + data.message, 'error');
                }

            } catch (error) {
                log('장바구니 추가 오류: ' + error.message, 'error');
            }
        }

        async function realTimeCartTest() {
            const resultDiv = document.getElementById('cartTestResult');
            resultDiv.innerHTML = '<p>실시간 테스트 진행 중...</p>';

            try {
                // 실제 상품 페이지에서 사용하는 것과 동일한 코드
                const response = await fetch('../../api/cart.php?action=add', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        product_id: 1,
                        quantity: 1
                    })
                });

                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);

                if (!response.ok) {
                    const errorText = await response.text();
                    resultDiv.innerHTML = `<p class="error">HTTP ${response.status}: ${errorText}</p>`;
                    return;
                }

                const data = await response.json();

                if (data.success) {
                    resultDiv.innerHTML = `
                        <p class="success">✅ 성공: ${data.message}</p>
                        <p>총 수량: ${data.data.item_count}개</p>
                        <p>총 금액: ${data.data.final_total.toLocaleString()}원</p>
                    `;
                } else {
                    resultDiv.innerHTML = `<p class="error">❌ 실패: ${data.message}</p>`;
                }

            } catch (error) {
                resultDiv.innerHTML = `<p class="error">오류: ${error.message}</p>`;
                console.error('Cart test error:', error);
            }
        }

        // 페이지 로드시 자동 테스트
        window.onload = function() {
            testLoginStatus();
        };
    </script>
</body>
</html>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>브라우저 세션 및 API 테스트</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { border: 1px solid #ccc; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .success { color: #008000; }
        .error { color: #ff0000; }
        .info { color: #0066cc; }
        button { padding: 10px 20px; margin: 5px; cursor: pointer; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔧 브라우저 세션 및 API 테스트</h1>

    <div class="test-section">
        <h2>1. 세션 상태 확인</h2>
        <button onclick="checkSession()">세션 상태 확인</button>
        <div id="sessionResult"></div>
    </div>

    <div class="test-section">
        <h2>2. 로그인 시뮬레이션</h2>
        <button onclick="simulateLogin()">테스트 로그인</button>
        <div id="loginResult"></div>
    </div>

    <div class="test-section">
        <h2>3. 장바구니 API 테스트</h2>
        <button onclick="testAddToCart()">장바구니 추가 테스트</button>
        <div id="cartResult"></div>
    </div>

    <div class="test-section">
        <h2>4. 장바구니 현황 확인</h2>
        <button onclick="checkCartStatus()">장바구니 현황</button>
        <div id="cartStatusResult"></div>
    </div>

    <script>
        function checkSession() {
            const resultDiv = document.getElementById('sessionResult');
            resultDiv.innerHTML = '<p class="info">세션 확인 중...</p>';

            fetch('/debug_session.php')
                .then(response => response.json())
                .then(data => {
                    resultDiv.innerHTML = `
                        <h3>세션 정보:</h3>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                        <p class="${data.is_logged_in ? 'success' : 'error'}">
                            로그인 상태: ${data.is_logged_in ? '✅ 로그인됨' : '❌ 비로그인'}
                        </p>
                    `;
                })
                .catch(error => {
                    resultDiv.innerHTML = `<p class="error">오류: ${error.message}</p>`;
                });
        }

        function simulateLogin() {
            const resultDiv = document.getElementById('loginResult');
            resultDiv.innerHTML = '<p class="info">로그인 시뮬레이션 중...</p>';

            // 로그인 시뮬레이션 - 실제로는 login API를 호출해야 함
            fetch('/test_login_simulation.php')
                .then(response => response.text())
                .then(data => {
                    resultDiv.innerHTML = `
                        <h3>로그인 결과:</h3>
                        <div>${data}</div>
                        <p class="success">✅ 로그인 시뮬레이션 완료</p>
                    `;
                })
                .catch(error => {
                    resultDiv.innerHTML = `<p class="error">오류: ${error.message}</p>`;
                });
        }

        function testAddToCart() {
            const resultDiv = document.getElementById('cartResult');
            resultDiv.innerHTML = '<p class="info">장바구니 추가 테스트 중...</p>';

            fetch('/api/cart.php?action=add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    product_id: 1,
                    quantity: 1
                })
            })
            .then(response => {
                console.log('응답 상태:', response.status);
                console.log('응답 헤더:', response.headers);

                if (!response.ok) {
                    return response.text().then(text => {
                        console.error('API 오류 응답:', text);
                        throw new Error(`HTTP ${response.status}: ${text}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('응답 데이터:', data);
                resultDiv.innerHTML = `
                    <h3>장바구니 추가 결과:</h3>
                    <pre>${JSON.stringify(data, null, 2)}</pre>
                    <p class="${data.success ? 'success' : 'error'}">
                        ${data.success ? '✅ 성공' : '❌ 실패'}: ${data.message}
                    </p>
                `;
            })
            .catch(error => {
                console.error('장바구니 추가 오류:', error);
                resultDiv.innerHTML = `
                    <h3>장바구니 추가 실패:</h3>
                    <p class="error">오류: ${error.message}</p>
                    <p class="info">콘솔에서 자세한 오류 정보를 확인하세요.</p>
                `;
            });
        }

        function checkCartStatus() {
            const resultDiv = document.getElementById('cartStatusResult');
            resultDiv.innerHTML = '<p class="info">장바구니 현황 확인 중...</p>';

            fetch('/api/cart.php?action=items')
                .then(response => response.json())
                .then(data => {
                    resultDiv.innerHTML = `
                        <h3>장바구니 현황:</h3>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                        <p class="info">총 ${data.count}개 상품</p>
                    `;
                })
                .catch(error => {
                    resultDiv.innerHTML = `<p class="error">오류: ${error.message}</p>`;
                });
        }

        // 페이지 로드시 자동으로 세션 확인
        window.onload = function() {
            checkSession();
        };
    </script>
</body>
</html>

<?php
// PHP 부분 - 현재 세션 상태 표시
session_start();

echo "<!-- 현재 PHP 세션 상태: -->";
echo "<!-- Session ID: " . session_id() . " -->";
echo "<!-- User ID: " . ($_SESSION['user_id'] ?? 'null') . " -->";
echo "<!-- Session Data: " . json_encode($_SESSION) . " -->";
?>
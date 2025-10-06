<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>장바구니 - 탄생</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Noto Sans KR', sans-serif; background: #f8f9fa; }

        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { font-size: 2.5rem; color: #333; margin-bottom: 10px; }
        .header p { color: #666; font-size: 1.1rem; }

        .cart-summary {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            text-align: center;
        }

        .cart-items {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .cart-item {
            display: flex;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #eee;
            transition: background 0.3s;
        }

        .cart-item:hover { background: #f8f9fa; }
        .cart-item:last-child { border-bottom: none; }

        .item-image {
            width: 80px;
            height: 80px;
            background: #f0f0f0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 2rem;
        }

        .item-info {
            flex: 1;
            margin-right: 20px;
        }

        .item-name {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .item-price {
            font-size: 1.1rem;
            color: #007bff;
            font-weight: bold;
        }

        .item-controls {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow: hidden;
        }

        .qty-btn {
            width: 35px;
            height: 35px;
            border: none;
            background: #f8f9fa;
            cursor: pointer;
            font-size: 1.2rem;
            transition: background 0.3s;
        }

        .qty-btn:hover { background: #e9ecef; }
        .qty-btn:disabled { opacity: 0.5; cursor: not-allowed; }

        .qty-input {
            width: 60px;
            height: 35px;
            border: none;
            text-align: center;
            font-size: 1rem;
            font-weight: bold;
        }

        .subtotal {
            font-size: 1.1rem;
            font-weight: bold;
            color: #333;
            min-width: 100px;
            text-align: right;
        }

        .remove-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .remove-btn:hover { background: #c82333; }

        .empty-cart {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-cart-icon { font-size: 5rem; margin-bottom: 20px; }
        .empty-cart h2 { margin-bottom: 10px; }
        .empty-cart p { margin-bottom: 30px; }

        .shop-btn {
            background: #007bff;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 1.1rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
        }

        .shop-btn:hover { background: #0056b3; }

        .total-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 30px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }

        .total-row.final {
            border-top: 2px solid #007bff;
            padding-top: 15px;
            margin-top: 20px;
            font-size: 1.3rem;
            font-weight: bold;
            color: #007bff;
        }

        .checkout-btn {
            width: 100%;
            background: #28a745;
            color: white;
            padding: 15px;
            border: none;
            border-radius: 5px;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            margin-top: 20px;
            transition: background 0.3s;
        }

        .checkout-btn:hover { background: #218838; }
        .checkout-btn:disabled { background: #6c757d; cursor: not-allowed; }

        .loading { text-align: center; padding: 40px; color: #666; }

        @media (max-width: 768px) {
            .container { padding: 10px; }
            .cart-item { flex-direction: column; align-items: flex-start; }
            .item-controls { margin-top: 15px; }
            .header h1 { font-size: 2rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🛒 장바구니</h1>
            <p>선택하신 상품을 확인하고 주문을 진행하세요</p>
        </div>

        <div class="cart-summary">
            <h3>장바구니 요약</h3>
            <p id="summaryText">상품을 로딩 중입니다...</p>
        </div>

        <div id="cartContent" class="loading">
            <p>장바구니 내용을 불러오는 중...</p>
        </div>

        <div id="totalSection" class="total-section" style="display: none;">
            <div class="total-row">
                <span>상품 총액</span>
                <span id="subtotalAmount">0원</span>
            </div>
            <div class="total-row">
                <span>배송비</span>
                <span id="shippingAmount">0원</span>
            </div>
            <div class="total-row final">
                <span>최종 결제 금액</span>
                <span id="finalAmount">0원</span>
            </div>
            <button class="checkout-btn" id="checkoutBtn" onclick="checkout()">
                주문하기
            </button>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="/pages/store/" class="shop-btn">계속 쇼핑하기</a>
        </div>
    </div>

    <script>
        let cartData = {};

        // 페이지 로드시 장바구니 데이터 로드
        window.onload = function() {
            loadCartData();
        };

        async function loadCartData() {
            try {
                const response = await fetch('/api/cart.php?action=items');

                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('API 오류 응답:', errorText);
                    throw new Error(`HTTP ${response.status}: ${errorText}`);
                }

                const data = await response.json();
                console.log('Cart API 응답:', data);

                if (data.success) {
                    cartData = data;
                    // 새로운 API 구조에 맞게 수정
                    const displayData = {
                        data: data.data,
                        summary: data.data ? getSummaryFromItems(data.data) : {item_count: 0, final_total: 0}
                    };
                    displayCart(displayData);
                    updateSummary(displayData.summary);
                } else {
                    showError('장바구니 데이터를 불러올 수 없습니다: ' + data.message);
                }
            } catch (error) {
                console.error('Cart load error:', error);
                showError('네트워크 오류가 발생했습니다: ' + error.message);
            }
        }

        // 아이템 데이터에서 요약 정보 생성
        function getSummaryFromItems(items) {
            let itemCount = 0;
            let total = 0;

            Object.values(items).forEach(item => {
                itemCount += item.quantity;
                total += item.price * item.quantity;
            });

            const shippingCost = total >= 50000 ? 0 : 3000;
            const finalTotal = total + shippingCost;

            return {
                item_count: itemCount,
                total: total,
                shipping_cost: shippingCost,
                final_total: finalTotal
            };
        }

        function displayCart(data) {
            const contentDiv = document.getElementById('cartContent');
            const items = data.data;

            if (Object.keys(items).length === 0) {
                contentDiv.innerHTML = `
                    <div class="empty-cart">
                        <div class="empty-cart-icon">🛒</div>
                        <h2>장바구니가 비어있습니다</h2>
                        <p>원하는 상품을 장바구니에 담아보세요</p>
                        <a href="/pages/store/" class="shop-btn">쇼핑하러 가기</a>
                    </div>
                `;
                return;
            }

            let html = '<div class="cart-items">';

            Object.values(items).forEach(item => {
                const subtotal = item.price * item.quantity;
                html += `
                    <div class="cart-item" data-product-id="${item.product_id}">
                        <div class="item-image">
                            ${item.image ? `<img src="${item.image}" alt="${item.name}" style="width:100%;height:100%;object-fit:cover;">` : '📦'}
                        </div>
                        <div class="item-info">
                            <div class="item-name">${item.name}</div>
                            <div class="item-price">${formatPrice(item.price)}원</div>
                        </div>
                        <div class="item-controls">
                            <div class="quantity-control">
                                <button class="qty-btn" onclick="updateQuantity(${item.product_id}, ${item.quantity - 1})" ${item.quantity <= 1 ? 'disabled' : ''}>-</button>
                                <input type="number" class="qty-input" value="${item.quantity}" min="1"
                                       onchange="updateQuantity(${item.product_id}, this.value)">
                                <button class="qty-btn" onclick="updateQuantity(${item.product_id}, ${item.quantity + 1})">+</button>
                            </div>
                            <div class="subtotal">${formatPrice(subtotal)}원</div>
                            <button class="remove-btn" onclick="removeItem(${item.product_id})">삭제</button>
                        </div>
                    </div>
                `;
            });

            html += '</div>';
            contentDiv.innerHTML = html;

            // 총액 섹션 표시
            document.getElementById('totalSection').style.display = 'block';
        }

        function updateSummary(summary) {
            const itemCount = summary.item_count || 0;
            const productCount = Object.keys(cartData.data || {}).length;

            document.getElementById('summaryText').textContent =
                `총 ${productCount}종류, ${itemCount}개 상품 (${formatPrice(summary.final_total)}원)`;

            document.getElementById('subtotalAmount').textContent = formatPrice(summary.total) + '원';
            document.getElementById('shippingAmount').textContent = formatPrice(summary.shipping_cost) + '원';
            document.getElementById('finalAmount').textContent = formatPrice(summary.final_total) + '원';

            // 체크아웃 버튼 활성화/비활성화
            const checkoutBtn = document.getElementById('checkoutBtn');
            if (itemCount > 0) {
                checkoutBtn.disabled = false;
                checkoutBtn.textContent = `${itemCount}개 상품 주문하기`;
            } else {
                checkoutBtn.disabled = true;
                checkoutBtn.textContent = '장바구니가 비어있습니다';
            }
        }

        async function updateQuantity(productId, newQuantity) {
            if (newQuantity < 1) return;

            try {
                const response = await fetch('/api/cart.php?action=update', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        product_id: productId,
                        quantity: parseInt(newQuantity)
                    })
                });

                const data = await response.json();
                if (data.success) {
                    await loadCartData(); // 전체 다시 로드
                } else {
                    alert('수량 변경에 실패했습니다: ' + data.message);
                }
            } catch (error) {
                console.error('Update quantity error:', error);
                alert('네트워크 오류가 발생했습니다.');
            }
        }

        async function removeItem(productId) {
            if (!confirm('이 상품을 장바구니에서 제거하시겠습니까?')) return;

            try {
                const response = await fetch(`/api/cart.php?action=item&product_id=${productId}`, {
                    method: 'DELETE'
                });

                const data = await response.json();
                if (data.success) {
                    await loadCartData(); // 전체 다시 로드
                } else {
                    alert('상품 제거에 실패했습니다: ' + data.message);
                }
            } catch (error) {
                console.error('Remove item error:', error);
                alert('네트워크 오류가 발생했습니다.');
            }
        }

        function checkout() {
            const itemCount = Object.keys(cartData.data || {}).length;
            if (itemCount === 0) {
                alert('장바구니가 비어있습니다.');
                return;
            }

            if (confirm(`${cartData.summary.item_count}개 상품을 주문하시겠습니까?`)) {
                alert('주문 기능은 준비 중입니다.');
                // TODO: 실제 주문 페이지로 이동
            }
        }

        function formatPrice(price) {
            return new Intl.NumberFormat('ko-KR').format(price);
        }

        function showError(message) {
            document.getElementById('cartContent').innerHTML = `
                <div class="empty-cart">
                    <div class="empty-cart-icon">❌</div>
                    <h2>오류가 발생했습니다</h2>
                    <p>${message}</p>
                    <button class="shop-btn" onclick="loadCartData()">다시 시도</button>
                </div>
            `;
        }
    </script>
</body>
</html>
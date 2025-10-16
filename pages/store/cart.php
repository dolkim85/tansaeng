<?php
// 데이터베이스 연결을 선택적으로 처리
$currentUser = null;

try {
    require_once __DIR__ . '/../../classes/Auth.php';
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();
} catch (Exception $e) {
    error_log("Auth failed: " . $e->getMessage());
    $currentUser = null;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>장바구니 - 탄생</title>
    <link rel="stylesheet" href="../../assets/css/main.css?v=<?= date('YmdHis') ?>">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Noto Sans KR', sans-serif; background: #f8f9fa; }

        .container { max-width: 1200px; margin: 0 auto; padding: 15px; }

        .cart-layout {
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }

        .cart-main {
            flex: 1;
            min-width: 0;
        }

        .cart-sidebar {
            width: 320px;
            flex-shrink: 0;
        }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { font-size: 1.8rem; color: #333; margin-bottom: 8px; }
        .header p { color: #666; font-size: 0.95rem; }

        .cart-summary {
            background: white;
            padding: 15px 18px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 15px;
            text-align: center;
            border: 1px solid #e0e0e0;
        }

        .cart-summary h3 {
            font-size: 1rem;
            margin-bottom: 8px;
            font-weight: 700;
            color: #333;
        }

        .cart-summary p {
            font-size: 0.95rem;
            font-weight: 600;
            color: #555;
        }

        .cart-items {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.1);
            overflow: hidden;
            border: 1px solid #e0e0e0;
        }

        .cart-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
            background: white;
        }

        .cart-item:hover {
            background: #f8f9fa;
        }
        .cart-item:last-child { border-bottom: none; }

        .item-checkbox {
            margin-right: 12px;
        }

        .item-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #007bff;
        }

        .select-all-container {
            padding: 10px 15px;
            border-bottom: 2px solid #007bff;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .select-all-container input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #007bff;
        }

        .select-all-container label {
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            color: #333;
        }

        .item-image {
            width: 60px;
            height: 60px;
            background: #f5f5f5;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 1.5rem;
            border: 1px solid #ddd;
        }

        .item-image img {
            border-radius: 6px;
        }

        .item-info {
            flex: 1;
            margin-right: 12px;
        }

        .item-name {
            font-size: 0.95rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
            line-height: 1.4;
        }

        .item-price {
            font-size: 1rem;
            color: #007bff;
            font-weight: 600;
        }

        .item-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow: hidden;
        }

        .qty-btn {
            width: 28px;
            height: 28px;
            border: none;
            background: #f8f9fa;
            color: #333;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: background 0.2s;
        }

        .qty-btn:hover {
            background: #e9ecef;
        }
        .qty-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .qty-input {
            width: 45px;
            height: 28px;
            border: none;
            text-align: center;
            font-size: 0.9rem;
            font-weight: 600;
            color: #333;
            background: white;
        }

        .subtotal {
            font-size: 1rem;
            font-weight: 700;
            color: #333;
            min-width: 85px;
            text-align: right;
        }

        .remove-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: background 0.2s;
        }

        .remove-btn:hover {
            background: #c82333;
        }

        .empty-cart {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .empty-cart-icon { font-size: 3.5rem; margin-bottom: 15px; }
        .empty-cart h2 { margin-bottom: 8px; font-size: 1.3rem; }
        .empty-cart p { margin-bottom: 20px; font-size: 0.95rem; }

        .shop-btn {
            background: #007bff;
            color: white;
            padding: 10px 24px;
            border: none;
            border-radius: 4px;
            font-size: 0.95rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
        }

        .shop-btn:hover { background: #0056b3; }

        .total-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
            position: sticky;
            top: 100px;
        }

        .total-section h3 {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: #333;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 0.95rem;
            font-weight: 500;
            color: #666;
            padding: 6px 0;
        }

        .total-row span:last-child {
            color: #333;
            font-weight: 600;
        }

        .total-row.final {
            border-top: 2px solid #007bff;
            padding-top: 15px;
            margin-top: 15px;
            font-size: 1.15rem;
            font-weight: 700;
            color: #007bff;
        }

        .total-row.final span {
            color: #007bff;
        }

        .total-row.final span:last-child {
            font-size: 1.25rem;
        }

        .checkout-btn {
            width: 100%;
            background: #007bff;
            color: white;
            padding: 14px;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 15px;
            transition: background 0.2s;
        }

        .checkout-btn:hover { background: #0056b3; }
        .checkout-btn:disabled { background: #6c757d; cursor: not-allowed; }

        .loading { text-align: center; padding: 30px; color: #666; font-size: 0.9rem; }

        @media (max-width: 992px) {
            .cart-layout {
                flex-direction: column;
            }

            .cart-sidebar {
                width: 100%;
                position: static;
            }

            .total-section {
                position: static;
            }
        }

        @media (max-width: 768px) {
            /* 모바일 레이아웃 */
            .container {
                padding: 10px;
                margin-top: 70px !important;
            }

            .header {
                margin-bottom: 15px;
            }

            .header h1 {
                font-size: 1.3rem;
            }

            .header p {
                font-size: 0.85rem;
            }

            /* 카트 아이템 - 더 컴팩트하게 */
            .cart-item {
                flex-wrap: wrap;
                padding: 12px 10px;
                gap: 10px;
            }

            .item-checkbox {
                margin-right: 8px;
            }

            .item-checkbox input[type="checkbox"] {
                width: 20px;
                height: 20px;
            }

            .item-image {
                width: 70px;
                height: 70px;
                font-size: 1.5rem;
                margin-right: 10px;
            }

            .item-image img {
                max-width: 100%;
                max-height: 100%;
            }

            .item-info {
                flex: 1;
                min-width: 150px;
                margin-right: 0;
            }

            .item-name {
                font-size: 0.9rem;
                margin-bottom: 6px;
            }

            .item-price {
                font-size: 0.9rem;
            }

            .item-shipping {
                font-size: 0.8rem !important;
                margin-top: 4px !important;
            }

            /* 컨트롤 영역 - 전체 너비 */
            .item-controls {
                width: 100%;
                margin-left: 28px;
                justify-content: space-between;
                flex-wrap: nowrap;
                gap: 8px;
            }

            .quantity-control {
                flex-shrink: 0;
            }

            .qty-btn {
                width: 32px;
                height: 32px;
                font-size: 1.1rem;
            }

            .qty-input {
                width: 50px;
                height: 32px;
                font-size: 0.95rem;
            }

            .subtotal {
                flex: 1;
                text-align: center;
                font-size: 0.95rem;
                min-width: 70px;
            }

            .remove-btn {
                padding: 8px 12px;
                font-size: 0.8rem;
                white-space: nowrap;
            }

            /* 요약 정보 */
            .cart-summary {
                padding: 12px 15px;
                margin-bottom: 12px;
            }

            .cart-summary h3 {
                font-size: 0.9rem;
                margin-bottom: 6px;
            }

            .cart-summary p {
                font-size: 0.85rem;
            }

            /* 결제 정보 */
            .total-section {
                padding: 15px;
                position: static !important;
                margin-top: 15px;
            }

            .total-section h3 {
                font-size: 1rem;
                margin-bottom: 12px;
            }

            .total-row {
                font-size: 0.9rem;
                padding: 5px 0;
                margin-bottom: 8px;
            }

            .total-row.final {
                font-size: 1.05rem;
                padding-top: 12px;
                margin-top: 12px;
            }

            .total-row.final span:last-child {
                font-size: 1.15rem;
            }

            .checkout-btn {
                padding: 14px;
                font-size: 0.95rem;
                min-height: 48px;
            }

            /* 빈 장바구니 */
            .empty-cart {
                padding: 30px 15px;
            }

            .empty-cart-icon {
                font-size: 3rem;
                margin-bottom: 12px;
            }

            .empty-cart h2 {
                font-size: 1.2rem;
                margin-bottom: 8px;
            }

            .empty-cart p {
                font-size: 0.9rem;
                margin-bottom: 15px;
            }

            .shop-btn {
                padding: 12px 20px;
                font-size: 0.9rem;
            }

            /* 전체 선택 */
            .select-all-container {
                padding: 10px 12px;
            }

            .select-all-container input[type="checkbox"] {
                width: 20px;
                height: 20px;
            }

            .select-all-container label {
                font-size: 0.95rem;
            }
        }

        @media (max-width: 480px) {
            /* 초소형 화면 */
            .item-image {
                width: 60px;
                height: 60px;
            }

            .item-name {
                font-size: 0.85rem;
            }

            .item-price {
                font-size: 0.85rem;
            }

            .qty-btn {
                width: 28px;
                height: 28px;
            }

            .qty-input {
                width: 45px;
            }

            .subtotal {
                font-size: 0.9rem;
            }

            .remove-btn {
                padding: 6px 10px;
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <div class="container" style="margin-top: 80px;">
        <div class="header">
            <h1>🛒 장바구니</h1>
            <p>선택하신 상품을 확인하고 주문을 진행하세요</p>
        </div>

        <div class="cart-summary">
            <h3>장바구니 요약</h3>
            <p id="summaryText">상품을 로딩 중입니다...</p>
        </div>

        <div class="cart-layout">
            <div class="cart-main">
                <div id="cartContent" class="loading">
                    <p>장바구니 내용을 불러오는 중...</p>
                </div>

                <div style="text-align: center; margin-top: 20px;">
                    <a href="/pages/store/" class="shop-btn">계속 쇼핑하기</a>
                </div>
            </div>

            <div class="cart-sidebar">
                <div id="totalSection" class="total-section" style="display: none;">
                    <h3>결제 정보</h3>
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
            </div>
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
            let totalShippingCost = 0;

            Object.values(items).forEach(item => {
                itemCount += item.quantity;
                total += item.price * item.quantity;

                // 배송비 계산 (shipping_unit_count 기준으로 계산)
                const shippingCost = item.shipping_cost || 0;
                const quantity = item.quantity;
                const shippingUnitCount = item.shipping_unit_count || 1;

                // shipping_unit_count 개수마다 배송비 1회 부과
                // 예: shipping_unit_count=10이면 10개마다 배송비 1회
                // 수량 15개 = ceil(15/10) = 2회 배송비
                if (shippingUnitCount > 0 && shippingCost > 0) {
                    const shippingTimes = Math.ceil(quantity / shippingUnitCount);
                    totalShippingCost += shippingCost * shippingTimes;
                }
            });

            const finalTotal = total + totalShippingCost;

            return {
                item_count: itemCount,
                total: total,
                shipping_cost: totalShippingCost,
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

            // 전체 선택 체크박스
            html += `
                <div class="select-all-container">
                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()" checked>
                    <label for="selectAll">전체 선택</label>
                </div>
            `;

            Object.values(items).forEach(item => {
                const subtotal = item.price * item.quantity;
                const shippingCost = item.shipping_cost || 0;
                const shippingUnitCount = item.shipping_unit_count || 1;
                const quantity = item.quantity;

                // 배송비 계산 및 표시 문구 생성
                let shippingText = '무료배송';
                if (shippingCost > 0 && shippingUnitCount > 0) {
                    const shippingTimes = Math.ceil(quantity / shippingUnitCount);
                    const totalShippingForItem = shippingCost * shippingTimes;
                    shippingText = `${formatPrice(shippingCost)}원 (${shippingUnitCount}개당) x ${shippingTimes}회 = ${formatPrice(totalShippingForItem)}원`;
                }

                html += `
                    <div class="cart-item" data-product-id="${item.product_id}">
                        <div class="item-checkbox">
                            <input type="checkbox" class="item-select" data-product-id="${item.product_id}"
                                   onchange="updateSelectedItems()" checked>
                        </div>
                        <div class="item-image">
                            ${item.image ? `<img src="${item.image}" alt="${item.name}" style="width:100%;height:100%;object-fit:cover;">` : '📦'}
                        </div>
                        <div class="item-info">
                            <div class="item-name">${item.name}</div>
                            <div class="item-price">${formatPrice(item.price)}원</div>
                            <div class="item-shipping" style="color: #666; font-size: 0.9rem; margin-top: 5px;">
                                배송비: ${shippingText}
                            </div>
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
            updateSelectedItems(); // 선택된 항목으로 업데이트
        }

        // 전체 선택/해제
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.item-select');

            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });

            updateSelectedItems();
        }

        // 선택된 항목만 계산
        function updateSelectedItems() {
            const checkboxes = document.querySelectorAll('.item-select');
            const selectAll = document.getElementById('selectAll');

            let selectedCount = 0;
            let selectedProductCount = 0;
            let selectedTotal = 0;
            let selectedShippingCost = 0;

            checkboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    const productId = checkbox.dataset.productId;
                    const item = Object.values(cartData.data).find(i => i.product_id == productId);

                    if (item) {
                        selectedProductCount++;
                        selectedCount += item.quantity;
                        selectedTotal += item.price * item.quantity;

                        // 배송비 계산 (shipping_unit_count 기준으로 계산)
                        const shippingCost = item.shipping_cost || 0;
                        const quantity = item.quantity;
                        const shippingUnitCount = item.shipping_unit_count || 1;

                        // shipping_unit_count 개수마다 배송비 1회 부과
                        if (shippingUnitCount > 0 && shippingCost > 0) {
                            const shippingTimes = Math.ceil(quantity / shippingUnitCount);
                            selectedShippingCost += shippingCost * shippingTimes;
                        }
                    }
                }
            });

            // 전체 선택 체크박스 상태 업데이트
            if (selectAll) {
                selectAll.checked = selectedProductCount === checkboxes.length && checkboxes.length > 0;
            }

            const finalTotal = selectedTotal + selectedShippingCost;

            // 요약 정보 업데이트
            document.getElementById('summaryText').textContent =
                `총 ${selectedProductCount}종류, ${selectedCount}개 상품 선택됨 (${formatPrice(finalTotal)}원)`;

            document.getElementById('subtotalAmount').textContent = formatPrice(selectedTotal) + '원';
            document.getElementById('shippingAmount').textContent = formatPrice(selectedShippingCost) + '원';
            document.getElementById('finalAmount').textContent = formatPrice(finalTotal) + '원';

            // 체크아웃 버튼 활성화/비활성화
            const checkoutBtn = document.getElementById('checkoutBtn');
            if (selectedCount > 0) {
                checkoutBtn.disabled = false;
                checkoutBtn.textContent = `${selectedCount}개 상품 주문하기`;
            } else {
                checkoutBtn.disabled = true;
                checkoutBtn.textContent = '상품을 선택해주세요';
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

        async function checkout() {
            // 선택된 상품만 카운트
            const checkboxes = document.querySelectorAll('.item-select:checked');
            const selectedItems = [];
            let totalQuantity = 0;

            checkboxes.forEach(checkbox => {
                const productId = checkbox.dataset.productId;
                const item = Object.values(cartData.data).find(i => i.product_id == productId);

                if (item) {
                    selectedItems.push(item);
                    totalQuantity += item.quantity;
                }
            });

            if (selectedItems.length === 0) {
                alert('주문할 상품을 선택해주세요.');
                return;
            }

            // 주문 페이지로 이동하기 위해 세션에 저장
            try {
                const response = await fetch('/api/cart.php?action=prepare_order', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ items: selectedItems })
                });

                const data = await response.json();
                if (data.success) {
                    // 주문 페이지로 이동
                    window.location.href = '/pages/store/order.php';
                } else {
                    alert('주문 준비 중 오류가 발생했습니다: ' + data.message);
                }
            } catch (error) {
                console.error('Checkout error:', error);
                alert('네트워크 오류가 발생했습니다.');
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

    <?php include '../../includes/footer.php'; ?>
</body>
</html>
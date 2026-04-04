<?php
// 세션 시작
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 데이터베이스 연결을 선택적으로 처리
$currentUser = null;
$dbConnected = false;
$cartItems = [];
$totalAmount = 0;
$shippingCost = 0;
$finalTotal = 0;

try {
    require_once __DIR__ . '/../../classes/Auth.php';
    require_once __DIR__ . '/../../classes/Cart.php';

    $auth = Auth::getInstance();
    $cart = new Cart();
    $currentUser = null;

    // 로그인 상태 확인 (선택사항)
    if ($auth->isLoggedIn()) {
        $currentUser = $auth->getCurrentUser();
    }

    $dbConnected = true;

    // 장바구니 항목 조회 (세션 기반)
    $cartItems = [];
    $sessionCart = $cart->getItems();

    // 디버그 정보
    error_log("Cart Page - Session ID: " . session_id());
    error_log("Cart Page - Session cart raw: " . json_encode($_SESSION['cart'] ?? []));
    error_log("Cart Page - Cart items from getItems(): " . json_encode($sessionCart));

    foreach ($sessionCart as $item) {
        $cartItems[] = [
            'id' => $item['product_id'],
            'product_id' => $item['product_id'],
            'name' => $item['name'],
            'price' => $item['original_price'],
            'discount_price' => $item['price'] != $item['original_price'] ? $item['price'] : null,
            'quantity' => $item['quantity'],
            'image_url' => $item['image'] ?? '',
            'sku' => $item['sku'] ?? '',
            'delivery_date' => date('n/j', strtotime('+2 days')) // 2일 후 배송
        ];
    }

    // 총 금액 계산
    $summary = $cart->getSummary();
    $totalAmount = $summary['total'];
    $shippingCost = $summary['shipping_cost'];
    $finalTotal = $summary['final_total'];

} catch (Exception $e) {
    // 데이터베이스 연결 실패시 빈 장바구니 표시
    error_log("Database connection failed: " . $e->getMessage());
    $cartItems = [];
    $totalAmount = 0;
    $shippingCost = 0;
    $finalTotal = 0;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>장바구니 - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        .cart-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #fff;
        }

        /* Progress Steps */
        .progress-steps {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
            padding: 20px 0;
            border-bottom: 1px solid #e5e5e5;
        }

        .step {
            display: flex;
            align-items: center;
            font-size: 16px;
            font-weight: bold;
        }

        .step-number {
            color: #999;
            margin-right: 4px;
        }

        .step-text {
            color: #999;
        }

        .step.active .step-number,
        .step.active .step-text {
            color: #0073e6;
        }

        .step-arrow {
            width: 16px;
            height: 16px;
            margin: 0 20px;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23999"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z"/></svg>') no-repeat center;
            background-size: contain;
        }

        /* Header */
        .cart-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .cart-title {
            font-size: 32px;
            font-weight: bold;
            color: #131516;
            margin-right: 8px;
        }

        .cart-count {
            font-size: 32px;
            font-weight: bold;
            color: #131516;
        }

        /* Main Layout */
        .cart-layout {
            display: flex;
            gap: 20px;
        }

        .cart-main {
            flex: 1;
        }

        .cart-sidebar {
            width: 300px;
            position: sticky;
            top: 20px;
            height: fit-content;
        }

        /* Cart Items */
        .cart-section {
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .section-header {
            background: #f8f9fa;
            padding: 14px 16px;
            border-bottom: 1px solid #e5e5e5;
            font-weight: bold;
            font-size: 18px;
        }

        .cart-item {
            display: flex;
            align-items: flex-start;
            padding: 16px;
            border-bottom: 1px solid #e5e5e5;
            position: relative;
            transition: all 0.3s ease;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .item-checkbox {
            margin-right: 12px;
            margin-top: 4px;
        }

        .item-checkbox input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .item-image {
            width: 140px;
            height: 140px;
            margin-right: 16px;
            flex-shrink: 0;
        }

        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 4px;
        }

        .item-content {
            flex: 1;
        }

        .item-name {
            font-size: 14px;
            color: #131516;
            line-height: 1.3;
            margin-bottom: 8px;
            text-decoration: none;
        }

        .item-name:hover {
            text-decoration: underline;
        }

        .delivery-info {
            font-size: 14px;
            color: #131516;
            margin-bottom: 8px;
        }

        .item-price {
            font-size: 20px;
            font-weight: bold;
            color: #131516;
            margin-bottom: 10px;
        }

        .original-price {
            font-size: 16px;
            color: #999;
            text-decoration: line-through;
            margin-right: 8px;
        }

        .discount-price {
            color: #e74c3c;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            border: 1px solid #c4cdd5;
            border-radius: 4px;
            width: 112px;
            height: 32px;
            margin-top: 16px;
        }

        .qty-btn {
            width: 32px;
            height: 30px;
            border: none;
            background: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: #666;
        }

        .qty-btn:hover {
            background: #f5f5f5;
        }

        .qty-btn:disabled {
            color: #ccc;
            cursor: not-allowed;
        }

        .quantity-input {
            flex: 1;
            text-align: center;
            border: none;
            outline: none;
            font-size: 14px;
            height: 100%;
        }

        .item-delete {
            position: absolute;
            top: 16px;
            right: 16px;
            font-size: 14px;
            color: #131516;
            text-decoration: underline;
            cursor: pointer;
        }

        .item-delete:hover {
            color: #e74c3c;
        }

        /* Selection Controls */
        .selection-controls {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            margin-bottom: 20px;
        }

        .select-all {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .select-all input[type="checkbox"] {
            width: 20px;
            height: 20px;
        }

        .select-count {
            color: #666;
            margin-left: 4px;
        }

        .bulk-actions {
            display: flex;
            gap: 12px;
        }

        .bulk-btn {
            padding: 5px 10px;
            border: 1px solid #ccc;
            background: white;
            font-size: 12px;
            cursor: pointer;
            border-radius: 2px;
        }

        .bulk-btn:hover {
            background: #f5f5f5;
        }

        /* Order Summary */
        .order-summary {
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            padding: 20px;
            background: white;
        }

        .summary-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 16px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
        }

        .summary-total {
            border-top: 1px solid #e5e5e5;
            margin-top: 8px;
            padding-top: 12px;
            font-size: 20px;
            font-weight: bold;
        }

        .order-btn {
            width: 100%;
            height: 60px;
            background: #e5e5e5;
            color: #999;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: bold;
            margin-top: 16px;
            cursor: not-allowed;
        }

        .order-btn.active {
            background: #0073e6;
            color: white;
            cursor: pointer;
        }

        .order-btn.active:hover {
            background: #005bb5;
        }

        /* Empty Cart */
        .empty-cart {
            text-align: center;
            padding: 80px 20px;
        }

        .empty-cart-icon {
            font-size: 64px;
            color: #ccc;
            margin-bottom: 20px;
        }

        .empty-cart h2 {
            font-size: 24px;
            color: #666;
            margin-bottom: 16px;
        }

        .empty-cart p {
            color: #999;
            margin-bottom: 32px;
        }

        .shop-btn {
            display: inline-block;
            padding: 12px 24px;
            background: #0073e6;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
        }

        .shop-btn:hover {
            background: #005bb5;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .cart-container {
                padding: 16px;
            }

            .cart-layout {
                flex-direction: column;
            }

            .cart-sidebar {
                width: 100%;
                position: static;
                order: -1; /* 모바일에서 위로 올리기 */
            }

            .progress-steps {
                display: none;
            }

            .cart-title {
                font-size: 20px;
            }

            .cart-count {
                font-size: 20px;
            }

            .cart-item {
                flex-direction: row;
                align-items: flex-start;
                padding: 12px;
            }

            .item-image {
                width: 75px;
                height: 75px;
                margin-right: 12px;
            }

            .item-name {
                font-size: 13px;
                line-height: 1.2;
            }

            .item-price {
                font-size: 16px;
                margin-bottom: 8px;
            }

            .quantity-controls {
                width: 100px;
                height: 28px;
                margin-top: 8px;
            }

            .qty-btn {
                width: 28px;
                height: 26px;
                font-size: 14px;
            }

            .selection-controls {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .bulk-actions {
                width: 100%;
                justify-content: flex-start;
            }

            .order-summary {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                border-radius: 16px 16px 0 0;
                border: none;
                border-top: 1px solid #e5e5e5;
                z-index: 100;
                margin: 0;
            }

            .summary-title {
                display: flex;
                justify-content: space-between;
                align-items: center;
                cursor: pointer;
            }

            .summary-title::after {
                content: '▲';
                font-size: 12px;
                color: #666;
            }

            .order-btn {
                height: 50px;
                font-size: 15px;
            }

            .notice-banner {
                font-size: 12px;
                padding: 8px 12px;
            }

            /* 모바일에서 바디에 패딩 추가하여 고정된 요약 박스와 겹치지 않게 */
            body {
                padding-bottom: 200px;
            }
        }

        @media (max-width: 480px) {
            .item-content {
                font-size: 12px;
            }

            .section-footer {
                padding: 12px !important;
                font-size: 12px !important;
            }

            .section-footer > div {
                flex-direction: column !important;
                gap: 4px !important;
            }
        }

        .notice-banner {
            background: #f8f9fa;
            padding: 12px 16px;
            text-align: center;
            font-size: 14px;
            color: #666;
            border-radius: 4px;
            margin-top: 12px;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <main>
        <div class="cart-container">
            <!-- Progress Steps -->
            <div class="progress-steps">
                <div class="step">
                    <span class="step-number">01</span>
                    <span class="step-text">옵션선택</span>
                </div>
                <div class="step-arrow"></div>
                <div class="step active">
                    <span class="step-number">02</span>
                    <span class="step-text">장바구니</span>
                </div>
                <div class="step-arrow"></div>
                <div class="step">
                    <span class="step-number">03</span>
                    <span class="step-text">주문/결제</span>
                </div>
                <div class="step-arrow"></div>
                <div class="step">
                    <span class="step-number">04</span>
                    <span class="step-text">주문완료</span>
                </div>
            </div>

            <!-- Cart Header -->
            <div class="cart-header">
                <h1 class="cart-title">장바구니</h1>
                <span class="cart-count">(<?= count($cartItems) ?>)</span>
            </div>

            <?php if (empty($cartItems)): ?>
                <div class="empty-cart">
                    <div class="empty-cart-icon">🛒</div>
                    <h2>장바구니가 비어있습니다</h2>
                    <p>원하는 상품을 장바구니에 담아보세요</p>
                    <a href="/pages/store/" class="shop-btn">쇼핑하러 가기</a>
                </div>
            <?php else: ?>
                <div class="cart-layout">
                    <!-- Main Cart Content -->
                    <div class="cart-main">
                        <!-- Cart Items Section -->
                        <div class="cart-section">
                            <div class="section-header">
                                일반배송 상품
                            </div>

                            <?php foreach ($cartItems as $index => $item): ?>
                            <div class="cart-item" data-item-id="<?= $item['id'] ?>">
                                <div class="item-checkbox">
                                    <input type="checkbox" class="item-select" data-item-id="<?= $item['id'] ?>"
                                           title="<?= htmlspecialchars($item['name']) ?>">
                                </div>

                                <div class="item-image">
                                    <a href="/pages/store/product_detail.php?id=<?= $item['id'] ?>">
                                        <img src="<?= $item['image_url'] ?: '/assets/images/products/placeholder.jpg' ?>"
                                             alt="<?= htmlspecialchars($item['name']) ?>" loading="lazy">
                                    </a>
                                </div>

                                <div class="item-content">
                                    <a href="/pages/store/product_detail.php?id=<?= $item['id'] ?>" class="item-name">
                                        <?= htmlspecialchars($item['name']) ?>
                                    </a>

                                    <div class="delivery-info">
                                        <span><?= $item['delivery_date'] ?></span>
                                        <span>도착 보장</span>
                                    </div>

                                    <div class="item-price">
                                        <?php if ($item['discount_price']): ?>
                                            <span class="original-price"><?= number_format($item['price']) ?>원</span>
                                            <span class="discount-price"><?= number_format($item['discount_price']) ?>원</span>
                                        <?php else: ?>
                                            <?= number_format($item['price']) ?>원
                                        <?php endif; ?>
                                    </div>

                                    <div class="quantity-controls">
                                        <button class="qty-btn minus" onclick="updateQuantity(<?= $item['id'] ?>, -1)"
                                                <?= $item['quantity'] <= 1 ? 'disabled' : '' ?>>-</button>
                                        <input type="text" class="quantity-input" value="<?= $item['quantity'] ?>" readonly>
                                        <button class="qty-btn plus" onclick="updateQuantity(<?= $item['id'] ?>, 1)">+</button>
                                    </div>
                                </div>

                                <div class="item-delete" onclick="removeItem(<?= $item['id'] ?>)">
                                    삭제
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <div class="section-footer" style="padding: 14px 16px; background: #f8f9fa; text-align: center; font-size: 14px;">
                                <div style="display: flex; align-items: center; justify-content: center; gap: 8px; flex-wrap: wrap;">
                                    <span>상품가격 <strong id="items-subtotal"><?= number_format($totalAmount) ?>원</strong></span>
                                    <span>+ 배송비 <strong id="items-shipping"><?= $shippingCost > 0 ? number_format($shippingCost) . '원' : '0원' ?></strong></span>
                                    <span>= 주문금액 <strong id="items-total"><?= number_format($finalTotal) ?>원</strong></span>
                                </div>
                            </div>
                        </div>

                        <!-- Selection Controls -->
                        <div class="selection-controls">
                            <label class="select-all">
                                <input type="checkbox" id="select-all" onchange="toggleSelectAll()">
                                <span style="font-size: 16px; font-weight: bold;">전체 선택</span>
                                <span class="select-count">(<span id="selected-count">0</span> / <?= count($cartItems) ?>)</span>
                            </label>

                            <div class="bulk-actions">
                                <button class="bulk-btn" onclick="deleteSelected()">선택삭제</button>
                                <button class="bulk-btn" onclick="deleteUnavailable()">품절/판매종료상품 전체삭제</button>
                            </div>
                        </div>
                    </div>

                    <!-- Order Summary Sidebar -->
                    <div class="cart-sidebar">
                        <div class="order-summary">
                            <div class="summary-title">주문 예상 금액</div>

                            <div class="summary-row">
                                <span>총 상품 가격</span>
                                <span id="summary-subtotal"><?= number_format($totalAmount) ?>원</span>
                            </div>

                            <div class="summary-row">
                                <span>총 배송비</span>
                                <span id="summary-shipping">+<?= $shippingCost > 0 ? number_format($shippingCost) . '원' : '0원' ?></span>
                            </div>

                            <div class="summary-row summary-total">
                                <span>총 주문 금액</span>
                                <span id="summary-total"><?= number_format($finalTotal) ?>원</span>
                            </div>

                            <button class="order-btn" id="order-button" onclick="proceedToCheckout()" disabled>
                                상품을 선택해주세요
                            </button>

                            <div class="notice-banner">
                                장바구니에 <strong>담은 지 90일이 지난 상품은 목록에서 삭제</strong>됩니다
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>

    <script>
        let selectedItems = new Set();

        // 개별 상품 선택/해제
        function toggleItemSelection() {
            updateSelectionUI();
            updateOrderButton();
        }

        // 전체 선택/해제
        function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById('select-all');
            const itemCheckboxes = document.querySelectorAll('.item-select');

            selectedItems.clear();

            itemCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
                if (selectAllCheckbox.checked) {
                    selectedItems.add(checkbox.dataset.itemId);
                }
            });

            updateSelectionUI();
            updateOrderButton();
        }

        // 선택 UI 업데이트
        function updateSelectionUI() {
            const selectedCount = document.querySelectorAll('.item-select:checked').length;
            const totalCount = document.querySelectorAll('.item-select').length;

            document.getElementById('selected-count').textContent = selectedCount;
            document.getElementById('select-all').checked = selectedCount === totalCount && totalCount > 0;
        }

        // 주문 버튼 상태 업데이트
        function updateOrderButton() {
            const selectedCount = document.querySelectorAll('.item-select:checked').length;
            const orderButton = document.getElementById('order-button');

            if (selectedCount > 0) {
                orderButton.classList.add('active');
                orderButton.disabled = false;
                orderButton.textContent = `선택한 상품 주문하기 (${selectedCount}개)`;
            } else {
                orderButton.classList.remove('active');
                orderButton.disabled = true;
                orderButton.textContent = '상품을 선택해주세요';
            }
        }

        // 수량 변경
        function updateQuantity(itemId, change) {
            const item = document.querySelector(`[data-item-id="${itemId}"]`);
            const quantityInput = item.querySelector('.quantity-input');
            let quantity = parseInt(quantityInput.value);

            quantity += change;
            if (quantity < 1) quantity = 1;

            fetch('../../api/cart.php?action=update', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    product_id: itemId,
                    quantity: quantity
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    quantityInput.value = quantity;
                    updateCartTotals();
                    // 버튼 상태 업데이트
                    const minusBtn = item.querySelector('.qty-btn.minus');
                    minusBtn.disabled = quantity <= 1;
                } else {
                    alert('수량 변경에 실패했습니다: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('네트워크 오류가 발생했습니다.');
            });
        }

        // 상품 제거
        function removeItem(itemId) {
            if (confirm('이 상품을 장바구니에서 제거하시겠습니까?')) {
                fetch(`../../api/cart.php?action=item&product_id=${itemId}`, {
                    method: 'DELETE'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const item = document.querySelector(`[data-item-id="${itemId}"]`);
                        item.remove();
                        updateCartTotals();
                        updateSelectionUI();
                        updateOrderButton();

                        // 장바구니가 비어있으면 페이지 새로고침
                        if (document.querySelectorAll('.cart-item').length === 0) {
                            location.reload();
                        }
                    } else {
                        alert('상품 제거에 실패했습니다: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('네트워크 오류가 발생했습니다.');
                });
            }
        }

        // 선택 상품 삭제
        function deleteSelected() {
            const selectedCheckboxes = document.querySelectorAll('.item-select:checked');
            if (selectedCheckboxes.length === 0) {
                alert('삭제할 상품을 선택해주세요.');
                return;
            }

            if (confirm(`선택한 ${selectedCheckboxes.length}개 상품을 삭제하시겠습니까?`)) {
                // 순차적으로 삭제하여 UI 갱신 문제 방지
                let deletedCount = 0;
                const totalToDelete = selectedCheckboxes.length;

                selectedCheckboxes.forEach((checkbox, index) => {
                    setTimeout(() => {
                        const itemId = checkbox.dataset.itemId;
                        fetch(`../../api/cart.php?action=item&product_id=${itemId}`, {
                            method: 'DELETE'
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const item = document.querySelector(`[data-item-id="${itemId}"]`);
                                item.style.opacity = '0.5';
                                item.style.transform = 'translateX(-20px)';
                                setTimeout(() => {
                                    item.remove();
                                    deletedCount++;

                                    if (deletedCount === totalToDelete) {
                                        updateCartTotals();
                                        updateSelectionUI();
                                        updateOrderButton();

                                        // 장바구니가 비어있으면 페이지 새로고침
                                        if (document.querySelectorAll('.cart-item').length === 0) {
                                            location.reload();
                                        }
                                    }
                                }, 300);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                        });
                    }, index * 100); // 100ms 간격으로 순차 삭제
                });
            }
        }

        // 품절/판매종료 상품 삭제
        function deleteUnavailable() {
            alert('품절/판매종료 상품이 없습니다.');
        }

        // 장바구니 총액 업데이트
        function updateCartTotals() {
            fetch('../../api/cart.php?action=summary')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const summary = data.data;
                    document.getElementById('items-subtotal').textContent = number_format(summary.total) + '원';
                    document.getElementById('items-shipping').textContent = summary.shipping_cost > 0 ? number_format(summary.shipping_cost) + '원' : '0원';
                    document.getElementById('items-total').textContent = number_format(summary.final_total) + '원';

                    document.getElementById('summary-subtotal').textContent = number_format(summary.total) + '원';
                    document.getElementById('summary-shipping').textContent = '+' + (summary.shipping_cost > 0 ? number_format(summary.shipping_cost) + '원' : '0원');
                    document.getElementById('summary-total').textContent = number_format(summary.final_total) + '원';
                }
            });
        }

        // 주문하기
        function proceedToCheckout() {
            const selectedItems = document.querySelectorAll('.item-select:checked');
            if (selectedItems.length === 0) {
                alert('주문할 상품을 선택해주세요.');
                return;
            }

            const orderButton = document.getElementById('order-button');
            const originalText = orderButton.textContent;

            // 로딩 상태 표시
            orderButton.disabled = true;
            orderButton.textContent = '주문 준비 중...';
            orderButton.style.background = '#ccc';

            // 선택된 상품 ID 수집
            const selectedProductIds = Array.from(selectedItems).map(item => item.dataset.itemId);

            // 주문 데이터 준비
            const orderData = {
                selected_items: selectedProductIds,
                action: 'prepare_order'
            };

            // 주문 준비 API 호출 (시뮬레이션)
            setTimeout(() => {
                // 실제로는 여기서 주문 페이지로 이동하거나 다음 단계 진행
                console.log('주문 준비 데이터:', orderData);

                // 주문 페이지 생성 또는 이동 (추후 구현)
                if (confirm(`선택한 ${selectedProductIds.length}개 상품을 주문하시겠습니까?\n\n주문 페이지로 이동합니다.`)) {
                    // 실제 주문 페이지로 이동 (추후 구현)
                    window.location.href = '/pages/store/checkout.php?items=' + selectedProductIds.join(',');
                } else {
                    // 취소시 버튼 상태 복원
                    orderButton.disabled = false;
                    orderButton.textContent = originalText;
                    orderButton.style.background = '#0073e6';
                }
            }, 1000);
        }

        // 숫자 포맷팅
        function number_format(number) {
            return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        // 페이지 로드시 초기화
        document.addEventListener('DOMContentLoaded', function() {
            // 체크박스 이벤트 리스너 등록
            document.querySelectorAll('.item-select').forEach(checkbox => {
                checkbox.addEventListener('change', toggleItemSelection);
            });

            // 초기 상태 설정
            updateSelectionUI();
            updateOrderButton();
        });
    </script>
</body>
</html>
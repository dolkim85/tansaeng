<?php
// 데이터베이스 연결을 선택적으로 처리
$currentUser = null;
$dbConnected = false;
$cartItems = [];
$totalAmount = 0;

try {
    require_once __DIR__ . '/../../classes/Auth.php';
    $auth = Auth::getInstance();
    
    // 로그인 확인
    if (!$auth->isLoggedIn()) {
        header('Location: /pages/auth/login.php');
        exit;
    }
    
    $currentUser = $auth->getCurrentUser();
    $db = Database::getInstance();
    $dbConnected = true;
    
    // 장바구니 항목 조회
    $cartItems = $db->select(
        "SELECT c.*, p.name, p.price, p.discount_price, p.image_url
         FROM cart c
         JOIN products p ON c.product_id = p.id
         WHERE c.user_id = :user_id",
        ['user_id' => $currentUser['id']]
    );
    
    // 총 금액 계산
    foreach ($cartItems as $item) {
        $price = $item['discount_price'] ?: $item['price'];
        $totalAmount += $price * $item['quantity'];
    }
    
} catch (Exception $e) {
    // 데이터베이스 연결 실패시 샘플 데이터 사용
    error_log("Database connection failed: " . $e->getMessage());
    
    // 로그인되지 않은 경우 리다이렉트
    if (!$currentUser) {
        header('Location: /pages/auth/login.php');
        exit;
    }
    
    // 샘플 장바구니 데이터
    $cartItems = [
        [
            'id' => 1,
            'product_id' => 1,
            'name' => '탄생 프리미엄 배지',
            'price' => 25000,
            'discount_price' => null,
            'quantity' => 2,
            'image_url' => null
        ],
        [
            'id' => 2,
            'product_id' => 2,
            'name' => '토마토 전용 양액',
            'price' => 35000,
            'discount_price' => 28000,
            'quantity' => 1,
            'image_url' => null
        ]
    ];
    
    // 총 금액 계산
    foreach ($cartItems as $item) {
        $price = $item['discount_price'] ?: $item['price'];
        $totalAmount += $price * $item['quantity'];
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>장바구니 - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main class="cart-main">
        <div class="container">
            <div class="page-header">
                <h1>🛒 장바구니</h1>
                <p>선택하신 상품들을 확인하고 주문하세요</p>
            </div>

            <?php if (empty($cartItems)): ?>
                <div class="empty-cart">
                    <div class="empty-icon">🛒</div>
                    <h2>장바구니가 비어있습니다</h2>
                    <p>원하는 상품을 장바구니에 담아보세요</p>
                    <a href="/pages/store/" class="btn btn-primary btn-lg">쇼핑하러 가기</a>
                </div>
            <?php else: ?>
                <div class="cart-content">
                    <div class="cart-items">
                        <h2>담긴 상품 (<?= count($cartItems) ?>개)</h2>
                        
                        <div class="items-list">
                            <?php foreach ($cartItems as $item): ?>
                            <div class="cart-item" data-item-id="<?= $item['id'] ?>">
                                <div class="item-image">
                                    <img src="<?= $item['image_url'] ?: '/assets/images/products/placeholder.jpg' ?>" 
                                         alt="<?= htmlspecialchars($item['name']) ?>" loading="lazy">
                                </div>
                                
                                <div class="item-info">
                                    <h3><?= htmlspecialchars($item['name']) ?></h3>
                                    <div class="item-price">
                                        <?php if ($item['discount_price']): ?>
                                            <span class="original-price"><?= number_format($item['price']) ?>원</span>
                                            <span class="sale-price"><?= number_format($item['discount_price']) ?>원</span>
                                        <?php else: ?>
                                            <span class="price"><?= number_format($item['price']) ?>원</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="item-quantity">
                                    <button class="qty-btn minus" onclick="updateQuantity(<?= $item['id'] ?>, -1)">-</button>
                                    <span class="quantity"><?= $item['quantity'] ?></span>
                                    <button class="qty-btn plus" onclick="updateQuantity(<?= $item['id'] ?>, 1)">+</button>
                                </div>
                                
                                <div class="item-total">
                                    <?php 
                                    $itemPrice = $item['discount_price'] ?: $item['price'];
                                    $itemTotal = $itemPrice * $item['quantity'];
                                    ?>
                                    <span class="total-price"><?= number_format($itemTotal) ?>원</span>
                                </div>
                                
                                <div class="item-actions">
                                    <button class="remove-btn" onclick="removeItem(<?= $item['id'] ?>)">
                                        <span>🗑️</span>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="cart-actions">
                            <button onclick="clearCart()" class="btn btn-outline">전체 비우기</button>
                            <a href="/pages/store/" class="btn btn-outline">계속 쇼핑</a>
                        </div>
                    </div>
                    
                    <div class="order-summary">
                        <h2>주문 요약</h2>
                        
                        <div class="summary-details">
                            <div class="summary-row">
                                <span>상품 금액</span>
                                <span id="subtotal"><?= number_format($totalAmount) ?>원</span>
                            </div>
                            <div class="summary-row">
                                <span>배송비</span>
                                <span id="shipping"><?= $totalAmount >= 50000 ? '무료' : '3,000원' ?></span>
                            </div>
                            <div class="summary-divider"></div>
                            <div class="summary-row total">
                                <span>총 주문 금액</span>
                                <span id="total">
                                    <?php 
                                    $shippingFee = $totalAmount >= 50000 ? 0 : 3000;
                                    $finalTotal = $totalAmount + $shippingFee;
                                    echo number_format($finalTotal);
                                    ?>원
                                </span>
                            </div>
                        </div>
                        
                        <div class="shipping-notice">
                            <p>💡 5만원 이상 구매시 무료배송</p>
                            <?php if ($totalAmount < 50000): ?>
                                <p>🚚 <?= number_format(50000 - $totalAmount) ?>원 더 구매하면 무료배송!</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="order-buttons">
                            <button class="btn btn-primary btn-lg btn-block" onclick="proceedToCheckout()">
                                주문하기
                            </button>
                            <button class="btn btn-outline btn-block" onclick="estimateShipping()">
                                배송비 조회
                            </button>
                        </div>
                        
                        <div class="payment-info">
                            <h3>결제 방법</h3>
                            <div class="payment-methods">
                                <span class="payment-icon">💳</span>
                                <span class="payment-icon">🏧</span>
                                <span class="payment-icon">📱</span>
                            </div>
                            <small>신용카드, 계좌이체, 무통장입금</small>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Recommended Products -->
            <section class="recommended-section">
                <h2>함께 구매하면 좋은 상품</h2>
                <div class="recommended-grid">
                    <div class="product-card">
                        <div class="product-image">
                            <img src="/assets/images/products/placeholder.jpg" alt="추천상품1" loading="lazy">
                        </div>
                        <div class="product-info">
                            <h3>스마트 pH 측정기</h3>
                            <p class="product-price">45,000원</p>
                            <button class="btn btn-outline btn-sm">장바구니 담기</button>
                        </div>
                    </div>
                    
                    <div class="product-card">
                        <div class="product-image">
                            <img src="/assets/images/products/placeholder.jpg" alt="추천상품2" loading="lazy">
                        </div>
                        <div class="product-info">
                            <h3>식물성장 LED 조명</h3>
                            <p class="product-price">75,000원</p>
                            <button class="btn btn-outline btn-sm">장바구니 담기</button>
                        </div>
                    </div>
                    
                    <div class="product-card">
                        <div class="product-image">
                            <img src="/assets/images/products/placeholder.jpg" alt="추천상품3" loading="lazy">
                        </div>
                        <div class="product-info">
                            <h3>자동 급수 시스템</h3>
                            <p class="product-price">120,000원</p>
                            <button class="btn btn-outline btn-sm">장바구니 담기</button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <?php include '../../includes/footer.php'; ?>

    <script src="/assets/js/main.js"></script>
    <script>
        function updateQuantity(itemId, change) {
            const item = document.querySelector(`[data-item-id="${itemId}"]`);
            const quantityElement = item.querySelector('.quantity');
            let quantity = parseInt(quantityElement.textContent);
            
            quantity += change;
            if (quantity < 1) quantity = 1;
            
            quantityElement.textContent = quantity;
            
            // 실제 구현시 AJAX로 서버에 업데이트
            updateCartDisplay();
        }
        
        function removeItem(itemId) {
            if (confirm('이 상품을 장바구니에서 제거하시겠습니까?')) {
                const item = document.querySelector(`[data-item-id="${itemId}"]`);
                item.remove();
                
                // 실제 구현시 AJAX로 서버에서 제거
                updateCartDisplay();
                
                // 장바구니가 비어있으면 페이지 새로고침
                if (document.querySelectorAll('.cart-item').length === 0) {
                    location.reload();
                }
            }
        }
        
        function clearCart() {
            if (confirm('장바구니의 모든 상품을 제거하시겠습니까?')) {
                // 실제 구현시 AJAX로 서버에서 모든 항목 제거
                location.reload();
            }
        }
        
        function updateCartDisplay() {
            // 총 금액 재계산
            let subtotal = 0;
            document.querySelectorAll('.cart-item').forEach(item => {
                const quantity = parseInt(item.querySelector('.quantity').textContent);
                const priceText = item.querySelector('.sale-price, .price').textContent;
                const price = parseInt(priceText.replace(/[^\d]/g, ''));
                const itemTotal = price * quantity;
                
                item.querySelector('.total-price').textContent = itemTotal.toLocaleString() + '원';
                subtotal += itemTotal;
            });
            
            const shippingFee = subtotal >= 50000 ? 0 : 3000;
            const total = subtotal + shippingFee;
            
            document.getElementById('subtotal').textContent = subtotal.toLocaleString() + '원';
            document.getElementById('shipping').textContent = shippingFee === 0 ? '무료' : shippingFee.toLocaleString() + '원';
            document.getElementById('total').textContent = total.toLocaleString() + '원';
        }
        
        function proceedToCheckout() {
            // 주문 페이지로 이동
            alert('주문 기능은 준비 중입니다.');
        }
        
        function estimateShipping() {
            alert('배송비 조회 기능은 준비 중입니다.');
        }
    </script>
</body>
</html>

<style>
.cart-main {
    padding: 2rem 0;
}

.page-header {
    text-align: center;
    margin-bottom: 3rem;
    padding: 3rem 0;
    background: linear-gradient(135deg, #E8F5E8 0%, #C8E6C9 100%);
    border-radius: 12px;
}

.page-header h1 {
    font-size: 2.5rem;
    color: #2E7D32;
    margin-bottom: 1rem;
}

.empty-cart {
    text-align: center;
    padding: 5rem 2rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.empty-icon {
    font-size: 5rem;
    margin-bottom: 1.5rem;
    opacity: 0.5;
}

.empty-cart h2 {
    color: #2E7D32;
    margin-bottom: 1rem;
}

.empty-cart p {
    color: #666;
    margin-bottom: 2rem;
}

.cart-content {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 3rem;
}

.cart-items {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.cart-items h2 {
    color: #2E7D32;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #f0f0f0;
}

.cart-item {
    display: grid;
    grid-template-columns: 80px 1fr 120px 100px 60px;
    gap: 1rem;
    align-items: center;
    padding: 1.5rem 0;
    border-bottom: 1px solid #f0f0f0;
}

.cart-item:last-child {
    border-bottom: none;
}

.item-image {
    width: 80px;
    height: 80px;
    border-radius: 8px;
    overflow: hidden;
    background: #f8f9fa;
}

.item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.item-info h3 {
    color: #2E7D32;
    margin-bottom: 0.5rem;
    font-size: 1rem;
}

.item-price {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.original-price {
    color: #999;
    text-decoration: line-through;
    font-size: 0.9rem;
}

.sale-price,
.price {
    color: #4CAF50;
    font-weight: 600;
}

.item-quantity {
    display: flex;
    align-items: center;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    overflow: hidden;
}

.qty-btn {
    background: #f8f9fa;
    border: none;
    padding: 0.5rem;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.qty-btn:hover {
    background: #e9ecef;
}

.quantity {
    padding: 0.5rem 1rem;
    background: white;
    border-left: 1px solid #e0e0e0;
    border-right: 1px solid #e0e0e0;
    min-width: 40px;
    text-align: center;
}

.item-total {
    text-align: right;
}

.total-price {
    font-weight: 600;
    color: #2E7D32;
}

.remove-btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 4px;
    transition: background-color 0.3s ease;
}

.remove-btn:hover {
    background: #ffebee;
}

.cart-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 2px solid #f0f0f0;
}

.order-summary {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    height: fit-content;
    position: sticky;
    top: 2rem;
}

.order-summary h2 {
    color: #2E7D32;
    margin-bottom: 1.5rem;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 0.8rem 0;
}

.summary-row.total {
    font-weight: 600;
    font-size: 1.2rem;
    color: #2E7D32;
}

.summary-divider {
    border-top: 1px solid #e0e0e0;
    margin: 1rem 0;
}

.shipping-notice {
    background: #E8F5E8;
    padding: 1rem;
    border-radius: 8px;
    margin: 1.5rem 0;
    font-size: 0.9rem;
}

.shipping-notice p {
    margin: 0.3rem 0;
    color: #2E7D32;
}

.order-buttons {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-bottom: 2rem;
}

.btn-block {
    width: 100%;
}

.payment-info {
    text-align: center;
    padding-top: 1.5rem;
    border-top: 1px solid #e0e0e0;
}

.payment-info h3 {
    color: #2E7D32;
    margin-bottom: 1rem;
    font-size: 1rem;
}

.payment-methods {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin-bottom: 0.5rem;
}

.payment-icon {
    font-size: 1.5rem;
}

.recommended-section {
    margin-top: 4rem;
}

.recommended-section h2 {
    color: #2E7D32;
    text-align: center;
    margin-bottom: 2rem;
}

.recommended-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}

.product-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
    transition: transform 0.3s ease;
}

.product-card:hover {
    transform: translateY(-2px);
}

.product-image {
    height: 150px;
    background: #f8f9fa;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-info {
    padding: 1rem;
    text-align: center;
}

.product-info h3 {
    color: #2E7D32;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.product-price {
    color: #4CAF50;
    font-weight: 600;
    margin-bottom: 1rem;
}

@media (max-width: 768px) {
    .cart-content {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
    
    .cart-item {
        grid-template-columns: 60px 1fr 80px 50px;
        gap: 0.5rem;
        font-size: 0.9rem;
    }
    
    .item-total {
        grid-column: 2;
        grid-row: 2;
        text-align: left;
        margin-top: 0.5rem;
    }
    
    .remove-btn {
        grid-column: 4;
        grid-row: 1;
    }
    
    .cart-actions {
        flex-direction: column;
    }
    
    .recommended-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>
<?php
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Database.php';

$productId = $_GET['id'] ?? 0;
$error = '';
$product = null;
$relatedProducts = [];
$reviews = [];
$averageRating = 0;

if (!$productId) {
    header('Location: index.php');
    exit;
}

try {
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    // Get product details
    $sql = "SELECT p.*, c.name as category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id = ? AND p.status = 'active'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        header('Location: index.php');
        exit;
    }

    // Update view count
    $sql = "UPDATE products SET views = views + 1 WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$productId]);

    // Get related products (same category)
    $sql = "SELECT p.*, c.name as category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.category_id = ? AND p.id != ? AND p.status = 'active'
            ORDER BY p.created_at DESC LIMIT 4";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$product['category_id'], $productId]);
    $relatedProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Use sample data when database is not available
    error_log("Database connection failed in product.php: " . $e->getMessage());
    header('Location: index.php');
    exit;
}

// Process product features
$features = [];
if (!empty($product['features'])) {
    $featuresData = json_decode($product['features'], true);
    if (is_array($featuresData)) {
        $features = $featuresData;
    } else {
        // If not JSON, treat as plain text with newlines
        $features = array_filter(array_map('trim', explode("\n", $product['features'])));
    }
}

// Process product images
$productImages = [];
if (!empty($product['images'])) {
    $imagesData = json_decode($product['images'], true);
    if (is_array($imagesData)) {
        $productImages = $imagesData;
    }
}

// If no specific images, use the main image_url
if (empty($productImages) && !empty($product['image_url'])) {
    $productImages = [$product['image_url']];
}

// If no images, use placeholder
if (empty($productImages)) {
    $productImages = ['/assets/images/product-placeholder.jpg'];
}

// Calculate discount
$hasDiscount = !empty($product['discount_percentage']) && $product['discount_percentage'] > 0;
$finalPrice = $hasDiscount
    ? $product['price'] * (100 - $product['discount_percentage']) / 100
    : $product['price'];

// Get stock quantity
$stockQuantity = $product['stock_quantity'] ?? $product['stock'] ?? 0;
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> - 탄생</title>
    <meta name="description" content="<?= htmlspecialchars($product['description'] ?? '') ?>">
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/store.css">
    <link rel="stylesheet" href="/assets/css/product-detail.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <!-- Breadcrumb -->
    <nav class="breadcrumb">
        <div class="container">
            <a href="/">홈</a>
            <span class="separator">></span>
            <a href="/pages/store/">스토어</a>
            <span class="separator">></span>
            <a href="/pages/store/?category=<?= $product['category_id'] ?>"><?= htmlspecialchars($product['category_name'] ?? '카테고리') ?></a>
            <span class="separator">></span>
            <span class="current"><?= htmlspecialchars($product['name']) ?></span>
        </div>
    </nav>

    <!-- Main Product Section -->
    <main class="product-detail-section">
        <div class="container">
            <div class="product-main">
                <!-- Product Gallery -->
                <div class="product-gallery">
                    <div class="main-image-container">
                        <?php if ($hasDiscount): ?>
                            <div class="image-badge"><?= $product['discount_percentage'] ?>% OFF</div>
                        <?php endif; ?>
                        <img src="<?= htmlspecialchars($productImages[0]) ?>"
                             alt="<?= htmlspecialchars($product['name']) ?>"
                             class="main-image" id="mainImage">
                    </div>

                    <?php if (count($productImages) > 1): ?>
                    <div class="thumbnail-list">
                        <?php foreach ($productImages as $index => $image): ?>
                        <img src="<?= htmlspecialchars($image) ?>"
                             alt="<?= htmlspecialchars($product['name']) ?> 이미지 <?= $index + 1 ?>"
                             class="thumbnail <?= $index === 0 ? 'active' : '' ?>"
                             onclick="changeMainImage('<?= htmlspecialchars($image) ?>', this)">
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Product Info -->
                <div class="product-info">
                    <div class="product-header">
                        <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>
                        <div class="product-meta">
                            <div class="meta-item">
                                <span class="rating-stars">
                                    <?= str_repeat('⭐', (int)round($product['rating_score'] ?? 4.5)) ?>
                                </span>
                                <span>(<?= $product['review_count'] ?? 0 ?>)</span>
                            </div>
                            <div class="meta-item">
                                👁️ <?= number_format($product['views'] ?? 0) ?>회 조회
                            </div>
                            <div class="meta-item">
                                📦 재고: <?= $product['stock_quantity'] ?? 0 ?>개
                            </div>
                        </div>
                    </div>

                    <!-- Price Section -->
                    <div class="price-section">
                        <div class="price-wrapper">
                            <?php if ($hasDiscount): ?>
                                <span class="original-price"><?= number_format($product['price']) ?>원</span>
                                <span class="current-price"><?= number_format($finalPrice) ?>원</span>
                                <span class="discount-badge"><?= $product['discount_percentage'] ?>% 할인</span>
                            <?php else: ?>
                                <span class="current-price"><?= number_format($product['price']) ?>원</span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($product['delivery_info'])): ?>
                        <div class="delivery-info">
                            <h4>🚚 배송정보</h4>
                            <div class="delivery-text"><?= htmlspecialchars($product['delivery_info']) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Product Description -->
                    <?php if (!empty($product['description'])): ?>
                    <div class="product-description">
                        <?= nl2br(htmlspecialchars($product['description'])) ?>
                    </div>
                    <?php endif; ?>

                    <!-- Features -->
                    <?php if (!empty($features)): ?>
                    <div class="specifications">
                        <h4>🌟 주요 특징</h4>
                        <ul class="spec-list">
                            <?php foreach ($features as $feature): ?>
                            <li>
                                <span class="spec-label">✓</span>
                                <span class="spec-value"><?= htmlspecialchars($feature) ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <!-- Purchase Section -->
                    <div class="specifications purchase-inline">
                        <h4>🛒 구매하기</h4>

                        <!-- Stock Status -->
                <div id="stockStatus" class="stock-status" data-stock="<?= $stockQuantity ?>">
                    <!-- JavaScript로 동적 업데이트 -->
                </div>

                <!-- Quantity Selection -->
                <div class="quantity-section">
                    <label class="quantity-label">수량</label>
                    <div class="quantity-controls">
                        <button class="quantity-btn" onclick="changeQuantity(-1)" id="decreaseBtn">-</button>
                        <input type="number" class="quantity-input" id="quantityInput" value="1" min="1" max="<?= $stockQuantity ?>">
                        <button class="quantity-btn" onclick="changeQuantity(1)" id="increaseBtn">+</button>
                    </div>
                </div>

                <!-- Total Price -->
                <div class="total-price">
                    <div class="total-label">총 금액</div>
                    <div class="total-amount" id="totalAmount"><?= number_format($finalPrice) ?>원</div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <?php if ($stockQuantity > 0): ?>
                        <button class="btn btn-secondary" onclick="addToCart()">
                            🛒 장바구니
                        </button>
                        <button class="btn btn-primary" onclick="buyNow()">
                            💳 바로구매
                        </button>
                    <?php else: ?>
                        <button class="btn" disabled>
                            품절된 상품입니다
                        </button>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Product Tabs -->
        <div class="product-tabs">
                <div class="tab-navigation">
                    <button class="tab-btn active" onclick="showTab('description')">상품설명</button>
                    <button class="tab-btn" onclick="showTab('specs')">상품정보</button>
                    <button class="tab-btn" onclick="showTab('reviews')">리뷰 (<?= $product['review_count'] ?? 0 ?>)</button>
                    <button class="tab-btn" onclick="showTab('qna')">문의</button>
                </div>

                <!-- Description Tab -->
                <div id="description" class="tab-content active">
                    <h3>📝 상품 상세설명</h3>
                    <?php if (!empty($product['detailed_description'])): ?>
                        <?php
                        // 이미지 툴바 제거 함수
                        function removeImageToolbars($html) {
                            // image-inline-toolbar div 제거
                            $html = preg_replace('/<div class="image-inline-toolbar">.*?<\/div>/s', '', $html);
                            return $html;
                        }
                        $cleanDescription = removeImageToolbars($product['detailed_description']);
                        ?>
                        <div class="product-description-content"><?= $cleanDescription ?></div>
                    <?php else: ?>
                        <div class="product-description-content"><?= nl2br(htmlspecialchars($product['description'] ?? '상세 설명이 준비 중입니다.')) ?></div>
                    <?php endif; ?>

                    <?php if (!empty($features)): ?>
                    <div class="features-grid">
                        <?php foreach ($features as $index => $feature): ?>
                        <div class="feature-item">
                            <div class="feature-icon">
                                <?= ['🌱', '💧', '🌿', '⚡', '🔬', '🌡️'][$index % 6] ?>
                            </div>
                            <div class="feature-title">특징 <?= $index + 1 ?></div>
                            <div class="feature-description"><?= htmlspecialchars($feature) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Specifications Tab -->
                <div id="specs" class="tab-content">
                    <h3>📋 상품정보</h3>
                    <div class="specifications">
                        <ul class="spec-list">
                            <li>
                                <span class="spec-label">상품명</span>
                                <span class="spec-value"><?= htmlspecialchars($product['name']) ?></span>
                            </li>
                            <li>
                                <span class="spec-label">카테고리</span>
                                <span class="spec-value"><?= htmlspecialchars($product['category_name'] ?? '일반') ?></span>
                            </li>
                            <?php if (!empty($product['weight'])): ?>
                            <li>
                                <span class="spec-label">중량</span>
                                <span class="spec-value"><?= htmlspecialchars($product['weight']) ?></span>
                            </li>
                            <?php endif; ?>
                            <?php if (!empty($product['dimensions'])): ?>
                            <li>
                                <span class="spec-label">크기</span>
                                <span class="spec-value"><?= htmlspecialchars($product['dimensions']) ?></span>
                            </li>
                            <?php endif; ?>
                            <li>
                                <span class="spec-label">재고수량</span>
                                <span class="spec-value"><?= $product['stock_quantity'] ?? 0 ?>개</span>
                            </li>
                            <li>
                                <span class="spec-label">등록일</span>
                                <span class="spec-value"><?= date('Y.m.d', strtotime($product['created_at'] ?? 'now')) ?></span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Reviews Tab -->
                <div id="reviews" class="tab-content">
                    <h3>⭐ 리뷰 (<?= $product['review_count'] ?? 0 ?>개)</h3>
                    <p>리뷰 시스템은 곧 추가될 예정입니다.</p>
                </div>

                <!-- Q&A Tab -->
                <div id="qna" class="tab-content">
                    <h3>❓ 상품문의</h3>
                    <p>상품에 대한 문의사항이 있으시면 <a href="/pages/support/contact.php">고객센터</a>로 연락해 주세요.</p>
                </div>
        </div>

        <div class="container">
            <!-- Related Products -->
            <?php if (!empty($relatedProducts)): ?>
            <div class="related-products">
                <div class="related-header">
                    <h3>🔗 관련 상품</h3>
                </div>
                <div class="related-grid">
                    <?php foreach ($relatedProducts as $relatedProduct): ?>
                    <a href="/pages/store/product.php?id=<?= $relatedProduct['id'] ?>" class="related-product">
                        <?php
                        $relatedImageSrc = !empty($relatedProduct['image_url'])
                            ? $relatedProduct['image_url']
                            : '/assets/images/product-placeholder.jpg';
                        ?>
                        <img src="<?= htmlspecialchars($relatedImageSrc) ?>"
                             alt="<?= htmlspecialchars($relatedProduct['name']) ?>"
                             class="related-image">
                        <div class="related-info">
                            <h4 class="related-title"><?= htmlspecialchars($relatedProduct['name']) ?></h4>
                            <div class="related-price"><?= number_format($relatedProduct['price']) ?>원</div>
                            <div class="related-rating">
                                <?= str_repeat('⭐', (int)round($relatedProduct['rating_score'] ?? 4.5)) ?>
                                (<?= $relatedProduct['review_count'] ?? 0 ?>)
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include '../../includes/footer.php'; ?>

    <script src="/assets/js/main.js"></script>
    <script>
        // Image gallery functionality
        function changeMainImage(imageSrc, thumbnailElement) {
            document.getElementById('mainImage').src = imageSrc;

            // Update active thumbnail
            document.querySelectorAll('.thumbnail').forEach(thumb => thumb.classList.remove('active'));
            thumbnailElement.classList.add('active');
        }

        // Quantity controls
        function changeQuantity(delta) {
            const input = document.getElementById('quantityInput');
            const currentValue = parseInt(input.value);
            const newValue = currentValue + delta;
            const maxValue = parseInt(input.max);

            if (newValue >= 1 && newValue <= maxValue) {
                input.value = newValue;
                updateTotalPrice();
            }

            // Update button states
            document.getElementById('decreaseBtn').disabled = newValue <= 1;
            document.getElementById('increaseBtn').disabled = newValue >= maxValue;
        }

        function updateTotalPrice() {
            const quantity = parseInt(document.getElementById('quantityInput').value);
            const unitPrice = <?= $finalPrice ?>;
            const totalPrice = quantity * unitPrice;

            document.getElementById('totalAmount').textContent = totalPrice.toLocaleString() + '원';
        }

        // Tab functionality
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });

            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

        // Purchase actions
        function addToCart() {
            const quantityInput = document.getElementById('quantityInput');
            const quantity = parseInt(quantityInput ? quantityInput.value : 1) || 1;
            const productId = <?= $product['id'] ?>;

            console.log('장바구니 추가 시작 - 상품 ID:', productId, '수량:', quantity);

            // 수량 유효성 검사
            if (quantity < 1) {
                alert('수량은 1개 이상이어야 합니다.');
                return;
            }

            // 버튼 비활성화 및 로딩 표시
            const button = event.target;
            const originalText = button.textContent;
            button.textContent = '추가 중...';
            button.disabled = true;

            // AJAX로 장바구니에 추가
            fetch('/api/cart.php?action=add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    product_id: productId,
                    quantity: quantity
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

                if (data.success) {
                    button.textContent = '완료!';

                    // 상세한 성공 메시지 - API 응답 구조에 맞게 수정
                    const totalItems = data.data?.cart?.item_count || data.data?.item_count || data.cart?.item_count || '?';
                    const totalAmount = data.data?.cart?.final_total || data.data?.final_total || data.cart?.final_total;
                    const formattedAmount = totalAmount ? new Intl.NumberFormat('ko-KR').format(totalAmount) : '?';

                    alert(`장바구니에 ${quantity}개가 추가되었습니다!\n총 ${totalItems}개 상품 (${formattedAmount}원)`);

                    // 수량 입력란 초기화
                    if (quantityInput) {
                        quantityInput.value = 1;
                    }

                    // 장바구니 카운트 즉시 업데이트
                    const cartCountElement = document.querySelector('.cart-count');
                    if (cartCountElement && totalItems !== '?') {
                        cartCountElement.textContent = totalItems;
                        cartCountElement.style.animation = 'pulse 0.5s ease-in-out';
                    }

                    // 전역 카운트 업데이트 함수 호출
                    if (typeof window.updateCartCount === 'function') {
                        window.updateCartCount();
                    }

                    // 재고 정보 실시간 업데이트
                    updateProductStock();

                    setTimeout(() => {
                        button.textContent = originalText;
                        button.disabled = false;
                    }, 1500);
                } else {
                    button.textContent = originalText;
                    button.disabled = false;
                    alert('오류: ' + (data.message || '장바구니 추가에 실패했습니다'));
                }
            })
            .catch(error => {
                console.error('장바구니 추가 오류:', error);
                button.textContent = originalText;
                button.disabled = false;

                let errorMessage = '네트워크 오류가 발생했습니다.';
                if (error.message.includes('HTTP')) {
                    errorMessage = 'API 호출 실패: ' + error.message + '\n로그인이 필요할 수 있습니다.';
                }
                alert(errorMessage);
            });
        }

        function buyNow() {
            const quantity = document.getElementById('quantityInput').value;
            alert(`${quantity}개 바로구매 기능은 준비 중입니다.`);
            // TODO: Implement actual purchase functionality
        }

        // 서버에서 현재 재고 정보 조회하여 업데이트
        async function updateProductStock() {
            const productId = <?= $product['id'] ?>;

            try {
                const response = await fetch(`/api/product_stock.php?id=${productId}`);
                const data = await response.json();

                if (data.success && data.stock !== undefined) {
                    updateStockDisplay(data.stock);
                } else {
                    console.error('재고 정보 조회 실패:', data.message);
                }
            } catch (error) {
                console.error('재고 정보 조회 오류:', error);
            }
        }

        // 재고 상태 업데이트 함수
        function updateStockDisplay(currentStock) {
            const stockStatus = document.getElementById('stockStatus');
            const quantityInput = document.getElementById('quantityInput');
            const increaseBtn = document.getElementById('increaseBtn');
            const addCartBtn = document.querySelector('button[onclick*="addToCart"]');
            const buyNowBtn = document.querySelector('button[onclick*="buyNow"]');

            if (!stockStatus) return;

            // 재고 상태 텍스트 및 스타일 업데이트
            stockStatus.setAttribute('data-stock', currentStock);

            if (currentStock > 10) {
                stockStatus.className = 'stock-status in-stock';
                stockStatus.innerHTML = `✅ 재고 충분 (${currentStock}개 남음)`;
            } else if (currentStock > 0) {
                stockStatus.className = 'stock-status low-stock';
                stockStatus.innerHTML = `⚠️ 재고 부족 (${currentStock}개 남음)`;
            } else {
                stockStatus.className = 'stock-status out-of-stock';
                stockStatus.innerHTML = '❌ 품절';
            }

            // 수량 입력 필드 최대값 업데이트
            if (quantityInput) {
                quantityInput.max = currentStock;

                // 현재 입력된 수량이 재고보다 많으면 조정
                if (parseInt(quantityInput.value) > currentStock) {
                    quantityInput.value = currentStock > 0 ? currentStock : 1;
                }
            }

            // 버튼 상태 업데이트
            if (currentStock <= 0) {
                if (addCartBtn) {
                    addCartBtn.disabled = true;
                    addCartBtn.textContent = '품절된 상품입니다';
                }
                if (buyNowBtn) {
                    buyNowBtn.disabled = true;
                    buyNowBtn.textContent = '품절된 상품입니다';
                }
                if (increaseBtn) {
                    increaseBtn.disabled = true;
                }
            } else {
                if (addCartBtn) {
                    addCartBtn.disabled = false;
                    addCartBtn.textContent = '🛒 장바구니';
                }
                if (buyNowBtn) {
                    buyNowBtn.disabled = false;
                    buyNowBtn.textContent = '💳 바로구매';
                }
                if (increaseBtn) {
                    increaseBtn.disabled = false;
                }
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // 초기 재고 상태 표시
            const initialStock = <?= $stockQuantity ?>;
            updateStockDisplay(initialStock);

            updateTotalPrice();

            // Set up quantity input change handler
            document.getElementById('quantityInput').addEventListener('change', function() {
                const value = parseInt(this.value);
                const max = parseInt(this.max);

                if (value < 1) this.value = 1;
                if (value > max) this.value = max;

                updateTotalPrice();
            });
        });
    </script>
</body>
</html>
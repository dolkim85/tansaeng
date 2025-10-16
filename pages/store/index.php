<?php
// 데이터베이스 연결을 선택적으로 처리
$currentUser = null;
$dbConnected = false;
$categories = [];
$featuredProducts = [];
$selectedCategory = $_GET['category'] ?? 'all';
$sortBy = $_GET['sort'] ?? 'newest';
$productType = $_GET['type'] ?? 'new';
$searchTerm = $_GET['search'] ?? '';

// 페이지네이션 설정
$itemsPerPage = 32; // 4x8 = 32개
$currentPage = max(1, intval($_GET['page'] ?? 1));
$offset = ($currentPage - 1) * $itemsPerPage;
$totalProducts = 0;
$totalPages = 0;

try {
    require_once __DIR__ . '/../../classes/Auth.php';
    require_once __DIR__ . '/../../classes/Database.php';
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();
    $db = Database::getInstance();
    $dbConnected = true;
    
    // Get categories from categories table (admin managed) with product counts
    $pdo = $db->getConnection();

    // 현재 제품 타입에 따른 추가 조건
    $typeCondition = '';
    switch ($productType) {
        case 'featured':
            $typeCondition = ' AND p.is_featured = 1';
            break;
        case 'new':
            $typeCondition = ' AND p.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)';
            break;
        case 'bestseller':
            $typeCondition = ' AND p.views >= 100';
            break;
        case 'sale':
            $typeCondition = ' AND p.discount_percentage > 0';
            break;
    }

    $stmt = $pdo->query("
        SELECT c.*, COUNT(p.id) as product_count
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'$typeCondition
        WHERE c.status = 'active'
        GROUP BY c.id
        ORDER BY c.sort_order, c.name
    ");
    $categories = $stmt->fetchAll();

    // Get products with pagination (with category filter, product type filter and sorting)
    $whereConditions = ["p.status = 'active'"];
    $params = [];

    if ($selectedCategory !== 'all' && is_numeric($selectedCategory)) {
        $whereConditions[] = 'p.category_id = ?';
        $params[] = $selectedCategory;
    }

    // 검색어 필터링
    if (!empty($searchTerm)) {
        $whereConditions[] = '(p.name LIKE ? OR p.description LIKE ? OR p.features LIKE ? OR p.detailed_description LIKE ?)';
        $searchParam = "%$searchTerm%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    // 제품 타입별 필터링
    switch ($productType) {
        case 'featured':
            $whereConditions[] = 'p.is_featured = 1';
            break;
        case 'new':
            $whereConditions[] = 'p.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)';
            break;
        case 'bestseller':
            $whereConditions[] = 'p.views >= 100';
            break;
        case 'sale':
            $whereConditions[] = 'p.discount_percentage > 0';
            break;
    }

    $whereClause = implode(' AND ', $whereConditions);

    // 총 상품 수 계산
    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) as total
         FROM products p
         WHERE $whereClause"
    );
    $countStmt->execute($params);
    $totalProducts = $countStmt->fetchColumn();
    $totalPages = ceil($totalProducts / $itemsPerPage);

    // 정렬 조건 설정
    $orderBy = 'p.is_featured DESC, p.created_at DESC'; // 기본값
    switch ($sortBy) {
        case 'newest':
            $orderBy = 'p.is_featured DESC, p.created_at DESC';
            break;
        case 'popular':
            $orderBy = 'p.is_featured DESC, p.views DESC, p.created_at DESC';
            break;
        case 'price-low':
            $orderBy = 'p.is_featured DESC, p.price ASC';
            break;
        case 'price-high':
            $orderBy = 'p.is_featured DESC, p.price DESC';
            break;
    }

    // 페이징된 상품 조회
    $stmt = $pdo->prepare(
        "SELECT p.*, c.name as category_name
         FROM products p
         LEFT JOIN categories c ON p.category_id = c.id
         WHERE $whereClause
         ORDER BY $orderBy
         LIMIT $itemsPerPage OFFSET $offset"
    );
    $stmt->execute($params);
    $featuredProducts = $stmt->fetchAll();
} catch (Exception $e) {
    // 데이터베이스 연결 실패시 빈 배열로 처리
    error_log("Database connection failed: " . $e->getMessage());
    $categories = [];
    $featuredProducts = [];
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>스토어 - 탄생</title>
    <link rel="stylesheet" href="../../assets/css/main.css?v=<?= date('YmdHis') ?>">
    <link rel="stylesheet" href="../../assets/css/store.css?v=<?= date('YmdHis') ?>">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main>
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <h1>스토어</h1>
                <p>스마트팜 제품 전문 쇼핑몰</p>
            </div>

            <div class="search-bar-wrapper">
                <div class="search-box">
                    <input type="text" placeholder="제품 검색..." id="productSearch" value="<?= htmlspecialchars($searchTerm) ?>">
                    <button type="button" onclick="searchProducts()" class="search-btn-icon" title="검색">🔍</button>
                    <?php if (!empty($searchTerm)): ?>
                    <button type="button" onclick="clearSearch()" class="btn-clear-search" title="초기화">✖</button>
                    <?php endif; ?>
                </div>
                <?php if (!empty($searchTerm)): ?>
                <div class="search-info" style="margin-top: 10px; color: #666; font-size: 14px;">
                    "<strong><?= htmlspecialchars($searchTerm) ?></strong>" 검색 결과: <?= number_format($totalProducts) ?>개 상품
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="container">
            <!-- Filter & Sort -->
            <section class="filter-section">
                <div class="filter-header">
                    <h2>제품 카테고리</h2>
                    <div class="filter-controls">
                        <select id="sortSelect" onchange="sortProducts()">
                            <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>최신 순</option>
                            <option value="popular" <?= $sortBy === 'popular' ? 'selected' : '' ?>>인기 순</option>
                            <option value="price-low" <?= $sortBy === 'price-low' ? 'selected' : '' ?>>낮은 가격 순</option>
                            <option value="price-high" <?= $sortBy === 'price-high' ? 'selected' : '' ?>>높은 가격 순</option>
                        </select>
                        <div class="view-toggle">
                            <button onclick="toggleView('grid')" class="view-btn active" id="gridView">⊞</button>
                            <button onclick="toggleView('list')" class="view-btn" id="listView">☰</button>
                        </div>
                    </div>
                </div>
                
                <!-- Categories -->
                <div class="categories-container">
                    <div class="categories-list">
                        <button onclick="filterByCategory('all')"
                                class="category-btn <?= $selectedCategory === 'all' ? 'active' : '' ?>">
                            <div class="category-text">
                                <h3>전체</h3>
                                <span class="category-count">(<?= array_sum(array_column($categories, 'product_count')) ?>개)</span>
                            </div>
                        </button>
                        <?php foreach ($categories as $category): ?>
                        <button onclick="filterByCategory('<?= $category['id'] ?>')"
                                class="category-btn <?= $selectedCategory == $category['id'] ? 'active' : '' ?>">
                            <div class="category-text">
                                <h3><?= htmlspecialchars($category['name']) ?></h3>
                                <span class="category-count">(<?= $category['product_count'] ?? 0 ?>개)</span>
                            </div>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <!-- Featured Products -->
            <section class="products-section">
                <div class="section-header">
                    <h2>
                        <?php
                        $sectionTitles = [
                            'featured' => '✨ 추천 제품',
                            'new' => '🆕 신상품',
                            'bestseller' => '🏆 베스트셀러',
                            'sale' => '🔥 할인 상품'
                        ];
                        echo $sectionTitles[$productType] ?? '🆕 신상품';
                        ?>
                    </h2>
                    <div class="section-nav">
                        <button class="nav-btn <?= $productType === 'featured' ? 'active' : '' ?>" onclick="showProducts('featured')">추천</button>
                        <button class="nav-btn <?= $productType === 'new' ? 'active' : '' ?>" onclick="showProducts('new')">신상품</button>
                        <button class="nav-btn <?= $productType === 'bestseller' ? 'active' : '' ?>" onclick="showProducts('bestseller')">베스트</button>
                        <button class="nav-btn <?= $productType === 'sale' ? 'active' : '' ?>" onclick="showProducts('sale')">할인</button>
                    </div>
                </div>
                
                <?php if (empty($featuredProducts)): ?>
                    <div class="no-products">
                        <div class="no-products-icon">📦</div>
                        <h3>등록된 제품이 없습니다</h3>
                        <p>곧 다양한 제품을 만나보실 수 있습니다</p>
                        <button onclick="location.href='/pages/support/contact.php'" class="btn btn-primary">
                            제품 문의하기
                        </button>
                    </div>
                <?php else: ?>
                    <div class="products-container" id="productsContainer">
                        <div class="products-grid" id="productsGrid">
                            <?php foreach ($featuredProducts as $product): ?>
                            <div class="product-card" onclick="location.href='/pages/store/product.php?id=<?= $product['id'] ?>'" style="cursor: pointer;">
                                <div class="product-image">
                                    <img src="<?= !empty($product['image_url']) ? htmlspecialchars($product['image_url']) : '/assets/images/products/placeholder.jpg' ?>" 
                                         alt="<?= htmlspecialchars($product['name']) ?>" 
                                         loading="lazy"
                                         onerror="this.src='/assets/images/products/placeholder.jpg'">
                                    <?php if (isset($product['discount_percentage']) && $product['discount_percentage'] > 0): ?>
                                    <div class="discount-badge">
                                        <?= $product['discount_percentage'] ?>%
                                    </div>
                                    <?php endif; ?>
                                    <div class="product-overlay">
                                        <button onclick="event.stopPropagation(); toggleWishlist(<?= $product['id'] ?>)" class="wish-btn">♡</button>
                                        <button onclick="event.stopPropagation(); addToCart(<?= $product['id'] ?>)" class="cart-btn">🛒</button>
                                    </div>
                                </div>
                                <div class="product-info">
                                    <div class="product-title">
                                        <?= htmlspecialchars($product['name']) ?>
                                    </div>

                                    <div class="product-price">
                                        <?php
                                        $basePrice = $product['price'];
                                        $discountedPrice = isset($product['discount_percentage']) && $product['discount_percentage'] > 0
                                                          ? $basePrice * (1 - $product['discount_percentage']/100)
                                                          : $basePrice;
                                        $shippingCost = $product['shipping_cost'] ?? 0;
                                        $totalPrice = $discountedPrice + $shippingCost;
                                        ?>

                                        <?php if (isset($product['discount_percentage']) && $product['discount_percentage'] > 0): ?>
                                            <span class="discount-rate"><?= $product['discount_percentage'] ?>%</span>
                                            <span class="price"><?= number_format($discountedPrice) ?>원</span>
                                            <span class="original-price"><?= number_format($basePrice) ?>원</span>
                                        <?php else: ?>
                                            <span class="price"><?= number_format($basePrice) ?>원</span>
                                        <?php endif; ?>

                                        <?php if ($shippingCost > 0): ?>
                                            <div class="shipping-info">
                                                <span class="shipping-cost">배송비 +<?= number_format($shippingCost) ?>원</span>
                                                <span class="total-price">총 <?= number_format($totalPrice) ?>원</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="product-rating">
                                        <div class="rating-stars">
                                            <span class="stars">★★★★★</span>
                                            <span class="rating-score"><?= $product['rating_score'] ?? 4.5 ?></span>
                                        </div>
                                        <div class="rating-reviews">
                                            <span class="rating-count">리뷰 <?= number_format($product['review_count'] ?? 0) ?>개</span>
                                        </div>
                                    </div>

                                    <div class="product-features">
                                        <span class="delivery-info"><?= htmlspecialchars($product['delivery_info'] ?? '무료배송') ?></span>
                                        <?php if (isset($product['discount_percentage']) && $product['discount_percentage'] > 0): ?>
                                        <span class="discount-label">할인중</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            총 <?= number_format($totalProducts) ?>개 상품 |
                            <?= $currentPage ?> / <?= $totalPages ?> 페이지
                        </div>
                        <div class="pagination-nav">
                            <?php
                            // URL 매개변수 구성
                            $baseParams = [];
                            if ($selectedCategory !== 'all') $baseParams['category'] = $selectedCategory;
                            if ($sortBy !== 'newest') $baseParams['sort'] = $sortBy;
                            if ($productType !== 'new') $baseParams['type'] = $productType;
                            if (!empty($searchTerm)) $baseParams['search'] = $searchTerm;

                            // 이전 페이지
                            if ($currentPage > 1):
                                $prevParams = $baseParams;
                                $prevParams['page'] = $currentPage - 1;
                                $prevUrl = '?' . http_build_query($prevParams);
                            ?>
                                <a href="<?= $prevUrl ?>" class="pagination-btn prev">이전</a>
                            <?php endif; ?>

                            <div class="pagination-pages">
                                <?php
                                $startPage = max(1, $currentPage - 2);
                                $endPage = min($totalPages, $currentPage + 2);

                                for ($i = $startPage; $i <= $endPage; $i++):
                                    $pageParams = $baseParams;
                                    $pageParams['page'] = $i;
                                    $pageUrl = '?' . http_build_query($pageParams);
                                    $isActive = ($i == $currentPage) ? 'active' : '';
                                ?>
                                    <a href="<?= $pageUrl ?>" class="pagination-page <?= $isActive ?>"><?= $i ?></a>
                                <?php endfor; ?>
                            </div>

                            <?php
                            // 다음 페이지
                            if ($currentPage < $totalPages):
                                $nextParams = $baseParams;
                                $nextParams['page'] = $currentPage + 1;
                                $nextUrl = '?' . http_build_query($nextParams);
                            ?>
                                <a href="<?= $nextUrl ?>" class="pagination-btn next">다음</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="results-info">
                        <p>총 <?= count($featuredProducts) ?>개의 상품이 있습니다</p>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

            <!-- Benefits Section -->
            <section class="benefits-section">
                <h2>탄생 스토어만의 특별함</h2>
                <div class="benefits-grid">
                    <div class="benefit-card">
                        <div class="benefit-icon">🚚</div>
                        <h3>무료 배송</h3>
                        <p>5만원 이상 주문시<br>전국 무료배송</p>
                    </div>
                    <div class="benefit-card">
                        <div class="benefit-icon">🔄</div>
                        <h3>쉬운 교환/반품</h3>
                        <p>30일 이내<br>무료 교환/반품</p>
                    </div>
                    <div class="benefit-card">
                        <div class="benefit-icon">👨‍💼</div>
                        <h3>전문 상담</h3>
                        <p>농업 전문가가<br>직접 상담해드립니다</p>
                    </div>
                    <div class="benefit-card">
                        <div class="benefit-icon">🏆</div>
                        <h3>품질 보증</h3>
                        <p>엄격한 품질관리로<br>최고 품질을 보장</p>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <?php include '../../includes/footer.php'; ?>

    <style>
    .cart-link {
        display: inline-flex;
        align-items: center;
        margin-left: 20px;
        text-decoration: none;
        background: #ff6b35;
        color: white;
        padding: 8px 15px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: bold;
        transition: all 0.3s ease;
    }
    .cart-link:hover {
        background: #e55a2b;
        transform: scale(1.05);
    }
    .cart-count {
        background: rgba(255,255,255,0.3);
        border-radius: 50%;
        min-width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-left: 5px;
        font-size: 12px;
        animation: pulse 0.5s ease-in-out;
    }
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.2); }
        100% { transform: scale(1); }
    }
    .cart-btn {
        background: #28a745;
        color: white;
        border: none;
        padding: 8px 12px;
        border-radius: 20px;
        font-size: 14px;
        cursor: pointer;
        margin-left: 8px;
        transition: all 0.3s ease;
    }
    .cart-btn:hover {
        background: #218838;
        transform: scale(1.05);
    }
    </style>

    <script src="/assets/js/main.js"></script>
    <script src="/assets/js/store.js"></script>

    <script>
    // 장바구니 카운트 업데이트 함수
    function updateCartCount() {
        fetch('/api/cart.php?action=count')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const count = data.data?.count || data.count || 0;
                const cartCountElement = document.getElementById('cartCount');
                if (cartCountElement) {
                    cartCountElement.textContent = count;
                    // 카운트 표시/숨김 처리
                    if (count > 0) {
                        cartCountElement.style.display = 'flex';
                    } else {
                        cartCountElement.style.display = 'none';
                    }
                    // 애니메이션 트리거
                    cartCountElement.style.animation = 'none';
                    setTimeout(() => {
                        cartCountElement.style.animation = 'pulse 0.5s ease-in-out';
                    }, 10);
                }
            }
        })
        .catch(error => console.error('Cart count update error:', error));
    }

    // 장바구니에 상품 추가
    async function addToCart(productId) {
        console.log('=== 장바구니 추가 시작 ===');
        console.log('상품 ID:', productId);

        try {
            const response = await fetch('/api/cart.php?action=add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    product_id: productId,
                    quantity: 1
                })
            });

            // HTTP 400도 JSON으로 파싱 (로그인 필요 등의 경우)
            const data = await response.json();
            console.log('응답 데이터:', data);

            if (data.success) {
                // 성공 메시지 표시
                alert(`장바구니에 추가되었습니다!\n총 ${data.data.item_count}개 상품 (${data.data.final_total.toLocaleString()}원)`);

                // 장바구니 카운트 업데이트
                updateCartCount();
            } else {
                // 로그인이 필요한 경우 팝업 표시
                if (data.require_login) {
                    if (confirm(data.message + '\n로그인 페이지로 이동하시겠습니까?')) {
                        // 현재 페이지를 기억하고 로그인 페이지로 이동
                        window.location.href = '/pages/auth/login.php?redirect=' + encodeURIComponent(window.location.pathname);
                    }
                } else {
                    alert('오류: ' + data.message);
                }
            }

        } catch (error) {
            console.error('장바구니 추가 오류:', error);
            alert('장바구니 추가 실패: ' + error.message);
        }
    }

    // 페이지 로드 시 장바구니 카운트 로드
    document.addEventListener('DOMContentLoaded', function() {
        updateCartCount();

        // 전역 함수로 등록
        window.updateCartCount = updateCartCount;
        window.addToCart = addToCart;
    });
    </script>
</body>
</html>
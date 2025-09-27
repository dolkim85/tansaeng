<?php
// 데이터베이스 연결을 선택적으로 처리
$currentUser = null;
$dbConnected = false;
$categories = [];
$featuredProducts = [];
$selectedCategory = $_GET['category'] ?? 'all';

try {
    require_once __DIR__ . '/../../classes/Auth.php';
    require_once __DIR__ . '/../../classes/Database.php';
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();
    $db = Database::getInstance();
    $dbConnected = true;
    
    // Get categories from categories table (admin managed) with product counts
    $pdo = $db->getConnection();
    $stmt = $pdo->query("
        SELECT c.*, COUNT(p.id) as product_count
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
        WHERE c.status = 'active'
        GROUP BY c.id
        ORDER BY c.sort_order, c.name
    ");
    $categories = $stmt->fetchAll();

    // Get all products (with category filter), prioritizing featured products
    $categoryWhere = '';
    $params = [];

    if ($selectedCategory !== 'all' && is_numeric($selectedCategory)) {
        $categoryWhere = ' AND p.category_id = ?';
        $params[] = $selectedCategory;
    }

    $stmt = $pdo->prepare(
        "SELECT p.*, c.name as category_name
         FROM products p
         LEFT JOIN categories c ON p.category_id = c.id
         WHERE p.status = 'active'$categoryWhere
         ORDER BY p.is_featured DESC, p.created_at DESC"
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
    <link rel="stylesheet" href="/assets/css/main.css?v=<?= date('YmdHis') ?>">
    <link rel="stylesheet" href="/assets/css/store.css?v=<?= date('YmdHis') ?>">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main >
        <!-- Hero Banner -->
        <section class="store-hero">
            <div class="container">
                <div class="hero-content">
                    <div class="hero-text">
                        <h1>🛒 스토어</h1>
                        <p class="hero-subtitle">스마트팜 제품 전문</p>
                    </div>
                    <div class="hero-search">
                        <div class="search-box">
                            <input type="text" placeholder="제품 검색..." id="productSearch">
                            <button type="button" onclick="searchProducts()">🔍</button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="container">
            <!-- Filter & Sort -->
            <section class="filter-section">
                <div class="filter-header">
                    <h2>제품 카테고리</h2>
                    <div class="filter-controls">
                        <select id="sortSelect" onchange="sortProducts()">
                            <option value="newest">최신 순</option>
                            <option value="popular">인기 순</option>
                            <option value="price-low">낮은 가격 순</option>
                            <option value="price-high">높은 가격 순</option>
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
                                <span class="category-count">(모든 제품)</span>
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
                    <h2>✨ 추천 제품</h2>
                    <div class="section-nav">
                        <button class="nav-btn" onclick="showProducts('featured')">추천</button>
                        <button class="nav-btn" onclick="showProducts('new')">신상품</button>
                        <button class="nav-btn" onclick="showProducts('bestseller')">베스트</button>
                        <button class="nav-btn" onclick="showProducts('sale')">할인</button>
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
                                        <button onclick="event.stopPropagation(); quickView(<?= $product['id'] ?>)" class="quick-btn">미리보기</button>
                                        <button onclick="event.stopPropagation(); toggleWishlist(<?= $product['id'] ?>)" class="wish-btn">♡</button>
                                    </div>
                                </div>
                                <div class="product-info">
                                    <div class="product-title">
                                        <?= htmlspecialchars($product['name']) ?>
                                    </div>

                                    <div class="product-price">
                                        <?php if (isset($product['discount_percentage']) && $product['discount_percentage'] > 0): ?>
                                            <span class="discount-rate"><?= $product['discount_percentage'] ?>%</span>
                                            <span class="price"><?= number_format($product['price'] * (1 - $product['discount_percentage']/100)) ?>원</span>
                                            <span class="original-price"><?= number_format($product['price']) ?>원</span>
                                        <?php else: ?>
                                            <span class="price"><?= number_format($product['price']) ?>원</span>
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
                    <div class="pagination-container">
                        <a href="/pages/store/products.php" class="btn btn-outline">더 많은 제품 보기</a>
                    </div>
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
    <script src="/assets/js/main.js"></script>
    <script src="/assets/js/store.js"></script>
</body>
</html>
<?php
// 데이터베이스 연결을 선택적으로 처리
$currentUser = null;
$dbConnected = false;
$categories = [];
$featuredProducts = [];

try {
    require_once __DIR__ . '/../../classes/Auth.php';
    require_once __DIR__ . '/../../classes/Database.php';
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();
    $db = Database::getInstance();
    $dbConnected = true;
    
    // Get category filter from URL
    $selectedCategory = intval($_GET['category'] ?? 0);

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

    // Get products based on category filter
    if ($selectedCategory) {
        // Show products from selected category
        $stmt = $pdo->prepare(
            "SELECT p.*, c.name as category_name
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.category_id = ? AND p.status = 'active'
             ORDER BY p.created_at DESC LIMIT 20"
        );
        $stmt->execute([$selectedCategory]);
        $featuredProducts = $stmt->fetchAll();
    } else {
        // Get featured products from admin managed products table
        $stmt = $pdo->query(
            "SELECT p.*, c.name as category_name
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.featured = 1 AND p.status = 'active'
             ORDER BY p.created_at DESC LIMIT 8"
        );
        $featuredProducts = $stmt->fetchAll();

        // If no featured products, show all active products
        if (empty($featuredProducts)) {
            $stmt = $pdo->query(
                "SELECT p.*, c.name as category_name
                 FROM products p
                 LEFT JOIN categories c ON p.category_id = c.id
                 WHERE p.status = 'active'
                 ORDER BY p.created_at DESC LIMIT 8"
            );
            $featuredProducts = $stmt->fetchAll();
        }
    }
} catch (Exception $e) {
    // 데이터베이스 연결 실패시 샘플 데이터 사용
    error_log("Database connection failed: " . $e->getMessage());
    
    // 샘플 카테고리 데이터 (관리자에서 관리하는 카테고리와 동일하게)
    $categories = [
        ['id' => 1, 'name' => '코코피트 배지', 'description' => '천연 코코넛 섬유 배지', 'product_count' => 1],
        ['id' => 2, 'name' => '펄라이트 배지', 'description' => '우수한 배수성 무기질 배지', 'product_count' => 1],
        ['id' => 3, 'name' => '혼합 배지', 'description' => '다양한 재료 혼합 배지', 'product_count' => 0],
        ['id' => 4, 'name' => '재배용품', 'description' => 'IoT 센서 및 모니터링 장비', 'product_count' => 1],
        ['id' => 5, 'name' => '씨앗/종자', 'description' => '고품질 씨앗과 종자', 'product_count' => 1],
        ['id' => 6, 'name' => '양액/비료', 'description' => '작물별 맞춤형 양액', 'product_count' => 1],
        ['id' => 7, 'name' => 'LED 조명', 'description' => '식물 성장용 LED 조명', 'product_count' => 1]
    ];
    
    // 샘플 제품 데이터 (관리자에서 관리하는 제품 구조와 동일하게)
    $featuredProducts = [
        [
            'id' => 1,
            'name' => '토마토 씨앗 (방울토마토)',
            'category_name' => '씨앗/종자',
            'description' => '고품질 방울토마토 씨앗으로 높은 발아율을 자랑합니다',
            'price' => 5000,
            'image_url' => '/assets/images/products/placeholder.jpg',
            'featured' => 1
        ],
        [
            'id' => 2,
            'name' => '코코피트 배지 (10L)',
            'category_name' => '코코피트 배지',
            'description' => '천연 코코넛 섬유로 만든 친환경 배지',
            'price' => 15000,
            'image_url' => '/assets/images/products/placeholder.jpg',
            'featured' => 1
        ],
        [
            'id' => 3,
            'name' => '토마토 전용 양액 (1L)',
            'category_name' => '양액/비료',
            'description' => '토마토 전용 맞춤형 양액으로 최적의 영양소 비율 제공',
            'price' => 35000,
            'image_url' => '/assets/images/products/placeholder.jpg',
            'featured' => 1
        ],
        [
            'id' => 4,
            'name' => 'IoT 환경 센서 키트',
            'category_name' => '재배용품',
            'description' => '온습도, pH, EC 센서 통합 키트',
            'price' => 150000,
            'image_url' => '/assets/images/products/placeholder.jpg',
            'featured' => 1
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>스토어 - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/store.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main class="store-main">
        <!-- Hero Banner -->
        <section class="store-hero">
            <div class="container">
                <div class="hero-content">
                    <div class="hero-text">
                        <h1>🛒 탄생 스토어</h1>
                        <p>최고 품질의 스마트팜 제품을 한 곳에서</p>
                        <p class="hero-description">친환경 배지부터 최신 IoT 장비까지, 스마트팜에 필요한 모든 제품을 만나보세요</p>
                        <div class="hero-badges">
                            <span class="badge">✅ 무료배송</span>
                            <span class="badge">✅ 품질보증</span>
                            <span class="badge">✅ 전문상담</span>
                        </div>
                    </div>
                    <div class="hero-search">
                        <div class="search-box">
                            <input type="text" placeholder="원하는 제품을 검색해보세요..." id="productSearch">
                            <button type="button" onclick="searchProducts()">🔍</button>
                        </div>
                        <div class="popular-searches">
                            <span>인기 검색어:</span>
                            <a href="#" onclick="searchKeyword('배지')">배지</a>
                            <a href="#" onclick="searchKeyword('양액')">양액</a>
                            <a href="#" onclick="searchKeyword('센서')">센서</a>
                            <a href="#" onclick="searchKeyword('LED')">LED조명</a>
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
                    <div class="categories-grid">
                        <?php foreach ($categories as $category): ?>
                        <div class="category-card" onclick="location.href='/pages/store/?category=<?= $category['id'] ?>'">
                            <div class="category-icon">
                                <?php
                                $icons = ['🌱', '🚿', '💧', '🛠️'];
                                echo $icons[($category['id'] - 1) % count($icons)];
                                ?>
                            </div>
                            <div class="category-info">
                                <h3><?= htmlspecialchars($category['name']) ?></h3>
                                <p><?= htmlspecialchars($category['description'] ?? '') ?></p>
                                <span class="category-count"><?= $category['product_count'] ?? 0 ?>개 제품</span>
                            </div>
                            <div class="category-arrow">→</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <!-- Products -->
            <section class="products-section">
                <div class="section-header">
                    <h2>
                        <?php if ($selectedCategory): ?>
                            <?php
                            $selectedCategoryName = '';
                            foreach ($categories as $cat) {
                                if ($cat['id'] == $selectedCategory) {
                                    $selectedCategoryName = $cat['name'];
                                    break;
                                }
                            }
                            ?>
                            🔍 <?= htmlspecialchars($selectedCategoryName) ?> 제품
                        <?php else: ?>
                            ✨ 추천 제품
                        <?php endif; ?>
                    </h2>
                    <div class="section-nav">
                        <?php if ($selectedCategory): ?>
                            <a href="/pages/store/" class="nav-btn">전체 보기</a>
                        <?php else: ?>
                            <button class="nav-btn" onclick="showProducts('featured')">추천</button>
                            <button class="nav-btn" onclick="showProducts('new')">신상품</button>
                            <button class="nav-btn" onclick="showProducts('bestseller')">베스트</button>
                            <button class="nav-btn" onclick="showProducts('sale')">할인</button>
                        <?php endif; ?>
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
                                    <div class="product-header">
                                        <span class="product-category"><?= htmlspecialchars($product['category_name']) ?></span>
                                        <div class="product-rating">
                                            <span class="stars">⭐⭐⭐⭐⭐</span>
                                            <span class="rating-count">(24)</span>
                                        </div>
                                    </div>
                                    <h3 class="product-title">
                                        <?= htmlspecialchars($product['name']) ?>
                                    </h3>
                                    <p class="product-description"><?= htmlspecialchars(mb_substr($product['description'] ?? '', 0, 80) . '...') ?></p>
                                    
                                    <div class="product-price">
                                        <span class="price"><?= number_format($product['price']) ?>원</span>
                                    </div>
                                    
                                    <div class="product-features">
                                        <span class="feature">🚚 무료배송</span>
                                        <span class="feature">🔄 교환가능</span>
                                    </div>
                                    
                                    <div class="product-actions">
                                        <?php if ($currentUser): ?>
                                        <button onclick="event.stopPropagation(); addToCart(<?= $product['id'] ?>)" class="btn btn-primary btn-block">
                                            장바구니 담기
                                        </button>
                                        <button onclick="event.stopPropagation(); buyNow(<?= $product['id'] ?>)" class="btn btn-outline btn-block">
                                            바로 구매
                                        </button>
                                        <?php else: ?>
                                        <button onclick="event.stopPropagation(); alert('로그인 후 이용 가능합니다'); location.href='/pages/auth/login.php'" class="btn btn-primary btn-block">
                                            로그인 후 구매
                                        </button>
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
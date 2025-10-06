<?php
// 모든 상품을 표시하는 페이지 (관리자에서 등록한 상품들)
$currentUser = null;
$dbConnected = false;
$categories = [];
$products = [];
$selectedCategory = intval($_GET['category'] ?? 0);
$search = trim($_GET['search'] ?? '');
$sortBy = $_GET['sort'] ?? 'newest';

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
    
    $pdo = $db->getConnection();
    
    // Get all categories with search-filtered product counts
    $searchCondition = '';
    $searchParams = [];

    if (!empty($search)) {
        $searchCondition = ' AND (p.name LIKE ? OR p.description LIKE ?)';
        $searchParams = ["%$search%", "%$search%"];
    }

    $stmt = $pdo->prepare("
        SELECT c.*, COUNT(p.id) as product_count
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'$searchCondition
        WHERE c.status = 'active'
        GROUP BY c.id
        ORDER BY c.sort_order, c.name
    ");
    $stmt->execute($searchParams);
    $categories = $stmt->fetchAll();
    
    // Build product query
    $whereConditions = ["p.status = 'active'"];
    $params = [];
    
    if ($selectedCategory > 0) {
        $whereConditions[] = "p.category_id = ?";
        $params[] = $selectedCategory;
    }
    
    if (!empty($search)) {
        $whereConditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // 전체 상품 수 계산
    $countSql = "SELECT COUNT(*) as total
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE $whereClause";
    
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalProducts = $countStmt->fetchColumn();
    $totalPages = ceil($totalProducts / $itemsPerPage);

    // 정렬 조건 설정
    $orderBy = 'p.created_at DESC'; // 기본값
    switch ($sortBy) {
        case 'newest':
            $orderBy = 'p.created_at DESC';
            break;
        case 'popular':
            $orderBy = 'p.views DESC, p.created_at DESC';
            break;
        case 'price-low':
            $orderBy = 'p.price ASC';
            break;
        case 'price-high':
            $orderBy = 'p.price DESC';
            break;
    }

    // Get products with pagination
    $sql = "SELECT p.*, c.name as category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE $whereClause
            ORDER BY $orderBy
            LIMIT $itemsPerPage OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>전체 상품 - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/store.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main >
        <div class="container">
            <div class="page-header">
                <h1>전체 상품
                    <a href="cart.php" class="cart-link">
                        <span class="cart-icon">🛒</span>
                        <span class="cart-count" id="cartCount">0</span>
                    </a>
                </h1>
                <p>관리자에서 등록한 모든 상품을 확인하세요</p>
            </div>
            
            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-controls">
                    <div class="category-filter">
                        <label>카테고리:</label>
                        <select onchange="filterByCategory(this.value)">
                            <option value="0">전체 카테고리</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>" <?= $selectedCategory == $category['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['name']) ?> (<?= $category['product_count'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="sort-filter">
                        <label>정렬:</label>
                        <select id="sortSelect" onchange="sortProducts()">
                            <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>최신 순</option>
                            <option value="popular" <?= $sortBy === 'popular' ? 'selected' : '' ?>>인기 순</option>
                            <option value="price-low" <?= $sortBy === 'price-low' ? 'selected' : '' ?>>낮은 가격 순</option>
                            <option value="price-high" <?= $sortBy === 'price-high' ? 'selected' : '' ?>>높은 가격 순</option>
                        </select>
                    </div>

                    <div class="search-filter">
                        <form method="get">
                            <input type="hidden" name="category" value="<?= $selectedCategory ?>">
                            <input type="hidden" name="sort" value="<?= $sortBy ?>">
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="상품명 또는 설명 검색...">
                            <button type="submit">검색</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Products Grid -->
            <?php if (empty($products)): ?>
                <div class="no-products">
                    <div class="no-products-icon">📦</div>
                    <h3>조건에 맞는 제품이 없습니다</h3>
                    <p>다른 조건으로 검색해보세요</p>
                </div>
            <?php else: ?>
                <div class="products-grid">
                    <?php foreach ($products as $product): ?>
                    <div class="product-card" onclick="location.href='/pages/store/product_detail.php?id=<?= $product['id'] ?>'" style="cursor: pointer;">
                        <div class="product-image">
                            <?php
                            $imageSrc = !empty($product['image_url'])
                                ? $product['image_url'] . '?v=' . strtotime($product['updated_at'] ?? 'now')
                                : '/assets/images/products/placeholder.jpg';
                            ?>
                            <img src="<?= htmlspecialchars($imageSrc) ?>" 
                                 alt="<?= htmlspecialchars($product['name']) ?>" 
                                 loading="lazy"
                                 onerror="this.src='/assets/images/products/placeholder.jpg'">
                        </div>
                        <div class="product-info">
                            <div class="product-header">
                                <span class="product-category"><?= htmlspecialchars($product['category_name'] ?? '미분류') ?></span>
                            </div>
                            <h3 class="product-title">
                                <?= htmlspecialchars($product['name']) ?>
                            </h3>
                            <p class="product-description"><?= htmlspecialchars(mb_substr($product['description'] ?? '', 0, 80) . '...') ?></p>
                            
                            <div class="product-price">
                                <span class="price"><?= number_format($product['price']) ?>원</span>
                            </div>
                            
                            <div class="product-meta">
                                <span class="stock">재고: <?= $product['stock'] ?? 0 ?>개</span>
                                <?php if (!empty($product['weight'])): ?>
                                <span class="weight">무게: <?= $product['weight'] ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="product-actions">
                                <?php if ($currentUser): ?>
                                <button onclick="event.stopPropagation(); addToCart(<?= $product['id'] ?>)" class="btn btn-primary btn-block">
                                    장바구니 담기
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
            <?php endif; ?>
            
            <!-- 페이지네이션 정보 -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination-container">
                <div class="pagination-info">
                    총 <?= number_format($totalProducts) ?>개 상품 | 
                    <?= $currentPage ?> / <?= $totalPages ?> 페이지
                </div>
            </div>
            <?php else: ?>
            <div class="results-info">
                <p>총 <?= count($products) ?>개의 상품이 있습니다</p>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- 고정 페이지네이션 -->
    <?php if ($totalPages > 1): ?>
    <div class="fixed-pagination" id="fixedPagination">
        <div class="pagination-nav">
            <?php
            // URL 매개변수 구성
            $baseParams = [];
            if ($selectedCategory > 0) $baseParams['category'] = $selectedCategory;
            if (!empty($search)) $baseParams['search'] = $search;
            if ($sortBy !== 'newest') $baseParams['sort'] = $sortBy;
            
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
    <?php endif; ?>

    <?php include '../../includes/footer.php'; ?>
    <script src="/assets/js/main.js"></script>
    <script>
        function filterByCategory(categoryId) {
            try {
                const url = new URL(window.location);
                if (categoryId > 0) {
                    url.searchParams.set('category', categoryId);
                } else {
                    url.searchParams.delete('category');
                }
                url.searchParams.delete('page'); // 카테고리 변경시 첫 페이지로

                // URL 변경 및 페이지 리로드
                window.location.href = url.toString();
            } catch (error) {
                // 구형 브라우저 호환성
                const params = new URLSearchParams(window.location.search);
                if (categoryId > 0) {
                    params.set('category', categoryId);
                } else {
                    params.delete('category');
                }
                params.delete('page');

                const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
                window.location.href = newUrl;
            }
        }

        function sortProducts() {
            const sortValue = document.getElementById('sortSelect').value;

            try {
                const url = new URL(window.location);
                if (sortValue !== 'newest') {
                    url.searchParams.set('sort', sortValue);
                } else {
                    url.searchParams.delete('sort');
                }
                url.searchParams.delete('page'); // 정렬 변경시 첫 페이지로

                window.location.href = url.toString();
            } catch (error) {
                // 구형 브라우저 호환성
                const params = new URLSearchParams(window.location.search);
                if (sortValue !== 'newest') {
                    params.set('sort', sortValue);
                } else {
                    params.delete('sort');
                }
                params.delete('page');

                const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
                window.location.href = newUrl;
            }
        }
        
        function addToCart(productId) {
            console.log('=== 장바구니 추가 시작 ===');
            console.log('상품 ID:', productId);
            console.log('현재 URL:', window.location.href);
            console.log('API URL:', '/api/cart.php?action=add');

            // 기본 수량 설정 (상품 목록에서는 1개)
            const quantity = 1;

            // 버튼 찾기 및 로딩 표시
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

                    // 성공 메시지 표시
                    const message = `상품이 장바구니에 추가되었습니다!\n총 ${data.cart.item_count}개 상품`;
                    alert(message);

                    // 장바구니 카운트 즉시 업데이트
                    const cartCountElement = document.getElementById('cartCount');
                    if (cartCountElement && data.cart && data.cart.item_count) {
                        cartCountElement.textContent = data.cart.item_count;
                        cartCountElement.style.animation = 'pulse 0.5s ease-in-out';
                    }

                    // 추가 안전장치: 서버에서 최신 카운트 다시 가져오기
                    setTimeout(() => {
                        if (typeof window.updateCartCount === 'function') {
                            window.updateCartCount();
                        }
                    }, 500);

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

                // 실제 오류 메시지 표시
                alert('장바구니 추가 실패: ' + error.message);
            });
        }
        
        // 고정 페이지네이션 스크롤 이벤트
        window.addEventListener('scroll', function() {
            const fixedPagination = document.getElementById('fixedPagination');
            if (fixedPagination) {
                const scrollPosition = window.pageYOffset;
                const windowHeight = window.innerHeight;
                const documentHeight = document.documentElement.scrollHeight;
                
                // 스크롤이 200px 이상이고 페이지 끝에서 100px 이상 떨어져 있을 때 표시
                if (scrollPosition > 200 && (documentHeight - scrollPosition - windowHeight) > 100) {
                    fixedPagination.style.display = 'block';
                    fixedPagination.style.opacity = '1';
                } else {
                    fixedPagination.style.opacity = '0';
                    setTimeout(() => {
                        if (fixedPagination.style.opacity === '0') {
                            fixedPagination.style.display = 'none';
                        }
                    }, 300);
                }
            }
        });
    </script>

    <script>
    // 장바구니 카운트 업데이트 함수 추가
    function updateCartCount() {
        fetch('/api/cart.php?action=count')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const cartCountElement = document.getElementById('cartCount');
                if (cartCountElement) {
                    cartCountElement.textContent = data.count || 0;
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

    // 페이지 로드 시 장바구니 카운트 로드
    document.addEventListener('DOMContentLoaded', function() {
        updateCartCount();
        // 전역 함수로 등록
        window.updateCartCount = updateCartCount;
    });
    </script>

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
        .product-title a {
            color: inherit;
            text-decoration: none;
        }
        
        .product-title a:hover {
            color: #007bff;
        }
        
        .product-image a {
            display: block;
            transition: transform 0.2s ease;
        }
        
        .product-image a:hover {
            transform: scale(1.02);
        }
        
        /* 4x10 그리드 레이아웃 */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }
        
        /* 반응형 그리드 */
        @media (max-width: 992px) {
            .products-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .products-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* 필터 섹션 스타일 */
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .filter-controls {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .category-filter,
        .sort-filter,
        .search-filter {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-controls label {
            font-weight: 500;
            color: #333;
            white-space: nowrap;
        }

        .filter-controls select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            background: white;
        }

        .search-filter input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            min-width: 200px;
        }

        .search-filter button {
            padding: 8px 16px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .search-filter button:hover {
            background: #0056b3;
        }

        @media (max-width: 768px) {
            .filter-controls {
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }

            .category-filter,
            .sort-filter,
            .search-filter {
                justify-content: space-between;
            }

            .search-filter input {
                min-width: auto;
                flex: 1;
            }
        }

        /* 페이지네이션 컨테이너 */
        .pagination-container {
            text-align: center;
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .pagination-info {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        /* 고정 페이지네이션 */
        .fixed-pagination {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(255, 255, 255, 0.95);
            border-radius: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            backdrop-filter: blur(10px);
            padding: 10px 20px;
            z-index: 1000;
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .pagination-nav {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .pagination-btn,
        .pagination-page {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            padding: 8px 12px;
            text-decoration: none;
            color: #666;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        
        .pagination-btn {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
        }
        
        .pagination-btn:hover {
            background: #e9ecef;
            color: #495057;
            transform: translateY(-1px);
        }
        
        .pagination-page {
            background: transparent;
        }
        
        .pagination-page:hover {
            background: #f8f9fa;
            color: #495057;
        }
        
        .pagination-page.active {
            background: #007bff;
            color: white;
            font-weight: 600;
        }
        
        .pagination-page.active:hover {
            background: #0056b3;
            color: white;
        }
        
        .pagination-pages {
            display: flex;
            align-items: center;
            gap: 2px;
            margin: 0 10px;
        }
        
        /* 상품 카드 높이 통일 */
        .product-card {
            display: flex;
            flex-direction: column;
            height: 100%;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .product-image {
            flex: 0 0 200px;
            overflow: hidden;
            border-radius: 8px 8px 0 0;
        }
        
        .product-image img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .product-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 15px;
        }
        
        .product-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
            flex: 1;
        }
        
        .product-price {
            font-size: 18px;
            font-weight: 700;
            color: #007bff;
            margin-top: auto;
        }
        
        .product-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
</body>
</html>
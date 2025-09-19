<?php
// 모든 상품을 표시하는 페이지 (관리자에서 등록한 상품들)
$currentUser = null;
$dbConnected = false;
$categories = [];
$products = [];
$selectedCategory = intval($_GET['category'] ?? 0);
$search = trim($_GET['search'] ?? '');

// 페이지네이션 설정
$itemsPerPage = 40; // 4x10 = 40개
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
    
    // Get all categories
    $stmt = $pdo->query("
        SELECT c.*, COUNT(p.id) as product_count
        FROM product_categories c 
        LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
        WHERE c.status = 'active' 
        GROUP BY c.id 
        ORDER BY c.name
    ");
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
                LEFT JOIN product_categories c ON p.category_id = c.id
                WHERE $whereClause";
    
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalProducts = $countStmt->fetchColumn();
    $totalPages = ceil($totalProducts / $itemsPerPage);

    // Get products with pagination
    $sql = "SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN product_categories c ON p.category_id = c.id 
            WHERE $whereClause 
            ORDER BY p.created_at DESC
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

    <main class="store-main">
        <div class="container">
            <div class="page-header">
                <h1>전체 상품</h1>
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
                    
                    <div class="search-filter">
                        <form method="get">
                            <input type="hidden" name="category" value="<?= $selectedCategory ?>">
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
                            <img src="<?= !empty($product['image_url']) ? htmlspecialchars($product['image_url']) : '/assets/images/products/placeholder.jpg' ?>" 
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
        
        function addToCart(productId) {
            // 장바구니 추가 로직 구현 필요
            alert('장바구니 기능은 추후 구현 예정입니다');
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
    
    <style>
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
        
        /* 5열 그리드 레이아웃 */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1.5rem;
            margin-bottom: 40px;
        }

        /* 반응형 그리드 */
        @media (max-width: 992px) {
            .products-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 1rem;
            }
        }

        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
        }

        @media (max-width: 480px) {
            .products-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
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
            height: 520px;
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
        }

        .product-actions {
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
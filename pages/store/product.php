<?php
$productId = $_GET['id'] ?? 0;
$error = '';
$product = null;
$relatedProducts = [];
$reviews = [];
$averageRating = 0;
$currentUser = null;
$dbConnected = false;

if (!$productId) {
    header('Location: index.php');
    exit;
}

try {
    require_once __DIR__ . '/../../classes/Auth.php';
    require_once __DIR__ . '/../../classes/Database.php';

    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $dbConnected = true;

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

    // Get product reviews (if reviews table exists)
    try {
        $sql = "SELECT r.*, u.name as author_name
                FROM reviews r
                LEFT JOIN users u ON r.user_id = u.id
                WHERE r.product_id = ? AND r.status = 'published'
                ORDER BY r.created_at DESC LIMIT 10";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$productId]);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate average rating
        if (!empty($reviews)) {
            $totalRating = array_sum(array_column($reviews, 'rating'));
            $averageRating = round($totalRating / count($reviews), 1);
        }
    } catch (Exception $reviewError) {
        // Reviews table doesn't exist yet - skip reviews
        $reviews = [];
        $averageRating = 0;
    }

} catch (Exception $e) {
    // Database connection failed - use sample data
    error_log("Database connection failed in product detail: " . $e->getMessage());
    $dbConnected = false;

    // Sample product data for fallback
    $sampleProducts = [
        1 => [
            'id' => 1,
            'name' => '토마토 씨앗 (방울토마토)',
            'category_id' => 1,
            'category_name' => '씨앗/종자',
            'description' => '고품질 방울토마토 씨앗으로 높은 발아율을 자랑합니다. 실내 재배용으로 최적화된 품종입니다.',
            'specifications' => '• 발아율: 90% 이상\n• 재배기간: 90-120일\n• 적정온도: 18-25℃\n• 수확량: 주당 1-2kg',
            'price' => 5000,
            'sale_price' => 4500,
            'stock_quantity' => 50,
            'sku' => 'TAN-001',
            'views' => 1247,
            'weight' => '0.1',
            'dimensions' => '10cm x 15cm',
            'image_url' => '/assets/images/products/placeholder.jpg',
            'images' => json_encode(['/assets/images/products/placeholder.jpg']),
            'featured' => 1
        ],
        2 => [
            'id' => 2,
            'name' => '코코피트 배지 (10L)',
            'category_id' => 2,
            'category_name' => '코코피트 배지',
            'description' => '천연 코코넛 섬유로 만든 친환경 배지입니다. 우수한 보습성과 배수성을 제공합니다.',
            'specifications' => '• 용량: 10L\n• 압축비율: 1:5\n• pH: 5.5-6.5\n• EC: 0.3-0.8',
            'price' => 15000,
            'sale_price' => null,
            'stock_quantity' => 30,
            'sku' => 'TAN-002',
            'views' => 892,
            'weight' => '2.5',
            'dimensions' => '30cm x 20cm x 15cm',
            'image_url' => '/assets/images/products/placeholder.jpg',
            'images' => json_encode(['/assets/images/products/placeholder.jpg']),
            'featured' => 1
        ],
        3 => [
            'id' => 3,
            'name' => '토마토 전용 양액 (1L)',
            'category_id' => 3,
            'category_name' => '양액/비료',
            'description' => '토마토 전용 맞춤형 양액으로 최적의 영양소 비율을 제공합니다.',
            'specifications' => '• 용량: 1L\n• 희석비율: 1:1000\n• NPK: 20-20-20\n• 미량원소 포함',
            'price' => 35000,
            'sale_price' => 32000,
            'stock_quantity' => 15,
            'sku' => 'TAN-003',
            'views' => 634,
            'weight' => '1.2',
            'dimensions' => '25cm x 8cm x 8cm',
            'image_url' => '/assets/images/products/placeholder.jpg',
            'images' => json_encode(['/assets/images/products/placeholder.jpg']),
            'featured' => 1
        ],
        4 => [
            'id' => 4,
            'name' => 'IoT 환경 센서 키트',
            'category_id' => 4,
            'category_name' => '재배용품',
            'description' => '온습도, pH, EC 센서 통합 키트로 스마트팜 환경을 완벽 제어할 수 있습니다.',
            'specifications' => '• 센서: 온도, 습도, pH, EC\n• 연결: WiFi/Bluetooth\n• 배터리: 2000mAh\n• 앱 지원: iOS/Android',
            'price' => 150000,
            'sale_price' => 135000,
            'stock_quantity' => 8,
            'sku' => 'TAN-004',
            'views' => 1523,
            'weight' => '0.8',
            'dimensions' => '15cm x 10cm x 5cm',
            'image_url' => '/assets/images/products/placeholder.jpg',
            'images' => json_encode(['/assets/images/products/placeholder.jpg']),
            'featured' => 1
        ]
    ];

    if (isset($sampleProducts[$productId])) {
        $product = $sampleProducts[$productId];

        // Get related products from same category
        $relatedProducts = array_filter($sampleProducts, function($p) use ($product) {
            return $p['category_id'] == $product['category_id'] && $p['id'] != $product['id'];
        });
        $relatedProducts = array_slice($relatedProducts, 0, 3);

        // Sample reviews
        $reviews = [
            [
                'id' => 1,
                'author_name' => '김농부',
                'rating' => 5,
                'title' => '정말 좋은 제품입니다',
                'content' => '발아율이 정말 좋아요. 추천합니다!',
                'created_at' => '2024-01-15 10:30:00'
            ],
            [
                'id' => 2,
                'author_name' => '이재배',
                'rating' => 4,
                'title' => '만족스러운 구매',
                'content' => '품질이 우수하고 배송도 빨랐습니다.',
                'created_at' => '2024-01-10 14:20:00'
            ]
        ];
        $averageRating = 4.5;
    } else {
        $error = '존재하지 않는 제품입니다.';
    }
}

// Parse images
$productImages = [];
if ($product && $product['images']) {
    $images = json_decode($product['images'], true);
    if (is_array($images)) {
        $productImages = $images;
    }
}

// If no images, use placeholder
if (empty($productImages)) {
    $productImages = ['/assets/images/product-placeholder.jpg'];
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name'] ?? '제품 상세') ?> - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        .product-detail-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .breadcrumb {
            margin-bottom: 20px;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .breadcrumb a {
            color: #666;
            text-decoration: none;
            margin-right: 8px;
        }

        .breadcrumb a:hover {
            color: #2E7D32;
        }

        .product-main {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }

        .product-images {
            position: relative;
        }

        .main-image {
            width: 100%;
            height: 500px;
            object-fit: cover;
            border-radius: 12px;
            border: 1px solid #eee;
            margin-bottom: 15px;
        }

        .image-thumbnails {
            display: flex;
            gap: 10px;
            overflow-x: auto;
        }

        .thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .thumbnail:hover,
        .thumbnail.active {
            border-color: #2E7D32;
        }

        .product-info {
            padding: 0;
        }

        .product-category {
            background: #e8f5e8;
            color: #2E7D32;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
            margin-bottom: 10px;
        }

        .product-title {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin: 0 0 15px 0;
            line-height: 1.2;
        }

        .product-rating {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }

        .rating-stars {
            display: flex;
            gap: 2px;
        }

        .star {
            color: #ffc107;
            font-size: 1.2rem;
        }

        .star.empty {
            color: #ddd;
        }

        .rating-text {
            color: #666;
            font-size: 0.9rem;
        }

        .product-price {
            margin-bottom: 25px;
        }

        .current-price {
            font-size: 2.2rem;
            font-weight: 700;
            color: #2E7D32;
            margin-right: 10px;
        }

        .original-price {
            font-size: 1.2rem;
            color: #999;
            text-decoration: line-through;
            margin-right: 10px;
        }

        .discount-badge {
            background: #ff4757;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .product-options {
            margin-bottom: 30px;
        }

        .option-group {
            margin-bottom: 20px;
        }

        .option-label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }

        .option-select,
        .quantity-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            max-width: 150px;
        }

        .quantity-btn {
            width: 40px;
            height: 40px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quantity-btn:hover {
            background: #f5f5f5;
        }

        .quantity-input {
            width: 60px;
            text-align: center;
            border: 1px solid #ddd;
            padding: 8px;
            font-size: 1rem;
        }

        .product-actions {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }

        .btn-large {
            flex: 1;
            padding: 16px 24px;
            font-size: 1.1rem;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
        }

        .btn-cart {
            background: white;
            color: #2E7D32;
            border: 2px solid #2E7D32;
        }

        .btn-cart:hover {
            background: #2E7D32;
            color: white;
        }

        .btn-buy {
            background: #2E7D32;
            color: white;
        }

        .btn-buy:hover {
            background: #1B5E20;
        }

        .product-meta {
            border-top: 1px solid #eee;
            padding-top: 20px;
        }

        .meta-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f5f5f5;
        }

        .meta-label {
            font-weight: 600;
            color: #666;
        }

        .meta-value {
            color: #333;
        }

        .product-tabs {
            margin-top: 40px;
        }

        .tab-nav {
            display: flex;
            border-bottom: 2px solid #eee;
            margin-bottom: 30px;
        }

        .tab-btn {
            padding: 15px 25px;
            background: none;
            border: none;
            font-size: 1.1rem;
            font-weight: 600;
            color: #666;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .tab-btn.active {
            color: #2E7D32;
            border-bottom-color: #2E7D32;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .description-content {
            line-height: 1.8;
            font-size: 1.05rem;
            color: #555;
        }

        .specs-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .specs-table th,
        .specs-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .specs-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
            width: 30%;
        }

        .reviews-section {
            margin-top: 20px;
        }

        .reviews-summary {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 30px;
        }

        .review-item {
            border-bottom: 1px solid #eee;
            padding: 20px 0;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .review-author {
            font-weight: 600;
            color: #333;
        }

        .review-date {
            color: #666;
            font-size: 0.9rem;
        }

        .review-content {
            color: #555;
            line-height: 1.6;
        }

        .related-products {
            margin-top: 60px;
        }

        .section-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 30px;
            text-align: center;
        }

        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
        }

        .related-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
            text-decoration: none;
        }

        .related-card:hover {
            transform: translateY(-5px);
        }

        .related-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .related-info {
            padding: 20px;
        }

        .related-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }

        .related-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: #2E7D32;
        }

        .stock-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .in-stock {
            background: #d4edda;
            color: #155724;
        }

        .out-of-stock {
            background: #f8d7da;
            color: #721c24;
        }

        .low-stock {
            background: #fff3cd;
            color: #856404;
        }

        @media (max-width: 768px) {
            .product-main {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .main-image {
                height: 350px;
            }

            .product-title {
                font-size: 1.5rem;
            }

            .current-price {
                font-size: 1.8rem;
            }

            .product-actions {
                flex-direction: column;
            }

            .tab-nav {
                flex-wrap: wrap;
            }

            .tab-btn {
                flex: 1;
                min-width: 120px;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main class="product-detail-container">
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php elseif ($product): ?>
            <!-- Breadcrumb -->
            <nav class="breadcrumb">
                <a href="/">홈</a> >
                <a href="/pages/store/">스토어</a> >
                <a href="/pages/store/?category=<?= $product['category_id'] ?>"><?= htmlspecialchars($product['category_name'] ?? '전체') ?></a> >
                <span><?= htmlspecialchars($product['name']) ?></span>
            </nav>

            <!-- Product Main -->
            <div class="product-main">
                <!-- Product Images -->
                <div class="product-images">
                    <img src="<?= htmlspecialchars($productImages[0]) ?>" alt="<?= htmlspecialchars($product['name']) ?>"
                         class="main-image" id="mainImage">

                    <?php if (count($productImages) > 1): ?>
                    <div class="image-thumbnails">
                        <?php foreach ($productImages as $index => $image): ?>
                        <img src="<?= htmlspecialchars($image) ?>" alt="제품 이미지 <?= $index + 1 ?>"
                             class="thumbnail <?= $index === 0 ? 'active' : '' ?>"
                             onclick="changeMainImage('<?= htmlspecialchars($image) ?>', this)">
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Product Info -->
                <div class="product-info">
                    <?php if ($product['category_name']): ?>
                    <div class="product-category"><?= htmlspecialchars($product['category_name']) ?></div>
                    <?php endif; ?>

                    <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>

                    <?php if (!empty($reviews)): ?>
                    <div class="product-rating">
                        <div class="rating-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="star <?= $i <= $averageRating ? '' : 'empty' ?>">★</span>
                            <?php endfor; ?>
                        </div>
                        <span class="rating-text"><?= $averageRating ?>/5 (<?= count($reviews) ?>개 리뷰)</span>
                    </div>
                    <?php endif; ?>

                    <div class="product-price">
                        <span class="current-price">₩<?= number_format($product['sale_price'] ?: $product['price']) ?></span>
                        <?php if ($product['sale_price'] && $product['sale_price'] < $product['price']): ?>
                        <span class="original-price">₩<?= number_format($product['price']) ?></span>
                        <span class="discount-badge">
                            <?= round((($product['price'] - $product['sale_price']) / $product['price']) * 100) ?>% 할인
                        </span>
                        <?php endif; ?>
                    </div>

                    <div class="product-options">
                        <div class="option-group">
                            <label class="option-label">수량</label>
                            <div class="quantity-controls">
                                <button type="button" class="quantity-btn" onclick="changeQuantity(-1)">-</button>
                                <input type="number" id="quantity" class="quantity-input" value="1" min="1" max="<?= $product['stock_quantity'] ?>">
                                <button type="button" class="quantity-btn" onclick="changeQuantity(1)">+</button>
                            </div>
                        </div>
                    </div>

                    <div class="product-actions">
                        <?php if ($currentUser): ?>
                        <button type="button" class="btn-large btn-cart" onclick="addToCart(<?= $product['id'] ?>)">
                            장바구니
                        </button>
                        <button type="button" class="btn-large btn-buy" onclick="buyNow(<?= $product['id'] ?>)">
                            바로구매
                        </button>
                        <?php else: ?>
                        <button type="button" class="btn-large btn-cart" onclick="alert('로그인 후 이용 가능합니다'); location.href='/pages/auth/login.php'">
                            장바구니
                        </button>
                        <button type="button" class="btn-large btn-buy" onclick="alert('로그인 후 이용 가능합니다'); location.href='/pages/auth/login.php'">
                            바로구매
                        </button>
                        <?php endif; ?>
                    </div>

                    <div class="product-meta">
                        <div class="meta-item">
                            <span class="meta-label">배송비</span>
                            <span class="meta-value">3,000원 (50,000원 이상 무료배송)</span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">재고상태</span>
                            <span class="meta-value">
                                <?php
                                $stockStatus = $product['stock_quantity'] > 10 ? 'in-stock' :
                                              ($product['stock_quantity'] > 0 ? 'low-stock' : 'out-of-stock');
                                $stockText = $product['stock_quantity'] > 10 ? '재고 충분' :
                                            ($product['stock_quantity'] > 0 ? '재고 부족' : '품절');
                                ?>
                                <span class="stock-status <?= $stockStatus ?>"><?= $stockText ?></span>
                            </span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">제품번호</span>
                            <span class="meta-value"><?= htmlspecialchars($product['sku'] ?: 'TAN-' . str_pad($product['id'], 6, '0', STR_PAD_LEFT)) ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">조회수</span>
                            <span class="meta-value"><?= number_format($product['views']) ?>회</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Product Tabs -->
            <div class="product-tabs">
                <div class="tab-nav">
                    <button class="tab-btn active" onclick="showTab('description')">상품설명</button>
                    <button class="tab-btn" onclick="showTab('specs')">상품정보</button>
                    <button class="tab-btn" onclick="showTab('reviews')">리뷰 (<?= count($reviews) ?>)</button>
                </div>

                <div id="description" class="tab-content active">
                    <div class="description-content">
                        <?= nl2br(htmlspecialchars($product['description'] ?: '상품 설명이 준비중입니다.')) ?>

                        <?php if ($product['specifications']): ?>
                        <h3 style="margin-top: 30px;">주요 특징</h3>
                        <?= nl2br(htmlspecialchars($product['specifications'])) ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="specs" class="tab-content">
                    <table class="specs-table">
                        <tr>
                            <th>제품명</th>
                            <td><?= htmlspecialchars($product['name']) ?></td>
                        </tr>
                        <tr>
                            <th>카테고리</th>
                            <td><?= htmlspecialchars($product['category_name'] ?: '미분류') ?></td>
                        </tr>
                        <?php if ($product['weight']): ?>
                        <tr>
                            <th>중량</th>
                            <td><?= htmlspecialchars($product['weight']) ?>kg</td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($product['dimensions']): ?>
                        <tr>
                            <th>크기</th>
                            <td><?= htmlspecialchars($product['dimensions']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th>제조업체</th>
                            <td>탄생 (Tansaeng)</td>
                        </tr>
                        <tr>
                            <th>원산지</th>
                            <td>대한민국</td>
                        </tr>
                    </table>
                </div>

                <div id="reviews" class="tab-content">
                    <div class="reviews-section">
                        <?php if (!empty($reviews)): ?>
                        <div class="reviews-summary">
                            <h3>고객 평점</h3>
                            <div class="rating-stars" style="justify-content: center; margin: 10px 0;">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star <?= $i <= $averageRating ? '' : 'empty' ?>" style="font-size: 2rem;">★</span>
                                <?php endfor; ?>
                            </div>
                            <p><?= $averageRating ?>/5 (총 <?= count($reviews) ?>개 리뷰)</p>
                        </div>

                        <?php foreach ($reviews as $review): ?>
                        <div class="review-item">
                            <div class="review-header">
                                <div>
                                    <span class="review-author"><?= htmlspecialchars($review['author_name'] ?: '익명') ?></span>
                                    <div class="rating-stars" style="display: inline-block; margin-left: 10px;">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="star <?= $i <= $review['rating'] ? '' : 'empty' ?>">★</span>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <span class="review-date"><?= date('Y.m.d', strtotime($review['created_at'])) ?></span>
                            </div>
                            <?php if ($review['title']): ?>
                            <h4 style="margin: 10px 0; color: #333;"><?= htmlspecialchars($review['title']) ?></h4>
                            <?php endif; ?>
                            <div class="review-content">
                                <?= nl2br(htmlspecialchars($review['content'])) ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <div class="reviews-summary">
                            <p>아직 리뷰가 없습니다.<br>첫 번째 리뷰를 작성해보세요!</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Related Products -->
            <?php if (!empty($relatedProducts)): ?>
            <div class="related-products">
                <h2 class="section-title">연관 상품</h2>
                <div class="related-grid">
                    <?php foreach ($relatedProducts as $related): ?>
                    <?php
                    $relatedImages = json_decode($related['images'], true);
                    $relatedImage = !empty($relatedImages) ? $relatedImages[0] : '/assets/images/product-placeholder.jpg';
                    ?>
                    <a href="product.php?id=<?= $related['id'] ?>" class="related-card">
                        <img src="<?= htmlspecialchars($relatedImage) ?>" alt="<?= htmlspecialchars($related['name']) ?>" class="related-image">
                        <div class="related-info">
                            <h3 class="related-title"><?= htmlspecialchars($related['name']) ?></h3>
                            <div class="related-price">₩<?= number_format($related['sale_price'] ?: $related['price']) ?></div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </main>

    <?php include '../../includes/footer.php'; ?>

    <script>
        function changeMainImage(src, thumbnail) {
            document.getElementById('mainImage').src = src;

            // Update active thumbnail
            document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
            thumbnail.classList.add('active');
        }

        function changeQuantity(delta) {
            const quantityInput = document.getElementById('quantity');
            const currentValue = parseInt(quantityInput.value);
            const maxStock = parseInt(quantityInput.max);

            const newValue = currentValue + delta;

            if (newValue >= 1 && newValue <= maxStock) {
                quantityInput.value = newValue;
            }
        }

        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabName).classList.add('active');

            // Add active class to clicked button
            event.target.classList.add('active');
        }

        function addToCart(productId) {
            const quantity = document.getElementById('quantity').value;

            // Add to cart via AJAX
            fetch('/api/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'add',
                    product_id: productId,
                    quantity: parseInt(quantity)
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('장바구니에 상품이 담겼습니다.');
                } else {
                    alert(data.message || '장바구니 담기에 실패했습니다.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('장바구니 담기에 실패했습니다.');
            });
        }

        function buyNow(productId) {
            const quantity = document.getElementById('quantity').value;

            // Redirect to checkout with immediate purchase
            window.location.href = `/pages/store/checkout.php?product_id=${productId}&quantity=${quantity}&direct=1`;
        }
    </script>
</body>
</html>
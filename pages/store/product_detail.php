<?php
// 상품 상세페이지
$currentUser = null;
$product = null;
$relatedProducts = [];
$productId = intval($_GET['id'] ?? 0);

if ($productId <= 0) {
    header('Location: /pages/store/');
    exit;
}

try {
    require_once __DIR__ . '/../../classes/Auth.php';
    require_once __DIR__ . '/../../classes/Database.php';
    
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // 상품 정보 조회
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN product_categories c ON p.category_id = c.id 
        WHERE p.id = ? AND p.status = 'active'
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    // 후기 통계 조회
    $review_stats = null;
    if ($product) {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_reviews,
                AVG(rating) as avg_rating
            FROM product_reviews 
            WHERE product_id = ? AND status = 'active'
        ");
        $stmt->execute([$productId]);
        $review_stats = $stmt->fetch();
    }
    
    if (!$product) {
        header('Location: /pages/store/');
        exit;
    }
    
    // 관련 상품 조회 (같은 카테고리의 다른 상품)
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN product_categories c ON p.category_id = c.id 
        WHERE p.category_id = ? AND p.id != ? AND p.status = 'active'
        ORDER BY RAND() 
        LIMIT 4
    ");
    $stmt->execute([$product['category_id'], $productId]);
    $relatedProducts = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Product detail error: " . $e->getMessage());
    header('Location: /pages/store/');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> - 탄생</title>
    <meta name="description" content="<?= htmlspecialchars(mb_substr($product['description'], 0, 160)) ?>">
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/store.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main class="product-detail-main">
        <div class="container">
            <!-- Breadcrumb -->
            <nav class="breadcrumb">
                <a href="/">홈</a>
                <span>></span>
                <a href="/pages/store/">스토어</a>
                <span>></span>
                <a href="/pages/store/products.php?category=<?= $product['category_id'] ?>"><?= htmlspecialchars($product['category_name']) ?></a>
                <span>></span>
                <span><?= htmlspecialchars($product['name']) ?></span>
            </nav>
            
            <!-- Product Detail -->
            <div class="product-detail-container">
                <?php if (!empty($product['image_url'])): ?>
                <div class="product-images">
                    <div class="main-image">
                        <img src="<?= htmlspecialchars($product['image_url']) ?>" 
                             alt="<?= htmlspecialchars($product['name']) ?>" 
                             id="mainProductImage"
                             onerror="this.style.display='none'; this.parentElement.parentElement.style.display='none';">
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="product-info">
                    <div class="product-header">
                        <span class="product-category"><?= htmlspecialchars($product['category_name']) ?></span>
                        <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>
                    </div>
                    
                    <div class="product-price">
                        <span class="current-price"><?= number_format($product['price']) ?>원</span>
                    </div>
                    
                    <div class="product-description">
                        <h3>상품 설명</h3>
                        <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                    </div>
                    
                    <div class="product-specs">
                        <?php if (!empty($product['weight'])): ?>
                        <div class="spec-item">
                            <span class="spec-label">무게:</span>
                            <span class="spec-value"><?= htmlspecialchars($product['weight']) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($product['dimensions'])): ?>
                        <div class="spec-item">
                            <span class="spec-label">크기:</span>
                            <span class="spec-value"><?= htmlspecialchars($product['dimensions']) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="spec-item">
                            <span class="spec-label">재고:</span>
                            <span class="spec-value stock-info"><?= $product['stock'] ?>개</span>
                        </div>
                    </div>
                    
                    <?php if ($currentUser): ?>
                    <div class="product-actions">
                        <div class="quantity-selector">
                            <label for="quantity">수량:</label>
                            <div class="quantity-controls">
                                <button type="button" onclick="changeQuantity(-1)">-</button>
                                <input type="number" id="quantity" value="1" min="1" max="<?= $product['stock'] ?>">
                                <button type="button" onclick="changeQuantity(1)">+</button>
                            </div>
                        </div>
                        
                        <div class="action-buttons">
                            <button onclick="addToCart(<?= $product['id'] ?>)" class="btn btn-primary btn-lg">
                                장바구니 담기
                            </button>
                            <button onclick="buyNow(<?= $product['id'] ?>)" class="btn btn-success btn-lg">
                                바로 구매
                            </button>
                        </div>
                        
                        <button onclick="toggleWishlist(<?= $product['id'] ?>)" class="btn btn-outline wishlist-btn">
                            ♡ 찜하기
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="product-actions">
                        <div class="login-prompt">
                            <p>로그인 후 구매할 수 있습니다</p>
                            <a href="/pages/auth/login.php" class="btn btn-primary btn-lg">로그인</a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Product Details Tabs -->
            <div class="product-tabs">
                <div class="tab-nav">
                    <button class="tab-btn active" onclick="showTab('description')">상세설명</button>
                    <button class="tab-btn" onclick="showTab('specs')">상품정보</button>
                    <button class="tab-btn" onclick="showTab('reviews')">구매후기</button>
                    <button class="tab-btn" onclick="showTab('qna')">상품문의</button>
                </div>
                
                <div class="tab-content">
                    <div class="tab-pane active" id="description">
                        <div class="detailed-description">
                            <h3>상세 설명</h3>
                            <?php if (!empty($product['detailed_description'])): ?>
                                <div class="rich-content"><?= $product['detailed_description'] ?></div>
                            <?php else: ?>
                                <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($product['media_files'])): ?>
                                <?php $media_files = json_decode($product['media_files'], true); ?>
                                <?php if (is_array($media_files)): ?>
                                <div class="product-media-gallery">
                                    <h4>상품 미디어</h4>
                                    <div class="media-gallery-grid">
                                        <?php foreach ($media_files as $media): ?>
                                            <div class="media-item">
                                                <?php if ($media['type'] === 'image'): ?>
                                                    <img src="<?= htmlspecialchars($media['url']) ?>" 
                                                         alt="상품 이미지" 
                                                         onclick="openImageModal(this.src)"
                                                         class="gallery-image">
                                                <?php else: ?>
                                                    <video controls class="gallery-video">
                                                        <source src="<?= htmlspecialchars($media['url']) ?>" 
                                                                type="video/<?= pathinfo($media['url'], PATHINFO_EXTENSION) ?>">
                                                        Your browser does not support the video tag.
                                                    </video>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <div class="product-features">
                                <h4>주요 특징</h4>
                                <ul>
                                    <?php 
                                    $features_displayed = false;
                                    if (!empty($product['features'])) {
                                        $features = json_decode($product['features'], true);
                                        if (is_array($features) && !empty($features)) {
                                            foreach ($features as $feature) {
                                                if (!empty(trim($feature))) {
                                                    echo '<li>' . htmlspecialchars(trim($feature)) . '</li>';
                                                    $features_displayed = true;
                                                }
                                            }
                                        }
                                    }
                                    
                                    // Fallback to default features if none are specified
                                    if (!$features_displayed) {
                                        echo '<li>고품질 원료 사용</li>';
                                        echo '<li>친환경 제조 공정</li>';
                                        echo '<li>안전한 포장 및 배송</li>';
                                        echo '<li>전문가 상담 서비스</li>';
                                    }
                                    ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-pane" id="specs">
                        <div class="product-specifications">
                            <h3>상품 정보</h3>
                            <table class="specs-table">
                                <tr>
                                    <td>상품명</td>
                                    <td><?= htmlspecialchars($product['name']) ?></td>
                                </tr>
                                <tr>
                                    <td>카테고리</td>
                                    <td><?= htmlspecialchars($product['category_name']) ?></td>
                                </tr>
                                <?php if (!empty($product['weight'])): ?>
                                <tr>
                                    <td>무게</td>
                                    <td><?= htmlspecialchars($product['weight']) ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($product['dimensions'])): ?>
                                <tr>
                                    <td>크기</td>
                                    <td><?= htmlspecialchars($product['dimensions']) ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td>재고</td>
                                    <td><?= $product['stock'] ?>개</td>
                                </tr>
                                <tr>
                                    <td>등록일</td>
                                    <td><?= date('Y년 m월 d일', strtotime($product['created_at'])) ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="tab-pane" id="reviews">
                        <div class="reviews-section">
                            <div class="review-header">
                                <h3>구매 후기 
                                    <?php if ($review_stats && $review_stats['total_reviews'] > 0): ?>
                                        (<?= $review_stats['total_reviews'] ?>개)
                                    <?php endif; ?>
                                </h3>
                                
                                <?php if ($review_stats && $review_stats['total_reviews'] > 0): ?>
                                    <div class="review-summary">
                                        <div class="avg-rating">
                                            <span class="rating-score"><?= number_format($review_stats['avg_rating'], 1) ?></span>
                                            <span class="rating-stars">
                                                <?php
                                                $avg = round($review_stats['avg_rating']);
                                                for ($i = 1; $i <= 5; $i++) {
                                                    echo $i <= $avg ? '⭐' : '☆';
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <p class="total-reviews"><?= $review_stats['total_reviews'] ?>개의 후기</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- 후기 목록 (기본 5개) -->
                            <div id="review-list">
                                <?php
                                // 후기 목록 조회 (기본 5개)
                                if ($review_stats && $review_stats['total_reviews'] > 0) {
                                    $stmt = $pdo->prepare("
                                        SELECT r.*, u.name as username 
                                        FROM product_reviews r 
                                        LEFT JOIN users u ON r.user_id = u.id 
                                        WHERE r.product_id = ? AND r.status = 'active' 
                                        ORDER BY r.created_at DESC 
                                        LIMIT 5
                                    ");
                                    $stmt->execute([$productId]);
                                    $reviews = $stmt->fetchAll();
                                    
                                    if ($reviews) {
                                        foreach ($reviews as $review) {
                                            echo '<div class="review-item">';
                                            echo '<div class="review-meta">';
                                            echo '<span class="reviewer-name">' . htmlspecialchars($review['user_name'] ?: '익명') . '</span>';
                                            echo '<span class="review-rating">';
                                            for ($i = 1; $i <= 5; $i++) {
                                                echo $i <= $review['rating'] ? '⭐' : '☆';
                                            }
                                            echo '</span>';
                                            echo '<span class="review-date">' . date('Y-m-d', strtotime($review['created_at'])) . '</span>';
                                            echo '</div>';
                                            echo '<div class="review-content">';
                                            echo '<p>' . nl2br(htmlspecialchars($review['content'])) . '</p>';
                                            
                                            // 후기 이미지 표시
                                            if (!empty($review['images'])) {
                                                $images = json_decode($review['images'], true);
                                                if (is_array($images) && count($images) > 0) {
                                                    echo '<div class="review-images">';
                                                    foreach ($images as $image) {
                                                        echo '<img src="' . htmlspecialchars($image) . '" alt="후기 이미지" onclick="openImageModal(this.src)" class="review-thumb">';
                                                    }
                                                    echo '</div>';
                                                }
                                            }
                                            echo '</div>';
                                            echo '</div>';
                                        }
                                    }
                                }
                                ?>
                            </div>
                            
                            <div class="review-actions">
                                <?php if ($currentUser): ?>
                                <button onclick="toggleReviewForm()" class="btn btn-primary" id="write-review-btn">
                                    후기 작성하기
                                </button>
                                <?php else: ?>
                                <a href="/pages/auth/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-primary">
                                    로그인 후 후기 작성
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($review_stats && $review_stats['total_reviews'] > 5): ?>
                                <a href="/pages/store/review.php?product_id=<?= $productId ?>" class="btn btn-outline">
                                    모든 후기 보기 (<?= $review_stats['total_reviews'] ?>개)
                                </a>
                                <?php endif; ?>
                            </div>
                            
                            <!-- 후기 작성 폼 (숨겨짐) -->
                            <?php if ($currentUser): ?>
                            <div id="review-form-container" style="display: none;">
                                <div class="review-write-form">
                                    <h4>후기 작성</h4>
                                    <form id="review-form" enctype="multipart/form-data">
                                        <input type="hidden" name="product_id" value="<?= $productId ?>">
                                        <?php if ($currentUser): ?>
                                        <input type="hidden" name="user_id" value="<?= $currentUser['id'] ?>">
                                        <input type="hidden" name="author" value="<?= htmlspecialchars($currentUser['name'] ?? '익명') ?>">
                                        <?php else: ?>
                                        <input type="hidden" name="author" value="익명">
                                        <?php endif; ?>
                                        
                                        <div class="form-group">
                                            <label for="rating">별점</label>
                                            <div class="rating-input">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <span class="star" data-rating="<?= $i ?>" onclick="setRating(<?= $i ?>)">☆</span>
                                                <?php endfor; ?>
                                                <input type="hidden" name="rating" id="rating-value" required>
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="content">후기 내용</label>
                                            <textarea name="content" id="content" rows="4" placeholder="상품에 대한 솔직한 후기를 남겨주세요." required></textarea>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="images">이미지 (선택사항)</label>
                                            <input type="file" name="images[]" id="images" multiple accept="image/*">
                                            <small class="form-help">최대 5개 이미지 업로드 가능</small>
                                        </div>
                                        
                                        <div class="form-actions">
                                            <button type="button" onclick="hideReviewForm()" class="btn btn-outline">취소</button>
                                            <button type="submit" class="btn btn-primary">후기 등록</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!$review_stats || $review_stats['total_reviews'] == 0): ?>
                            <div class="no-reviews">
                                <p>아직 등록된 후기가 없습니다.</p>
                                <p>첫 번째 후기를 작성해보세요!</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="tab-pane" id="qna">
                        <div class="qna-section">
                            <h3>상품 문의</h3>
                            <div class="no-qna">
                                <p>궁금한 점이 있으시면 언제든 문의해주세요.</p>
                                <a href="/pages/support/contact.php" class="btn btn-primary">문의하기</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Related Products -->
            <?php if (!empty($relatedProducts)): ?>
            <div class="related-products">
                <h3>관련 상품</h3>
                <div class="products-grid">
                    <?php foreach ($relatedProducts as $relatedProduct): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <a href="/pages/store/product_detail.php?id=<?= $relatedProduct['id'] ?>">
                                <img src="<?= !empty($relatedProduct['image_url']) ? htmlspecialchars($relatedProduct['image_url']) : '/assets/images/products/placeholder.jpg' ?>" 
                                     alt="<?= htmlspecialchars($relatedProduct['name']) ?>" 
                                     loading="lazy"
                                     onerror="this.src='/assets/images/products/placeholder.jpg'">
                            </a>
                        </div>
                        <div class="product-info">
                            <span class="product-category"><?= htmlspecialchars($relatedProduct['category_name']) ?></span>
                            <h4><a href="/pages/store/product_detail.php?id=<?= $relatedProduct['id'] ?>"><?= htmlspecialchars($relatedProduct['name']) ?></a></h4>
                            <div class="product-price">
                                <span class="price"><?= number_format($relatedProduct['price']) ?>원</span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Back to List Button -->
            <div class="back-to-list-container">
                <a href="javascript:history.back()" class="btn btn-back-to-list">
                    ← 목록으로 돌아가기
                </a>
            </div>
        </div>
    </main>

    <!-- Image Modal -->
    <div id="imageModal" class="modal" onclick="closeImageModal()">
        <span class="close">&times;</span>
        <img class="modal-content" id="modalImage">
    </div>

    <?php include '../../includes/footer.php'; ?>
    <script src="/assets/js/main.js"></script>
    <script>
        // 수량 조절
        function changeQuantity(delta) {
            const quantityInput = document.getElementById('quantity');
            const currentValue = parseInt(quantityInput.value) || 1;
            const newValue = Math.max(1, Math.min(<?= $product['stock'] ?>, currentValue + delta));
            quantityInput.value = newValue;
        }
        
        // 장바구니 추가
        function addToCart(productId) {
            const quantity = parseInt(document.getElementById('quantity').value) || 1;
            
            fetch('/api/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    product_id: productId,
                    quantity: quantity
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    updateCartCount();
                } else {
                    alert(data.message || '장바구니 추가에 실패했습니다');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('오류가 발생했습니다');
            });
        }
        
        // 바로 구매
        function buyNow(productId) {
            const quantity = parseInt(document.getElementById('quantity').value) || 1;
            // 임시로 장바구니에 추가 후 장바구니 페이지로 이동
            addToCart(productId);
            setTimeout(() => {
                location.href = '/pages/store/cart.php';
            }, 1000);
        }
        
        // 찜하기 (추후 구현)
        function toggleWishlist(productId) {
            alert('찜하기 기능은 추후 구현 예정입니다');
        }
        
        // 탭 전환
        function showTab(tabName) {
            // 모든 탭 버튼 비활성화
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
            
            // 선택된 탭 활성화
            event.target.classList.add('active');
            document.getElementById(tabName).classList.add('active');
        }
        
        // 장바구니 개수 업데이트 (헤더에 표시)
        function updateCartCount() {
            fetch('/api/cart.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const cartCountElement = document.querySelector('.cart-count');
                    if (cartCountElement) {
                        cartCountElement.textContent = data.summary.total_items;
                    }
                }
            })
            .catch(error => console.error('Cart count update error:', error));
        }
        
        // 이미지 모달 기능
        function openImageModal(imageSrc) {
            document.getElementById('imageModal').style.display = 'block';
            document.getElementById('modalImage').src = imageSrc;
        }
        
        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
        }
        
        // ESC 키로 모달 닫기
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeImageModal();
            }
        });
        
        // 후기 작성 폼 토글
        function toggleReviewForm() {
            const formContainer = document.getElementById('review-form-container');
            const writeBtn = document.getElementById('write-review-btn');
            
            if (formContainer.style.display === 'none') {
                formContainer.style.display = 'block';
                writeBtn.textContent = '작성 취소';
                writeBtn.classList.remove('btn-primary');
                writeBtn.classList.add('btn-outline');
                
                // 폼으로 스크롤
                formContainer.scrollIntoView({ behavior: 'smooth' });
            } else {
                hideReviewForm();
            }
        }
        
        function hideReviewForm() {
            const formContainer = document.getElementById('review-form-container');
            const writeBtn = document.getElementById('write-review-btn');
            const form = document.getElementById('review-form');
            
            formContainer.style.display = 'none';
            writeBtn.textContent = '후기 작성하기';
            writeBtn.classList.remove('btn-outline');
            writeBtn.classList.add('btn-primary');
            
            // 폼 초기화
            form.reset();
            setRating(0);
        }
        
        // 별점 설정
        function setRating(rating) {
            document.getElementById('rating-value').value = rating;
            
            const stars = document.querySelectorAll('.star');
            stars.forEach((star, index) => {
                if (index < rating) {
                    star.textContent = '★';
                    star.classList.add('selected');
                } else {
                    star.textContent = '☆';
                    star.classList.remove('selected');
                }
            });
        }
        
        // 후기 제출
        document.addEventListener('DOMContentLoaded', function() {
            const reviewForm = document.getElementById('review-form');
            if (reviewForm) {
                reviewForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // 필수 입력값 체크
                    const rating = document.getElementById('rating-value').value;
                    const content = document.getElementById('content').value;
                    
                    if (!rating || rating < 1 || rating > 5) {
                        alert('별점을 선택해주세요.');
                        return;
                    }
                    
                    if (!content.trim()) {
                        alert('후기 내용을 입력해주세요.');
                        return;
                    }
                    
                    const formData = new FormData(this);
                    formData.append('action', 'add_review');
                    
                    // 현재 상품 ID를 GET 파라미터에서 가져와서 URL에 추가
                    const productId = formData.get('product_id') || <?= $productId ?>;
                    
                    fetch(`/pages/store/review.php?product_id=${productId}`, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        console.log('Response status:', response.status);
                        return response.text();
                    })
                    .then(data => {
                        console.log('Response data:', data);
                        console.log('Response length:', data.length);
                        console.log('Response trimmed:', data.trim());
                        
                        // 응답이 정확히 'success'인지 체크
                        if (data.trim() === 'success') {
                            alert('후기가 성공적으로 등록되었습니다.');
                            location.reload();
                        } else if (data.startsWith('error:')) {
                            const errorMsg = data.replace('error:', '').trim();
                            alert('오류: ' + errorMsg);
                        } else if (data.includes('성공적으로 등록')) {
                            alert('후기가 성공적으로 등록되었습니다.');
                            location.reload();
                        } else {
                            alert('후기 등록에 실패했습니다. 서버 응답: ' + data.substring(0, 100));
                            console.error('Full server response:', data);
                        }
                    })
                    .catch(error => {
                        console.error('Network error:', error);
                        alert('네트워크 오류가 발생했습니다. 인터넷 연결을 확인해주세요.');
                    });
                });
            }
        });
    </script>
    
    <style>
        .product-detail-main {
            padding: 2rem 0;
            background: #f8f9fa;
        }
        
        .breadcrumb {
            margin-bottom: 2rem;
            font-size: 0.9rem;
        }
        
        .breadcrumb a {
            color: #666;
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            color: #007bff;
        }
        
        .breadcrumb span {
            margin: 0 0.5rem;
            color: #999;
        }
        
        .product-detail-container {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
        }
        
        .product-detail-container:not(:has(.product-images)) {
            grid-template-columns: 1fr;
        }
        
        .main-image img {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #eee;
        }
        
        .product-header {
            margin-bottom: 1.5rem;
        }
        
        .product-category {
            background: #e3f2fd;
            color: #1976d2;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .product-title {
            margin: 1rem 0;
            font-size: 1.8rem;
            font-weight: 700;
            color: #333;
        }
        
        .current-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: #007bff;
        }
        
        .product-description {
            margin: 2rem 0;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .product-specs {
            margin: 2rem 0;
        }
        
        .spec-item {
            display: flex;
            margin: 0.5rem 0;
        }
        
        .spec-label {
            font-weight: 600;
            min-width: 80px;
            color: #666;
        }
        
        .spec-value {
            color: #333;
        }
        
        .quantity-selector {
            margin: 1rem 0;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .quantity-controls button {
            background: #f0f0f0;
            border: 1px solid #ddd;
            width: 35px;
            height: 35px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .quantity-controls input {
            width: 60px;
            text-align: center;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin: 1rem 0;
        }
        
        .wishlist-btn {
            margin-top: 1rem;
            width: 100%;
        }
        
        .product-tabs {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .tab-nav {
            display: flex;
            border-bottom: 1px solid #eee;
        }
        
        .tab-btn {
            flex: 1;
            padding: 1rem;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            border-bottom: 3px solid transparent;
        }
        
        .tab-btn.active {
            border-bottom-color: #007bff;
            color: #007bff;
            font-weight: 600;
        }
        
        .tab-content {
            padding: 2rem;
        }
        
        .tab-pane {
            display: none;
        }
        
        .tab-pane.active {
            display: block;
        }
        
        .specs-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .specs-table td {
            padding: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .specs-table td:first-child {
            font-weight: 600;
            background: #f8f9fa;
            width: 150px;
        }
        
        .related-products {
            margin-top: 3rem;
        }
        
        .related-products h3 {
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .no-reviews, .no-qna {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2rem;
        }
        
        .review-summary {
            text-align: right;
        }
        
        .avg-rating {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            justify-content: flex-end;
            margin-bottom: 0.5rem;
        }
        
        .rating-score {
            font-size: 2rem;
            font-weight: 700;
            color: #007bff;
        }
        
        .rating-stars {
            font-size: 1.2rem;
        }
        
        .total-reviews {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }
        
        .review-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin: 2rem 0;
        }
        
        .review-actions .btn {
            padding: 0.75rem 1.5rem;
        }
        
        .product-media-gallery {
            margin: 2rem 0;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .product-media-gallery h4 {
            margin: 0 0 1rem 0;
            color: #333;
        }
        
        .media-gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .media-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .gallery-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        .gallery-image:hover {
            transform: scale(1.02);
        }
        
        .gallery-video {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        /* Image Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8);
            cursor: pointer;
        }
        
        .modal-content {
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 90%;
            margin-top: 5%;
            cursor: default;
        }
        
        .close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .back-to-list-container {
            text-align: center;
            margin: 3rem 0 2rem 0;
            padding: 2rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        /* 후기 작성 폼 스타일 */
        .review-write-form {
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 8px;
            margin-top: 2rem;
            border: 1px solid #e9ecef;
        }
        
        .review-write-form h4 {
            margin-bottom: 1.5rem;
            color: #333;
        }
        
        .rating-input {
            display: flex;
            gap: 0.2rem;
            margin-top: 0.5rem;
        }
        
        .star {
            font-size: 1.5rem;
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s ease;
        }
        
        .star:hover,
        .star.selected {
            color: #ffc107;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }
        
        .review-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .review-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        
        .reviewer-name {
            font-weight: 600;
            color: #333;
        }
        
        .review-rating {
            color: #ffc107;
        }
        
        .review-date {
            color: #666;
            margin-left: auto;
        }
        
        .review-content p {
            margin: 0;
            line-height: 1.6;
            color: #555;
        }
        
        .review-images {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        
        .review-thumb {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        .review-thumb:hover {
            transform: scale(1.1);
        }
        
        .form-help {
            display: block;
            margin-top: 0.25rem;
            font-size: 0.875rem;
            color: #6c757d;
        }
        
        .btn-back-to-list {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 12px 24px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 2px solid #6c757d;
        }
        
        .btn-back-to-list:hover {
            background: #5a6268;
            border-color: #5a6268;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        @media (max-width: 768px) {
            .product-detail-container {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .tab-nav {
                flex-wrap: wrap;
            }
            
            .tab-btn {
                flex: none;
                min-width: 25%;
            }
        }
        
        /* Rich Content 스타일 */
        .rich-content {
            line-height: 1.8;
            font-size: 15px;
        }
        
        .rich-content h1,
        .rich-content h2,
        .rich-content h3,
        .rich-content h4,
        .rich-content h5,
        .rich-content h6 {
            color: #333;
            margin: 1.5em 0 0.5em 0;
            font-weight: 600;
        }
        
        .rich-content h1 { font-size: 2em; }
        .rich-content h2 { font-size: 1.7em; }
        .rich-content h3 { font-size: 1.5em; }
        .rich-content h4 { font-size: 1.3em; }
        .rich-content h5 { font-size: 1.1em; }
        .rich-content h6 { font-size: 1em; }
        
        .rich-content p {
            margin: 1em 0;
        }
        
        .rich-content ul,
        .rich-content ol {
            margin: 1em 0;
            padding-left: 2em;
        }
        
        .rich-content li {
            margin: 0.5em 0;
        }
        
        .rich-content img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin: 1em 0;
        }
        
        .rich-content figure {
            margin: 1.5em 0;
            text-align: center;
        }
        
        .rich-content figure img {
            margin: 0;
        }
        
        .rich-content figcaption {
            font-size: 0.9em;
            color: #666;
            font-style: italic;
            margin-top: 0.5em;
        }
        
        .rich-content blockquote {
            border-left: 4px solid #007bff;
            margin: 1.5em 0;
            padding: 1em 1.5em;
            background: #f8f9fa;
            font-style: italic;
        }
        
        .rich-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5em 0;
            border: 1px solid #ddd;
        }
        
        .rich-content table th,
        .rich-content table td {
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
        }
        
        .rich-content table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .rich-content table tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .rich-content a {
            color: #007bff;
            text-decoration: none;
        }
        
        .rich-content a:hover {
            text-decoration: underline;
        }
        
        .rich-content strong {
            font-weight: 600;
        }
        
        .rich-content em {
            font-style: italic;
        }
        
        .rich-content u {
            text-decoration: underline;
        }
        
        .rich-content s {
            text-decoration: line-through;
        }
    </style>
</body>
</html>
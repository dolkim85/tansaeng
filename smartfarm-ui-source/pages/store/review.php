<?php
// 후기 관리 페이지
// 모든 헤더 출력 전에 처리
ob_start(); // 출력 버퍼링 시작

$currentUser = null;
$product = null;
$reviews = [];
$product_id = intval($_GET['product_id'] ?? 0);
$success = '';
$error = '';

if ($product_id <= 0) {
    header('Location: /pages/store/products.php');
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
    $stmt = $pdo->prepare("SELECT id, name FROM products WHERE id = ? AND status = 'active'");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        header('Location: /pages/store/products.php');
        exit;
    }
    
    // 디버깅: POST 데이터 로그
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        error_log("POST data received: " . print_r($_POST, true));
        error_log("FILES data received: " . print_r($_FILES, true));
        error_log("Headers: " . print_r(getallheaders(), true));
        error_log("Request URI: " . $_SERVER['REQUEST_URI']);
    }
    
    // 후기 작성 처리
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && ($_POST['action'] === 'add_review' || $_POST['action'] === 'add')) {
        $rating = intval($_POST['rating'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $user_name = $author ?: ($currentUser ? ($currentUser['name'] ?? '익명') : '익명');
        
        // 제목 자동 생성 (별점 기반)
        $rating_text = ['', '별로예요', '그냥그래요', '괜찮아요', '좋아요', '최고예요'];
        $title = $rating_text[$rating] ?? '후기';
        
        if ($rating < 1 || $rating > 5) {
            $error = '평점은 1~5점 사이로 선택해주세요.';
        } elseif (empty($content)) {
            $error = '후기 내용을 입력해주세요.';
        } elseif (empty($user_name)) {
            $error = '작성자명을 입력해주세요.';
        } else {
            // 디버깅: 받은 데이터 로그
            error_log("Review submission - Rating: $rating, Content: $content, Author: $user_name, Product ID: $product_id");
            
            // 이미지 업로드 처리
            $images = [];
            if (isset($_FILES['review_images']) && is_array($_FILES['review_images']['name'])) {
                $upload_dir = __DIR__ . '/../../uploads/reviews/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                for ($i = 0; $i < count($_FILES['review_images']['name']); $i++) {
                    if ($_FILES['review_images']['error'][$i] === UPLOAD_ERR_OK) {
                        $file_extension = strtolower(pathinfo($_FILES['review_images']['name'][$i], PATHINFO_EXTENSION));
                        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                        
                        if (in_array($file_extension, $allowed_extensions)) {
                            $new_filename = uniqid('review_') . '.' . $file_extension;
                            $upload_path = $upload_dir . $new_filename;
                            
                            if (move_uploaded_file($_FILES['review_images']['tmp_name'][$i], $upload_path)) {
                                $images[] = '/uploads/reviews/' . $new_filename;
                            }
                        }
                    }
                }
            }
            
            $images_json = !empty($images) ? json_encode($images, JSON_UNESCAPED_UNICODE) : null;
            
            $sql = "INSERT INTO product_reviews (product_id, user_id, user_name, rating, title, content, images) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            
            try {
                $stmt->execute([
                    $product_id, 
                    $currentUser ? $currentUser['id'] : null, 
                    $user_name, 
                    $rating, 
                    $title, 
                    $content, 
                    $images_json
                ]);
                
                error_log("Review inserted successfully with ID: " . $pdo->lastInsertId());
                $success = '후기가 성공적으로 등록되었습니다.';
            } catch (PDOException $e) {
                error_log("Review insert failed: " . $e->getMessage());
                $error = '후기 등록에 실패했습니다: ' . $e->getMessage();
            }
            
            // AJAX 요청 또는 FormData 요청 감지
            $isAjax = !empty($_POST['ajax']) || 
                      (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') ||
                      (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false) ||
                      (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
            
            if ($isAjax) {
                ob_clean();
                header('Content-Type: text/plain; charset=utf-8');
                if ($success) {
                    echo 'success';
                } else {
                    echo 'error: ' . ($error ?: '알 수 없는 오류가 발생했습니다.');
                }
                exit;
            }
            
            // 폼 데이터 초기화
            $content = '';
            $rating = 5;
        }
    }
    
    // 후기 삭제 처리
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_review') {
        $review_id = intval($_POST['review_id'] ?? 0);
        
        // 본인 후기만 삭제 가능 (또는 관리자)
        $where_clause = "id = ? AND product_id = ?";
        $params = [$review_id, $product_id];
        
        if ($currentUser && $currentUser['user_level'] < 9) {
            $where_clause .= " AND (user_id = ? OR user_name = ?)";
            $params[] = $currentUser['id'];
            $params[] = $currentUser['name'];
        }
        
        $sql = "DELETE FROM product_reviews WHERE $where_clause";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        if ($stmt->rowCount() > 0) {
            $success = '후기가 삭제되었습니다.';
        } else {
            $error = '후기 삭제 권한이 없습니다.';
        }
    }
    
    // 후기 목록 조회
    $sql = "SELECT * FROM product_reviews WHERE product_id = ? AND status = 'active' ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$product_id]);
    $reviews = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = '오류가 발생했습니다: ' . $e->getMessage();
}

// 모든 처리가 완료된 후 출력 시작
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name'] ?? '') ?> 후기 - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/store.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main >
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <h1>상품 후기</h1>
                <div class="breadcrumb">
                    <a href="/">홈</a> > 
                    <a href="/pages/store/">스토어</a> > 
                    <a href="/pages/store/product_detail.php?id=<?= $product_id ?>"><?= htmlspecialchars($product['name']) ?></a> > 
                    <span>후기</span>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Review Form -->
            <div class="review-form-section">
                <h2>후기 작성하기</h2>
                <form method="post" enctype="multipart/form-data" class="review-form">
                    <input type="hidden" name="action" value="add_review">
                    
                    <div class="form-group">
                        <label for="rating">평점 *</label>
                        <div class="rating-input">
                            <select name="rating" id="rating" required>
                                <option value="5">⭐⭐⭐⭐⭐ (5점) - 매우 만족</option>
                                <option value="4">⭐⭐⭐⭐ (4점) - 만족</option>
                                <option value="3">⭐⭐⭐ (3점) - 보통</option>
                                <option value="2">⭐⭐ (2점) - 불만족</option>
                                <option value="1">⭐ (1점) - 매우 불만족</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="author">작성자명 *</label>
                        <input type="text" id="author" name="author" required placeholder="작성자명을 입력하세요" 
                               value="<?= $currentUser ? htmlspecialchars($currentUser['name'] ?? '') : '' ?>">
                        <?php if ($currentUser): ?>
                            <div class="form-help">로그인된 사용자명이 기본값으로 설정됩니다. 필요시 수정 가능합니다.</div>
                        <?php endif; ?>
                    </div>


                    <div class="form-group">
                        <label for="content">후기 내용 *</label>
                        <textarea id="content" name="content" rows="5" required placeholder="상품에 대한 솔직한 후기를 작성해주세요"><?= htmlspecialchars($content ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="review_images">후기 이미지 (최대 5장)</label>
                        <input type="file" id="review_images" name="review_images[]" multiple accept="image/*" class="file-input">
                        <div class="form-help">JPG, PNG, GIF, WebP 형식의 이미지를 업로드할 수 있습니다.</div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">후기 등록</button>
                        <a href="/pages/store/product_detail.php?id=<?= $product_id ?>" class="btn btn-outline">취소</a>
                    </div>
                </form>
            </div>

            <!-- Reviews List -->
            <div class="reviews-list-section">
                <h2>상품 후기 (<?= count($reviews) ?>개)</h2>
                
                <?php if (empty($reviews)): ?>
                    <div class="no-reviews">
                        <p>아직 등록된 후기가 없습니다.</p>
                        <p>첫 번째 후기를 작성해보세요!</p>
                    </div>
                <?php else: ?>
                    <div class="reviews-list">
                        <?php foreach ($reviews as $review): ?>
                        <div class="review-item">
                            <div class="review-header">
                                <div class="review-meta">
                                    <span class="reviewer-name"><?= htmlspecialchars($review['user_name'] ?? '익명') ?></span>
                                    <span class="review-rating">
                                        <?= str_repeat('⭐', $review['rating']) ?>
                                    </span>
                                    <span class="review-date"><?= date('Y.m.d', strtotime($review['created_at'])) ?></span>
                                </div>
                                
                                <?php if ($currentUser && (
                                    $currentUser['user_level'] >= 9 || 
                                    $currentUser['id'] == $review['user_id'] || 
                                    $currentUser['name'] == $review['user_name']
                                )): ?>
                                <form method="post" class="delete-form" onsubmit="return confirm('정말 삭제하시겠습니까?')">
                                    <input type="hidden" name="action" value="delete_review">
                                    <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                                    <button type="submit" class="btn btn-delete">삭제</button>
                                </form>
                                <?php endif; ?>
                            </div>
                            
                            <div class="review-content">
                                <h4><?= htmlspecialchars($review['title']) ?></h4>
                                <p><?= nl2br(htmlspecialchars($review['content'])) ?></p>
                                
                                <?php if ($review['images']): ?>
                                    <?php $images = json_decode($review['images'], true); ?>
                                    <?php if (is_array($images)): ?>
                                    <div class="review-images">
                                        <?php foreach ($images as $image): ?>
                                            <img src="<?= htmlspecialchars($image) ?>" alt="후기 이미지" onclick="openImageModal(this.src)">
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Back Button -->
            <div class="back-to-product">
                <a href="/pages/store/product_detail.php?id=<?= $product_id ?>" class="btn btn-outline">← 상품으로 돌아가기</a>
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
    
    <style>
        .review-main {
            padding: 2rem 0;
            background: #f8f9fa;
            min-height: 80vh;
        }
        
        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .breadcrumb {
            margin-top: 0.5rem;
            color: #666;
            font-size: 0.9rem;
        }
        
        .breadcrumb a {
            color: #007bff;
            text-decoration: none;
        }
        
        .review-form-section, .reviews-list-section {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .form-group textarea {
            resize: vertical;
        }
        
        .form-help {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.25rem;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-outline {
            background: white;
            color: #007bff;
            border: 1px solid #007bff;
        }
        
        .btn-outline:hover {
            background: #007bff;
            color: white;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
        
        .btn-delete:hover {
            background: #c82333;
        }
        
        .review-item {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .review-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
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
            font-size: 0.9rem;
        }
        
        .delete-form {
            margin: 0;
        }
        
        .review-content h4 {
            margin: 0 0 0.5rem 0;
            color: #333;
        }
        
        .review-content p {
            color: #666;
            line-height: 1.6;
            margin: 0;
        }
        
        .review-images {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        
        .review-images img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 4px;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        .review-images img:hover {
            transform: scale(1.05);
        }
        
        .no-reviews {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .back-to-product {
            text-align: center;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
        
        @media (max-width: 768px) {
            .review-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .review-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .review-images img {
                width: 80px;
                height: 80px;
            }
        }
    </style>
    
    <script>
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
    </script>
</body>
</html>
<?php
// Initialize session and auth before any output
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
require_once $base_path . '/classes/Database.php';

$auth = Auth::getInstance();
$auth->requireAdmin();

$success = '';
$error = '';
$product = null;

// Get product ID
$product_id = $_GET['id'] ?? 0;
if (!$product_id) {
    header('Location: index.php');
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();
    
    // Get product details
    $sql = "SELECT * FROM products WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        header('Location: index.php?error=상품을 찾을 수 없습니다');
        exit;
    }
    
} catch (Exception $e) {
    $error = '상품 정보를 불러올 수 없습니다.';
}

// Handle product update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $product) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $detailed_description = trim($_POST['detailed_description'] ?? '');
    $features = trim($_POST['features'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $category_id = intval($_POST['category_id'] ?? 1);
    
    $stock_quantity = intval($_POST['stock_quantity'] ?? 0);
    $weight = trim($_POST['weight'] ?? '');
    $dimensions = trim($_POST['dimensions'] ?? '');
    $image_url = trim($_POST['image_url'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    
    if (empty($name)) {
        $error = '상품명을 입력해주세요.';
    } elseif ($price <= 0) {
        $error = '올바른 가격을 입력해주세요.';
    } else {
        try {
            // 카테고리명 가져오기
            $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
            $stmt->execute([$category_id]);
            $category_name = $stmt->fetchColumn();
            if (!$category_name) {
                $category_name = '일반';
            }
            
            // Process features into JSON
            $features_array = [];
            if (!empty($features)) {
                $features_lines = array_filter(array_map('trim', explode("\n", $features)));
                $features_array = $features_lines;
            }
            $features_json = !empty($features_array) ? json_encode($features_array, JSON_UNESCAPED_UNICODE) : null;
            
            $media_json = null;
            
            $sql = "UPDATE products SET 
                    name = ?, description = ?, detailed_description = ?, features = ?, price = ?, category_id = ?, 
                    stock_quantity = ?, weight = ?, dimensions = ?, image_url = ?, 
                    status = ?, is_featured = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $name, $description, $detailed_description, $features_json, $price, $category_id, 
                $stock_quantity, $weight, $dimensions, $image_url, $status, $is_featured, $product_id
            ]);
            
            $success = '상품이 성공적으로 수정되었습니다.';
            
            // Update local product data
            $product['name'] = $name;
            $product['description'] = $description;
            $product['price'] = $price;
            $product['category_id'] = $category_id;
            $product['category'] = $category_name;
            $product['stock_quantity'] = $stock_quantity;
            $product['weight'] = $weight;
            $product['dimensions'] = $dimensions;
            $product['image_url'] = $image_url;
            $product['status'] = $status;
            $product['is_featured'] = $is_featured;
            
        } catch (Exception $e) {
            $error = '상품 수정에 실패했습니다: ' . $e->getMessage();
        }
    }
}

// 카테고리 목록 가져오기
$categories = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
    $categories = $stmt->fetchAll();
    if (empty($categories)) {
        $categories = [['id' => 1, 'name' => '일반']];
    }
} catch (Exception $e) {
    $categories = [['id' => 1, 'name' => '일반']];
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>상품 수정 - 탄생 관리자</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <link rel="stylesheet" href="/assets/css/korean-editor.css">
    <script src="/assets/js/korean-editor.js"></script>
</head>
<body class="admin-body">
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="admin-container">
        <?php include '../includes/admin_sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-content">
                <div class="page-header">
                    <div class="page-title">
                        <h1>상품 수정</h1>
                        <p><?= htmlspecialchars($product['name'] ?? '') ?></p>
                    </div>
                    <div class="page-actions">
                        <a href="index.php" class="btn btn-outline">목록으로</a>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <strong>성공:</strong> <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <strong>오류:</strong> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($product): ?>
                <div class="admin-card">
                    <form method="post" enctype="multipart/form-data" class="product-form">
                        <div class="form-grid">
                            <div class="form-section">
                                <h3>기본 정보</h3>
                                
                                <div class="form-group">
                                    <label class="form-label" for="name">상품명 *</label>
                                    <input type="text" id="name" name="name" class="form-input" 
                                           value="<?= htmlspecialchars($product['name']) ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="description">상품 요약 설명</label>
                                    <textarea id="description" name="description" class="form-input" rows="3"
                                              placeholder="상품의 간단한 요약 설명 (목록에 표시됨)"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="detailed_description">상품 상세 설명</label>
                                    <textarea id="detailed_description" name="detailed_description" class="form-input" data-korean-editor
                                              data-height="500px" data-upload-url="/admin/api/image_upload.php"
                                              placeholder="상품에 대한 자세한 설명을 입력하세요. 이미지, 글꼴, 색상 등을 자유롭게 편집할 수 있습니다."><?= htmlspecialchars($product['detailed_description'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="features">상품 주요 특징</label>
                                    <textarea id="features" name="features" class="form-input" rows="4"
                                              placeholder="특징을 한 줄에 하나씩 입력하세요"><?php 
                                        if (!empty($product['features'])) {
                                            $features_array = json_decode($product['features'], true);
                                            if (is_array($features_array)) {
                                                echo htmlspecialchars(implode("\n", $features_array));
                                            }
                                        }
                                    ?></textarea>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label" for="price">가격 *</label>
                                        <input type="number" id="price" name="price" class="form-input" 
                                               value="<?= $product['price'] ?>" min="0" step="0.01" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label" for="category_id">카테고리</label>
                                        <select id="category_id" name="category_id" class="form-input">
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?= $category['id'] ?>" 
                                                        <?= $product['category_id'] == $category['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($category['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">상품 메인 이미지</label>
                                    
                                    <?php if ($product['image_url']): ?>
                                        <div class="current-image" style="margin-bottom: 15px;">
                                            <strong>현재 이미지:</strong><br>
                                            <img src="<?= htmlspecialchars($product['image_url']) ?>" 
                                                 alt="현재 상품 이미지" style="max-width: 200px; margin-top: 10px; border-radius: 4px;">
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="image-upload-container">
                                        <div class="upload-method">
                                            <label class="upload-option">
                                                <input type="radio" name="image_method" value="url" checked onchange="toggleImageMethod('url')">
                                                <span>URL로 이미지 변경</span>
                                            </label>
                                            <label class="upload-option">
                                                <input type="radio" name="image_method" value="file" onchange="toggleImageMethod('file')">
                                                <span>컴퓨터에서 이미지 업로드</span>
                                            </label>
                                        </div>
                                        
                                        <div id="url-input" class="input-section">
                                            <input type="url" id="image_url" name="image_url" class="form-input" 
                                                   value="<?= htmlspecialchars($product['image_url'] ?? '') ?>" 
                                                   placeholder="https://example.com/image.jpg">
                                            <div class="form-help">새로운 이미지 URL을 입력하세요.</div>
                                        </div>
                                        
                                        <div id="file-input" class="input-section" style="display: none;">
                                            <input type="file" id="product_image" name="product_image" class="form-input" 
                                                   accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                                            <div class="form-help">새로운 이미지 파일을 선택하세요.</div>
                                        </div>
                                    </div>
                                </div>
                                
                            </div>
                            
                            <div class="form-section">
                                <h3>재고 및 배송 정보</h3>
                                
                                <div class="form-group">
                                    <label class="form-label" for="stock_quantity">재고 수량</label>
                                    <input type="number" id="stock_quantity" name="stock_quantity" class="form-input" 
                                           value="<?= $product['stock'] ?>" min="0">
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label" for="weight">무게</label>
                                        <input type="text" id="weight" name="weight" class="form-input" 
                                               value="<?= htmlspecialchars($product['weight'] ?? '') ?>" 
                                               placeholder="예: 1kg, 500g">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label" for="dimensions">크기</label>
                                        <input type="text" id="dimensions" name="dimensions" class="form-input" 
                                               value="<?= htmlspecialchars($product['dimensions'] ?? '') ?>" 
                                               placeholder="예: 30x20x10cm">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="status">상품 상태</label>
                                    <select id="status" name="status" class="form-input">
                                        <option value="active" <?= $product['status'] === 'active' ? 'selected' : '' ?>>판매중</option>
                                        <option value="inactive" <?= $product['status'] === 'inactive' ? 'selected' : '' ?>>미판매</option>
                                        <option value="out_of_stock" <?= $product['status'] === 'out_of_stock' ? 'selected' : '' ?>>품절</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-checkbox">
                                        <input type="checkbox" name="is_featured" <?= $product['is_featured'] ? 'checked' : '' ?>>
                                        <span>추천 상품으로 설정</span>
                                    </label>
                                </div>
                                
                                <div class="product-info">
                                    <div class="info-item">
                                        <strong>등록일:</strong> <?= date('Y-m-d H:i', strtotime($product['created_at'])) ?>
                                    </div>
                                    <div class="info-item">
                                        <strong>수정일:</strong> <?= date('Y-m-d H:i', strtotime($product['updated_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">상품 수정</button>
                            <a href="index.php" class="btn btn-outline">취소</a>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="/assets/js/main.js"></script>
    
    <style>
        .admin-container {
            display: flex;
            min-height: 100vh;
            background-color: #f8f9fa;
        }
        
        .admin-content {
            flex: 1;
            padding: 30px;
            margin-left: 250px;
        }
        
        .page-header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .page-title h1 {
            margin: 0 0 5px 0;
            color: #333;
            font-size: 1.8rem;
        }
        
        .page-title p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }
        
        .admin-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .product-form {
            padding: 30px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 30px;
        }
        
        .form-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
        }
        
        .form-section h3 {
            margin: 0 0 20px 0;
            color: #333;
            font-size: 1.2rem;
            font-weight: 600;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .form-checkbox input[type="checkbox"] {
            margin: 0;
        }
        
        .product-info {
            margin-top: 20px;
            padding: 15px;
            background: #e9ecef;
            border-radius: 6px;
        }
        
        .info-item {
            margin-bottom: 5px;
            font-size: 13px;
            color: #666;
        }
        
        .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        
        .media-item {
            text-align: center;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #f9f9f9;
        }
        
        .current-media h5 {
            margin: 10px 0 5px 0;
            color: #333;
        }
        
        .image-upload-container {
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 20px;
            background: #f8f9fa;
        }
        
        .upload-method {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .upload-option {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .upload-option input[type="radio"] {
            margin: 0;
        }
        
        .input-section {
            transition: all 0.3s ease;
        }
        
        .form-actions {
            padding-top: 20px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 15px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #0056b3;
        }
        
        .btn-outline {
            background-color: white;
            color: #666;
            border: 1px solid #ddd;
        }
        
        .btn-outline:hover {
            background-color: #f8f9fa;
        }
        
        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 6px;
            border-left: 4px solid;
        }
        
        .alert-success {
            background-color: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }
        
        @media (max-width: 768px) {
            .admin-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
    
    <script>
        // 에디터는 자동으로 초기화됩니다 (data-korean-editor 속성 사용)
        
        function toggleImageMethod(method) {
            const urlInput = document.getElementById('url-input');
            const fileInput = document.getElementById('file-input');
            
            if (method === 'url') {
                urlInput.style.display = 'block';
                fileInput.style.display = 'none';
            } else {
                urlInput.style.display = 'none';
                fileInput.style.display = 'block';
            }
        }
    </script>
</body>
</html>
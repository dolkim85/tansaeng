<?php
// Initialize session and auth before any output
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
$auth = Auth::getInstance();
$auth->requireAdmin();

require_once $base_path . '/classes/Database.php';

$success = '';
$error = '';

// 상품 추가 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 디버깅을 위한 POST 데이터 로깅
    error_log("=== 새상품 추가 POST 데이터 ===");
    error_log("image_method: " . ($_POST['image_method'] ?? 'not set'));
    error_log("image_url: " . ($_POST['image_url'] ?? 'not set'));
    error_log("cropped_image_data exists: " . (!empty($_POST['cropped_image_data']) ? 'yes' : 'no'));
    error_log("file upload exists: " . (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK ? 'yes' : 'no'));

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $detailed_description = trim($_POST['detailed_description'] ?? '');
    $features = trim($_POST['features'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $category_id = intval($_POST['category_id'] ?? 1);
    $stock_quantity = intval($_POST['stock_quantity'] ?? 0);
    $weight = trim($_POST['weight'] ?? '') ?: null;
    $dimensions = trim($_POST['dimensions'] ?? '') ?: null;
    $status = $_POST['status'] ?? 'active';
    // 새로 등록되는 상품은 기본적으로 추천 상품으로 설정하여 메인페이지에 바로 표시
    $is_featured = isset($_POST['is_featured']) ? 1 : 1; // 기본값을 1로 변경

    // 이미지 업데이트 방법에 따른 처리
    $image_method = $_POST['image_method'] ?? 'url';
    $posted_image_url = trim($_POST['image_url'] ?? '');
    $image_url = '';

    // URL 방법을 선택했고, URL이 입력된 경우
    if ($image_method === 'url' && !empty($posted_image_url)) {
        $image_url = $posted_image_url;
        error_log("이미지 URL로 설정: " . $image_url);
    }

    // 이미지 처리 로직 - 우선순위 대로 처리
    try {
        // 1순위: 크롭된 이미지 데이터가 있는 경우
        if (!empty($_POST['cropped_image_data'])) {
            error_log("Processing cropped image data");
            $cropped_data = $_POST['cropped_image_data'];

            if (preg_match('/^data:image\/(png|jpg|jpeg);base64,/', $cropped_data, $matches)) {
                $base64_data = preg_replace('/^data:image\/(png|jpg|jpeg);base64,/', '', $cropped_data);
                $image_data = base64_decode($base64_data);

                if ($image_data !== false && strlen($image_data) > 0) {
                    $upload_dir = $base_path . '/uploads/products/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    $new_filename = uniqid('product_cropped_') . '.jpg';
                    $upload_path = $upload_dir . $new_filename;

                    if (file_put_contents($upload_path, $image_data)) {
                        $image_url = '/uploads/products/' . $new_filename;
                        error_log("✅ Cropped image saved: " . $image_url);
                    } else {
                        throw new Exception('크롭된 이미지 파일 저장 실패');
                    }
                } else {
                    throw new Exception('크롭된 이미지 데이터 디코딩 실패');
                }
            } else {
                throw new Exception('크롭된 이미지 형식 오류');
            }
        }
        // 2순위: 파일 업로드가 있는 경우 (크롭 데이터가 없을 때)
        elseif (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            error_log("Processing file upload: " . $_FILES['product_image']['name']);

            $upload_dir = $base_path . '/uploads/products/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_extension = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($file_extension, $allowed_extensions)) {
                throw new Exception('지원하지 않는 이미지 형식입니다. (JPG, PNG, GIF, WebP만 가능)');
            }

            $new_filename = uniqid('product_') . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $upload_path)) {
                $image_url = '/uploads/products/' . $new_filename;
                error_log("✅ File uploaded: " . $image_url);
            } else {
                throw new Exception('파일 이동 실패');
            }
        }
        // 3순위: URL이 입력된 경우
        elseif ($image_method === 'url' && !empty($posted_image_url)) {
            // 이미 위에서 처리됨
            error_log("✅ Image URL set: " . $image_url);
        }
        else {
            error_log("⚠️ No image data provided");
        }

    } catch (Exception $imageError) {
        error_log("❌ Image processing error: " . $imageError->getMessage());
        $error = $imageError->getMessage();
    }

    // 기본 이미지 설정 (이미지가 없는 경우)
    if (empty($image_url)) {
        // placeholder 이미지 사용
        $image_url = '/assets/images/products/placeholder.jpg';
        error_log("📷 Using placeholder image: " . $image_url);
    }

    // 최종 이미지 URL 상태 로깅
    error_log("Final image_url: " . ($image_url ?: '(empty)'));

    if (empty($name)) {
        $error = '상품명을 입력해주세요.';
    } elseif ($price <= 0) {
        $error = '올바른 가격을 입력해주세요.';
    } elseif (!$error) {
        try {
            $pdo = Database::getInstance()->getConnection();
            
            // 기존 products 테이블에 필요한 컬럼들 추가
            try {
                // weight 컬럼이 DECIMAL이면 VARCHAR로 변경
                $pdo->exec("ALTER TABLE products MODIFY COLUMN weight VARCHAR(50) NULL");
            } catch (Exception $e) {
                try {
                    // weight 컬럼 추가
                    $pdo->exec("ALTER TABLE products ADD COLUMN weight VARCHAR(50) NULL");
                } catch (Exception $e2) {
                    // 이미 존재하는 경우 무시
                }
            }
            
            try {
                // dimensions 컬럼 추가 또는 타입 변경
                $pdo->exec("ALTER TABLE products MODIFY COLUMN dimensions VARCHAR(100) NULL");
            } catch (Exception $e) {
                try {
                    $pdo->exec("ALTER TABLE products ADD COLUMN dimensions VARCHAR(100) NULL");
                } catch (Exception $e2) {
                    // 이미 존재하는 경우 무시
                }
            }
            
            try {
                // image_url 컬럼 추가
                $pdo->exec("ALTER TABLE products ADD COLUMN image_url VARCHAR(500)");
            } catch (Exception $e) {
                // 이미 존재하는 경우 무시
            }
            
            try {
                // is_featured 컬럼 추가
                $pdo->exec("ALTER TABLE products ADD COLUMN is_featured BOOLEAN DEFAULT FALSE");
            } catch (Exception $e) {
                // 이미 존재하는 경우 무시
            }

            // 상품카드에 필요한 추가 컬럼들
            try {
                $pdo->exec("ALTER TABLE products ADD COLUMN discount_percentage INT DEFAULT 0");
            } catch (Exception $e) {
                // 이미 존재하는 경우 무시
            }

            try {
                $pdo->exec("ALTER TABLE products ADD COLUMN rating_score DECIMAL(2,1) DEFAULT 4.5");
            } catch (Exception $e) {
                // 이미 존재하는 경우 무시
            }

            try {
                $pdo->exec("ALTER TABLE products ADD COLUMN review_count INT DEFAULT 0");
            } catch (Exception $e) {
                // 이미 존재하는 경우 무시
            }

            try {
                $pdo->exec("ALTER TABLE products ADD COLUMN delivery_info VARCHAR(100) DEFAULT '무료배송'");
            } catch (Exception $e) {
                // 이미 존재하는 경우 무시
            }

            try {
                $pdo->exec("ALTER TABLE products ADD COLUMN shipping_cost DECIMAL(10,2) DEFAULT 0");
            } catch (Exception $e) {
                // 이미 존재하는 경우 무시
            }

            try {
                $pdo->exec("ALTER TABLE products ADD COLUMN shipping_unit_count INT DEFAULT 1");
            } catch (Exception $e) {
                // 이미 존재하는 경우 무시
            }

            try {
                $pdo->exec("ALTER TABLE products ADD COLUMN features TEXT");
            } catch (Exception $e) {
                // 이미 존재하는 경우 무시
            }

            try {
                $pdo->exec("ALTER TABLE products ADD COLUMN detailed_description TEXT");
            } catch (Exception $e) {
                // 이미 존재하는 경우 무시
            }
            
            // 카테고리 테이블이 없으면 생성
            $sql = "CREATE TABLE IF NOT EXISTS categories (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL UNIQUE,
                description TEXT,
                display_order INT DEFAULT 0,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            $pdo->exec($sql);
            
            // 기본 카테고리 삽입
            $sql = "INSERT IGNORE INTO categories (id, name, description) VALUES 
                    (1, '배지', '코코피트, 펄라이트 등 재배용 배지'),
                    (2, '농업용품', '농업에 필요한 각종 도구 및 용품'),
                    (3, '양액', '식물 성장에 필요한 영양액'),
                    (4, '기타', '기타 상품')";
            $pdo->exec($sql);
            
            // Get category name for backward compatibility
            $category_name = '일반';
            if ($category_id) {
                $cat_sql = "SELECT name FROM categories WHERE id = ?";
                $cat_stmt = $pdo->prepare($cat_sql);
                $cat_stmt->execute([$category_id]);
                $cat_result = $cat_stmt->fetchColumn();
                if ($cat_result) $category_name = $cat_result;
            }

            // Process features into JSON
            $features_array = [];
            if (!empty($features)) {
                $features_lines = array_filter(array_map('trim', explode("\n", $features)));
                $features_array = $features_lines;
            }
            $features_json = !empty($features_array) ? json_encode($features_array, JSON_UNESCAPED_UNICODE) : null;
            $media_json = null;
            
            // 새로운 상품카드 필드들 추가
            $discount_percentage = intval($_POST['discount_percentage'] ?? 0);
            $rating_score = floatval($_POST['rating_score'] ?? 4.5);
            $review_count = intval($_POST['review_count'] ?? 0);
            $delivery_info = trim($_POST['delivery_info'] ?? '무료배송');
            $shipping_type = $_POST['shipping_type'] ?? 'free';
            $shipping_cost = ($shipping_type === 'paid') ? floatval($_POST['shipping_cost'] ?? 0) : 0;
            $shipping_unit_count = intval($_POST['shipping_unit_count'] ?? 1);

            // 이미지 URL 확인
            if (!empty($image_url)) {
                error_log("Product will be saved with image: " . $image_url);
            }

            // 상품 추가 (새로운 필드들 포함)
            $sql = "INSERT INTO products (name, description, detailed_description, features, price, category_id, stock_quantity, weight, dimensions, image_url, status, is_featured, discount_percentage, rating_score, review_count, delivery_info, shipping_cost, shipping_unit_count)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $name, $description, $detailed_description, $features_json, $price, $category_id, $stock_quantity,
                $weight, $dimensions, $image_url, $status, $is_featured,
                $discount_percentage, $rating_score, $review_count, $delivery_info, $shipping_cost, $shipping_unit_count
            ]);
            
            $success = '상품이 성공적으로 등록되었습니다.';
            
            // 폼 데이터 초기화
            $name = $description = $detailed_description = $features = $weight = $dimensions = $image_url = '';
            $price = $stock_quantity = $category_id = 0;
            $status = 'active';
            $is_featured = false;
            
        } catch (Exception $e) {
            $error = '상품 등록에 실패했습니다: ' . $e->getMessage();
        }
    }
}

// 카테고리 목록 가져오기
$categories = [];
try {
    $pdo = Database::getInstance()->getConnection();
    $sql = "SELECT id, name FROM categories ORDER BY name";
    $stmt = $pdo->query($sql);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // 카테고리 테이블이 없는 경우 기본값 사용
    $categories = [
        ['id' => 1, 'name' => '배지'],
        ['id' => 2, 'name' => '농업용품'],
        ['id' => 3, 'name' => '양액'],
        ['id' => 4, 'name' => '기타']
    ];
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>상품 추가 - 탄생 관리자</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <!-- Cropper.js CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
    <link rel="stylesheet" href="../../assets/css/korean-editor.css">
    <script src="../../assets/js/korean-editor.js"></script>
</head>
<body class="admin-body">
    <?php include '../includes/admin_header.php'; ?>

    <div class="admin-container">
        <?php include '../includes/admin_sidebar.php'; ?>

        <main class="admin-main">
            <div class="admin-content">
                <div class="page-header">
                    <div class="page-title">
                        <h1>📦 상품 추가</h1>
                        <p>새로운 상품을 등록합니다</p>
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
            
                <form method="post" enctype="multipart/form-data" class="admin-form">
                    <div class="form-section">
                        <div class="section-header">
                            <span class="section-icon">📝</span>
                            <h3>기본 정보</h3>
                        </div>
                        <div class="section-body">
                        
                            <div class="form-group">
                                <label for="name">상품명 <span class="required">*</span></label>
                                <input type="text" id="name" name="name" class="form-control"
                                       value="<?= htmlspecialchars($name ?? '') ?>" required
                                       placeholder="예: 프리미엄 코코피트 배지">
                            </div>

                            <div class="form-group">
                                <label for="description">상품 요약 설명</label>
                                <textarea id="description" name="description" class="form-control"
                                          placeholder="상품의 간단한 요약 설명 (목록에 표시됨)"><?= htmlspecialchars($description ?? '') ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="detailed_description">상품 상세 설명</label>
                                <textarea id="detailed_description" name="detailed_description" class="form-control large" data-korean-editor
                                          data-height="500px" data-upload-url="/admin/api/image_upload.php"
                                          placeholder="상품에 대한 자세한 설명을 입력하세요."><?= htmlspecialchars($detailed_description ?? '') ?></textarea>
                                <small>네이버 블로그 스타일 에디터로 상품 상세 설명을 작성하세요.</small>
                            </div>

                            <div class="form-group">
                                <label for="features">상품 주요 특징</label>
                                <textarea id="features" name="features" class="form-control"
                                          placeholder="특징 1&#10;특징 2&#10;특징 3&#10;각 줄에 하나씩 입력하세요"><?= htmlspecialchars($features ?? '') ?></textarea>
                                <small>상품의 주요 특징을 한 줄에 하나씩 입력하세요.</small>
                            </div>
                        
                        <div class="form-group main-image-section">
                            <label class="form-label">상품 메인 이미지 <span class="required">*</span></label>
                            <div class="form-help">상품의 대표 이미지를 설정하세요. 이 이미지는 메인 페이지와 상품 목록에 표시됩니다.</div>

                            <div class="image-upload-container enhanced">
                                <div class="upload-methods-header">
                                    <h4>🖼️ 이미지 업로드 방법 선택</h4>
                                </div>
                                <div class="upload-method-cards">
                                    <label class="method-card active" data-method="url">
                                        <input type="radio" name="image_method" value="url" checked onchange="toggleImageMethod('url')">
                                        <div class="method-content">
                                            <div class="method-icon">🔗</div>
                                            <div class="method-details">
                                                <strong>URL 링크</strong>
                                                <p>웹상의 이미지 주소를 직접 입력</p>
                                                <small>빠르고 간편한 방법</small>
                                            </div>
                                        </div>
                                    </label>
                                    <label class="method-card" data-method="file">
                                        <input type="radio" name="image_method" value="file" onchange="toggleImageMethod('file')">
                                        <div class="method-content">
                                            <div class="method-icon">📁</div>
                                            <div class="method-details">
                                                <strong>파일 업로드</strong>
                                                <p>컴퓨터의 이미지 파일 업로드</p>
                                                <small>자르기/편집 기능 지원</small>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                                
                                <div id="url-input" class="input-section">
                                    <input type="url" id="image_url" name="image_url" class="form-input"
                                           value="<?= htmlspecialchars($image_url ?? '') ?>"
                                           placeholder="https://example.com/image.jpg"
                                           onchange="previewImageFromUrl()" oninput="previewImageFromUrl()">
                                    <div class="form-help">외부 이미지 URL을 입력하세요.</div>
                                </div>

                                <div id="file-input" class="input-section" style="display: none;">
                                    <input type="file" id="product_image" name="product_image" class="form-input"
                                           accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                                           onchange="previewImageFromFile(this)">
                                    <div class="form-help">JPG, PNG, GIF, WebP 형식의 이미지 파일을 선택하세요.</div>
                                </div>

                                <!-- 이미지 가이드라인 -->
                                <div class="image-guidelines-box">
                                    <div class="guidelines-header">
                                        <span class="guidelines-icon">📸</span>
                                        <h4>이미지 가이드라인</h4>
                                    </div>
                                    <div class="guidelines-content">
                                        <div class="guideline-row">
                                            <div class="guideline-item recommended">
                                                <span class="item-label">최적 크기</span>
                                                <span class="item-value">800x600px 이상</span>
                                            </div>
                                            <div class="guideline-item">
                                                <span class="item-label">최대 용량</span>
                                                <span class="item-value">5MB</span>
                                            </div>
                                        </div>
                                        <div class="guideline-row">
                                            <div class="guideline-item">
                                                <span class="item-label">지원 형식</span>
                                                <span class="item-value">JPG, PNG, WebP, GIF</span>
                                            </div>
                                            <div class="guideline-item">
                                                <span class="item-label">권장 비율</span>
                                                <span class="item-value">4:3 또는 16:9</span>
                                            </div>
                                        </div>
                                        <div class="guideline-tip">
                                            💡 <strong>팁:</strong> 상품 전체가 잘 보이고 배경이 깔끔한 이미지를 사용하세요.
                                        </div>
                                    </div>
                                </div>

                                <!-- 이미지 미리보기 및 편집 영역 -->
                                <div id="image-preview-container" class="image-preview-container" style="display: none;">
                                    <div class="preview-header">
                                        <h4>이미지 미리보기 및 편집</h4>
                                        <button type="button" onclick="removePreview()" class="btn-remove">✕</button>
                                    </div>

                                    <div class="preview-tabs">
                                        <button type="button" class="tab-btn active" onclick="switchTab('preview')">미리보기</button>
                                        <button type="button" class="tab-btn" onclick="switchTab('crop')">자르기/편집</button>
                                    </div>

                                    <div id="preview-tab" class="tab-content active">
                                        <div class="preview-content">
                                            <div class="preview-image-wrapper">
                                                <img id="preview-image" src="" alt="미리보기">
                                            </div>
                                            <div class="image-info">
                                                <span id="image-dimensions">크기: -</span>
                                                <span id="image-size">용량: -</span>
                                                <span id="image-ratio">비율: -</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="crop-tab" class="tab-content">
                                        <div class="crop-content">
                                            <div class="crop-controls">
                                                <button type="button" onclick="setAspectRatio(4/3)" class="btn btn-sm">4:3 비율</button>
                                                <button type="button" onclick="setAspectRatio(1)" class="btn btn-sm">1:1 비율</button>
                                                <button type="button" onclick="setAspectRatio(16/9)" class="btn btn-sm">16:9 비율</button>
                                                <button type="button" onclick="setAspectRatio(0)" class="btn btn-sm">자유 비율</button>
                                                <button type="button" onclick="resetCrop()" class="btn btn-sm">초기화</button>
                                                <button type="button" onclick="applyCrop()" class="btn btn-sm btn-primary">적용</button>
                                            </div>
                                            <div class="crop-image-wrapper">
                                                <img id="crop-image" src="" alt="편집할 이미지">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="price">
                                    가격 (원) <span class="required">*</span>
                                </label>
                                <input type="number" id="price" name="price" class="form-input" 
                                       value="<?= htmlspecialchars($price ?? '') ?>" 
                                       min="0" step="100" required>
                                <div class="form-help">세금 포함 가격을 입력하세요.</div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="category_id">카테고리</label>
                                <select id="category_id" name="category_id" class="form-select">
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>" 
                                                <?= (($category_id ?? 1) == $category['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-help">상품이 속할 카테고리를 선택하세요.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3 class="form-section-title">재고 및 물리 정보</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="stock_quantity">재고 수량</label>
                                <input type="number" id="stock_quantity" name="stock_quantity" class="form-input" 
                                       value="<?= htmlspecialchars($stock_quantity ?? '0') ?>" min="0">
                                <div class="form-help">현재 보유 중인 재고 수량입니다.</div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="weight">중량</label>
                                <input type="text" id="weight" name="weight" class="form-input" 
                                       value="<?= htmlspecialchars($weight ?? '') ?>"
                                       placeholder="예: 10kg, 500g">
                                <div class="form-help">상품의 무게를 입력하세요.</div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="dimensions">크기/규격</label>
                            <input type="text" id="dimensions" name="dimensions" class="form-input" 
                                   value="<?= htmlspecialchars($dimensions ?? '') ?>"
                                   placeholder="예: 30x20x10cm, 50L">
                            <div class="form-help">상품의 크기나 규격을 입력하세요.</div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3 class="form-section-title">상품카드 표시 정보</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="discount_percentage">할인율 (%)</label>
                                <input type="number" id="discount_percentage" name="discount_percentage" class="form-input"
                                       value="<?= htmlspecialchars($discount_percentage ?? '0') ?>" min="0" max="100">
                                <div class="form-help">0~100 사이의 숫자를 입력하세요. (0은 할인 없음)</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="rating_score">평점</label>
                                <input type="number" id="rating_score" name="rating_score" class="form-input"
                                       value="<?= htmlspecialchars($rating_score ?? '4.5') ?>" min="0" max="5" step="0.1">
                                <div class="form-help">0~5 사이의 점수를 입력하세요.</div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="review_count">리뷰 개수</label>
                                <input type="number" id="review_count" name="review_count" class="form-input"
                                       value="<?= htmlspecialchars($review_count ?? '0') ?>" min="0">
                                <div class="form-help">상품 리뷰의 총 개수입니다.</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="shipping_type">배송 방식</label>
                                <select id="shipping_type" name="shipping_type" class="form-select" onchange="toggleShippingFields()">
                                    <option value="free" <?= ($shipping_cost ?? 0) == 0 ? 'selected' : '' ?>>무료배송</option>
                                    <option value="paid" <?= ($shipping_cost ?? 0) > 0 ? 'selected' : '' ?>>유료배송</option>
                                </select>
                                <div class="form-help">배송 방식을 선택하세요.</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="delivery_info">배송정보</label>
                                <input type="text" id="delivery_info" name="delivery_info" class="form-input"
                                       value="<?= htmlspecialchars($delivery_info ?? '무료배송') ?>"
                                       placeholder="예: 무료배송, 당일배송">
                                <div class="form-help">상품카드에 표시될 배송정보입니다.</div>
                            </div>

                            <div id="shipping_cost_fields" style="display: <?= ($shipping_cost ?? 0) > 0 ? 'block' : 'none' ?>;">
                                <div class="form-group">
                                    <label class="form-label" for="shipping_cost">배송비 (원)</label>
                                    <input type="number" id="shipping_cost" name="shipping_cost" class="form-input"
                                           value="<?= htmlspecialchars($shipping_cost ?? '0') ?>"
                                           placeholder="0" min="0" step="100">
                                    <div class="form-help">배송비를 설정하세요. 상품 가격에 추가로 표시됩니다.</div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="shipping_unit_count">배송비 적용 단위 (개수)</label>
                                    <input type="number" id="shipping_unit_count" name="shipping_unit_count" class="form-input"
                                           value="<?= htmlspecialchars($shipping_unit_count ?? '1') ?>"
                                           placeholder="1" min="1" max="100">
                                    <div class="form-help">설정한 개수마다 배송비가 추가됩니다. (예: 10개 단위로 설정 시, 11~20개면 배송비 2배)</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="form-section-title">상품 설정</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="status">상태</label>
                                <select id="status" name="status" class="form-select">
                                    <option value="active" <?= (($status ?? 'active') === 'active') ? 'selected' : '' ?>>
                                        활성 (판매 중)
                                    </option>
                                    <option value="inactive" <?= (($status ?? 'active') === 'inactive') ? 'selected' : '' ?>>
                                        비활성 (판매 중단)
                                    </option>
                                </select>
                                <div class="form-help">상품의 현재 판매 상태입니다.</div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-checkbox">
                                    <input type="checkbox" name="is_featured"
                                           <?= ($is_featured ?? true) ? 'checked' : '' ?>>
                                    <span class="checkbox-text">추천 상품으로 설정</span>
                                </label>
                                <div class="form-help">✅ 기본적으로 체크됨 - 메인 페이지에 추천 상품으로 표시됩니다.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">💾 상품 등록</button>
                        <a href="index.php" class="btn btn-outline">취소</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <script src="/assets/js/main.js"></script>
    <script src="/assets/js/image-resize.js"></script>
    <script src="../../assets/js/korean-editor.js"></script>
    <script>
        // 에디터 초기화 확인 및 디버깅
        document.addEventListener('DOMContentLoaded', function() {
            console.log('페이지 로드 완료');

            setTimeout(() => {
                const editorContainer = document.querySelector('.korean-editor-container');
                const textarea = document.querySelector('textarea[data-korean-editor]');

                if (editorContainer) {
                    console.log('✅ 에디터가 성공적으로 초기화되었습니다.');
                } else if (textarea) {
                    console.log('❌ 에디터 초기화 실패. 수동으로 초기화를 시도합니다.');
                    // 수동 초기화
                    try {
                        const container = textarea.parentElement;
                        const editor = new KoreanEditor(container, {
                            height: textarea.dataset.height || '500px',
                            placeholder: textarea.placeholder || '내용을 입력하세요...',
                            imageUploadUrl: textarea.dataset.uploadUrl || '/admin/api/image_upload.php'
                        });
                        console.log('✅ 수동 초기화 성공');
                    } catch (error) {
                        console.error('❌ 수동 초기화 실패:', error);
                    }
                } else {
                    console.log('❌ 에디터 대상 요소를 찾을 수 없습니다.');
                }
            }, 1000);
        });

        // Cropper.js 변수
        let originalImageData = null;

        function toggleImageMethod(method) {
            const urlInput = document.getElementById('url-input');
            const fileInput = document.getElementById('file-input');

            // 방법 카드 활성화 상태 업데이트
            document.querySelectorAll('.method-card').forEach(card => {
                card.classList.remove('active');
            });
            document.querySelector(`[data-method="${method}"]`).classList.add('active');

            if (method === 'url') {
                urlInput.style.display = 'block';
                fileInput.style.display = 'none';
                document.getElementById('image_url').required = true;
                document.getElementById('product_image').required = false;
            } else {
                urlInput.style.display = 'none';
                fileInput.style.display = 'block';
                document.getElementById('image_url').required = false;
                document.getElementById('product_image').required = true;
            }
            // 미리보기 제거
            removePreview();
        }

        function previewImageFromUrl() {
            const url = document.getElementById('image_url').value;
            console.log('previewImageFromUrl called with:', url);
            if (url.trim()) {
                showImagePreview(url);
            } else {
                removePreview();
            }
        }

        function previewImageFromFile(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    showImagePreview(e.target.result);
                    updateFileInfo(input.files[0]);
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function showImagePreview(src) {
            console.log('showImagePreview called with:', src);
            const container = document.getElementById('image-preview-container');
            const previewImg = document.getElementById('preview-image');
            const cropImg = document.getElementById('crop-image');

            if (!container || !previewImg) {
                console.error('Required preview elements not found');
                return;
            }

            // 원본 이미지 데이터 저장
            originalImageData = src;

            previewImg.onload = function() {
                console.log('Image loaded successfully');
                // 이미지 정보 업데이트
                const width = this.naturalWidth;
                const height = this.naturalHeight;
                const ratio = (width / height).toFixed(2);

                const dimensionsEl = document.getElementById('image-dimensions');
                const ratioEl = document.getElementById('image-ratio');

                if (dimensionsEl) dimensionsEl.textContent = `크기: ${width}x${height}px`;
                if (ratioEl) ratioEl.textContent = `비율: ${ratio}:1`;

                container.style.display = 'block';
                switchTab('preview'); // 미리보기 탭으로 시작
            };

            previewImg.onerror = function() {
                console.error('Failed to load image:', src);
                alert('이미지를 불러올 수 없습니다. URL을 확인해주세요.');
            };

            previewImg.src = src;
            if (cropImg) cropImg.src = src;
        }

        function removePreview() {
            // Destroy cropper if exists
            if (window.mainCropper) {
                window.mainCropper.destroy();
                window.mainCropper = null;
            }

            const container = document.getElementById('image-preview-container');
            const img = document.getElementById('preview-image');
            const fileInput = document.getElementById('product_image');

            container.style.display = 'none';
            img.src = '';
            fileInput.value = '';
            document.getElementById('image-width').value = '';
            document.getElementById('image-height').value = '';
            document.getElementById('image-dimensions').textContent = '크기: -';
            document.getElementById('image-size').textContent = '용량: -';

            // Reset to preview tab
            switchTab('preview');
        }

        function resetImageSize() {
            const img = document.getElementById('preview-image');
            const originalWidth = parseInt(img.dataset.originalWidth);
            const originalHeight = parseInt(img.dataset.originalHeight);

            if (originalWidth && originalHeight) {
                document.getElementById('image-width').value = originalWidth;
                document.getElementById('image-height').value = originalHeight;
                applyImageSize();
            }
        }

        function applyImageSize() {
            const img = document.getElementById('preview-image');
            const width = document.getElementById('image-width').value;
            const height = document.getElementById('image-height').value;

            if (width) img.style.width = width + 'px';
            if (height) img.style.height = height + 'px';

            updateImageDimensions();
        }

        function updateImageDimensions() {
            const img = document.getElementById('preview-image');
            const width = img.offsetWidth || img.naturalWidth;
            const height = img.offsetHeight || img.naturalHeight;

            document.getElementById('image-dimensions').textContent =
                `크기: ${width} × ${height}px`;
        }

        function updateFileInfo(file) {
            const sizeKB = (file.size / 1024).toFixed(1);
            const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
            const sizeText = file.size > 1024 * 1024 ? `${sizeMB}MB` : `${sizeKB}KB`;
            document.getElementById('image-size').textContent = `용량: ${sizeText}`;
        }

        // 탭 전환 기능
        function switchTab(tabName) {
            // 탭 버튼 활성화
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelector(`[onclick="switchTab('${tabName}')"]`).classList.add('active');

            // 탭 컨텐츠 활성화
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.getElementById(`${tabName}-tab`).classList.add('active');

            // 크롭 탭으로 전환할 때 Cropper 초기화
            if (tabName === 'crop' && originalImageData) {
                setTimeout(initializeCropper, 100); // DOM 업데이트 대기
            } else if (cropper) {
                cropper.destroy();
                cropper = null;
            }
        }

        // Cropper.js 초기화
        function initializeCropper() {
            const cropImg = document.getElementById('crop-image');

            if (window.mainCropper) {
                window.mainCropper.destroy();
            }

            window.mainCropper = new Cropper(cropImg, {
                aspectRatio: 4 / 3, // 기본 4:3 비율
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 0.8,
                restore: false,
                guides: true,
                center: true,
                highlight: false,
                cropBoxMovable: true,
                cropBoxResizable: true,
                toggleDragModeOnDblclick: false,
            });
        }

        // 비율 설정
        function setAspectRatio(ratio) {
            if (window.mainCropper) {
                window.mainCropper.setAspectRatio(ratio);
            }
        }

        // 크롭 초기화
        function resetCrop() {
            if (window.mainCropper) {
                window.mainCropper.reset();
            }
        }

        // 배송 옵션 토글 함수
        function toggleShippingFields() {
            const shippingType = document.getElementById('shipping_type').value;
            const shippingFields = document.getElementById('shipping_cost_fields');

            if (shippingType === 'paid') {
                shippingFields.style.display = 'block';
            } else {
                shippingFields.style.display = 'none';
                // 무료배송 선택시 배송비 필드 초기화
                document.getElementById('shipping_cost').value = '';
                document.getElementById('shipping_unit_count').value = '1';
            }
        }

        // 크롭 적용
        function applyCrop() {
            if (!window.mainCropper) return;

            const canvas = window.mainCropper.getCroppedCanvas({
                maxWidth: 1200,
                maxHeight: 1200,
                fillColor: '#fff',
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });

            // 크롭된 이미지를 미리보기에 적용
            const previewImg = document.getElementById('preview-image');
            const croppedDataURL = canvas.toDataURL('image/jpeg', 0.9);
            previewImg.src = croppedDataURL;

            // 크롭된 이미지 데이터를 hidden input에 저장
            let croppedInput = document.getElementById('cropped-image-data');
            if (!croppedInput) {
                croppedInput = document.createElement('input');
                croppedInput.type = 'hidden';
                croppedInput.id = 'cropped-image-data';
                croppedInput.name = 'cropped_image_data';
                document.querySelector('form').appendChild(croppedInput);
            }
            croppedInput.value = croppedDataURL;

            // 정보 업데이트
            const width = canvas.width;
            const height = canvas.height;
            const ratio = (width / height).toFixed(2);

            document.getElementById('image-dimensions').textContent = `크기: ${width}x${height}px`;
            document.getElementById('image-ratio').textContent = `비율: ${ratio}:1`;

            // 미리보기 탭으로 전환
            switchTab('preview');

            alert('이미지가 성공적으로 편집되었습니다!');
        }

        // 폼 제출 시 크롭된 이미지 데이터 확인
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');

            if (form) {
                form.addEventListener('submit', function(e) {
                    console.log('=== 폼 제출 시작 ===');

                    // 현재 활성화된 이미지 방법 확인
                    const imageMethod = document.querySelector('input[name="image_method"]:checked');
                    console.log('선택된 이미지 방법:', imageMethod ? imageMethod.value : 'none');

                    // 크롭된 이미지가 있는지 확인
                    const croppedInput = document.getElementById('cropped-image-data');
                    if (croppedInput && croppedInput.value) {
                        console.log('크롭된 이미지 데이터 있음 (길이:', croppedInput.value.length, ')');
                    } else {
                        console.log('크롭된 이미지 데이터 없음');
                    }

                    // 파일 업로드가 있는지 확인
                    const fileInput = document.getElementById('product_image');
                    if (fileInput && fileInput.files.length > 0) {
                        console.log('업로드된 파일:', fileInput.files[0].name);
                    } else {
                        console.log('업로드된 파일 없음');
                    }

                    // URL이 있는지 확인
                    const urlInput = document.getElementById('image_url');
                    if (urlInput && urlInput.value.trim()) {
                        console.log('입력된 URL:', urlInput.value);
                    } else {
                        console.log('입력된 URL 없음');
                    }

                    console.log('=== 폼 제출 계속 ===');
                });
            }
        });
    </script>

    <!-- Cropper.js JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    
    <style>
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
        
        .input-section.hidden {
            display: none;
        }

        /* 이미지 미리보기 스타일 */
        .image-preview-container {
            margin-top: 20px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: white;
            overflow: hidden;
        }

        .preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
        }

        .preview-header h4 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: #333;
        }

        .btn-remove {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 5px 10px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-remove:hover {
            background: #c82333;
        }

        .preview-content {
            padding: 20px;
        }

        #preview-image {
            max-width: 100%;
            max-height: 300px;
            border: 1px solid #ddd;
            border-radius: 4px;
            display: block;
            margin: 0 auto 20px auto;
        }

        .image-controls {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .size-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .size-controls label {
            font-weight: 500;
            color: #555;
            white-space: nowrap;
        }

        .size-controls input[type="number"] {
            width: 80px;
            padding: 5px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 4px;
            border: 1px solid #ddd;
            background: white;
            cursor: pointer;
            white-space: nowrap;
        }

        .btn-sm.btn-primary {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        .btn-sm:hover {
            background: #f8f9fa;
        }

        .btn-sm.btn-primary:hover {
            background: #0056b3;
            border-color: #0056b3;
        }

        .image-info {
            display: flex;
            gap: 20px;
            font-size: 14px;
            color: #666;
        }

        .image-info span {
            background: #f8f9fa;
            padding: 5px 10px;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
        }

        /* 이미지 가이드 스타일 */
        .image-guide {
            background: #e8f4fd;
            border: 1px solid #bee5eb;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .image-guide h4 {
            margin: 0 0 10px 0;
            color: #0c5460;
            font-size: 16px;
        }

        .image-guide ul {
            margin: 0;
            padding-left: 20px;
        }

        .image-guide li {
            margin-bottom: 5px;
            color: #155724;
        }

        /* 탭 스타일 */
        .preview-tabs {
            display: flex;
            border-bottom: 1px solid #dee2e6;
            background: #f8f9fa;
        }

        .tab-btn {
            padding: 12px 20px;
            border: none;
            background: transparent;
            cursor: pointer;
            font-weight: 500;
            color: #6c757d;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .tab-btn.active {
            color: #007bff;
            border-bottom-color: #007bff;
            background: white;
        }

        .tab-btn:hover:not(.active) {
            color: #495057;
            background: #e9ecef;
        }

        /* 탭 컨텐츠 */
        .tab-content {
            display: none;
            padding: 20px;
        }

        .tab-content.active {
            display: block;
        }

        /* 미리보기 탭 */
        .preview-image-wrapper {
            text-align: center;
            margin-bottom: 15px;
        }

        .preview-image-wrapper img {
            max-width: 100%;
            max-height: 400px;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        /* 크롭 탭 */
        .crop-controls {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .crop-image-wrapper {
            text-align: center;
            max-height: 500px;
            overflow: hidden;
        }

        .crop-image-wrapper img {
            max-width: 100%;
            max-height: 450px;
        }

        /* 개선된 이미지 업로드 스타일 */
        .main-image-section .form-help {
            background: #e3f2fd;
            padding: 12px;
            border-radius: 6px;
            border-left: 4px solid #2196f3;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.5;
        }

        .image-upload-container.enhanced {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 2px dashed #dee2e6;
            border-radius: 12px;
            padding: 30px;
            transition: all 0.3s ease;
        }

        .image-upload-container.enhanced:hover {
            border-color: #007bff;
            box-shadow: 0 4px 12px rgba(0,123,255,0.1);
        }

        .upload-methods-header {
            text-align: center;
            margin-bottom: 25px;
        }

        .upload-methods-header h4 {
            margin: 0;
            color: #495057;
            font-size: 18px;
            font-weight: 600;
        }

        .upload-method-cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }

        .method-card {
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .method-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #007bff, #28a745);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .method-card:hover::before,
        .method-card.active::before {
            transform: scaleX(1);
        }

        .method-card:hover {
            border-color: #007bff;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,123,255,0.15);
        }

        .method-card.active {
            border-color: #007bff;
            background: linear-gradient(135deg, #f0f8ff 0%, #e3f2fd 100%);
            transform: translateY(-2px);
        }

        .method-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .method-content {
            text-align: center;
        }

        .method-icon {
            font-size: 24px;
            margin-bottom: 10px;
            display: block;
        }

        .method-details strong {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-size: 16px;
        }

        .method-details p {
            margin: 0 0 5px 0;
            font-size: 13px;
            color: #666;
            line-height: 1.4;
        }

        .method-details small {
            color: #28a745;
            font-weight: 500;
            font-size: 12px;
        }

        .image-guidelines-box {
            background: linear-gradient(135deg, #fff3cd 0%, #f8d7da 100%);
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }

        .guidelines-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .guidelines-icon {
            font-size: 20px;
        }

        .guidelines-header h4 {
            margin: 0;
            color: #856404;
            font-size: 16px;
        }

        .guidelines-content {
            space-y: 10px;
        }

        .guideline-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 10px;
        }

        .guideline-item {
            background: rgba(255,255,255,0.7);
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid rgba(255,193,7,0.3);
        }

        .guideline-item.recommended {
            border-color: #28a745;
            background: rgba(40,167,69,0.1);
        }

        .item-label {
            font-weight: 500;
            color: #495057;
            font-size: 12px;
            display: block;
        }

        .item-value {
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        .guideline-tip {
            background: rgba(255,255,255,0.9);
            padding: 12px;
            border-radius: 8px;
            border-left: 4px solid #17a2b8;
            margin-top: 15px;
            font-size: 13px;
            line-height: 1.4;
        }

        /* 반응형 */
        @media (max-width: 768px) {
            .crop-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .crop-controls .btn {
                margin-bottom: 5px;
            }

            .preview-tabs {
                flex-direction: column;
            }

            .tab-btn {
                text-align: center;
            }

            .upload-method-cards {
                grid-template-columns: 1fr;
            }

            .guideline-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>
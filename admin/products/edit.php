<?php
// Initialize session and auth before any output
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
require_once $base_path . '/classes/Database.php';

$auth = Auth::getInstance();
$auth->requireAdmin();

// 세션 시작 및 알림 메시지 확인
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$success = '';
$error = '';
$product = null;
$update_notification = null;

// 세션에서 업데이트 결과 메시지 확인
if (isset($_SESSION['update_success'])) {
    $update_notification = $_SESSION['update_success'];
    $update_notification['type'] = 'success';
    unset($_SESSION['update_success']); // 한 번 표시 후 제거
} elseif (isset($_SESSION['update_error'])) {
    $update_notification = $_SESSION['update_error'];
    $update_notification['type'] = 'error';
    unset($_SESSION['update_error']); // 한 번 표시 후 제거
}

// Get product ID
$product_id = $_GET['id'] ?? 0;
if (!$product_id) {
    header('Location: index.php');
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();

    // 필요한 컬럼들 추가
    try {
        $pdo->exec("ALTER TABLE products ADD COLUMN detailed_description TEXT");
    } catch (Exception $e) {
        // 이미 존재하는 경우 무시
    }

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

// AJAX 요청인지 확인
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

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
    // 기존 이미지 URL을 유지 (새로운 업로드가 있으면 나중에 덮어씀)
    $image_url = $product['image_url'] ?? '';
    $status = $_POST['status'] ?? 'active';
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;

    // 새로운 상품카드 필드들
    $discount_percentage = intval($_POST['discount_percentage'] ?? 0);
    $rating_score = floatval($_POST['rating_score'] ?? 4.5);
    $review_count = intval($_POST['review_count'] ?? 0);
    $delivery_info = trim($_POST['delivery_info'] ?? '무료배송');
    $shipping_type = $_POST['shipping_type'] ?? 'free';
    $shipping_cost = ($shipping_type === 'paid') ? floatval($_POST['shipping_cost'] ?? 0) : 0;
    $shipping_unit_count = intval($_POST['shipping_unit_count'] ?? 1);

    // 이미지 업데이트 방법에 따른 처리
    $image_method = $_POST['image_method'] ?? 'url';
    $posted_image_url = trim($_POST['image_url'] ?? '');

    // URL 방법을 선택했고, URL이 입력된 경우
    if ($image_method === 'url' && !empty($posted_image_url)) {
        $image_url = $posted_image_url;
        error_log("이미지 URL로 업데이트: " . $image_url);
    }

    // 파일 업로드 방법을 선택했고, 파일이 업로드된 경우
    if ($image_method === 'file' && isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = $base_path . '/uploads/products/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_extension = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($file_extension, $allowed_extensions)) {
            $new_filename = uniqid('product_') . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;

            // 크롭된 이미지 데이터가 있는 경우 처리
            if (!empty($_POST['cropped_image_data'])) {
                // Base64 이미지 데이터에서 실제 이미지 추출
                $cropped_data = $_POST['cropped_image_data'];
                if (preg_match('/^data:image\/(png|jpg|jpeg);base64,/', $cropped_data, $matches)) {
                    $extension = $matches[1] === 'jpg' ? 'jpeg' : $matches[1];
                    $base64_data = preg_replace('/^data:image\/(png|jpg|jpeg);base64,/', '', $cropped_data);
                    $image_data = base64_decode($base64_data);

                    if ($image_data !== false) {
                        $new_filename = uniqid('product_cropped_') . '.jpg'; // 크롭된 이미지는 JPG로 저장
                        $upload_path = $upload_dir . $new_filename;

                        if (file_put_contents($upload_path, $image_data)) {
                            $image_url = '/uploads/products/' . $new_filename;
                            error_log("크롭된 이미지 저장 성공: " . $image_url);
                        } else {
                            $error = '크롭된 이미지 저장에 실패했습니다.';
                        }
                    } else {
                        $error = '크롭된 이미지 데이터가 올바르지 않습니다.';
                    }
                } else {
                    $error = '올바르지 않은 이미지 형식입니다.';
                }
            } else {
                // 일반 파일 업로드
                if (move_uploaded_file($_FILES['product_image']['tmp_name'], $upload_path)) {
                    $image_url = '/uploads/products/' . $new_filename;
                    error_log("일반 이미지 업로드 성공: " . $image_url);
                } else {
                    $error = '이미지 업로드에 실패했습니다.';
                }
            }
        } else {
            $error = '지원하지 않는 이미지 형식입니다. (JPG, PNG, GIF, WebP만 가능)';
        }
    }

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
            
            // 디버깅 로그 추가
            error_log("상품 업데이트 - ID: $product_id, 이미지 URL: " . ($image_url ?: '(비어있음)'));

            $sql = "UPDATE products SET
                    name = ?, description = ?, detailed_description = ?, features = ?, price = ?, category_id = ?,
                    stock_quantity = ?, weight = ?, dimensions = ?, image_url = ?,
                    status = ?, is_featured = ?, discount_percentage = ?, rating_score = ?, review_count = ?, delivery_info = ?, shipping_cost = ?, shipping_unit_count = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                $name, $description, $detailed_description, $features_json, $price, $category_id,
                $stock_quantity, $weight, $dimensions, $image_url, $status, $is_featured,
                $discount_percentage, $rating_score, $review_count, $delivery_info, $shipping_cost, $shipping_unit_count, $product_id
            ]);

            $affectedRows = $stmt->rowCount();

            if ($result && $affectedRows > 0) {
                $success = '상품이 성공적으로 수정되었습니다.';

                // 세션에 성공 메시지 저장
                if (session_status() == PHP_SESSION_NONE) {
                    session_start();
                }
                $_SESSION['update_success'] = [
                    'message' => '상품이 성공적으로 수정되었습니다!',
                    'timestamp' => time(),
                    'updated_fields' => [
                        'name' => $name,
                        'detailed_description' => !empty($detailed_description),
                        'price' => $price,
                        'affected_rows' => $affectedRows
                    ]
                ];

                error_log("✅ 상품 업데이트 성공 - ID: $product_id, 영향받은 행: $affectedRows");

                // AJAX 요청인 경우 JSON 응답
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => '상품이 성공적으로 수정되었습니다!',
                        'affected_rows' => $affectedRows
                    ]);
                    exit;
                }
            } else {
                $error = '상품 수정 중 오류가 발생했습니다.';
                error_log("❌ 상품 업데이트 실패 - ID: $product_id, 영향받은 행: $affectedRows");

                // AJAX 요청인 경우 JSON 응답
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => '상품 수정 중 오류가 발생했습니다.',
                        'affected_rows' => $affectedRows
                    ]);
                    exit;
                }
            }
            
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

            // 세션에 실패 메시지 저장
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['update_error'] = [
                'message' => '상품 수정에 실패했습니다.',
                'details' => $e->getMessage(),
                'timestamp' => time()
            ];

            error_log("❌ 상품 업데이트 실패 - ID: $product_id, 오류: " . $e->getMessage());

            // AJAX 요청인 경우 JSON 응답
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => '상품 수정 중 오류가 발생했습니다.',
                    'error' => $e->getMessage()
                ]);
                exit;
            }
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
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/korean-editor.css">
    <script src="../../assets/js/image-resize.js"></script>
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
                        <h1>상품 수정</h1>
                        <p><?= htmlspecialchars($product['name'] ?? '') ?></p>
                    </div>
                    <div class="page-actions">
                        <a href="index.php" class="btn btn-outline">목록으로</a>
                    </div>
                </div>

                <!-- 업데이트 알림 메시지 -->
                <?php if ($update_notification): ?>
                <div class="update-notification <?= $update_notification['type'] ?>" id="updateNotification">
                    <div class="notification-content">
                        <div class="notification-icon">
                            <?= $update_notification['type'] === 'success' ? '✅' : '❌' ?>
                        </div>
                        <div class="notification-details">
                            <h4><?= htmlspecialchars($update_notification['message']) ?></h4>
                            <div class="notification-info">
                                <?php if ($update_notification['type'] === 'success'): ?>
                                    <span>📝 영향받은 행: <?= $update_notification['updated_fields']['affected_rows'] ?>개</span>
                                <?php else: ?>
                                    <span>🚨 오류 세부사항: <?= htmlspecialchars($update_notification['details']) ?></span>
                                <?php endif; ?>
                                <span>⏰ <?= date('Y-m-d H:i:s', $update_notification['timestamp']) ?></span>
                            </div>
                        </div>
                        <button class="notification-close" onclick="closeNotification()">×</button>
                    </div>
                </div>
                <?php endif; ?>

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
                    <form id="product-form" method="post" enctype="multipart/form-data" class="product-form">
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
                                    <div class="form-help" style="margin-bottom: 15px;">상품의 대표 이미지를 설정하세요. 고해상도 이미지 권장 (800x600px 이상)</div>

                                    <?php if ($product['image_url']): ?>
                                        <div class="current-image-preview">
                                            <div class="current-image-header">
                                                <strong>📷 현재 이미지</strong>
                                                <span class="image-status active">사용 중</span>
                                            </div>
                                            <div class="current-image-container">
                                                <img src="<?= htmlspecialchars($product['image_url']) ?>"
                                                     alt="현재 상품 이미지" class="current-product-image">
                                                <div class="image-overlay">
                                                    <button type="button" onclick="previewCurrentImage('<?= htmlspecialchars($product['image_url']) ?>')" class="btn-preview">🔍 크게보기</button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="image-upload-container improved">
                                        <div class="upload-methods">
                                            <h4>🖼️ 새 이미지 업로드 방법</h4>
                                            <div class="method-options">
                                                <label class="method-card active" data-method="url">
                                                    <input type="radio" name="image_method" value="url" checked onchange="toggleImageMethod('url')">
                                                    <div class="method-icon">🔗</div>
                                                    <div class="method-info">
                                                        <strong>URL 링크</strong>
                                                        <p>웹상의 이미지 주소 입력</p>
                                                    </div>
                                                </label>
                                                <label class="method-card" data-method="file">
                                                    <input type="radio" name="image_method" value="file" onchange="toggleImageMethod('file')">
                                                    <div class="method-icon">📁</div>
                                                    <div class="method-info">
                                                        <strong>파일 업로드</strong>
                                                        <p>컴퓨터에서 이미지 선택</p>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div id="url-input" class="input-section">
                                            <input type="url" id="image_url" name="image_url" class="form-input" 
                                                   value="<?= htmlspecialchars($product['image_url'] ?? '') ?>" 
                                                   placeholder="https://example.com/image.jpg">
                                            <div class="form-help">새로운 이미지 URL을 입력하세요.</div>
                                        </div>
                                        
                                        <div id="file-input" class="input-section" style="display: none;">
                                            <input type="file" id="product_image" name="product_image" class="form-input"
                                                   accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                                                   onchange="handleFileSelect(event)">
                                            <div class="form-help">새로운 이미지 파일을 선택하세요.</div>

                                            <!-- 새 이미지 미리보기와 편집기 -->
                                            <div id="new-image-preview" style="display: none; margin-top: 15px;">
                                                <div class="image-preview-container">
                                                    <div class="preview-header">
                                                        <div class="preview-tabs">
                                                            <button type="button" class="tab-btn active" data-tab="preview" onclick="switchTab('preview')">미리보기</button>
                                                            <button type="button" class="tab-btn" data-tab="crop" onclick="switchTab('crop')">크기조절/자르기</button>
                                                        </div>
                                                        <button type="button" class="btn-remove" onclick="removeNewImagePreview()">제거</button>
                                                    </div>

                                                    <!-- 미리보기 탭 -->
                                                    <div class="tab-content active" data-content="preview">
                                                        <div class="image-guidelines">
                                                            <h4>📌 이미지 권장사항</h4>
                                                            <ul class="guidelines-list">
                                                                <li>최적 해상도: 800x600px 이상</li>
                                                                <li>권장 비율: 4:3 또는 16:9</li>
                                                                <li>파일 크기: 5MB 이하</li>
                                                                <li>지원 형식: JPG, PNG, GIF, WebP</li>
                                                            </ul>
                                                        </div>
                                                        <img id="preview-new-image" src="" alt="새 이미지 미리보기">
                                                        <div class="image-info">
                                                            <span id="image-dimensions">크기: -</span>
                                                            <span id="image-ratio">비율: -</span>
                                                            <span id="image-size">용량: -</span>
                                                        </div>
                                                    </div>

                                                    <!-- 크롭 탭 -->
                                                    <div class="tab-content" data-content="crop">
                                                        <div class="crop-controls">
                                                            <button type="button" onclick="setAspectRatio(16/9)">16:9</button>
                                                            <button type="button" onclick="setAspectRatio(4/3)">4:3</button>
                                                            <button type="button" onclick="setAspectRatio(1)">1:1</button>
                                                            <button type="button" onclick="setAspectRatio(0)">자유</button>
                                                            <button type="button" onclick="resetCrop()">리셋</button>
                                                            <button type="button" class="btn-primary" onclick="applyCrop()">적용</button>
                                                        </div>
                                                        <img id="crop-new-image" src="" alt="크롭할 이미지">
                                                    </div>
                                                </div>
                                                <input type="hidden" id="cropped-image-data" name="cropped_image_data">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                            </div>
                            
                            <div class="form-section">
                                <h3>상품카드 표시 정보</h3>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label" for="discount_percentage">할인율 (%)</label>
                                        <input type="number" id="discount_percentage" name="discount_percentage" class="form-input"
                                               value="<?= $product['discount_percentage'] ?? 0 ?>" min="0" max="100">
                                        <div class="form-help">0~100 사이의 숫자를 입력하세요. (0은 할인 없음)</div>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label" for="rating_score">평점</label>
                                        <input type="number" id="rating_score" name="rating_score" class="form-input"
                                               value="<?= $product['rating_score'] ?? 4.5 ?>" min="0" max="5" step="0.1">
                                        <div class="form-help">0~5 사이의 점수를 입력하세요.</div>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label" for="review_count">리뷰 개수</label>
                                        <input type="number" id="review_count" name="review_count" class="form-input"
                                               value="<?= $product['review_count'] ?? 0 ?>" min="0">
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label" for="delivery_info">배송정보</label>
                                        <input type="text" id="delivery_info" name="delivery_info" class="form-input"
                                               value="<?= htmlspecialchars($product['delivery_info'] ?? '무료배송') ?>"
                                               placeholder="예: 무료배송, 당일배송">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label" for="shipping_type">배송 옵션</label>
                                        <select id="shipping_type" name="shipping_type" class="form-select" onchange="toggleShippingFields()">
                                            <option value="free" <?= ($product['shipping_cost'] ?? 0) == 0 ? 'selected' : '' ?>>무료배송</option>
                                            <option value="paid" <?= ($product['shipping_cost'] ?? 0) > 0 ? 'selected' : '' ?>>유료배송</option>
                                        </select>
                                        <div class="form-help">배송비 정책을 선택하세요</div>
                                    </div>
                                </div>

                                <div id="shipping_cost_fields" style="display: <?= ($product['shipping_cost'] ?? 0) > 0 ? 'block' : 'none' ?>;">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label class="form-label" for="shipping_cost">배송비 (원)</label>
                                            <input type="number" id="shipping_cost" name="shipping_cost" class="form-input"
                                                   value="<?= htmlspecialchars($product['shipping_cost'] ?? '0') ?>"
                                                   placeholder="0" min="0" step="100">
                                            <div class="form-help">배송비를 설정하세요. 상품 가격에 추가로 표시됩니다.</div>
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label" for="shipping_unit_count">배송비 적용 단위 (개수)</label>
                                            <input type="number" id="shipping_unit_count" name="shipping_unit_count" class="form-input"
                                                   value="<?= htmlspecialchars($product['shipping_unit_count'] ?? '1') ?>"
                                                   placeholder="1" min="1" max="100">
                                            <div class="form-help">설정한 개수마다 배송비가 추가됩니다. (예: 10개 단위로 설정 시, 11~20개면 배송비 2배)</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3>재고 및 배송 정보</h3>
                                
                                <div class="form-group">
                                    <label class="form-label" for="stock_quantity">재고 수량</label>
                                    <input type="number" id="stock_quantity" name="stock_quantity" class="form-input"
                                           value="<?= $product['stock_quantity'] ?? $product['stock'] ?? 0 ?>" min="0">
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
                            <button type="button" id="confirmUpdateBtn" class="btn btn-primary">📝 상품 수정 확인</button>
                            <a href="index.php" class="btn btn-outline">취소</a>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="../../assets/js/main.js"></script>
    
    <style>
        .admin-container {
            display: flex;
            min-height: 100vh;
            background-color: #f8f9fa;
        }
        
        .admin-content {
            flex: 1;
            padding: 30px;
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
        
        /* 개선된 이미지 업로드 스타일 */
        .current-image-preview {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
        }

        .current-image-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .image-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .image-status.active {
            background: #d4edda;
            color: #155724;
        }

        .current-image-container {
            position: relative;
            display: inline-block;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .current-product-image {
            max-width: 200px;
            max-height: 150px;
            object-fit: cover;
            display: block;
        }

        .image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .current-image-container:hover .image-overlay {
            opacity: 1;
        }

        .btn-preview {
            background: #fff;
            color: #333;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-preview:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .image-upload-container.improved {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 25px;
        }

        .upload-methods h4 {
            margin: 0 0 15px 0;
            color: #495057;
            font-size: 16px;
            text-align: center;
        }

        .method-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .method-card {
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .method-card:hover {
            border-color: #007bff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,123,255,0.15);
        }

        .method-card.active {
            border-color: #007bff;
            background: linear-gradient(135deg, #f0f8ff 0%, #e3f2fd 100%);
        }

        .method-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .method-info {
            text-align: center;
        }

        .method-info strong {
            display: block;
            margin-bottom: 5px;
            color: #333;
        }

        .method-info p {
            margin: 0;
            font-size: 13px;
            color: #666;
            line-height: 1.4;
        }

        /* 업데이트 알림 메시지 스타일 */
        .update-notification {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: 1px solid #c3e6cb;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            animation: slideDown 0.5s ease-out, fadeOut 0.5s ease-in 4.5s forwards;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.2);
        }

        .update-notification.error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border-color: #f5c6cb;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.2);
        }

        .notification-content {
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }

        .notification-icon {
            font-size: 24px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .notification-details {
            flex: 1;
        }

        .notification-details h4 {
            margin: 0 0 8px 0;
            color: #155724;
            font-size: 16px;
            font-weight: 600;
        }

        .update-notification.error .notification-details h4 {
            color: #721c24;
        }

        .notification-info {
            display: flex;
            gap: 15px;
            font-size: 14px;
            color: #155724;
            opacity: 0.8;
        }

        .update-notification.error .notification-info {
            color: #721c24;
        }

        .notification-close {
            background: none;
            border: none;
            font-size: 20px;
            color: #155724;
            cursor: pointer;
            padding: 0;
            line-height: 1;
            opacity: 0.7;
            transition: opacity 0.2s;
        }

        .update-notification.error .notification-close {
            color: #721c24;
        }

        .notification-close:hover {
            opacity: 1;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(-10px);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* 취소 버튼 옆 상태 메시지 스타일 */
        .update-status-message {
            padding: 8px 15px;
            border-radius: 6px;
            animation: fadeIn 0.3s ease-out;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            font-size: 14px;
            white-space: nowrap;
            display: inline-block;
            margin-left: 10px;
        }

        .update-status-message.success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .update-status-message.error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .update-status-message.progress {
            background: linear-gradient(135deg, #e2f3ff 0%, #b3d9ff 100%);
            border: 1px solid #b3d9ff;
            color: #0c5460;
        }

        .status-content {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .status-icon, .status-spinner {
            font-size: 18px;
            flex-shrink: 0;
        }

        .status-spinner {
            animation: spin 1s linear infinite;
        }

        .status-close {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            margin-left: auto;
            opacity: 0.7;
            transition: opacity 0.2s;
            color: inherit;
        }

        .status-close:hover {
            opacity: 1;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
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

            .method-options {
                grid-template-columns: 1fr;
            }

            #preview-image, #crop-image, #preview-new-image {
                max-height: 200px;
            }

            .tab-content {
                padding: 10px;
                max-height: 300px;
            }

            .image-preview-container {
                margin-top: 10px;
            }

            .preview-header {
                padding: 10px;
            }

            .tab-btn {
                padding: 5px 10px;
                font-size: 12px;
            }
        }
    </style>
    
    <script>
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

        // 이미지 관련 함수들
        let originalImageData = null;
        let newImageCropper = null;

        function handleFileSelect(event) {
            const file = event.target.files[0];
            if (!file) {
                removeNewImagePreview();
                return;
            }

            // 파일 크기 체크 (5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('파일 크기는 5MB 이하여야 합니다.');
                event.target.value = '';
                removeNewImagePreview();
                return;
            }

            // 파일 형식 체크
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                alert('지원하지 않는 파일 형식입니다.');
                event.target.value = '';
                removeNewImagePreview();
                return;
            }

            originalImageData = { size: file.size };

            // 새 이미지 미리보기 표시
            const reader = new FileReader();
            reader.onload = function(e) {
                showNewImagePreview(e.target.result);
            };
            reader.readAsDataURL(file);
        }

        function showNewImagePreview(src) {
            const previewContainer = document.getElementById('new-image-preview');
            const previewImage = document.getElementById('preview-new-image');
            const cropImage = document.getElementById('crop-new-image');

            previewImage.onload = function() {
                const width = this.naturalWidth;
                const height = this.naturalHeight;
                const size = originalImageData.size;
                const ratio = (width / height).toFixed(2);

                document.getElementById('image-dimensions').textContent = `크기: ${width}x${height}px`;
                document.getElementById('image-ratio').textContent = `비율: ${ratio}:1`;
                document.getElementById('image-size').textContent = `용량: ${(size / 1024 / 1024).toFixed(2)}MB`;

                previewContainer.style.display = 'block';
                switchTab('preview');
            };

            previewImage.src = src;
            cropImage.src = src;
        }

        function removeNewImagePreview() {
            if (newImageCropper) {
                newImageCropper.destroy();
                newImageCropper = null;
            }

            const previewContainer = document.getElementById('new-image-preview');
            const previewImage = document.getElementById('preview-new-image');
            const cropImage = document.getElementById('crop-new-image');
            const fileInput = document.getElementById('product_image');
            const croppedInput = document.getElementById('cropped-image-data');

            previewContainer.style.display = 'none';
            previewImage.src = '';
            cropImage.src = '';
            fileInput.value = '';
            if (croppedInput) croppedInput.value = '';

            document.getElementById('image-dimensions').textContent = '크기: -';
            document.getElementById('image-ratio').textContent = '비율: -';
            document.getElementById('image-size').textContent = '용량: -';

            switchTab('preview');
        }

        function showPreview(file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                showImagePreview(e.target.result);
            };
            reader.readAsDataURL(file);
        }

        function showImagePreview(src) {
            const container = document.getElementById('image-preview-container');
            const previewImg = document.getElementById('preview-image');
            const cropImg = document.getElementById('crop-image');

            previewImg.onload = function() {
                const width = this.naturalWidth;
                const height = this.naturalHeight;
                const size = originalImageData.size;
                const ratio = (width / height).toFixed(2);

                document.getElementById('image-dimensions').textContent = `크기: ${width}x${height}px`;
                document.getElementById('image-ratio').textContent = `비율: ${ratio}:1`;
                document.getElementById('image-size').textContent = `용량: ${(size / 1024 / 1024).toFixed(2)}MB`;

                container.style.display = 'block';
                switchTab('preview');
            };

            previewImg.src = src;
            cropImg.src = src;
        }

        function removePreview() {
            if (window.mainCropper) {
                window.mainCropper.destroy();
                window.mainCropper = null;
            }

            const container = document.getElementById('image-preview-container');
            const img = document.getElementById('preview-image');
            const fileInput = document.getElementById('main_image');

            container.style.display = 'none';
            img.src = '';
            fileInput.value = '';
            document.getElementById('image-dimensions').textContent = '크기: -';
            document.getElementById('image-size').textContent = '용량: -';

            switchTab('preview');
        }

        function switchTab(tab) {
            const previewTab = document.querySelector('.tab-btn[data-tab="preview"]');
            const cropTab = document.querySelector('.tab-btn[data-tab="crop"]');
            const previewContent = document.querySelector('.tab-content[data-content="preview"]');
            const cropContent = document.querySelector('.tab-content[data-content="crop"]');

            if (tab === 'preview') {
                previewTab.classList.add('active');
                cropTab.classList.remove('active');
                previewContent.classList.add('active');
                cropContent.classList.remove('active');
            } else {
                cropTab.classList.add('active');
                previewTab.classList.remove('active');
                cropContent.classList.add('active');
                previewContent.classList.remove('active');

                setTimeout(initializeNewImageCropper, 100);
            }
        }

        function initializeNewImageCropper() {
            const cropImg = document.getElementById('crop-new-image');

            if (!cropImg || !cropImg.src) return;

            if (newImageCropper) {
                newImageCropper.destroy();
            }

            newImageCropper = new Cropper(cropImg, {
                aspectRatio: 4 / 3,
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

        function setAspectRatio(ratio) {
            if (newImageCropper) {
                newImageCropper.setAspectRatio(ratio);
            }
        }

        function resetCrop() {
            if (newImageCropper) {
                newImageCropper.reset();
            }
        }

        function applyCrop() {
            if (!newImageCropper) {
                alert('크롭 도구가 초기화되지 않았습니다. 이미지를 다시 선택해주세요.');
                return;
            }

            // 로딩 표시
            const applyBtn = document.querySelector('.crop-controls .btn-primary');
            const originalText = applyBtn.textContent;
            applyBtn.textContent = '적용 중...';
            applyBtn.disabled = true;

            const canvas = newImageCropper.getCroppedCanvas({
                maxWidth: 1200,
                maxHeight: 1200,
                fillColor: '#fff',
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });

            const croppedDataURL = canvas.toDataURL('image/jpeg', 0.9);
            const width = canvas.width;
            const height = canvas.height;

            // AJAX로 서버에 업데이트
            const formData = new FormData();
            formData.append('product_id', <?= $product_id ?>);
            formData.append('cropped_image_data', croppedDataURL);

            fetch('update_image_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 미리보기 업데이트
                    const previewImg = document.getElementById('preview-new-image');
                    previewImg.src = croppedDataURL;

                    // 현재 이미지 업데이트 (캐시 무효화)
                    const currentImg = document.querySelector('.current-product-image');
                    if (currentImg) {
                        currentImg.src = data.image_url + '?v=' + data.timestamp;
                    }

                    // 정보 업데이트
                    const ratio = (width / height).toFixed(2);
                    document.getElementById('image-dimensions').textContent = `크기: ${width}x${height}px`;
                    document.getElementById('image-ratio').textContent = `비율: ${ratio}:1`;

                    // 완료 페이지로 전환
                    showCompletionPreview(data);

                } else {
                    alert('오류: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('서버 오류가 발생했습니다.');
            })
            .finally(() => {
                // 버튼 상태 복원
                applyBtn.textContent = originalText;
                applyBtn.disabled = false;
            });
        }

        function showCompletionPreview(data) {
            // 완료 모달 HTML
            const modalHTML = `
                <div id="completion-modal" style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.5);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 10000;
                ">
                    <div style="
                        background: white;
                        border-radius: 12px;
                        padding: 30px;
                        max-width: 600px;
                        width: 90%;
                        max-height: 80vh;
                        overflow-y: auto;
                        box-shadow: 0 20px 40px rgba(0,0,0,0.3);
                    ">
                        <div style="text-align: center; margin-bottom: 20px;">
                            <div style="
                                width: 60px;
                                height: 60px;
                                background: #28a745;
                                border-radius: 50%;
                                margin: 0 auto 15px;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                font-size: 30px;
                                color: white;
                            ">✓</div>
                            <h2 style="margin: 0; color: #28a745;">이미지 업데이트 완료!</h2>
                        </div>

                        <div style="border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                            <h4 style="margin-top: 0;">📸 업데이트된 이미지</h4>
                            <img src="${data.image_url}?v=${data.timestamp}" alt="업데이트된 상품 이미지"
                                 style="max-width: 100%; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        </div>

                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                            <h4 style="margin-top: 0;">🔍 디버깅 정보</h4>
                            <div style="font-family: monospace; font-size: 12px; line-height: 1.4;">
                                <div style="background: #e8f5e8; padding: 10px; border-radius: 4px; margin-bottom: 10px;">
                                    <p style="margin: 0;"><strong>📄 기본 정보</strong></p>
                                    <p style="margin: 5px 0;">상품 ID: ${data.product.id}</p>
                                    <p style="margin: 5px 0;">상품명: ${data.product.name}</p>
                                    <p style="margin: 5px 0;">업데이트 시간: ${data.debug.update_time}</p>
                                </div>
                                <div style="background: #e3f2fd; padding: 10px; border-radius: 4px; margin-bottom: 10px;">
                                    <p style="margin: 0;"><strong>🗄️ 데이터베이스</strong></p>
                                    <p style="margin: 5px 0;">이전 이미지: ${data.debug.old_image_url}</p>
                                    <p style="margin: 5px 0;">새 이미지: ${data.debug.new_image_url}</p>
                                    <p style="margin: 5px 0;">영향받은 행: ${data.debug.affected_rows}개</p>
                                    <p style="margin: 5px 0;">업데이트 성공: <span style="color: #28a745;">✓ ${data.debug.db_update_result ? '예' : '아니오'}</span></p>
                                </div>
                                <div style="background: #fff3cd; padding: 10px; border-radius: 4px;">
                                    <p style="margin: 0;"><strong>📁 파일 정보</strong></p>
                                    <p style="margin: 5px 0;">파일 크기: ${data.debug.file_size}</p>
                                    <p style="margin: 5px 0;">파일 저장: <span style="color: #28a745;">✓ ${data.debug.file_saved ? '성공' : '실패'}</span></p>
                                    <p style="margin: 5px 0;">캐시 무효화: v=${data.timestamp}</p>
                                </div>
                            </div>
                        </div>

                        <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                            <h4 style="margin-top: 0;">📊 상품 정보</h4>
                            <p><strong>카테고리:</strong> ${data.product.category_name || '미분류'}</p>
                            <p><strong>가격:</strong> ${Number(data.product.price).toLocaleString()}원</p>
                            <p><strong>상태:</strong> ${data.product.status === 'active' ? '활성' : '비활성'}</p>
                            <p><strong>추천 상품:</strong> ${data.product.is_featured ? '예' : '아니오'}</p>
                        </div>

                        <div style="text-align: center;">
                            <button onclick="closeCompletionModal()" style="
                                background: #007bff;
                                color: white;
                                border: none;
                                padding: 12px 24px;
                                border-radius: 6px;
                                cursor: pointer;
                                font-size: 16px;
                                margin-right: 10px;
                            ">확인</button>
                            <button onclick="viewMainPage()" style="
                                background: #28a745;
                                color: white;
                                border: none;
                                padding: 12px 24px;
                                border-radius: 6px;
                                cursor: pointer;
                                font-size: 16px;
                            ">메인페이지에서 확인</button>
                        </div>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', modalHTML);

            // 미리보기 탭으로 전환하고 이미지 업데이트
            switchTab('preview');
        }

        function closeCompletionModal() {
            const modal = document.getElementById('completion-modal');
            if (modal) {
                modal.remove();
            }
        }

        function viewMainPage() {
            closeCompletionModal();
            // 새 탭에서 메인페이지 열기
            window.open('/', '_blank');
        }

        // 폼 데이터 변경 감지 및 확인 시스템
        let originalFormData = {};

        function saveOriginalFormData() {
            const form = document.getElementById('product-form');
            const formData = new FormData(form);
            originalFormData = {};

            for (let [key, value] of formData.entries()) {
                originalFormData[key] = value;
            }

            // 체크박스와 라디오 버튼 처리
            const checkboxes = form.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(cb => {
                originalFormData[cb.name] = cb.checked;
            });

            const radios = form.querySelectorAll('input[type="radio"]:checked');
            radios.forEach(radio => {
                originalFormData[radio.name] = radio.value;
            });

            // 에디터가 적용된 필드들 처리
            const editorFields = form.querySelectorAll('[data-korean-editor]');
            editorFields.forEach(field => {
                // 에디터가 초기화되었는지 확인하고 데이터 가져오기
                if (typeof window.koreanEditor !== 'undefined' && window.koreanEditor[field.id]) {
                    originalFormData[field.name] = window.koreanEditor[field.id].getData();
                } else {
                    originalFormData[field.name] = field.value;
                }
            });
        }

        function getChangedFields() {
            const form = document.getElementById('product-form');
            const currentFormData = new FormData(form);
            const changes = [];

            // 현재 체크박스 상태 확인
            const checkboxes = form.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(cb => {
                currentFormData.set(cb.name, cb.checked);
            });

            // 현재 라디오 버튼 상태 확인
            const radios = form.querySelectorAll('input[type="radio"]:checked');
            radios.forEach(radio => {
                currentFormData.set(radio.name, radio.value);
            });

            // 에디터가 적용된 필드들의 현재 데이터 확인
            const editorFields = form.querySelectorAll('[data-korean-editor]');
            editorFields.forEach(field => {
                if (typeof window.koreanEditor !== 'undefined' && window.koreanEditor[field.id]) {
                    const editorData = window.koreanEditor[field.id].getData();
                    currentFormData.set(field.name, editorData);
                } else {
                    currentFormData.set(field.name, field.value);
                }
            });

            // 필드별 변경사항 확인
            const fieldLabels = {
                'name': '상품명',
                'description': '상품 설명',
                'detailed_description': '상세 설명',
                'features': '상품 특징',
                'price': '가격',
                'category_id': '카테고리',
                'stock_quantity': '재고 수량',
                'weight': '무게',
                'dimensions': '크기',
                'image_url': '이미지 URL',
                'status': '판매 상태',
                'is_featured': '추천 상품',
                'discount_percentage': '할인율',
                'rating_score': '평점',
                'review_count': '리뷰 수',
                'delivery_info': '배송 정보'
            };

            for (let [key, newValue] of currentFormData.entries()) {
                const originalValue = originalFormData[key];
                if (originalValue != newValue) {
                    const fieldName = fieldLabels[key] || key;
                    let displayOriginal = originalValue || '(비어있음)';
                    let displayNew = newValue || '(비어있음)';

                    // 특별한 필드 처리
                    if (key === 'is_featured') {
                        displayOriginal = originalValue ? '예' : '아니오';
                        displayNew = newValue ? '예' : '아니오';
                    } else if (key === 'status') {
                        displayOriginal = originalValue === 'active' ? '활성' : '비활성';
                        displayNew = newValue === 'active' ? '활성' : '비활성';
                    } else if (key === 'price' && newValue) {
                        displayNew = Number(newValue).toLocaleString() + '원';
                        displayOriginal = Number(originalValue || 0).toLocaleString() + '원';
                    }

                    // HTML 태그가 있는 긴 텍스트 처리
                    if (key === 'detailed_description' || key === 'features') {
                        // HTML 태그 제거하고 길이 제한
                        const stripHtml = (str) => {
                            const temp = document.createElement('div');
                            temp.innerHTML = str;
                            const text = temp.textContent || temp.innerText || '';
                            return text.length > 100 ? text.substring(0, 100) + '...' : text;
                        };
                        displayOriginal = stripHtml(displayOriginal);
                        displayNew = stripHtml(displayNew);
                    }

                    changes.push({
                        field: fieldName,
                        original: displayOriginal,
                        new: displayNew
                    });
                }
            }

            return changes;
        }

        function showUpdateConfirmation() {
            console.log('showUpdateConfirmation 함수 호출됨');

            let changes;
            try {
                changes = getChangedFields();
                console.log('감지된 변경사항:', changes);

                if (changes.length === 0) {
                    alert('변경된 내용이 없습니다.');
                    return;
                }
            } catch (error) {
                console.error('변경사항 감지 중 오류:', error);
                alert('변경사항을 확인하는 중 오류가 발생했습니다: ' + error.message);
                return;
            }

            const changesHTML = changes.map(change => `
                <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; margin-bottom: 8px;">
                    <strong>${change.field}:</strong><br>
                    <span style="color: #dc3545; text-decoration: line-through;">${change.original}</span><br>
                    <span style="color: #28a745; font-weight: bold;">→ ${change.new}</span>
                </div>
            `).join('');

            const confirmModalHTML = `
                <div id="update-confirm-modal" style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.5);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 10000;
                ">
                    <div style="
                        background: white;
                        border-radius: 12px;
                        padding: 30px;
                        max-width: 600px;
                        width: 90%;
                        max-height: 80vh;
                        overflow-y: auto;
                        box-shadow: 0 20px 40px rgba(0,0,0,0.3);
                    ">
                        <div style="text-align: center; margin-bottom: 25px;">
                            <div style="
                                width: 60px;
                                height: 60px;
                                background: #ffc107;
                                border-radius: 50%;
                                margin: 0 auto 15px;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                font-size: 30px;
                                color: white;
                            ">⚠️</div>
                            <h2 style="margin: 0; color: #333;">상품 수정 확인</h2>
                            <p style="color: #666; margin: 10px 0 0 0;">다음 내용으로 상품을 수정하시겠습니까?</p>
                        </div>

                        <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ffeaa7;">
                            <h4 style="margin-top: 0; color: #856404;">📝 변경 사항 (${changes.length}개)</h4>
                            ${changesHTML}
                        </div>

                        <div style="text-align: center;">
                            <button onclick="proceedUpdate()" style="
                                background: #28a745;
                                color: white;
                                border: none;
                                padding: 12px 24px;
                                border-radius: 6px;
                                cursor: pointer;
                                font-size: 16px;
                                margin-right: 10px;
                                font-weight: bold;
                            ">✅ 수정 반영</button>
                            <button onclick="closeUpdateConfirmModal()" style="
                                background: #6c757d;
                                color: white;
                                border: none;
                                padding: 12px 24px;
                                border-radius: 6px;
                                cursor: pointer;
                                font-size: 16px;
                            ">❌ 취소</button>
                        </div>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', confirmModalHTML);
        }

        function closeUpdateConfirmModal() {
            const modal = document.getElementById('update-confirm-modal');
            if (modal) {
                modal.remove();
            }
        }

        function proceedUpdate() {
            closeUpdateConfirmModal();

            // 로딩 상태 표시
            showUpdateProgress('업데이트 중...');

            // 에디터 데이터를 폼에 동기화
            const form = document.getElementById('product-form');
            const editorFields = form.querySelectorAll('[data-korean-editor]');

            editorFields.forEach(field => {
                if (typeof window.koreanEditor !== 'undefined' && window.koreanEditor[field.id]) {
                    // getContent() 메서드 사용 (올바른 메서드명)
                    const editorContent = window.koreanEditor[field.id].getContent();
                    field.value = editorContent;
                    console.log(`✅ 에디터 데이터 동기화 - ${field.name}:`, editorContent.substring(0, 100) + '...');
                } else {
                    console.log(`❌ 에디터를 찾을 수 없음 - ${field.id}`);
                }
            });

            // AJAX로 폼 데이터 전송
            const formData = new FormData(form);

            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    // JSON 응답인지 확인
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        return response.json();
                    } else {
                        return response.text();
                    }
                } else {
                    throw new Error('서버 응답 오류: ' + response.status);
                }
            })
            .then(data => {
                if (typeof data === 'object') {
                    // JSON 응답 처리
                    if (data.success) {
                        showUpdateSuccess(data.message || '상품이 성공적으로 수정되었습니다!');
                    } else {
                        showUpdateError(data.message || '상품 수정 중 오류가 발생했습니다.');
                    }
                } else {
                    // HTML 응답 처리 (fallback)
                    if (data.includes('성공적으로 수정되었습니다') || data.includes('update_success')) {
                        showUpdateSuccess('상품이 성공적으로 수정되었습니다!');
                    } else if (data.includes('오류') || data.includes('실패') || data.includes('update_error')) {
                        showUpdateError('상품 수정 중 오류가 발생했습니다.');
                    } else {
                        showUpdateSuccess('상품이 수정되었습니다.');
                    }
                }

                // 페이지 일부 업데이트를 위해 원본 데이터 다시 저장
                setTimeout(() => {
                    saveOriginalFormData();
                }, 500);

                console.log('업데이트 완료');
            })
            .catch(error => {
                // 업데이트 실패
                console.error('업데이트 오류:', error);
                showUpdateError('상품 수정 중 오류가 발생했습니다: ' + error.message);

                // AJAX 실패 시 폴백으로 일반 폼 제출
                console.log('AJAX 실패, 일반 폼 제출로 전환합니다.');
                setTimeout(() => {
                    const submitBtn = document.createElement('button');
                    submitBtn.type = 'submit';
                    submitBtn.style.display = 'none';
                    form.appendChild(submitBtn);
                    submitBtn.click();
                }, 1000);
            });
        }

        // 에디터 완전 초기화 대기 함수
        function waitForEditorInitialization() {
            return new Promise((resolve) => {
                const checkEditor = () => {
                    const editorFields = document.querySelectorAll('[data-korean-editor]');
                    let allEditorsReady = true;

                    editorFields.forEach(field => {
                        if (!window.koreanEditor || !window.koreanEditor[field.id]) {
                            allEditorsReady = false;
                        }
                    });

                    if (allEditorsReady || editorFields.length === 0) {
                        resolve();
                    } else {
                        setTimeout(checkEditor, 100);
                    }
                };
                checkEditor();
            });
        }

        // 페이지 로드 시 초기 데이터 저장
        document.addEventListener('DOMContentLoaded', async function() {
            // 에디터 완전 초기화 대기
            await waitForEditorInitialization();

            // 초기 데이터 저장
            setTimeout(function() {
                saveOriginalFormData();
                console.log('초기 폼 데이터 저장 완료:', originalFormData);
            }, 500);

            // 확인 버튼 이벤트 리스너
            document.getElementById('confirmUpdateBtn').addEventListener('click', showUpdateConfirmation);
        });

        // 수정 버튼 아래 메시지 표시 함수들
        function showUpdateProgress(message) {
            const statusArea = getOrCreateStatusArea();
            statusArea.className = 'update-status-message progress';
            statusArea.innerHTML = `
                <div class="status-content">
                    <div class="status-spinner">⏳</div>
                    <span>${message}</span>
                </div>
            `;
        }

        function showUpdateSuccess(message) {
            const statusArea = getOrCreateStatusArea();
            statusArea.className = 'update-status-message success';
            statusArea.innerHTML = `
                <div class="status-content">
                    <div class="status-icon">✅</div>
                    <span>${message}</span>
                    <button class="status-close" onclick="hideStatusMessage()">×</button>
                </div>
            `;

            // 3초 후 자동 닫기
            setTimeout(() => {
                hideStatusMessage();
            }, 3000);
        }

        function showUpdateError(message) {
            const statusArea = getOrCreateStatusArea();
            statusArea.className = 'update-status-message error';
            statusArea.innerHTML = `
                <div class="status-content">
                    <div class="status-icon">❌</div>
                    <span>${message}</span>
                    <button class="status-close" onclick="hideStatusMessage()">×</button>
                </div>
            `;
        }

        function getOrCreateStatusArea() {
            let statusArea = document.getElementById('updateStatusArea');
            if (!statusArea) {
                statusArea = document.createElement('div');
                statusArea.id = 'updateStatusArea';
                statusArea.style.display = 'inline-block';
                statusArea.style.marginLeft = '10px';
                statusArea.style.verticalAlign = 'middle';

                // 취소 버튼 옆에 삽입
                const cancelButton = document.querySelector('.form-actions a[href="index.php"]');
                if (cancelButton) {
                    cancelButton.parentNode.insertBefore(statusArea, cancelButton.nextSibling);
                }
            }
            return statusArea;
        }

        function hideStatusMessage() {
            const statusArea = document.getElementById('updateStatusArea');
            if (statusArea) {
                statusArea.style.animation = 'fadeOut 0.3s ease forwards';
                setTimeout(() => {
                    statusArea.remove();
                }, 300);
            }
        }

        // 알림 메시지 관련 함수들
        function closeNotification() {
            const notification = document.getElementById('updateNotification');
            if (notification) {
                notification.style.animation = 'fadeOut 0.3s ease-in forwards';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }
        }

        // 자동으로 5초 후에 알림 닫기 + 수정 버튼 아래에도 동일한 메시지 표시
        window.addEventListener('load', function() {
            const notification = document.getElementById('updateNotification');
            if (notification) {
                // 세션 알림이 있는 경우 수정 버튼 아래에도 같은 메시지 표시
                const notificationText = notification.querySelector('h4')?.textContent;
                const isSuccess = notification.classList.contains('success');

                if (notificationText) {
                    if (isSuccess) {
                        showUpdateSuccess(notificationText);
                    } else {
                        showUpdateError(notificationText);
                    }
                }

                setTimeout(() => {
                    closeNotification();
                }, 5000);
            }
        });
    </script>

    <!-- Cropper.js 라이브러리 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

    <style>
        .image-preview-container {
            margin-top: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
            overflow: hidden;
            max-width: 100%;
        }

        .preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            background: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
        }

        .preview-tabs {
            display: flex;
            gap: 10px;
        }

        .tab-btn {
            padding: 6px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s;
        }

        .tab-btn.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        .tab-content {
            display: none;
            padding: 15px;
            max-height: 500px;
            overflow-y: auto;
        }

        .tab-content[data-content="crop"] {
            max-height: 600px;
        }

        .tab-content.active {
            display: block;
        }

        .btn-remove {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 4px 8px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-remove:hover {
            background: #c82333;
        }

        #preview-image, #crop-image, #preview-new-image {
            max-width: 100%;
            max-height: 250px;
            width: auto;
            border: 1px solid #ddd;
            border-radius: 6px;
            display: block;
            margin: 0 auto 15px auto;
            object-fit: contain;
            background: #f8f9fa;
        }

        .crop-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
            justify-content: center;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 6px;
            border: 2px solid #e9ecef;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .crop-controls button {
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            cursor: pointer;
            font-size: 11px;
        }

        .crop-controls .btn-primary {
            background: #28a745;
            color: white;
            border-color: #28a745;
            font-weight: bold;
            padding: 6px 12px;
            font-size: 12px;
            box-shadow: 0 2px 4px rgba(40,167,69,0.2);
        }

        .crop-controls .btn-primary:hover {
            background: #218838;
            border-color: #1e7e34;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(40,167,69,0.3);
        }

        .image-info {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            justify-content: center;
            color: #666;
            font-size: 14px;
        }

        .image-guidelines {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 15px;
        }

        .image-guidelines h4 {
            margin: 0 0 10px 0;
            color: #1976d2;
            font-size: 14px;
        }

        .guidelines-list {
            margin: 0;
            padding-left: 20px;
            font-size: 13px;
            color: #424242;
        }

        .guidelines-list li {
            margin-bottom: 5px;
        }

        @media (max-width: 768px) {
            .crop-controls {
                flex-direction: column;
                align-items: center;
            }

            .preview-tabs {
                flex-direction: column;
                gap: 5px;
            }
        }
    </style>
</body>
</html>
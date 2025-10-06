<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ||
          (isset($_SESSION['user_level']) && $_SESSION['user_level'] == 9);

if (!$isAdmin) {
    header('Location: /pages/auth/login.php?redirect=/admin/settings/media.php');
    exit;
}

$success = '';
$error = '';

// 파일 업로드 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = DatabaseConfig::getConnection();
        $uploadDir = __DIR__ . '/../../uploads/media/';

        // 디렉토리가 없으면 생성
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $settings = [];

        // 로고 업로드 처리
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $logoInfo = pathinfo($_FILES['logo']['name']);
            $logoName = 'logo_' . time() . '.' . $logoInfo['extension'];
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $logoName)) {
                $settings['site_logo'] = '/uploads/media/' . $logoName;
            }
        }

        // 파비콘 업로드 처리
        if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
            $faviconInfo = pathinfo($_FILES['favicon']['name']);
            $faviconName = 'favicon_' . time() . '.' . $faviconInfo['extension'];
            if (move_uploaded_file($_FILES['favicon']['tmp_name'], $uploadDir . $faviconName)) {
                $settings['site_favicon'] = '/uploads/media/' . $faviconName;
            }
        }

        // 배경 이미지 업로드 처리
        if (isset($_FILES['hero_bg']) && $_FILES['hero_bg']['error'] === UPLOAD_ERR_OK) {
            $bgInfo = pathinfo($_FILES['hero_bg']['name']);
            $bgName = 'hero_bg_' . time() . '.' . $bgInfo['extension'];
            if (move_uploaded_file($_FILES['hero_bg']['tmp_name'], $uploadDir . $bgName)) {
                $settings['hero_background'] = '/uploads/media/' . $bgName;
            }
        }

        // 회사 소개 이미지 업로드 처리
        if (isset($_FILES['about_image']) && $_FILES['about_image']['error'] === UPLOAD_ERR_OK) {
            $aboutInfo = pathinfo($_FILES['about_image']['name']);
            $aboutName = 'about_' . time() . '.' . $aboutInfo['extension'];
            if (move_uploaded_file($_FILES['about_image']['tmp_name'], $uploadDir . $aboutName)) {
                $settings['about_image'] = '/uploads/media/' . $aboutName;
            }
        }

        // 슬라이더 이미지 업로드 처리
        if (isset($_FILES['slider_images'])) {
            $sliderImages = [];
            $existingSliderImages = trim($_POST['hero_media_list'] ?? '');

            // 기존 이미지 목록이 있으면 배열로 변환
            if (!empty($existingSliderImages)) {
                $sliderImages = array_filter(explode(',', $existingSliderImages));
            }

            $fileCount = count($_FILES['slider_images']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['slider_images']['error'][$i] === UPLOAD_ERR_OK) {
                    $sliderInfo = pathinfo($_FILES['slider_images']['name'][$i]);
                    $sliderName = 'hero_media_' . time() . '_' . $i . '.' . $sliderInfo['extension'];
                    if (move_uploaded_file($_FILES['slider_images']['tmp_name'][$i], $uploadDir . $sliderName)) {
                        $sliderImages[] = '/uploads/media/' . $sliderName;
                    }
                }
            }

            if (!empty($sliderImages)) {
                $settings['hero_media_list'] = implode(',', $sliderImages);
            }
        }

        // 텍스트 설정 저장
        $textSettings = [
            'hero_title' => trim($_POST['hero_title'] ?? ''),
            'hero_subtitle' => trim($_POST['hero_subtitle'] ?? ''),
            'hero_description' => trim($_POST['hero_description'] ?? ''),
            'hero_media_list' => trim($_POST['hero_media_list'] ?? ''),
            'about_video_url' => trim($_POST['about_video_url'] ?? ''),
            'gallery_images' => trim($_POST['gallery_images'] ?? ''),
            'social_youtube' => trim($_POST['social_youtube'] ?? ''),
            'social_instagram' => trim($_POST['social_instagram'] ?? ''),
            'social_facebook' => trim($_POST['social_facebook'] ?? ''),
            'social_blog' => trim($_POST['social_blog'] ?? '')
        ];

        $settings = array_merge($settings, $textSettings);

        foreach ($settings as $key => $value) {
            $sql = "INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$key, $value]);
        }

        $success = '미디어 설정이 성공적으로 저장되었습니다.';
    } catch (Exception $e) {
        $error = '저장 중 오류가 발생했습니다: ' . $e->getMessage();
    }
}

$currentSettings = [];
try {
    $pdo = DatabaseConfig::getConnection();
    $sql = "SELECT setting_key, setting_value FROM site_settings";
    $stmt = $pdo->query($sql);
    while ($row = $stmt->fetch()) {
        $currentSettings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    $error = '데이터 불러오기 중 오류가 발생했습니다.';
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>미디어 관리 - 탄생 관리자</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-body">
    <?php include '../includes/admin_header.php'; ?>

    <div class="admin-container">
        <?php include '../includes/admin_sidebar.php'; ?>

        <main class="admin-main">
            <div class="admin-content settings-container">
                <div class="settings-header">
                    <h1>🎨 미디어 관리</h1>
                    <p>사이트에 사용되는 이미지, 동영상 등의 미디어를 관리합니다</p>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data" class="admin-form">
                    <div class="form-section">
                        <div class="section-header">
                            <span class="section-icon">🏷️</span>
                            <h3>기본 브랜딩</h3>
                        </div>
                        <div class="section-body">
                            <div class="form-group">
                                <label for="logo">사이트 로고</label>
                                <div class="upload-area">
                                    <input type="file" id="logo" name="logo" accept="image/*" class="form-control">
                                    <small>권장 크기: 200x60px, PNG/JPG/SVG 형식</small>
                                </div>
                                <?php if (!empty($currentSettings['site_logo'])): ?>
                                    <div class="current-image">
                                        <p>현재 로고:</p>
                                        <img src="<?= htmlspecialchars($currentSettings['site_logo']) ?>" alt="현재 로고" style="max-height: 60px;">
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label for="favicon">파비콘</label>
                                <input type="file" id="favicon" name="favicon" accept="image/*">
                                <small>권장 크기: 32x32px, ICO/PNG 형식</small>
                                <?php if (!empty($currentSettings['site_favicon'])): ?>
                                    <div class="current-image">
                                        <p>현재 파비콘:</p>
                                        <img src="<?= htmlspecialchars($currentSettings['site_favicon']) ?>" alt="현재 파비콘" style="max-height: 32px;">
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>메인 페이지 히어로 섹션</h3>

                        <div class="form-group">
                            <label for="hero_title">히어로 제목</label>
                            <input type="text" id="hero_title" name="hero_title"
                                   value="<?= htmlspecialchars($currentSettings['hero_title'] ?? '스마트팜의 미래를 여는 탄생') ?>">
                        </div>

                        <div class="form-group">
                            <label for="hero_subtitle">히어로 부제목</label>
                            <input type="text" id="hero_subtitle" name="hero_subtitle"
                                   value="<?= htmlspecialchars($currentSettings['hero_subtitle'] ?? '혁신적인 배지 기술로 지속가능한 농업을 실현합니다') ?>">
                        </div>

                        <div class="form-group">
                            <label for="hero_description">히어로 설명</label>
                            <textarea id="hero_description" name="hero_description" class="form-control" rows="3"><?= htmlspecialchars($currentSettings['hero_description'] ?? '탄생의 고품질 수경재배 배지와 AI 기반 식물분석 서비스로 스마트팜의 새로운 가능성을 경험하세요.') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="hero_bg">히어로 배경 이미지 (단일)</label>
                            <input type="file" id="hero_bg" name="hero_bg" accept="image/*">
                            <small>권장 크기: 1920x1080px, JPG/PNG 형식 (단일 이미지 업로드)</small>
                            <?php if (!empty($currentSettings['hero_background'])): ?>
                                <div class="current-image">
                                    <p>현재 배경 이미지:</p>
                                    <img src="<?= htmlspecialchars($currentSettings['hero_background']) ?>" alt="현재 배경" style="max-width: 300px; max-height: 200px;">
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="hero_media_list">메인페이지 슬라이더 이미지 관리</label>

                            <!-- 직관적인 이미지 업로드 영역 -->
                            <div class="image-upload-section">
                                <div class="upload-instructions">
                                    <h4>💡 이미지 업로드 방법</h4>
                                    <ul>
                                        <li><strong>컴퓨터에서 선택:</strong> "이미지 추가" 버튼을 클릭하여 여러 이미지를 한번에 업로드</li>
                                        <li><strong>드래그 앤 드롭:</strong> 파일을 업로드 영역에 직접 끌어다 놓기</li>
                                        <li><strong>이미지 순서:</strong> 업로드한 순서대로 슬라이더에 표시됩니다</li>
                                        <li><strong>권장 크기:</strong> 1920x1080px (풀스크린), 최대 20MB</li>
                                    </ul>
                                </div>

                                <div class="upload-buttons">
                                    <button type="button" onclick="insertSliderImage()" class="btn btn-primary" id="sliderUploadBtn">
                                        📷 이미지 추가 (컴퓨터에서 선택)
                                    </button>
                                    <button type="button" onclick="toggleTextEditor()" class="btn btn-secondary">
                                        📝 직접 URL 입력
                                    </button>
                                    <button type="button" onclick="previewSlider()" class="btn btn-success">
                                        👁️ 슬라이더 미리보기
                                    </button>
                                    <button type="button" onclick="testFunction()" class="btn btn-outline">
                                        🧪 테스트
                                    </button>
                                </div>
                            </div>

                            <!-- 업로드된 이미지 목록 관리 -->
                            <div id="uploaded_images_list" class="uploaded-images-list">
                                <h4>업로드된 슬라이더 이미지 (<span id="image_count">0</span>개)</h4>
                                <div id="images_grid" class="images-grid">
                                    <!-- 업로드된 이미지들이 여기에 표시됩니다 -->
                                </div>
                                <div class="image-management-actions">
                                    <button type="button" onclick="sortImages()" class="btn btn-outline">🔄 순서 변경</button>
                                    <button type="button" onclick="clearSliderImages()" class="btn btn-danger">🗑️ 전체 삭제</button>
                                </div>
                            </div>

                            <!-- URL 직접 입력 (숨김 상태) -->
                            <div id="text_editor_section" class="text-editor-section" style="display: none;">
                                <div class="editor-container">
                                    <textarea id="hero_media_list" name="hero_media_list" class="form-control editor-textarea" rows="6"
                                        placeholder="슬라이더에 사용할 이미지 URL을 한 줄에 하나씩 입력하세요.
예시:
/uploads/media/slider1.jpg
/uploads/media/slider2.jpg
/uploads/media/slider3.jpg"><?= htmlspecialchars($currentSettings['hero_media_list'] ?? '') ?></textarea>
                                    <div class="editor-help">
                                        <p><strong>URL 직접 입력 방법:</strong></p>
                                        <ul>
                                            <li>각 이미지 URL을 새 줄에 입력하세요</li>
                                            <li>상대 경로 (/uploads/media/image.jpg) 또는 절대 URL 사용 가능</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <!-- 미리보기 영역 -->
                            <div id="slider_preview" class="slider-preview" style="display: none;">
                                <h4>슬라이더 미리보기:</h4>
                                <div class="preview-container"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>회사 소개 미디어</h3>

                        <div class="form-group">
                            <label for="about_image">회사 소개 이미지</label>
                            <input type="file" id="about_image" name="about_image" accept="image/*">
                            <small>권장 크기: 800x600px, JPG/PNG 형식</small>
                            <?php if (!empty($currentSettings['about_image'])): ?>
                                <div class="current-image">
                                    <p>현재 이미지:</p>
                                    <img src="<?= htmlspecialchars($currentSettings['about_image']) ?>" alt="회사 소개" style="max-width: 300px; max-height: 200px;">
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="about_video_url">회사 소개 동영상 URL</label>
                            <input type="url" id="about_video_url" name="about_video_url"
                                   value="<?= htmlspecialchars($currentSettings['about_video_url'] ?? '') ?>"
                                   placeholder="https://www.youtube.com/watch?v=...">
                            <small>YouTube, Vimeo 등의 동영상 URL을 입력하세요</small>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>갤러리 및 포트폴리오</h3>

                        <div class="form-group">
                            <label for="gallery_images">갤러리 이미지 목록</label>
                            <div class="editor-container">
                                <div class="editor-toolbar">
                                    <button type="button" onclick="insertGalleryImage()" class="toolbar-btn">📷 이미지 추가</button>
                                    <button type="button" onclick="previewGallery()" class="toolbar-btn">👁️ 미리보기</button>
                                    <button type="button" onclick="clearGalleryImages()" class="toolbar-btn">🗑️ 전체 삭제</button>
                                </div>
                                <textarea id="gallery_images" name="gallery_images" class="form-control editor-textarea" rows="6"
                                    placeholder="갤러리에 사용할 이미지 URL을 한 줄에 하나씩 입력하세요.
예시:
/uploads/media/gallery1.jpg
/uploads/media/gallery2.jpg
/uploads/media/gallery3.jpg"><?= htmlspecialchars($currentSettings['gallery_images'] ?? '/assets/images/gallery1.jpg
/assets/images/gallery2.jpg
/assets/images/gallery3.jpg') ?></textarea>
                                <div class="editor-help">
                                    <p><strong>사용법:</strong></p>
                                    <ul>
                                        <li>각 이미지 URL을 새 줄에 입력하세요</li>
                                        <li>상대 경로 (/uploads/media/image.jpg) 또는 절대 URL 사용 가능</li>
                                        <li>권장 크기: 800x600px, JPG/PNG 형식</li>
                                    </ul>
                                </div>
                            </div>
                            <div id="gallery_preview" class="slider-preview" style="display: none;">
                                <h4>갤러리 미리보기:</h4>
                                <div class="preview-container gallery-preview-container"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>소셜 미디어 링크</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="social_youtube">YouTube</label>
                                <input type="url" id="social_youtube" name="social_youtube"
                                       value="<?= htmlspecialchars($currentSettings['social_youtube'] ?? '') ?>"
                                       placeholder="https://youtube.com/@탄생">
                            </div>

                            <div class="form-group">
                                <label for="social_instagram">Instagram</label>
                                <input type="url" id="social_instagram" name="social_instagram"
                                       value="<?= htmlspecialchars($currentSettings['social_instagram'] ?? '') ?>"
                                       placeholder="https://instagram.com/탄생">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="social_facebook">Facebook</label>
                                <input type="url" id="social_facebook" name="social_facebook"
                                       value="<?= htmlspecialchars($currentSettings['social_facebook'] ?? '') ?>"
                                       placeholder="https://facebook.com/탄생">
                            </div>

                            <div class="form-group">
                                <label for="social_blog">블로그</label>
                                <input type="url" id="social_blog" name="social_blog"
                                       value="<?= htmlspecialchars($currentSettings['social_blog'] ?? '') ?>"
                                       placeholder="https://blog.탄생.com">
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">저장</button>
                        <button type="reset" class="btn btn-secondary">취소</button>
                    </div>
                </form>

                <div class="form-section">
                    <h3>미디어 가이드라인</h3>
                    <div class="info-box">
                        <h4>이미지 최적화 가이드:</h4>
                        <ul>
                            <li><strong>로고:</strong> 투명 배경 PNG 권장, 200x60px</li>
                            <li><strong>파비콘:</strong> 32x32px ICO 또는 PNG</li>
                            <li><strong>히어로 배경:</strong> 1920x1080px, 파일 크기 1MB 이하</li>
                            <li><strong>갤러리:</strong> 800x600px, JPG 형식 권장</li>
                            <li><strong>최대 파일 크기:</strong> 20MB</li>
                            <li><strong>지원 형식:</strong> JPG, PNG, GIF, SVG</li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <style>
        .editor-container {
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 1rem;
        }

        .editor-toolbar {
            background: #f8f9fa;
            padding: 10px;
            border-bottom: 1px solid #ddd;
            display: flex;
            gap: 10px;
        }

        .toolbar-btn {
            background: #fff;
            border: 1px solid #ddd;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .toolbar-btn:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }

        .editor-textarea {
            border: none !important;
            border-radius: 0 !important;
            resize: vertical;
            font-family: monospace;
            font-size: 14px;
            line-height: 1.5;
        }

        .editor-help {
            background: #f8f9fa;
            padding: 15px;
            font-size: 13px;
        }

        .editor-help ul {
            margin: 10px 0 0 20px;
            color: #666;
        }

        .slider-preview {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .preview-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .preview-item {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .preview-item img {
            width: 100%;
            height: 120px;
            object-fit: cover;
        }

        .preview-item p {
            padding: 10px;
            margin: 0;
            font-size: 12px;
            color: #666;
            text-align: center;
        }

        .upload-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .upload-modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            width: 90%;
            max-width: 500px;
        }

        .upload-modal h3 {
            margin-top: 0;
            margin-bottom: 20px;
        }

        .file-upload-area {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 40px 20px;
            text-align: center;
            margin-bottom: 20px;
            transition: border-color 0.3s ease;
        }

        .file-upload-area:hover {
            border-color: #4CAF50;
        }

        .file-upload-area.dragover {
            border-color: #4CAF50;
            background-color: #f0fff0;
        }

        .upload-progress {
            display: none;
            margin-top: 15px;
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: #4CAF50;
            width: 0%;
            transition: width 0.3s ease;
        }

        /* 개선된 슬라이더 관리 스타일 */
        .image-upload-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .upload-instructions {
            background: white;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            border-left: 4px solid #4CAF50;
        }

        .upload-instructions h4 {
            margin-top: 0;
            color: #2E7D32;
        }

        .upload-instructions ul {
            margin: 10px 0;
            padding-left: 20px;
        }

        .upload-instructions li {
            margin: 8px 0;
            line-height: 1.5;
        }

        .upload-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .uploaded-images-list {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
        }

        .uploaded-images-list h4 {
            color: #2E7D32;
            margin-bottom: 15px;
        }

        .images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
            min-height: 50px;
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 15px;
        }

        .images-grid:empty::before {
            content: "아직 업로드된 이미지가 없습니다. 위의 '이미지 추가' 버튼을 클릭하거나 이 영역에 이미지를 드래그하세요.";
            display: block;
            text-align: center;
            color: #999;
            font-style: italic;
            line-height: 2;
        }

        .image-item {
            position: relative;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }

        .image-item:hover {
            transform: translateY(-2px);
        }

        .image-item img {
            width: 100%;
            height: 120px;
            object-fit: cover;
        }

        .image-item-info {
            padding: 10px;
        }

        .image-item-title {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .image-item-actions {
            display: flex;
            gap: 5px;
            justify-content: space-between;
        }

        .image-item-btn {
            background: none;
            border: 1px solid #ddd;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
            transition: all 0.2s ease;
        }

        .image-item-btn:hover {
            background: #f0f0f0;
        }

        .image-item-btn.delete {
            color: #dc3545;
            border-color: #dc3545;
        }

        .image-item-btn.delete:hover {
            background: #dc3545;
            color: white;
        }

        .image-item-order {
            position: absolute;
            top: 5px;
            left: 5px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }

        .image-management-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .text-editor-section {
            margin-top: 20px;
        }

        .btn {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s ease;
        }

        .btn:hover {
            background: #45a049;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-success {
            background: #28a745;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-outline {
            background: transparent;
            color: #4CAF50;
            border: 1px solid #4CAF50;
        }

        .btn-outline:hover {
            background: #4CAF50;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
        }

        .btn-danger:hover {
            background: #c82333;
        }
    </style>

    <script>
        // 테스트 함수
        function testFunction() {
            alert('테스트 버튼이 클릭되었습니다!');
            console.log('테스트 함수 호출됨');
        }

        // 슬라이더 이미지 추가 함수
        function insertSliderImage() {
            console.log('슬라이더 이미지 추가 함수 호출됨');
            alert('슬라이더 이미지 추가 함수가 호출되었습니다!');

            // 기존의 hidden file input이 있으면 제거
            const existingInput = document.getElementById('hiddenSliderFileInput');
            if (existingInput) {
                existingInput.remove();
            }

            // 새로운 file input 생성
            const fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.id = 'hiddenSliderFileInput';
            fileInput.accept = 'image/*';
            fileInput.multiple = true;
            fileInput.style.display = 'none';

            // 파일 선택 이벤트 리스너
            fileInput.addEventListener('change', function(e) {
                console.log('파일 선택됨:', e.target.files.length, '개');
                if (e.target.files.length > 0) {
                    uploadSliderImages(e.target.files);
                }
            });

            // body에 추가하고 클릭
            document.body.appendChild(fileInput);
            fileInput.click();
        }

        // 갤러리 이미지 추가 함수
        function insertGalleryImage() {
            console.log('갤러리 이미지 추가 함수 호출됨');

            // 기존의 hidden file input이 있으면 제거
            const existingInput = document.getElementById('hiddenGalleryFileInput');
            if (existingInput) {
                existingInput.remove();
            }

            // 새로운 file input 생성
            const fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.id = 'hiddenGalleryFileInput';
            fileInput.accept = 'image/*';
            fileInput.multiple = true;
            fileInput.style.display = 'none';

            // 파일 선택 이벤트 리스너
            fileInput.addEventListener('change', function(e) {
                console.log('갤러리 파일 선택됨:', e.target.files.length, '개');
                if (e.target.files.length > 0) {
                    uploadGalleryImages(e.target.files);
                }
            });

            // body에 추가하고 클릭
            document.body.appendChild(fileInput);
            fileInput.click();
        }

        // 슬라이더 이미지 업로드 처리
        function uploadSliderImages(files) {
            console.log('슬라이더 이미지 업로드 시작:', files.length, '개');

            const formData = new FormData();

            // 기존 폼 데이터 추가 (다른 설정 유지)
            const form = document.querySelector('form');
            const formInputs = form.querySelectorAll('input, textarea, select');
            formInputs.forEach(input => {
                if (input.type !== 'file' && input.name) {
                    formData.append(input.name, input.value);
                }
            });

            // 슬라이더 이미지 파일들 추가
            for (let i = 0; i < files.length; i++) {
                formData.append('slider_images[]', files[i]);
                console.log('파일 추가:', files[i].name);
            }

            // 업로드 상태 표시
            showUploadStatus('슬라이더 이미지 업로드 중...', files.length);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('서버 응답 받음');
                return response.text();
            })
            .then(data => {
                console.log('업로드 완료');
                hideUploadStatus();
                alert('슬라이더 이미지가 성공적으로 업로드되었습니다!');
                // 페이지 새로고침하여 업로드된 이미지 반영
                location.reload();
            })
            .catch(error => {
                console.error('업로드 오류:', error);
                hideUploadStatus();
                alert('업로드 중 오류가 발생했습니다: ' + error.message);
            });
        }

        // 갤러리 이미지 업로드 처리
        function uploadGalleryImages(files) {
            console.log('갤러리 이미지 업로드 시작:', files.length, '개');

            const formData = new FormData();
            for (let i = 0; i < files.length; i++) {
                formData.append('slider_images[]', files[i]);
            }

            showUploadStatus('갤러리 이미지 업로드 중...', files.length);

            fetch('/admin/includes/upload_slider_images.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const textarea = document.getElementById('gallery_images');
                    const currentValue = textarea.value.trim();
                    const newUrls = data.urls.join('\\n');

                    if (currentValue) {
                        textarea.value = currentValue + '\\n' + newUrls;
                    } else {
                        textarea.value = newUrls;
                    }

                    previewGallery();
                    hideUploadStatus();
                    alert(`${data.urls.length}개의 이미지가 성공적으로 업로드되었습니다!`);
                } else {
                    hideUploadStatus();
                    alert('업로드 실패: ' + (data.error || '알 수 없는 오류'));
                }
            })
            .catch(error => {
                console.error('업로드 오류:', error);
                hideUploadStatus();
                alert('업로드 중 오류가 발생했습니다: ' + error.message);
            });
        }

        // 업로드 상태 표시
        function showUploadStatus(message, fileCount) {
            // 기존 상태 표시 제거
            hideUploadStatus();

            const statusDiv = document.createElement('div');
            statusDiv.id = 'uploadStatus';
            statusDiv.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: rgba(0,0,0,0.8);
                color: white;
                padding: 20px;
                border-radius: 8px;
                z-index: 10000;
                text-align: center;
            `;
            statusDiv.innerHTML = `
                <div>${message}</div>
                <div style="margin-top: 10px;">${fileCount}개 파일 처리 중...</div>
                <div style="margin-top: 10px;">잠시만 기다려주세요...</div>
            `;

            document.body.appendChild(statusDiv);
        }

        // 업로드 상태 숨기기
        function hideUploadStatus() {
            const statusDiv = document.getElementById('uploadStatus');
            if (statusDiv) {
                statusDiv.remove();
            }
        }


        // 슬라이더 미리보기
        function previewSlider() {
            const textarea = document.getElementById('hero_media_list');
            const previewDiv = document.getElementById('slider_preview');
            const previewContainer = previewDiv.querySelector('.preview-container');

            const urls = textarea.value.split('\\n').map(url => url.trim()).filter(url => url);

            if (urls.length === 0) {
                previewDiv.style.display = 'none';
                return;
            }

            previewContainer.innerHTML = '';

            urls.forEach((url, index) => {
                const previewItem = document.createElement('div');
                previewItem.className = 'preview-item';
                previewItem.innerHTML = `
                    <img src="${url}" alt="슬라이더 이미지 ${index + 1}" onerror="this.src='/assets/images/placeholder.jpg'">
                    <p>슬라이드 ${index + 1}</p>
                `;
                previewContainer.appendChild(previewItem);
            });

            previewDiv.style.display = 'block';
        }

        // 슬라이더 이미지 전체 삭제
        function clearSliderImages() {
            if (confirm('모든 슬라이더 이미지를 삭제하시겠습니까?')) {
                document.getElementById('hero_media_list').value = '';
                document.getElementById('slider_preview').style.display = 'none';
            }
        }

        // 갤러리 미리보기
        function previewGallery() {
            const textarea = document.getElementById('gallery_images');
            const previewDiv = document.getElementById('gallery_preview');
            const previewContainer = previewDiv.querySelector('.preview-container');

            const urls = textarea.value.split('\\n').map(url => url.trim()).filter(url => url);

            if (urls.length === 0) {
                previewDiv.style.display = 'none';
                return;
            }

            previewContainer.innerHTML = '';

            urls.forEach((url, index) => {
                const previewItem = document.createElement('div');
                previewItem.className = 'preview-item';
                previewItem.innerHTML = `
                    <img src="${url}" alt="갤러리 이미지 ${index + 1}" onerror="this.src='/assets/images/placeholder.jpg'">
                    <p>갤러리 ${index + 1}</p>
                `;
                previewContainer.appendChild(previewItem);
            });

            previewDiv.style.display = 'block';
        }

        // 갤러리 이미지 전체 삭제
        function clearGalleryImages() {
            if (confirm('모든 갤러리 이미지를 삭제하시겠습니까?')) {
                document.getElementById('gallery_images').value = '';
                document.getElementById('gallery_preview').style.display = 'none';
            }
        }

        // URL 직접 입력 에디터 토글
        function toggleTextEditor() {
            const textSection = document.getElementById('text_editor_section');
            const isVisible = textSection.style.display !== 'none';

            if (isVisible) {
                textSection.style.display = 'none';
            } else {
                textSection.style.display = 'block';
            }
        }

        // 이미지 목록을 그리드에 표시
        function updateImagesGrid() {
            const textarea = document.getElementById('hero_media_list');
            const imagesGrid = document.getElementById('images_grid');
            const imageCount = document.getElementById('image_count');

            const urls = textarea.value.split('\\n').map(url => url.trim()).filter(url => url);

            imagesGrid.innerHTML = '';

            urls.forEach((url, index) => {
                const imageItem = document.createElement('div');
                imageItem.className = 'image-item';
                imageItem.innerHTML = `
                    <div class="image-item-order">${index + 1}</div>
                    <img src="${url}" alt="슬라이더 이미지 ${index + 1}" onerror="this.src='/assets/images/placeholder.jpg'">
                    <div class="image-item-info">
                        <div class="image-item-title">슬라이드 ${index + 1}</div>
                        <div class="image-item-actions">
                            <button type="button" class="image-item-btn" onclick="moveImageUp(${index})" ${index === 0 ? 'disabled' : ''}>↑</button>
                            <button type="button" class="image-item-btn" onclick="moveImageDown(${index})" ${index === urls.length - 1 ? 'disabled' : ''}>↓</button>
                            <button type="button" class="image-item-btn delete" onclick="removeImage(${index})">삭제</button>
                        </div>
                    </div>
                `;
                imagesGrid.appendChild(imageItem);
            });

            imageCount.textContent = urls.length;
        }

        // 이미지 위로 이동
        function moveImageUp(index) {
            const textarea = document.getElementById('hero_media_list');
            const urls = textarea.value.split('\\n').map(url => url.trim()).filter(url => url);

            if (index > 0) {
                [urls[index - 1], urls[index]] = [urls[index], urls[index - 1]];
                textarea.value = urls.join('\\n');
                updateImagesGrid();
            }
        }

        // 이미지 아래로 이동
        function moveImageDown(index) {
            const textarea = document.getElementById('hero_media_list');
            const urls = textarea.value.split('\\n').map(url => url.trim()).filter(url => url);

            if (index < urls.length - 1) {
                [urls[index], urls[index + 1]] = [urls[index + 1], urls[index]];
                textarea.value = urls.join('\\n');
                updateImagesGrid();
            }
        }

        // 개별 이미지 제거
        function removeImage(index) {
            if (confirm('이 이미지를 제거하시겠습니까?')) {
                const textarea = document.getElementById('hero_media_list');
                const urls = textarea.value.split('\\n').map(url => url.trim()).filter(url => url);

                urls.splice(index, 1);
                textarea.value = urls.join('\\n');
                updateImagesGrid();
            }
        }

        // 이미지 순서 변경 (드래그 앤 드롭)
        function sortImages() {
            alert('드래그 앤 드롭으로 순서를 변경하거나, 각 이미지의 ↑↓ 버튼을 사용하세요.');
        }

        // 이미지 그리드에 드래그 앤 드롭 추가
        function enableGridDropZone() {
            const imagesGrid = document.getElementById('images_grid');

            imagesGrid.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                this.style.borderColor = '#4CAF50';
                this.style.backgroundColor = '#f0fff0';
            });

            imagesGrid.addEventListener('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                this.style.borderColor = '#ddd';
                this.style.backgroundColor = 'transparent';
            });

            imagesGrid.addEventListener('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                this.style.borderColor = '#ddd';
                this.style.backgroundColor = 'transparent';

                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    console.log('드래그 앤 드롭으로 파일 업로드:', files.length, '개');
                    uploadSliderImages(files);
                }
            });
        }

        // 슬라이더 이미지 자동 저장
        function autoSaveSliderImages() {
            const textarea = document.getElementById('hero_media_list');
            const formData = new FormData();
            formData.append('hero_media_list', textarea.value);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                console.log('슬라이더 이미지가 자동 저장되었습니다.');
            })
            .catch(error => {
                console.error('자동 저장 중 오류:', error);
            });
        }

        // 페이지 로드시 초기화
        document.addEventListener('DOMContentLoaded', function() {
            // 이미지 그리드 업데이트
            updateImagesGrid();

            // 드래그 앤 드롭 활성화
            enableGridDropZone();

            // 슬라이더 미리보기 초기화
            const sliderTextarea = document.getElementById('hero_media_list');
            if (sliderTextarea && sliderTextarea.value.trim()) {
                previewSlider();
            }

            // 갤러리 미리보기 초기화
            const galleryTextarea = document.getElementById('gallery_images');
            if (galleryTextarea && galleryTextarea.value.trim()) {
                previewGallery();
            }

            // 슬라이더 텍스트영역 변경시 자동 업데이트
            sliderTextarea.addEventListener('input', function() {
                updateImagesGrid();
                if (this.value.trim()) {
                    previewSlider();
                } else {
                    document.getElementById('slider_preview').style.display = 'none';
                }
            });

            // 갤러리 텍스트영역 변경시 자동 미리보기
            galleryTextarea.addEventListener('input', function() {
                if (this.value.trim()) {
                    previewGallery();
                } else {
                    document.getElementById('gallery_preview').style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
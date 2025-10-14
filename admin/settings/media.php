<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ||
          (isset($_SESSION['user_level']) && $_SESSION['user_level'] == 9);

if (!$isAdmin) {
    header('Location: /pages/auth/login.php?redirect=/admin/settings/media.php');
    exit;
}

$pdo = DatabaseConfig::getConnection();

$success = '';
$error = '';

// POST 요청 처리 (저장)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $settingsToSave = [
            'hero_media_list' => $_POST['hero_media_list'] ?? '',
            'hero_title' => $_POST['hero_title'] ?? '',
            'hero_subtitle' => $_POST['hero_subtitle'] ?? '',
            'hero_description' => $_POST['hero_description'] ?? '',
            'hero_background' => $_POST['hero_background'] ?? '',
            'site_logo' => $_POST['site_logo'] ?? '',
            'site_favicon' => $_POST['site_favicon'] ?? '',
            'about_image' => $_POST['about_image'] ?? '',
            'about_video_url' => $_POST['about_video_url'] ?? '',
            'gallery_images' => $_POST['gallery_images'] ?? '',
            'social_youtube' => $_POST['social_youtube'] ?? '',
            'social_instagram' => $_POST['social_instagram'] ?? '',
            'social_facebook' => $_POST['social_facebook'] ?? '',
            'social_blog' => $_POST['social_blog'] ?? ''
        ];

        $updatedCount = 0;
        foreach ($settingsToSave as $key => $value) {
            $sql = "INSERT INTO site_settings (setting_key, setting_value, created_at, updated_at)
                    VALUES (?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$key, $value, $value]);
            $updatedCount++;
        }

        echo json_encode([
            'success' => true,
            'message' => "모든 설정이 저장되었습니다! ({$updatedCount}개 항목)",
            'debug' => [
                'hero_background' => $settingsToSave['hero_background'],
                'hero_media_list_length' => strlen($settingsToSave['hero_media_list'])
            ]
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// 현재 설정 불러오기
$currentSettings = [];
try {
    $sql = "SELECT setting_key, setting_value FROM site_settings";
    $stmt = $pdo->query($sql);
    while ($row = $stmt->fetch()) {
        $currentSettings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    $error = '데이터 불러오기 중 오류가 발생했습니다.';
}

// 기존 슬라이더 이미지 목록
$sliderImages = [];
if (!empty($currentSettings['hero_media_list'])) {
    // 콤마 또는 줄바꿈으로 구분된 이미지 목록 처리
    $mediaList = str_replace(["\r\n", "\r", ","], "\n", $currentSettings['hero_media_list']);
    $sliderImages = array_filter(array_map('trim', explode("\n", $mediaList)));
}

// 갤러리 이미지 목록
$galleryImages = [];
if (!empty($currentSettings['gallery_images'])) {
    $galleryImages = array_filter(explode("\n", $currentSettings['gallery_images']));
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
    <style>
        .media-editor-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            height: calc(100vh - 200px);
            margin-top: 20px;
        }

        .editor-panel {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow-y: auto;
        }

        .editor-panel h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }

        /* 이미지 업로드 영역 */
        .upload-zone {
            border: 3px dashed #ddd;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            background: #f8f9fa;
            margin-bottom: 15px;
            transition: all 0.3s;
            cursor: pointer;
        }

        .upload-zone:hover {
            border-color: #3498db;
            background: #e3f2fd;
        }

        .upload-zone.dragover {
            border-color: #2ecc71;
            background: #d4edda;
            transform: scale(1.02);
        }

        .upload-zone .icon {
            font-size: 36px;
            margin-bottom: 5px;
        }

        .upload-zone p {
            margin: 3px 0;
            color: #666;
            font-size: 14px;
        }

        .upload-zone .highlight {
            color: #3498db;
            font-weight: bold;
        }

        /* 선택된 파일 리스트 */
        .selected-files-list {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            max-height: 200px;
            overflow-y: auto;
        }

        .selected-files-list h5 {
            margin: 0 0 10px 0;
            color: #2c3e50;
            font-size: 14px;
        }

        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 10px;
            background: #f8f9fa;
            border-radius: 5px;
            margin-bottom: 5px;
            font-size: 13px;
        }

        .file-item:last-child {
            margin-bottom: 0;
        }

        .file-item-info {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            overflow: hidden;
        }

        .file-item-icon {
            font-size: 18px;
        }

        .file-item-name {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #2c3e50;
        }

        .file-item-size {
            color: #7f8c8d;
            font-size: 11px;
            white-space: nowrap;
            margin-left: 10px;
        }

        .file-item-remove {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 11px;
            white-space: nowrap;
        }

        .file-item-remove:hover {
            background: #c0392b;
        }

        .empty-files-message {
            text-align: center;
            color: #999;
            padding: 20px;
            font-size: 13px;
        }

        /* 이미지 그리드 */
        .images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .image-card {
            position: relative;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s;
            cursor: move;
        }

        .image-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .image-card.dragging {
            opacity: 0.5;
            transform: scale(0.95);
        }

        .image-card.drag-over {
            border: 3px dashed #3498db;
            transform: scale(1.05);
        }

        .image-card img {
            width: 100%;
            height: 120px;
            object-fit: cover;
        }

        .image-card-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .image-card:hover .image-card-overlay {
            opacity: 1;
        }

        .image-card-actions {
            display: flex;
            gap: 10px;
        }

        .image-card-btn {
            background: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            transition: transform 0.2s;
        }

        .image-card-btn:hover {
            transform: scale(1.1);
        }

        .image-card-btn.delete {
            background: #e74c3c;
            color: white;
        }

        .image-card-number {
            position: absolute;
            top: 5px;
            left: 5px;
            background: rgba(52, 152, 219, 0.9);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }

        /* 폼 그룹 */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #2c3e50;
        }

        .form-group input[type="text"],
        .form-group input[type="url"],
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group small {
            display: block;
            margin-top: 5px;
            color: #7f8c8d;
            font-size: 12px;
        }

        /* 프리뷰 패널 */
        .preview-panel {
            background: #f8f9fa;
        }

        .preview-slider {
            width: 100%;
            height: 400px;
            position: relative;
            background: #000;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .preview-slide {
            display: none;
            width: 100%;
            height: 100%;
            position: relative;
        }

        .preview-slide.active {
            display: block;
        }

        .preview-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .preview-slide-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
            padding: 30px;
            color: white;
        }

        .preview-slide-overlay h2 {
            margin: 0 0 10px 0;
            font-size: 2em;
        }

        .preview-slide-overlay p {
            margin: 5px 0;
        }

        .preview-controls {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
            z-index: 10;
        }

        .preview-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255,255,255,0.5);
            cursor: pointer;
            transition: all 0.3s;
        }

        .preview-dot.active {
            background: white;
            width: 30px;
            border-radius: 6px;
        }

        .preview-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0,0,0,0.5);
            color: white;
            border: none;
            padding: 15px 20px;
            font-size: 24px;
            cursor: pointer;
            border-radius: 5px;
            transition: background 0.3s;
        }

        .preview-arrow:hover {
            background: rgba(0,0,0,0.8);
        }

        .preview-arrow.left {
            left: 20px;
        }

        .preview-arrow.right {
            right: 20px;
        }

        .preview-info {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .preview-info h4 {
            margin-top: 0;
            color: #2c3e50;
        }

        /* 현재 이미지 표시 */
        .current-image {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            text-align: center;
        }

        .current-image img {
            max-width: 100%;
            max-height: 100px;
            border-radius: 5px;
        }

        /* 툴바 */
        .toolbar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-success {
            background: #2ecc71;
            color: white;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        /* 업로드 진행 표시 */
        .upload-progress {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            z-index: 10000;
            text-align: center;
        }

        .progress-bar {
            width: 300px;
            height: 20px;
            background: #ecf0f1;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 15px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #3498db, #2ecc71);
            width: 0%;
            transition: width 0.3s;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        /* 탭 */
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #ecf0f1;
        }

        .tab {
            padding: 10px 20px;
            background: transparent;
            border: none;
            cursor: pointer;
            color: #7f8c8d;
            font-size: 14px;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
        }

        .tab.active {
            color: #3498db;
            border-bottom-color: #3498db;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        @media (max-width: 1200px) {
            .media-editor-container {
                grid-template-columns: 1fr;
                height: auto;
            }
        }
    </style>
</head>
<body class="admin-body">
    <?php include '../includes/admin_header.php'; ?>

    <div class="admin-container">
        <?php include '../includes/admin_sidebar.php'; ?>

        <main class="admin-main">
            <div class="admin-content">
                <div class="settings-header">
                    <h1>🎨 미디어 관리</h1>
                    <p>좌측에서 이미지를 업로드하고 관리하면, 우측에서 실시간으로 미리보기됩니다</p>
                </div>

                <div class="toolbar">
                    <button class="btn btn-success" onclick="saveAllMedia()">💾 모두 저장</button>
                    <button class="btn btn-primary" onclick="refreshPreview()">🔄 미리보기 새로고침</button>
                    <a href="/" target="_blank" class="btn btn-primary">🌐 실제 사이트 보기</a>
                </div>

                <div id="alertArea"></div>

                <div class="media-editor-container">
                    <!-- 왼쪽: 이미지 관리 패널 -->
                    <div class="editor-panel">
                        <h3>📁 미디어 업로드 & 설정</h3>

                        <!-- 탭 -->
                        <div class="tabs">
                            <button class="tab active" onclick="switchTab('slider')">메인 슬라이더</button>
                            <button class="tab" onclick="switchTab('hero')">히어로 설정</button>
                            <button class="tab" onclick="switchTab('logo')">로고/파비콘</button>
                            <button class="tab" onclick="switchTab('other')">기타 미디어</button>
                            <button class="tab" onclick="switchTab('social')">소셜 링크</button>
                        </div>

                        <!-- 슬라이더 탭 -->
                        <div id="tab-slider" class="tab-content active">
                            <div class="upload-zone" id="sliderUploadZone" onclick="document.getElementById('sliderFileInput').click()">
                                <div class="icon">📷</div>
                                <p class="highlight">클릭하여 이미지 선택 또는 드래그 앤 드롭</p>
                                <p>여러 이미지를 한번에 업로드할 수 있습니다</p>
                                <p style="font-size: 12px; color: #999;">권장 크기: 1920x1080px | 최대 20MB | JPG, PNG, GIF, WEBP</p>
                            </div>
                            <input type="file" id="sliderFileInput" accept="image/*" multiple style="display: none;">

                            <!-- 선택된 파일 리스트 -->
                            <div class="selected-files-list" id="selectedFilesList">
                                <h5>📋 선택된 파일 (<span id="selectedFilesCount">0</span>개)</h5>
                                <div id="selectedFilesContainer">
                                    <div class="empty-files-message">선택된 파일이 없습니다</div>
                                </div>
                            </div>

                            <div class="toolbar">
                                <button class="btn btn-primary" onclick="uploadSliderImages()">⬆️ 선택한 이미지 업로드</button>
                                <button class="btn btn-danger" onclick="clearAllSliderImages()">🗑️ 전체 삭제</button>
                            </div>

                            <h4>업로드된 슬라이더 이미지 (<span id="sliderCount"><?= count($sliderImages) ?></span>개)</h4>
                            <p style="color: #7f8c8d; font-size: 13px; margin-bottom: 10px;">💡 이미지를 드래그하여 순서를 변경할 수 있습니다</p>
                            <div class="images-grid" id="sliderImagesGrid">
                                <?php foreach ($sliderImages as $index => $imagePath): ?>
                                    <div class="image-card" data-url="<?= htmlspecialchars(trim($imagePath)) ?>" draggable="true">
                                        <div class="image-card-number"><?= $index + 1 ?></div>
                                        <img src="<?= htmlspecialchars(trim($imagePath)) ?>" alt="슬라이더 <?= $index + 1 ?>">
                                        <div class="image-card-overlay">
                                            <div class="image-card-actions">
                                                <button class="image-card-btn delete" onclick="removeSliderImage(this)">🗑️ 삭제</button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- 히어로 설정 탭 -->
                        <div id="tab-hero" class="tab-content">
                            <div class="form-group">
                                <label for="hero_title">히어로 제목</label>
                                <input type="text" id="hero_title" name="hero_title"
                                       value="<?= htmlspecialchars($currentSettings['hero_title'] ?? '스마트팜의 미래를 여는 탄생') ?>">
                                <small>메인 페이지 상단에 표시되는 큰 제목</small>
                            </div>

                            <div class="form-group">
                                <label for="hero_subtitle">히어로 부제목</label>
                                <input type="text" id="hero_subtitle" name="hero_subtitle"
                                       value="<?= htmlspecialchars($currentSettings['hero_subtitle'] ?? '혁신적인 배지 기술로 지속가능한 농업을 실현합니다') ?>">
                                <small>제목 아래에 표시되는 부제목</small>
                            </div>

                            <div class="form-group">
                                <label for="hero_description">히어로 설명</label>
                                <textarea id="hero_description" name="hero_description"><?= htmlspecialchars($currentSettings['hero_description'] ?? '탄생의 고품질 수경재배 배지와 AI 기반 식물분석 서비스로 스마트팜의 새로운 가능성을 경험하세요.') ?></textarea>
                                <small>제목 아래에 표시되는 설명 텍스트</small>
                            </div>

                            <div class="form-group">
                                <label>히어로 배경 이미지</label>
                                <?php if (!empty($currentSettings['hero_background'])): ?>
                                    <div class="current-image" id="heroBgPreview">
                                        <p style="font-weight: bold; margin-bottom: 10px;">현재 배경:</p>
                                        <img src="<?= htmlspecialchars($currentSettings['hero_background']) ?>" alt="히어로 배경" style="margin-bottom: 10px;">
                                        <div style="display: flex; gap: 10px; justify-content: center;">
                                            <button type="button" class="btn btn-primary" onclick="uploadSingleImage('hero_bg')" style="padding: 8px 15px; font-size: 13px;">
                                                🔄 다른 이미지 선택
                                            </button>
                                            <button type="button" class="btn btn-danger" onclick="removeHeroBackground()" style="padding: 8px 15px; font-size: 13px;">
                                                🗑️ 배경 삭제
                                            </button>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="upload-zone" onclick="uploadSingleImage('hero_bg')">
                                        <div class="icon">🌄</div>
                                        <p>배경 이미지 업로드 (권장: 1920x1080px)</p>
                                    </div>
                                <?php endif; ?>
                                <input type="hidden" id="hero_background" name="hero_background" value="<?= htmlspecialchars($currentSettings['hero_background'] ?? '') ?>">
                            </div>
                        </div>

                        <!-- 로고/파비콘 탭 -->
                        <div id="tab-logo" class="tab-content">
                            <h4>🏷️ 사이트 로고</h4>
                            <div class="upload-zone" onclick="uploadSingleImage('logo')">
                                <div class="icon">🖼️</div>
                                <p>로고 이미지 업로드 (권장: 200x60px, PNG)</p>
                            </div>
                            <input type="hidden" id="site_logo" name="site_logo" value="<?= htmlspecialchars($currentSettings['site_logo'] ?? '') ?>">
                            <?php if (!empty($currentSettings['site_logo'])): ?>
                                <div class="current-image">
                                    <p>현재 로고:</p>
                                    <img src="<?= htmlspecialchars($currentSettings['site_logo']) ?>" alt="현재 로고">
                                </div>
                            <?php endif; ?>

                            <h4 style="margin-top: 30px;">🎯 파비콘</h4>
                            <div class="upload-zone" onclick="uploadSingleImage('favicon')">
                                <div class="icon">⭐</div>
                                <p>파비콘 업로드 (권장: 32x32px, ICO/PNG)</p>
                            </div>
                            <input type="hidden" id="site_favicon" name="site_favicon" value="<?= htmlspecialchars($currentSettings['site_favicon'] ?? '') ?>">
                            <?php if (!empty($currentSettings['site_favicon'])): ?>
                                <div class="current-image">
                                    <p>현재 파비콘:</p>
                                    <img src="<?= htmlspecialchars($currentSettings['site_favicon']) ?>" alt="현재 파비콘">
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- 기타 미디어 탭 -->
                        <div id="tab-other" class="tab-content">
                            <h4>🏢 회사 소개 이미지</h4>
                            <div class="upload-zone" onclick="uploadSingleImage('about')">
                                <div class="icon">📸</div>
                                <p>회사 소개 이미지 업로드</p>
                            </div>
                            <input type="hidden" id="about_image" name="about_image" value="<?= htmlspecialchars($currentSettings['about_image'] ?? '') ?>">
                            <?php if (!empty($currentSettings['about_image'])): ?>
                                <div class="current-image">
                                    <img src="<?= htmlspecialchars($currentSettings['about_image']) ?>" alt="회사 소개">
                                </div>
                            <?php endif; ?>

                            <div class="form-group" style="margin-top: 30px;">
                                <label for="about_video_url">회사 소개 동영상 URL</label>
                                <input type="url" id="about_video_url" name="about_video_url"
                                       value="<?= htmlspecialchars($currentSettings['about_video_url'] ?? '') ?>"
                                       placeholder="https://www.youtube.com/watch?v=...">
                                <small>YouTube, Vimeo 등의 동영상 URL</small>
                            </div>

                            <h4 style="margin-top: 30px;">🖼️ 갤러리 이미지</h4>
                            <div class="form-group">
                                <label for="gallery_images">갤러리 이미지 URL 목록</label>
                                <textarea id="gallery_images" name="gallery_images" rows="6"><?= htmlspecialchars($currentSettings['gallery_images'] ?? '') ?></textarea>
                                <small>각 이미지 URL을 새 줄에 하나씩 입력하세요</small>
                            </div>
                        </div>

                        <!-- 소셜 링크 탭 -->
                        <div id="tab-social" class="tab-content">
                            <div class="form-group">
                                <label for="social_youtube">📺 YouTube</label>
                                <input type="url" id="social_youtube" name="social_youtube"
                                       value="<?= htmlspecialchars($currentSettings['social_youtube'] ?? '') ?>"
                                       placeholder="https://youtube.com/@탄생">
                            </div>

                            <div class="form-group">
                                <label for="social_instagram">📸 Instagram</label>
                                <input type="url" id="social_instagram" name="social_instagram"
                                       value="<?= htmlspecialchars($currentSettings['social_instagram'] ?? '') ?>"
                                       placeholder="https://instagram.com/탄생">
                            </div>

                            <div class="form-group">
                                <label for="social_facebook">👥 Facebook</label>
                                <input type="url" id="social_facebook" name="social_facebook"
                                       value="<?= htmlspecialchars($currentSettings['social_facebook'] ?? '') ?>"
                                       placeholder="https://facebook.com/탄생">
                            </div>

                            <div class="form-group">
                                <label for="social_blog">📝 블로그</label>
                                <input type="url" id="social_blog" name="social_blog"
                                       value="<?= htmlspecialchars($currentSettings['social_blog'] ?? '') ?>"
                                       placeholder="https://blog.탄생.com">
                            </div>
                        </div>
                    </div>

                    <!-- 오른쪽: 실시간 미리보기 패널 -->
                    <div class="editor-panel preview-panel">
                        <h3>👁️ 실시간 미리보기</h3>

                        <h4>메인 슬라이더 미리보기</h4>
                        <div class="preview-slider" id="previewSlider">
                            <?php if (empty($sliderImages)): ?>
                                <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #999;">
                                    슬라이더 이미지가 없습니다
                                </div>
                            <?php else: ?>
                                <?php foreach ($sliderImages as $index => $imagePath): ?>
                                    <div class="preview-slide <?= $index === 0 ? 'active' : '' ?>">
                                        <img src="<?= htmlspecialchars(trim($imagePath)) ?>" alt="슬라이드 <?= $index + 1 ?>">
                                        <div class="preview-slide-overlay">
                                            <h2 id="preview_hero_title"><?= htmlspecialchars($currentSettings['hero_title'] ?? '스마트팜의 미래를 여는 탄생') ?></h2>
                                            <p id="preview_hero_subtitle" style="font-size: 1.2em; opacity: 0.9;"><?= htmlspecialchars($currentSettings['hero_subtitle'] ?? '혁신적인 배지 기술로 지속가능한 농업을 실현합니다') ?></p>
                                            <p id="preview_hero_description" style="opacity: 0.8;"><?= htmlspecialchars($currentSettings['hero_description'] ?? '탄생의 고품질 수경재배 배지와 AI 기반 식물분석 서비스로 스마트팜의 새로운 가능성을 경험하세요.') ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <button class="preview-arrow left" onclick="prevSlide()">‹</button>
                                <button class="preview-arrow right" onclick="nextSlide()">›</button>
                                <div class="preview-controls" id="previewDots">
                                    <?php foreach ($sliderImages as $index => $imagePath): ?>
                                        <span class="preview-dot <?= $index === 0 ? 'active' : '' ?>" onclick="goToSlide(<?= $index ?>)"></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="preview-info">
                            <h4>📊 현재 상태</h4>
                            <p><strong>슬라이더 이미지:</strong> <span id="previewCount"><?= count($sliderImages) ?></span>개</p>
                            <p><strong>현재 슬라이드:</strong> <span id="currentSlideNum">1</span> / <span id="totalSlides"><?= count($sliderImages) ?></span></p>

                            <h4 style="margin-top: 20px;">🔗 소셜 링크</h4>
                            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                <?php if (!empty($currentSettings['social_youtube'])): ?>
                                    <a href="<?= htmlspecialchars($currentSettings['social_youtube']) ?>" target="_blank" style="padding: 5px 10px; background: #ff0000; color: white; border-radius: 5px; text-decoration: none;">📺 YouTube</a>
                                <?php endif; ?>
                                <?php if (!empty($currentSettings['social_instagram'])): ?>
                                    <a href="<?= htmlspecialchars($currentSettings['social_instagram']) ?>" target="_blank" style="padding: 5px 10px; background: #E4405F; color: white; border-radius: 5px; text-decoration: none;">📸 Instagram</a>
                                <?php endif; ?>
                                <?php if (!empty($currentSettings['social_facebook'])): ?>
                                    <a href="<?= htmlspecialchars($currentSettings['social_facebook']) ?>" target="_blank" style="padding: 5px 10px; background: #1877f2; color: white; border-radius: 5px; text-decoration: none;">👥 Facebook</a>
                                <?php endif; ?>
                                <?php if (!empty($currentSettings['social_blog'])): ?>
                                    <a href="<?= htmlspecialchars($currentSettings['social_blog']) ?>" target="_blank" style="padding: 5px 10px; background: #20c997; color: white; border-radius: 5px; text-decoration: none;">📝 블로그</a>
                                <?php endif; ?>
                            </div>

                            <p style="margin-top: 15px; padding: 10px; background: #fff3cd; border-radius: 5px; font-size: 13px;">
                                💡 <strong>Tip:</strong> 설정을 변경하고 "💾 모두 저장" 버튼을 누르면 실제 웹사이트에 즉시 반영됩니다!
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- 업로드 진행 표시 -->
    <div class="upload-progress" id="uploadProgress">
        <h3>⏳ 업로드 중...</h3>
        <p id="uploadProgressText">파일을 업로드하고 있습니다. 잠시만 기다려주세요.</p>
        <div class="progress-bar">
            <div class="progress-fill" id="progressFill"></div>
        </div>
    </div>

    <script>
        let currentSlide = 0;
        let currentUploadTarget = '';

        // 탭 전환
        function switchTab(tabName) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(tc => tc.classList.remove('active'));

            event.target.classList.add('active');
            document.getElementById('tab-' + tabName).classList.add('active');
        }

        // 드래그 앤 드롭 설정
        const sliderUploadZone = document.getElementById('sliderUploadZone');

        sliderUploadZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });

        sliderUploadZone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });

        sliderUploadZone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');

            const files = e.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('sliderFileInput').files = files;
                displaySelectedFiles();
            }
        });

        // 파일 입력 변경 감지
        document.getElementById('sliderFileInput').addEventListener('change', function() {
            displaySelectedFiles();
        });

        // 선택된 파일 표시
        function displaySelectedFiles() {
            const fileInput = document.getElementById('sliderFileInput');
            const files = fileInput.files;
            const container = document.getElementById('selectedFilesContainer');
            const countSpan = document.getElementById('selectedFilesCount');

            if (files.length === 0) {
                container.innerHTML = '<div class="empty-files-message">선택된 파일이 없습니다</div>';
                countSpan.textContent = '0';
                return;
            }

            countSpan.textContent = files.length;
            container.innerHTML = '';

            Array.from(files).forEach((file, index) => {
                const fileSize = formatFileSize(file.size);
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                fileItem.innerHTML = `
                    <div class="file-item-info">
                        <span class="file-item-icon">🖼️</span>
                        <span class="file-item-name">${escapeHtml(file.name)}</span>
                        <span class="file-item-size">${fileSize}</span>
                    </div>
                    <button class="file-item-remove" onclick="removeSelectedFile(${index})">✕</button>
                `;
                container.appendChild(fileItem);
            });
        }

        // 파일 크기 포맷
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        // HTML 이스케이프
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // 선택된 파일 제거
        function removeSelectedFile(index) {
            const fileInput = document.getElementById('sliderFileInput');
            const dt = new DataTransfer();
            const files = Array.from(fileInput.files);

            files.forEach((file, i) => {
                if (i !== index) {
                    dt.items.add(file);
                }
            });

            fileInput.files = dt.files;
            displaySelectedFiles();
        }

        // 슬라이더 이미지 업로드
        function uploadSliderImages() {
            const fileInput = document.getElementById('sliderFileInput');
            const files = fileInput.files;

            if (files.length === 0) {
                showAlert('업로드할 파일을 선택해주세요', 'error');
                return;
            }

            const formData = new FormData();
            for (let i = 0; i < files.length; i++) {
                formData.append('slider_images[]', files[i]);
            }

            showUploadProgress();

            fetch('/admin/includes/upload_slider_images.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideUploadProgress();

                if (data.success) {
                    showAlert(data.message || '이미지가 성공적으로 업로드되었습니다!', 'success');

                    // 그리드에 새 이미지 추가
                    data.urls.forEach(url => {
                        addImageToGrid(url);
                    });

                    // 미리보기 업데이트
                    updatePreview();

                    // 파일 입력 초기화
                    fileInput.value = '';
                    displaySelectedFiles();
                } else {
                    showAlert(data.error || '업로드 실패', 'error');
                }
            })
            .catch(error => {
                hideUploadProgress();
                showAlert('업로드 중 오류가 발생했습니다: ' + error.message, 'error');
            });
        }

        // 단일 이미지 업로드 (로고, 파비콘 등)
        function uploadSingleImage(type) {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/*';

            input.onchange = function(e) {
                const file = e.target.files[0];
                if (!file) return;

                const formData = new FormData();
                formData.append('slider_images[]', file);

                showUploadProgress();

                fetch('/admin/includes/upload_slider_images.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    hideUploadProgress();

                    if (data.success && data.urls.length > 0) {
                        const url = data.urls[0];

                        // hidden input에 URL 저장
                        let fieldId = '';
                        switch(type) {
                            case 'logo': fieldId = 'site_logo'; break;
                            case 'favicon': fieldId = 'site_favicon'; break;
                            case 'hero_bg': fieldId = 'hero_background'; break;
                            case 'about': fieldId = 'about_image'; break;
                        }

                        if (fieldId) {
                            document.getElementById(fieldId).value = url;
                            showAlert('이미지가 업로드되었습니다! "모두 저장" 버튼을 눌러주세요.', 'success');

                            // 페이지 새로고침하여 현재 이미지 표시
                            setTimeout(() => location.reload(), 1500);
                        }
                    } else {
                        showAlert(data.error || '업로드 실패', 'error');
                    }
                })
                .catch(error => {
                    hideUploadProgress();
                    showAlert('업로드 중 오류: ' + error.message, 'error');
                });
            };

            input.click();
        }

        // 히어로 배경 이미지 삭제
        function removeHeroBackground() {
            if (confirm('히어로 배경 이미지를 삭제하시겠습니까?')) {
                document.getElementById('hero_background').value = '';
                console.log('hero_background 값 삭제됨:', document.getElementById('hero_background').value);

                // 즉시 저장
                showAlert('배경 이미지를 삭제 중입니다...', 'success');
                saveAllMedia();
            }
        }

        // 그리드에 이미지 추가
        function addImageToGrid(imageUrl) {
            const grid = document.getElementById('sliderImagesGrid');
            const currentCount = grid.children.length;

            const imageCard = document.createElement('div');
            imageCard.className = 'image-card';
            imageCard.setAttribute('data-url', imageUrl);
            imageCard.setAttribute('draggable', 'true');
            imageCard.innerHTML = `
                <div class="image-card-number">${currentCount + 1}</div>
                <img src="${imageUrl}" alt="슬라이더 ${currentCount + 1}">
                <div class="image-card-overlay">
                    <div class="image-card-actions">
                        <button class="image-card-btn delete" onclick="removeSliderImage(this)">🗑️ 삭제</button>
                    </div>
                </div>
            `;

            grid.appendChild(imageCard);
            setupDragAndDrop(imageCard);
            updateSliderCount();
        }

        // 드래그 앤 드롭 설정 (이미지 순서 변경)
        function setupDragAndDrop(card) {
            card.addEventListener('dragstart', function(e) {
                this.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/html', this.innerHTML);
            });

            card.addEventListener('dragend', function(e) {
                this.classList.remove('dragging');
                document.querySelectorAll('.image-card').forEach(c => c.classList.remove('drag-over'));
            });

            card.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';

                const draggingCard = document.querySelector('.dragging');
                if (!draggingCard || draggingCard === this) return;

                this.classList.add('drag-over');
            });

            card.addEventListener('dragleave', function(e) {
                this.classList.remove('drag-over');
            });

            card.addEventListener('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();

                this.classList.remove('drag-over');

                const draggingCard = document.querySelector('.dragging');
                if (!draggingCard || draggingCard === this) return;

                const grid = document.getElementById('sliderImagesGrid');
                const allCards = Array.from(grid.children);
                const draggedIndex = allCards.indexOf(draggingCard);
                const targetIndex = allCards.indexOf(this);

                if (draggedIndex < targetIndex) {
                    this.parentNode.insertBefore(draggingCard, this.nextSibling);
                } else {
                    this.parentNode.insertBefore(draggingCard, this);
                }

                // 번호 재정렬
                updateImageNumbers();
                updatePreview();
                showAlert('이미지 순서가 변경되었습니다. "모두 저장" 버튼을 눌러 저장하세요.', 'success');
            });
        }

        // 이미지 번호 업데이트
        function updateImageNumbers() {
            const cards = document.querySelectorAll('#sliderImagesGrid .image-card');
            cards.forEach((card, index) => {
                card.querySelector('.image-card-number').textContent = index + 1;
            });
        }

        // 페이지 로드 시 기존 이미지 카드에 드래그 앤 드롭 설정
        document.addEventListener('DOMContentLoaded', function() {
            const existingCards = document.querySelectorAll('#sliderImagesGrid .image-card');
            existingCards.forEach(card => setupDragAndDrop(card));

            // 히어로 텍스트 실시간 업데이트
            ['hero_title', 'hero_subtitle', 'hero_description'].forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    element.addEventListener('input', function() {
                        updatePreview();
                    });
                }
            });
        });

        // 슬라이더 이미지 삭제
        function removeSliderImage(button) {
            if (confirm('이 이미지를 삭제하시겠습니까?')) {
                const imageCard = button.closest('.image-card');
                imageCard.remove();

                // 번호 재정렬
                const cards = document.querySelectorAll('#sliderImagesGrid .image-card');
                cards.forEach((card, index) => {
                    card.querySelector('.image-card-number').textContent = index + 1;
                });

                updateSliderCount();
                updatePreview();
            }
        }

        // 전체 삭제
        function clearAllSliderImages() {
            if (confirm('모든 슬라이더 이미지를 삭제하시겠습니까?')) {
                document.getElementById('sliderImagesGrid').innerHTML = '';
                updateSliderCount();
                updatePreview();
                showAlert('모든 이미지가 삭제되었습니다', 'success');
            }
        }

        // 슬라이더 개수 업데이트
        function updateSliderCount() {
            const count = document.querySelectorAll('#sliderImagesGrid .image-card').length;
            document.getElementById('sliderCount').textContent = count;
            document.getElementById('previewCount').textContent = count;
            document.getElementById('totalSlides').textContent = count;
        }

        // 미리보기 업데이트
        function updatePreview() {
            const cards = document.querySelectorAll('#sliderImagesGrid .image-card');
            const previewSlider = document.getElementById('previewSlider');

            // 히어로 텍스트 가져오기
            const heroTitle = document.getElementById('hero_title').value;
            const heroSubtitle = document.getElementById('hero_subtitle').value;
            const heroDescription = document.getElementById('hero_description').value;

            if (cards.length === 0) {
                previewSlider.innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #999;">슬라이더 이미지가 없습니다</div>';
                return;
            }

            let slidesHTML = '';
            let dotsHTML = '';

            cards.forEach((card, index) => {
                const imageUrl = card.getAttribute('data-url');
                slidesHTML += `
                    <div class="preview-slide ${index === 0 ? 'active' : ''}">
                        <img src="${imageUrl}" alt="슬라이드 ${index + 1}">
                        <div class="preview-slide-overlay">
                            <h2>${heroTitle}</h2>
                            <p style="font-size: 1.2em; opacity: 0.9;">${heroSubtitle}</p>
                            <p style="opacity: 0.8;">${heroDescription}</p>
                        </div>
                    </div>
                `;
                dotsHTML += `<span class="preview-dot ${index === 0 ? 'active' : ''}" onclick="goToSlide(${index})"></span>`;
            });

            previewSlider.innerHTML = `
                ${slidesHTML}
                <button class="preview-arrow left" onclick="prevSlide()">‹</button>
                <button class="preview-arrow right" onclick="nextSlide()">›</button>
                <div class="preview-controls">${dotsHTML}</div>
            `;

            currentSlide = 0;
            updateCurrentSlideNum();
        }

        // 슬라이더 네비게이션
        function prevSlide() {
            const slides = document.querySelectorAll('.preview-slide');
            const dots = document.querySelectorAll('.preview-dot');

            if (slides.length === 0) return;

            slides[currentSlide].classList.remove('active');
            dots[currentSlide].classList.remove('active');

            currentSlide = (currentSlide - 1 + slides.length) % slides.length;

            slides[currentSlide].classList.add('active');
            dots[currentSlide].classList.add('active');
            updateCurrentSlideNum();
        }

        function nextSlide() {
            const slides = document.querySelectorAll('.preview-slide');
            const dots = document.querySelectorAll('.preview-dot');

            if (slides.length === 0) return;

            slides[currentSlide].classList.remove('active');
            dots[currentSlide].classList.remove('active');

            currentSlide = (currentSlide + 1) % slides.length;

            slides[currentSlide].classList.add('active');
            dots[currentSlide].classList.add('active');
            updateCurrentSlideNum();
        }

        function goToSlide(index) {
            const slides = document.querySelectorAll('.preview-slide');
            const dots = document.querySelectorAll('.preview-dot');

            if (slides.length === 0) return;

            slides[currentSlide].classList.remove('active');
            dots[currentSlide].classList.remove('active');

            currentSlide = index;

            slides[currentSlide].classList.add('active');
            dots[currentSlide].classList.add('active');
            updateCurrentSlideNum();
        }

        function updateCurrentSlideNum() {
            document.getElementById('currentSlideNum').textContent = currentSlide + 1;
        }

        // 자동 슬라이더
        setInterval(() => {
            const slides = document.querySelectorAll('.preview-slide');
            if (slides.length > 1) {
                nextSlide();
            }
        }, 4000);

        // 모두 저장
        function saveAllMedia() {
            const cards = document.querySelectorAll('#sliderImagesGrid .image-card');
            const imageUrls = Array.from(cards).map(card => card.getAttribute('data-url'));

            const heroBgValue = document.getElementById('hero_background').value;
            console.log('저장할 hero_background 값:', heroBgValue);
            console.log('hero_background 빈 값 여부:', heroBgValue === '');

            const formData = new FormData();
            // 메인 페이지와 호환되도록 줄바꿈(\n)으로 구분하여 저장
            formData.append('hero_media_list', imageUrls.join('\n'));
            formData.append('hero_title', document.getElementById('hero_title').value);
            formData.append('hero_subtitle', document.getElementById('hero_subtitle').value);
            formData.append('hero_description', document.getElementById('hero_description').value);
            formData.append('hero_background', heroBgValue);
            formData.append('site_logo', document.getElementById('site_logo').value);
            formData.append('site_favicon', document.getElementById('site_favicon').value);
            formData.append('about_image', document.getElementById('about_image').value);
            formData.append('about_video_url', document.getElementById('about_video_url').value);
            formData.append('gallery_images', document.getElementById('gallery_images').value);
            formData.append('social_youtube', document.getElementById('social_youtube').value);
            formData.append('social_instagram', document.getElementById('social_instagram').value);
            formData.append('social_facebook', document.getElementById('social_facebook').value);
            formData.append('social_blog', document.getElementById('social_blog').value);

            showUploadProgress();
            document.getElementById('uploadProgressText').textContent = '설정을 저장하고 있습니다...';

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideUploadProgress();

                console.log('서버 응답:', data);

                if (data.success) {
                    if (data.debug) {
                        console.log('디버그 정보:', data.debug);
                    }
                    showAlert('✅ ' + data.message + ' 실제 사이트에 반영되었습니다!', 'success');

                    // 3초 후 페이지 새로고침
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    showAlert('❌ 저장 실패: ' + data.error, 'error');
                }
            })
            .catch(error => {
                hideUploadProgress();
                console.error('저장 오류:', error);
                showAlert('저장 중 오류가 발생했습니다: ' + error.message, 'error');
            });
        }

        // 미리보기 새로고침
        function refreshPreview() {
            updatePreview();
            showAlert('미리보기가 새로고침되었습니다', 'success');
        }

        // 알림 표시
        function showAlert(message, type) {
            const alertArea = document.getElementById('alertArea');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;

            alertArea.innerHTML = '';
            alertArea.appendChild(alert);

            setTimeout(() => {
                alert.remove();
            }, 5000);
        }

        // 업로드 진행 표시
        function showUploadProgress() {
            document.getElementById('uploadProgress').style.display = 'block';
        }

        function hideUploadProgress() {
            document.getElementById('uploadProgress').style.display = 'none';
        }
    </script>
</body>
</html>

<?php
// Initialize session and auth before any output
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
$auth = Auth::getInstance();
$auth->requireAdmin();

require_once $base_path . '/classes/Database.php';

$success = '';
$error = '';

// 디버깅을 위한 로그 작성
if (!empty($_POST) || !empty($_FILES)) {
    $log = date('Y-m-d H:i:s') . " - POST: " . count($_POST) . ", FILES: " . count($_FILES);
    if (isset($_FILES['company_intro_video'])) {
        $log .= " - company_intro_video error: " . $_FILES['company_intro_video']['error'];
    }
    file_put_contents('/tmp/upload.log', $log . "\n", FILE_APPEND);
}

// 미디어 설정 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = Database::getInstance()->getConnection();
        
        // 설정 테이블 생성 (존재하지 않는 경우)
        $sql = "CREATE TABLE IF NOT EXISTS site_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $pdo->exec($sql);
        
        // 테이블이 정상적으로 생성되었는지 확인
        $check_table = $pdo->query("SHOW TABLES LIKE 'site_settings'")->fetch();
        if (!$check_table) {
            throw new Exception("site_settings 테이블 생성에 실패했습니다.");
        }
        
        // 파일 업로드 처리
        $upload_dir = $base_path . '/uploads/media/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                error_log("업로드 디렉토리 생성 실패: " . $upload_dir);
                throw new Exception("업로드 디렉토리를 생성할 수 없습니다: " . $upload_dir);
            }
        }
        
        // 디렉토리 쓰기 권한 확인
        if (!is_writable($upload_dir)) {
            error_log("업로드 디렉토리 쓰기 권한 없음: " . $upload_dir . " (권한: " . substr(sprintf('%o', fileperms($upload_dir)), -4) . ")");
            
            // 권한 수정 시도
            if (!chmod($upload_dir, 0755)) {
                throw new Exception("업로드 디렉토리에 쓰기 권한이 없습니다: " . $upload_dir);
            }
        }
        
        $settings = [];
        
        // 로고 업로드 처리
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $file_extension = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $new_filename = 'logo.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                // 기존 로고 파일들 삭제
                foreach (glob($upload_dir . 'logo.*') as $old_logo) {
                    unlink($old_logo);
                }
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_path)) {
                    $settings['site_logo'] = '/uploads/media/' . $new_filename;
                }
            } else {
                $error = '로고는 이미지 파일만 업로드 가능합니다.';
            }
        }
        
        // AI 식물분석 동영상 업로드
        if (isset($_FILES['plant_analysis_video']) && $_FILES['plant_analysis_video']['error'] === UPLOAD_ERR_OK) {
            $file_extension = strtolower(pathinfo($_FILES['plant_analysis_video']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['mp4', 'webm', 'ogg'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $new_filename = 'plant_analysis_video.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                // 새 파일을 먼저 업로드 시도
                if (move_uploaded_file($_FILES['plant_analysis_video']['tmp_name'], $upload_path)) {
                    // 업로드 성공 후 기존 파일들 삭제 (새 파일 제외)
                    foreach (glob($upload_dir . 'plant_analysis_video.*') as $old_file) {
                        if ($old_file !== $upload_path) {
                            unlink($old_file);
                        }
                    }
                    $settings['plant_analysis_video'] = '/uploads/media/' . $new_filename;
                    error_log("AI 식물분석 영상 업로드 성공: " . $settings['plant_analysis_video']);
                } else {
                    error_log("AI 식물분석 영상 업로드 실패");
                }
            } else {
                $error = 'AI 식물분석 동영상은 mp4, webm, ogg 형식만 가능합니다.';
            }
        }
        
        // 회사 소개 영상 업로드
        file_put_contents('/tmp/admin_debug.log', 
            date('Y-m-d H:i:s') . " - 회사소개영상 처리 시작\n" . 
            "FILES isset: " . (isset($_FILES['company_intro_video']) ? "YES" : "NO") . "\n" .
            "Error code: " . ($_FILES['company_intro_video']['error'] ?? 'N/A') . "\n",
            FILE_APPEND
        );
        
        if (isset($_FILES['company_intro_video']) && $_FILES['company_intro_video']['error'] === UPLOAD_ERR_OK) {
            $file_extension = strtolower(pathinfo($_FILES['company_intro_video']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['mp4', 'webm', 'ogg'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $new_filename = 'company_intro_video.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                // 새 파일을 먼저 업로드 시도
                file_put_contents('/tmp/admin_debug.log', "업로드 시도: " . $upload_path . "\n", FILE_APPEND);
                
                if (move_uploaded_file($_FILES['company_intro_video']['tmp_name'], $upload_path)) {
                    // 업로드 성공 후 기존 파일들 삭제 (새 파일 제외)
                    foreach (glob($upload_dir . 'company_intro_video.*') as $old_file) {
                        if ($old_file !== $upload_path) {
                            unlink($old_file);
                        }
                    }
                    $settings['company_intro_video'] = '/uploads/media/' . $new_filename;
                    file_put_contents('/tmp/admin_debug.log', "✅ 파일 업로드 성공, settings 배열에 추가: " . $settings['company_intro_video'] . "\n", FILE_APPEND);
                    error_log("회사 소개 영상 업로드 성공: " . $settings['company_intro_video']);
                } else {
                    file_put_contents('/tmp/admin_debug.log', "❌ 파일 업로드 실패\n", FILE_APPEND);
                    error_log("회사 소개 영상 업로드 실패");
                }
            } else {
                $error = '회사 소개 영상은 mp4, webm, ogg 형식만 가능합니다.';
            }
        }
        
        // 회사 소개 영상 업로드 오류 처리
        if (isset($_FILES['company_intro_video']) && $_FILES['company_intro_video']['error'] !== UPLOAD_ERR_OK && $_FILES['company_intro_video']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE => '업로드된 파일이 너무 큽니다.',
                UPLOAD_ERR_FORM_SIZE => '업로드된 파일이 허용 크기를 초과합니다.',
                UPLOAD_ERR_PARTIAL => '파일이 부분적으로만 업로드되었습니다.',
                UPLOAD_ERR_NO_FILE => '업로드된 파일이 없습니다.',
                UPLOAD_ERR_NO_TMP_DIR => '임시 디렉토리가 없습니다.',
                UPLOAD_ERR_CANT_WRITE => '디스크 쓰기에 실패했습니다.',
                UPLOAD_ERR_EXTENSION => '확장자에 의해 업로드가 중단되었습니다.'
            ];
            $error_msg = $upload_errors[$_FILES['company_intro_video']['error']] ?? '알 수 없는 업로드 오류';
            $error = '회사 소개 영상 업로드 실패: ' . $error_msg;
            error_log("회사 소개 영상 업로드 오류: " . $error);
        }
        
        // 메인 배경 이미지들 업로드 (히어로 슬라이드)
        for ($i = 1; $i <= 4; $i++) {
            $field_name = "hero_image_$i";
            if (isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] === UPLOAD_ERR_OK) {
                $file_extension = strtolower(pathinfo($_FILES[$field_name]['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
                
                if (in_array($file_extension, $allowed_extensions)) {
                    $new_filename = "hero_$i." . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    // 기존 파일 삭제
                    foreach (glob($upload_dir . "hero_$i.*") as $old_file) {
                        unlink($old_file);
                    }
                    
                    if (move_uploaded_file($_FILES[$field_name]['tmp_name'], $upload_path)) {
                        $settings["hero_image_$i"] = '/uploads/media/' . $new_filename;
                    }
                }
            }
        }
        
        // 텍스트 설정들 처리
        $text_settings = [
            'plant_analysis_title' => trim($_POST['plant_analysis_title'] ?? ''),
            'plant_analysis_description' => trim($_POST['plant_analysis_description'] ?? ''),
            'company_intro_title' => trim($_POST['company_intro_title'] ?? ''),
            'company_intro_description' => trim($_POST['company_intro_description'] ?? ''),
            'hero_1_title' => trim($_POST['hero_1_title'] ?? ''),
            'hero_1_subtitle' => trim($_POST['hero_1_subtitle'] ?? ''),
            'hero_1_cta_text' => trim($_POST['hero_1_cta_text'] ?? ''),
            'hero_1_cta_link' => trim($_POST['hero_1_cta_link'] ?? ''),
            'hero_2_title' => trim($_POST['hero_2_title'] ?? ''),
            'hero_2_subtitle' => trim($_POST['hero_2_subtitle'] ?? ''),
            'hero_2_cta_text' => trim($_POST['hero_2_cta_text'] ?? ''),
            'hero_2_cta_link' => trim($_POST['hero_2_cta_link'] ?? ''),
            'hero_3_title' => trim($_POST['hero_3_title'] ?? ''),
            'hero_3_subtitle' => trim($_POST['hero_3_subtitle'] ?? ''),
            'hero_3_cta_text' => trim($_POST['hero_3_cta_text'] ?? ''),
            'hero_3_cta_link' => trim($_POST['hero_3_cta_link'] ?? ''),
            'hero_4_title' => trim($_POST['hero_4_title'] ?? ''),
            'hero_4_subtitle' => trim($_POST['hero_4_subtitle'] ?? ''),
            'hero_4_cta_text' => trim($_POST['hero_4_cta_text'] ?? ''),
            'hero_4_cta_link' => trim($_POST['hero_4_cta_link'] ?? ''),
        ];
        
        $settings = array_merge($settings, $text_settings);
        
        // 데이터베이스에 설정 저장
        file_put_contents('/tmp/admin_debug.log', 
            "저장할 settings 배열: " . print_r($settings, true) . "\n", 
            FILE_APPEND
        );
        
        $saved_count = 0;
        foreach ($settings as $key => $value) {
            try {
                $sql = "INSERT INTO site_settings (setting_key, setting_value) 
                        VALUES (?, ?) 
                        ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = CURRENT_TIMESTAMP";
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([$key, $value, $value]);
                
                if ($result) {
                    $saved_count++;
                    error_log("설정 저장 성공: {$key} = {$value}");
                } else {
                    error_log("설정 저장 실패: {$key} = {$value}");
                }
            } catch (Exception $e) {
                error_log("설정 저장 오류: {$key} - " . $e->getMessage());
                throw new Exception("설정 '{$key}' 저장 중 오류: " . $e->getMessage());
            }
        }
        
        // 저장된 설정 확인
        if ($saved_count > 0) {
            $verify_sql = "SELECT COUNT(*) as count FROM site_settings WHERE setting_key IN ('" . implode("','", array_keys($settings)) . "')";
            $verify_result = $pdo->query($verify_sql)->fetch();
            error_log("저장된 설정 수: {$saved_count}, 데이터베이스 확인 결과: " . $verify_result['count']);
        }
        
        $success = '✅ 미디어 설정이 저장되었습니다! (' . $saved_count . '개 설정 업데이트됨)';
        
    } catch (Exception $e) {
        $error = '설정 저장에 실패했습니다: ' . $e->getMessage();
    }
}

// 현재 설정값 불러오기
$current_settings = [];
try {
    $pdo = Database::getInstance()->getConnection();
    
    // 테이블 생성 (존재하지 않는 경우)
    $create_sql = "CREATE TABLE IF NOT EXISTS site_settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($create_sql);
    
    $sql = "SELECT setting_key, setting_value FROM site_settings";
    $stmt = $pdo->query($sql);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }
    
    error_log("현재 설정 로드됨: " . count($current_settings) . "개");
    
} catch (Exception $e) {
    error_log("설정 로드 실패: " . $e->getMessage());
}

// 기본값 설정
$defaults = [
    'site_logo' => '/assets/images/logo.png',
    'plant_analysis_title' => 'AI 식물분석 서비스',
    'plant_analysis_description' => '라즈베리파이 카메라와 AI 기술을 활용하여 식물의 건강상태를 실시간으로 분석하고 관리할 수 있습니다.',
    'plant_analysis_video' => '/uploads/media/plant_analysis_video.mp4',
    'company_intro_title' => '탄생 소개 영상',
    'company_intro_description' => '우리의 기술과 비전을 영상으로 만나보세요',
    'company_intro_video' => '/uploads/media/company_intro_video.mp4',
    'hero_1_title' => '탄생 스마트팜 배지',
    'hero_1_subtitle' => '최고 품질의 수경재배용 배지로 건강한 농작물을 키워보세요',
    'hero_1_cta_text' => '제품 보기',
    'hero_1_cta_link' => '/pages/products/media.php',
    'hero_image_1' => '/assets/images/banners/hero-1.jpg',
    'hero_2_title' => 'AI 식물분석 시스템',
    'hero_2_subtitle' => '첨단 기술로 식물의 건강상태를 정확하게 분석합니다',
    'hero_2_cta_text' => '분석하기',
    'hero_2_cta_link' => '/pages/plant_analysis/',
    'hero_image_2' => '/assets/images/banners/hero-2.jpg',
    'hero_3_title' => '스마트팜 솔루션',
    'hero_3_subtitle' => '라즈베리파이와 AI 기술이 결합된 스마트한 농업',
    'hero_3_cta_text' => '자세히 보기',
    'hero_3_cta_link' => '/pages/company/about.php',
    'hero_image_3' => '/assets/images/banners/hero-3.jpg',
    'hero_4_title' => '고객 지원',
    'hero_4_subtitle' => '전문적인 기술지원과 상담 서비스를 제공합니다',
    'hero_4_cta_text' => '문의하기',
    'hero_4_cta_link' => '/pages/company/contact.php',
    'hero_image_4' => '/assets/images/banners/hero-4.jpg',
];

foreach ($defaults as $key => $default) {
    if (!isset($current_settings[$key])) {
        $current_settings[$key] = $default;
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>미디어 관리 - 탄생 관리자</title>
    <link rel="stylesheet" href="/assets/css/main.css">
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
        }
        
        .page-title {
            margin: 0;
            color: #333;
            font-size: 1.8rem;
        }
        
        .page-subtitle {
            color: #666;
            margin-top: 5px;
        }
        
        .settings-form {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-section {
            margin-bottom: 40px;
            padding: 20px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
        }
        
        .form-section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f8f9fa;
            display: flex;
            align-items: center;
            gap: 10px;
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
        
        .form-input.textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .file-input {
            border: 2px dashed #ddd;
            border-radius: 6px;
            padding: 20px;
            text-align: center;
            transition: border-color 0.3s ease;
        }
        
        .file-input:hover {
            border-color: #007bff;
        }
        
        .file-input input[type="file"] {
            margin-bottom: 10px;
        }
        
        .current-file {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #e9ecef;
        }
        
        .current-image {
            max-width: 200px;
            max-height: 150px;
            border-radius: 4px;
            margin-top: 10px;
        }
        
        .current-video {
            max-width: 300px;
            max-height: 200px;
            margin-top: 10px;
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
        
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background-color: #1e7e34;
        }
        
        .btn-outline {
            background-color: transparent;
            color: #007bff;
            border: 1px solid #007bff;
        }
        
        .btn-outline:hover {
            background-color: #007bff;
            color: white;
        }
        
        .form-actions {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
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
        
        .form-help {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .hero-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .admin-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include '../includes/admin_sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="page-header">
                <h1 class="page-title">🎬 미디어 관리</h1>
                <p class="page-subtitle">사이트 로고, 동영상, 이미지 등을 관리합니다</p>
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
            
            <form class="settings-form" method="post" enctype="multipart/form-data">
                
                <!-- 로고 설정 -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <span>🏷️</span> 사이트 로고
                    </h3>
                    
                    <div class="form-group">
                        <label class="form-label" for="logo">로고 파일</label>
                        <div class="file-input">
                            <input type="file" id="logo" name="logo" accept="image/*">
                            <div class="form-help">PNG, JPG, SVG 파일 지원 (권장 크기: 200x60px)</div>
                        </div>
                        <?php if (!empty($current_settings['site_logo'])): ?>
                            <div class="current-file">
                                <strong>현재 로고:</strong>
                                <img src="<?= htmlspecialchars($current_settings['site_logo']) ?>" 
                                     alt="현재 로고" class="current-image">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- 메인 히어로 섹션 -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <span>🎯</span> 메인 페이지 히어로 섹션
                    </h3>
                    
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                    <div class="hero-section">
                        <h4>히어로 슬라이드 <?= $i ?></h4>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="hero_<?= $i ?>_title">제목</label>
                                <input type="text" id="hero_<?= $i ?>_title" name="hero_<?= $i ?>_title" class="form-input" 
                                       value="<?= htmlspecialchars($current_settings["hero_{$i}_title"]) ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="hero_<?= $i ?>_cta_text">버튼 텍스트</label>
                                <input type="text" id="hero_<?= $i ?>_cta_text" name="hero_<?= $i ?>_cta_text" class="form-input" 
                                       value="<?= htmlspecialchars($current_settings["hero_{$i}_cta_text"]) ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="hero_<?= $i ?>_subtitle">부제목</label>
                            <input type="text" id="hero_<?= $i ?>_subtitle" name="hero_<?= $i ?>_subtitle" class="form-input" 
                                   value="<?= htmlspecialchars($current_settings["hero_{$i}_subtitle"]) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="hero_<?= $i ?>_cta_link">버튼 링크</label>
                            <input type="text" id="hero_<?= $i ?>_cta_link" name="hero_<?= $i ?>_cta_link" class="form-input" 
                                   value="<?= htmlspecialchars($current_settings["hero_{$i}_cta_link"]) ?>"
                                   placeholder="/pages/products/ 또는 https://example.com">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="hero_image_<?= $i ?>">배경 이미지</label>
                            <div class="file-input">
                                <input type="file" id="hero_image_<?= $i ?>" name="hero_image_<?= $i ?>" accept="image/*">
                                <div class="form-help">권장 크기: 1920x1080px</div>
                            </div>
                            <?php if (!empty($current_settings["hero_image_$i"])): ?>
                                <div class="current-file">
                                    <strong>현재 이미지:</strong>
                                    <img src="<?= htmlspecialchars($current_settings["hero_image_$i"]) ?>" 
                                         alt="히어로 이미지 <?= $i ?>" class="current-image">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
                
                <!-- AI 식물분석 섹션 -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <span>🤖</span> AI 식물분석 섹션
                    </h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="plant_analysis_title">섹션 제목</label>
                            <input type="text" id="plant_analysis_title" name="plant_analysis_title" class="form-input" 
                                   value="<?= htmlspecialchars($current_settings['plant_analysis_title']) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="plant_analysis_video">데모 영상</label>
                            <div class="file-input">
                                <input type="file" id="plant_analysis_video" name="plant_analysis_video" accept="video/*">
                                <div class="form-help">MP4, WebM, OGG 형식 지원</div>
                            </div>
                            <?php if (!empty($current_settings['plant_analysis_video'])): ?>
                                <div class="current-file">
                                    <strong>현재 동영상:</strong>
                                    <video controls class="current-video">
                                        <source src="<?= htmlspecialchars($current_settings['plant_analysis_video']) ?>" 
                                                type="video/<?= pathinfo($current_settings['plant_analysis_video'], PATHINFO_EXTENSION) ?>">
                                        브라우저가 비디오를 지원하지 않습니다.
                                    </video>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="plant_analysis_description">섹션 설명</label>
                        <textarea id="plant_analysis_description" name="plant_analysis_description" class="form-input textarea"
                                  placeholder="AI 식물분석 서비스에 대한 설명을 입력하세요"><?= htmlspecialchars($current_settings['plant_analysis_description']) ?></textarea>
                    </div>
                </div>
                
                <!-- 회사 소개 영상 섹션 -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <span>🏢</span> 회사 소개 영상 섹션
                    </h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="company_intro_title">섹션 제목</label>
                            <input type="text" id="company_intro_title" name="company_intro_title" class="form-input" 
                                   value="<?= htmlspecialchars($current_settings['company_intro_title']) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="company_intro_video">소개 영상</label>
                            <div class="file-input">
                                <input type="file" id="company_intro_video" name="company_intro_video" accept="video/*">
                                <div class="form-help">MP4, WebM, OGG 형식 지원</div>
                            </div>
                            <?php if (!empty($current_settings['company_intro_video'])): ?>
                                <div class="current-file">
                                    <strong>현재 동영상:</strong>
                                    <video controls class="current-video">
                                        <source src="<?= htmlspecialchars($current_settings['company_intro_video']) ?>" 
                                                type="video/<?= pathinfo($current_settings['company_intro_video'], PATHINFO_EXTENSION) ?>">
                                        브라우저가 비디오를 지원하지 않습니다.
                                    </video>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="company_intro_description">섹션 설명</label>
                        <textarea id="company_intro_description" name="company_intro_description" class="form-input textarea"
                                  placeholder="회사 소개 영상에 대한 설명을 입력하세요"><?= htmlspecialchars($current_settings['company_intro_description']) ?></textarea>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-success">💾 설정 저장</button>
                    <a href="/admin/settings/" class="btn btn-outline">⚙️ 기본 설정으로 돌아가기</a>
                    <button type="button" onclick="previewChanges()" class="btn btn-primary">👁️ 미리보기</button>
                </div>
                
                <!-- 테스트 도구들 -->
                <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                    <h3 style="margin-top: 0;">🔧 개발자 도구</h3>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="/check_upload.php" class="btn btn-outline" target="_blank">📊 업로드 상태 확인</a>
                        <a href="/force_update.php" class="btn btn-outline" target="_blank">🔄 강제 업데이트</a>
                        <a href="/?debug=1" class="btn btn-outline" target="_blank">🐛 메인페이지 디버그</a>
                        <a href="/video_debug.php" class="btn btn-outline" target="_blank">🎬 비디오 연동 테스트</a>
                    </div>
                    <p style="font-size: 12px; color: #666; margin: 10px 0 0 0;">
                        업로드 후 문제가 있으면 위 도구들을 사용해 상태를 확인하세요.
                    </p>
                </div>
            </form>
        </main>
    </div>
    
    <script src="/assets/js/main.js"></script>
    <script>
        function previewChanges() {
            // 새 창에서 메인페이지 미리보기
            window.open('/', '_blank');
        }
        
        // 파일 선택시 미리보기
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function() {
                const file = this.files[0];
                if (!file) return;
                
                const preview = this.parentElement.nextElementSibling;
                if (!preview || !preview.classList.contains('current-file')) {
                    return;
                }
                
                if (file.type.startsWith('image/')) {
                    const img = preview.querySelector('img');
                    if (img) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            img.src = e.target.result;
                        };
                        reader.readAsDataURL(file);
                    }
                } else if (file.type.startsWith('video/')) {
                    const video = preview.querySelector('video source');
                    if (video) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            video.src = e.target.result;
                            video.parentElement.load();
                        };
                        reader.readAsDataURL(file);
                    }
                }
            });
        });
    </script>
</body>
</html>
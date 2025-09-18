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

        // 텍스트 설정 저장
        $textSettings = [
            'hero_title' => trim($_POST['hero_title'] ?? ''),
            'hero_subtitle' => trim($_POST['hero_subtitle'] ?? ''),
            'hero_description' => trim($_POST['hero_description'] ?? ''),
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
                            <label for="hero_bg">히어로 배경 이미지</label>
                            <input type="file" id="hero_bg" name="hero_bg" accept="image/*">
                            <small>권장 크기: 1920x1080px, JPG/PNG 형식</small>
                            <?php if (!empty($currentSettings['hero_background'])): ?>
                                <div class="current-image">
                                    <p>현재 배경 이미지:</p>
                                    <img src="<?= htmlspecialchars($currentSettings['hero_background']) ?>" alt="현재 배경" style="max-width: 300px; max-height: 200px;">
                                </div>
                            <?php endif; ?>
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
                            <label for="gallery_images">갤러리 이미지 URL</label>
                            <textarea id="gallery_images" name="gallery_images" class="form-control" rows="6" placeholder="한 줄에 하나씩 이미지 URL을 입력하세요"><?= htmlspecialchars($currentSettings['gallery_images'] ?? '/assets/images/gallery1.jpg
/assets/images/gallery2.jpg
/assets/images/gallery3.jpg') ?></textarea>
                            <small>각 이미지 URL을 새 줄에 입력하세요</small>
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
                            <li><strong>최대 파일 크기:</strong> 5MB</li>
                            <li><strong>지원 형식:</strong> JPG, PNG, GIF, SVG</li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
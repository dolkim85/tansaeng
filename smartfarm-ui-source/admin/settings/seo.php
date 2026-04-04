<?php
// Initialize session and auth before any output
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
require_once $base_path . '/classes/Database.php';

$auth = Auth::getInstance();
$auth->requireAdmin();

$success = '';
$error = '';

// Handle SEO settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = Database::getInstance()->getConnection();

        $settings = [
            'site_title' => trim($_POST['site_title'] ?? ''),
            'site_description' => trim($_POST['site_description'] ?? ''),
            'site_keywords' => trim($_POST['site_keywords'] ?? ''),
            'og_title' => trim($_POST['og_title'] ?? ''),
            'og_description' => trim($_POST['og_description'] ?? ''),
            'og_image' => trim($_POST['og_image'] ?? ''),
            'twitter_card' => $_POST['twitter_card'] ?? 'summary',
            'twitter_site' => trim($_POST['twitter_site'] ?? ''),
            'google_analytics_id' => trim($_POST['google_analytics_id'] ?? ''),
            'google_search_console_verification' => trim($_POST['google_search_console_verification'] ?? ''),
            'naver_site_verification' => trim($_POST['naver_site_verification'] ?? ''),
            'robots_txt' => trim($_POST['robots_txt'] ?? ''),
            'canonical_url' => trim($_POST['canonical_url'] ?? ''),
        ];

        $pdo->beginTransaction();

        foreach ($settings as $key => $value) {
            $sql = "INSERT INTO site_settings (setting_key, setting_value, updated_at)
                    VALUES (?, ?, NOW())
                    ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$key, $value, $value]);
        }

        $pdo->commit();
        $success = 'SEO 설정이 저장되었습니다.';

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'SEO 설정 저장에 실패했습니다: ' . $e->getMessage();
    }
}

// Load current SEO settings
$seo_settings = [];
try {
    $pdo = Database::getInstance()->getConnection();

    $sql = "SELECT setting_key, setting_value FROM site_settings
            WHERE setting_key IN (
                'site_title', 'site_description', 'site_keywords',
                'og_title', 'og_description', 'og_image',
                'twitter_card', 'twitter_site',
                'google_analytics_id', 'google_search_console_verification',
                'naver_site_verification', 'robots_txt', 'canonical_url'
            )";
    $stmt = $pdo->query($sql);
    while ($row = $stmt->fetch()) {
        $seo_settings[$row['setting_key']] = $row['setting_value'];
    }

} catch (Exception $e) {
    $error = 'SEO 설정을 불러오는데 실패했습니다.';
}

function getSetting($key, $default = '') {
    global $seo_settings;
    return $seo_settings[$key] ?? $default;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEO 설정 - 탄생 관리자</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        .seo-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: bold;
            color: #333;
        }

        .settings-form {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 30px;
        }

        .section {
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 1px solid #e9ecef;
        }

        .section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-control:focus {
            border-color: #007bff;
            outline: none;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }

        .form-help {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }

        .btn-primary {
            background-color: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .preview-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin-top: 10px;
        }

        .preview-title {
            font-weight: 600;
            color: #1a0dab;
            font-size: 18px;
            margin-bottom: 5px;
        }

        .preview-url {
            color: #006621;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .preview-description {
            color: #545454;
            font-size: 13px;
            line-height: 1.4;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }

        @media (max-width: 768px) {
            .seo-container {
                padding: 10px;
            }

            .settings-form {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>

    <div class="admin-layout">
        <?php include '../includes/admin_sidebar.php'; ?>

        <main class="admin-main">
            <div class="seo-container">
                <div class="page-header">
                    <h1 class="page-title">SEO 설정</h1>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" class="settings-form">
                    <!-- Basic SEO Settings -->
                    <div class="section">
                        <h2 class="section-title">🔍 기본 SEO 설정</h2>

                        <div class="form-group">
                            <label class="form-label">사이트 제목</label>
                            <input type="text" name="site_title" class="form-control"
                                   value="<?= htmlspecialchars(getSetting('site_title')) ?>"
                                   placeholder="예: 탄생 - 스마트팜 배지 전문 기업">
                            <p class="form-help">브라우저 탭과 검색 결과에 표시되는 제목입니다.</p>
                        </div>

                        <div class="form-group">
                            <label class="form-label">사이트 설명 (Meta Description)</label>
                            <textarea name="site_description" class="form-control"
                                      placeholder="사이트를 간략하게 설명해주세요 (150-160자 권장)"><?= htmlspecialchars(getSetting('site_description')) ?></textarea>
                            <p class="form-help">검색 결과에 표시되는 사이트 설명입니다.</p>
                        </div>

                        <div class="form-group">
                            <label class="form-label">키워드 (Meta Keywords)</label>
                            <input type="text" name="site_keywords" class="form-control"
                                   value="<?= htmlspecialchars(getSetting('site_keywords')) ?>"
                                   placeholder="예: 스마트팜, 배지, 코코피트, 수경재배">
                            <p class="form-help">쉼표(,)로 구분하여 입력해주세요.</p>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Canonical URL</label>
                            <input type="url" name="canonical_url" class="form-control"
                                   value="<?= htmlspecialchars(getSetting('canonical_url')) ?>"
                                   placeholder="https://www.tansaeng.com">
                            <p class="form-help">메인 도메인 URL을 입력하세요.</p>
                        </div>

                        <div class="preview-box">
                            <div class="preview-title">탄생 - 스마트팜 배지 전문 기업</div>
                            <div class="preview-url">https://www.tansaeng.com</div>
                            <div class="preview-description">
                                고품질 스마트팜 배지 제조 및 AI 식물분석 서비스를 제공하는 탄생입니다.
                            </div>
                        </div>
                    </div>

                    <!-- Open Graph (Facebook, KakaoTalk) -->
                    <div class="section">
                        <h2 class="section-title">📱 소셜 미디어 설정 (Open Graph)</h2>

                        <div class="form-group">
                            <label class="form-label">OG 제목</label>
                            <input type="text" name="og_title" class="form-control"
                                   value="<?= htmlspecialchars(getSetting('og_title')) ?>"
                                   placeholder="소셜 미디어에 공유될 때 표시되는 제목">
                            <p class="form-help">Facebook, 카카오톡 등에서 링크 공유 시 표시됩니다.</p>
                        </div>

                        <div class="form-group">
                            <label class="form-label">OG 설명</label>
                            <textarea name="og_description" class="form-control"
                                      placeholder="소셜 미디어에 공유될 때 표시되는 설명"><?= htmlspecialchars(getSetting('og_description')) ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">OG 이미지 URL</label>
                            <input type="url" name="og_image" class="form-control"
                                   value="<?= htmlspecialchars(getSetting('og_image')) ?>"
                                   placeholder="https://www.tansaeng.com/assets/images/og-image.jpg">
                            <p class="form-help">권장 크기: 1200x630px</p>
                        </div>
                    </div>

                    <!-- Twitter Card -->
                    <div class="section">
                        <h2 class="section-title">🐦 트위터 카드 설정</h2>

                        <div class="form-group">
                            <label class="form-label">카드 타입</label>
                            <select name="twitter_card" class="form-control">
                                <option value="summary" <?= getSetting('twitter_card') === 'summary' ? 'selected' : '' ?>>
                                    Summary
                                </option>
                                <option value="summary_large_image" <?= getSetting('twitter_card') === 'summary_large_image' ? 'selected' : '' ?>>
                                    Summary Large Image
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">트위터 계정</label>
                            <input type="text" name="twitter_site" class="form-control"
                                   value="<?= htmlspecialchars(getSetting('twitter_site')) ?>"
                                   placeholder="@tansaeng">
                            <p class="form-help">@ 기호를 포함하여 입력하세요.</p>
                        </div>
                    </div>

                    <!-- Analytics & Verification -->
                    <div class="section">
                        <h2 class="section-title">📊 분석 도구 & 검증</h2>

                        <div class="form-group">
                            <label class="form-label">Google Analytics ID</label>
                            <input type="text" name="google_analytics_id" class="form-control"
                                   value="<?= htmlspecialchars(getSetting('google_analytics_id')) ?>"
                                   placeholder="G-XXXXXXXXXX 또는 UA-XXXXXXXXX-X">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Google Search Console 인증 코드</label>
                            <input type="text" name="google_search_console_verification" class="form-control"
                                   value="<?= htmlspecialchars(getSetting('google_search_console_verification')) ?>"
                                   placeholder="메타 태그의 content 값만 입력">
                        </div>

                        <div class="form-group">
                            <label class="form-label">네이버 사이트 인증 코드</label>
                            <input type="text" name="naver_site_verification" class="form-control"
                                   value="<?= htmlspecialchars(getSetting('naver_site_verification')) ?>"
                                   placeholder="메타 태그의 content 값만 입력">
                        </div>
                    </div>

                    <!-- Robots.txt -->
                    <div class="section">
                        <h2 class="section-title">🤖 Robots.txt 설정</h2>

                        <div class="form-group">
                            <label class="form-label">Robots.txt 내용</label>
                            <textarea name="robots_txt" class="form-control" rows="10"
                                      placeholder="User-agent: *&#10;Disallow: /admin/&#10;Allow: /"><?= htmlspecialchars(getSetting('robots_txt')) ?></textarea>
                            <p class="form-help">검색 엔진 크롤러에 대한 접근 규칙을 설정합니다.</p>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">설정 저장</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>

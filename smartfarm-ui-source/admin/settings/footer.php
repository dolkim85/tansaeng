<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ||
          (isset($_SESSION['user_level']) && $_SESSION['user_level'] == 9);

if (!$isAdmin) {
    header('Location: /pages/auth/login.php?redirect=/admin/settings/footer.php');
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = DatabaseConfig::getConnection();

        // 메뉴 데이터를 JSON 형식으로 변환 (name|url 형식)
        function parseMenuInput($input) {
            $lines = array_filter(array_map('trim', explode("\n", $input)));
            $menu = [];
            foreach ($lines as $line) {
                $parts = explode('|', $line, 2);
                $menu[] = [
                    'name' => trim($parts[0]),
                    'url' => isset($parts[1]) ? trim($parts[1]) : '#'
                ];
            }
            return json_encode($menu);
        }

        $settings = [
            'footer_company_desc' => trim($_POST['footer_company_desc'] ?? ''),
            'footer_address' => trim($_POST['footer_address'] ?? ''),
            'footer_phone' => trim($_POST['footer_phone'] ?? ''),
            'footer_fax' => trim($_POST['footer_fax'] ?? ''),
            'footer_email' => trim($_POST['footer_email'] ?? ''),
            'footer_business_hours_weekday' => trim($_POST['footer_business_hours_weekday'] ?? ''),
            'footer_business_hours_saturday' => trim($_POST['footer_business_hours_saturday'] ?? ''),
            'footer_business_hours_holiday' => trim($_POST['footer_business_hours_holiday'] ?? ''),
            'footer_copyright' => trim($_POST['footer_copyright'] ?? ''),
            'footer_social_facebook' => trim($_POST['footer_social_facebook'] ?? ''),
            'footer_social_instagram' => trim($_POST['footer_social_instagram'] ?? ''),
            'footer_social_youtube' => trim($_POST['footer_social_youtube'] ?? ''),
            'footer_social_blog' => trim($_POST['footer_social_blog'] ?? ''),
            'footer_menu_products' => parseMenuInput($_POST['footer_menu_products'] ?? ''),
            'footer_menu_services' => parseMenuInput($_POST['footer_menu_services'] ?? ''),
            'footer_menu_company' => parseMenuInput($_POST['footer_menu_company'] ?? ''),
            'footer_menu_legal' => parseMenuInput($_POST['footer_menu_legal'] ?? '')
        ];

        foreach ($settings as $key => $value) {
            $sql = "INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$key, $value]);
        }

        $success = '푸터 설정이 성공적으로 저장되었습니다.';
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

// JSON 배열을 텍스트로 변환하는 함수 (새 형식: name|url)
function jsonToText($jsonString) {
    if (empty($jsonString)) return '';
    $array = json_decode($jsonString, true);
    if (!is_array($array)) return '';

    $lines = [];
    foreach ($array as $item) {
        if (is_array($item) && isset($item['name'])) {
            // 새 형식: {"name": "메뉴명", "url": "/path"}
            $lines[] = $item['name'] . '|' . ($item['url'] ?? '#');
        } else if (is_string($item)) {
            // 구 형식: "메뉴명"
            $lines[] = $item . '|#';
        }
    }
    return implode("\n", $lines);
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>푸터 관리 - 탄생 관리자</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        .footer-editor-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }

        .editor-panel {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow-y: auto;
            max-height: calc(100vh - 200px);
        }

        .editor-panel h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }

        .preview-panel {
            background: #f8f9fa;
            position: sticky;
            top: 20px;
        }

        .preview-footer {
            background: #2c3e50;
            color: white;
            padding: 40px 20px 20px;
            border-radius: 10px;
            font-size: 14px;
        }

        .preview-footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }

        .preview-footer-section h3 {
            color: white;
            font-size: 16px;
            margin-bottom: 15px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 8px;
        }

        .preview-footer-section p,
        .preview-footer-section ul {
            margin: 8px 0;
            line-height: 1.6;
        }

        .preview-footer-section ul {
            list-style: none;
            padding: 0;
        }

        .preview-footer-section li {
            margin: 5px 0;
        }

        .preview-footer-section a {
            color: #ecf0f1;
            text-decoration: none;
            transition: color 0.3s;
        }

        .preview-footer-section a:hover {
            color: #3498db;
        }

        .preview-footer-social {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .preview-footer-social a {
            background: rgba(255,255,255,0.1);
            padding: 8px 12px;
            border-radius: 5px;
            transition: background 0.3s;
        }

        .preview-footer-social a:hover {
            background: rgba(52, 152, 219, 0.3);
        }

        .preview-footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #95a5a6;
        }

        .preview-info {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }

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
            text-decoration: none;
            display: inline-block;
        }

        .btn-success {
            background: #2ecc71;
            color: white;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-outline {
            background: white;
            color: #3498db;
            border: 2px solid #3498db;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
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

        @media (max-width: 1200px) {
            .footer-editor-container {
                grid-template-columns: 1fr;
            }

            .preview-panel {
                position: static;
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
                    <h1>🔗 푸터 관리</h1>
                    <p>좌측에서 푸터 내용을 수정하면, 우측에서 실시간으로 미리보기됩니다</p>
                </div>

                <div class="toolbar">
                    <button class="btn btn-success" onclick="document.getElementById('footerForm').submit()">💾 모두 저장</button>
                    <button class="btn btn-primary" onclick="refreshPreview()">🔄 미리보기 새로고침</button>
                    <a href="/" target="_blank" class="btn btn-outline">🌐 실제 사이트 보기</a>
                </div>

                <div id="alertArea">
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                </div>

                <div class="footer-editor-container">
                    <!-- 왼쪽: 편집 패널 -->
                    <div class="editor-panel">
                        <h3>📝 푸터 내용 편집</h3>

                <form method="post" class="admin-form" id="footerForm">
                    <div class="form-section">
                        <h3>회사 정보</h3>

                        <div class="form-group">
                            <label for="footer_company_desc">회사 설명</label>
                            <textarea id="footer_company_desc" name="footer_company_desc" class="form-control" rows="3" oninput="updatePreview()"><?= htmlspecialchars($currentSettings['footer_company_desc'] ?? '스마트팜 배지 제조 전문회사로서 최고 품질의 제품과 혁신적인 AI 기술을 통해 미래 농업을 선도합니다.') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="footer_address">주소</label>
                            <input type="text" id="footer_address" name="footer_address"
                                   value="<?= htmlspecialchars($currentSettings['footer_address'] ?? '서울특별시 강남구 테헤란로 123') ?>"
                                   oninput="updatePreview()">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="footer_phone">전화번호</label>
                                <input type="tel" id="footer_phone" name="footer_phone"
                                       value="<?= htmlspecialchars($currentSettings['footer_phone'] ?? '02-0000-0000') ?>"
                                       oninput="updatePreview()">
                            </div>

                            <div class="form-group">
                                <label for="footer_fax">팩스</label>
                                <input type="tel" id="footer_fax" name="footer_fax"
                                       value="<?= htmlspecialchars($currentSettings['footer_fax'] ?? '02-0000-0001') ?>"
                                       oninput="updatePreview()">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="footer_email">이메일</label>
                            <input type="email" id="footer_email" name="footer_email"
                                   value="<?= htmlspecialchars($currentSettings['footer_email'] ?? 'info@tansaeng.com') ?>"
                                   oninput="updatePreview()">
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>운영 시간</h3>

                        <div class="form-group">
                            <label for="footer_business_hours_weekday">평일</label>
                            <input type="text" id="footer_business_hours_weekday" name="footer_business_hours_weekday"
                                   value="<?= htmlspecialchars($currentSettings['footer_business_hours_weekday'] ?? '평일: 09:00 - 18:00') ?>">
                        </div>

                        <div class="form-group">
                            <label for="footer_business_hours_saturday">토요일</label>
                            <input type="text" id="footer_business_hours_saturday" name="footer_business_hours_saturday"
                                   value="<?= htmlspecialchars($currentSettings['footer_business_hours_saturday'] ?? '토요일: 09:00 - 13:00') ?>">
                        </div>

                        <div class="form-group">
                            <label for="footer_business_hours_holiday">일요일/공휴일</label>
                            <input type="text" id="footer_business_hours_holiday" name="footer_business_hours_holiday"
                                   value="<?= htmlspecialchars($currentSettings['footer_business_hours_holiday'] ?? '일요일/공휴일: 휴무') ?>">
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>소셜 미디어</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="footer_social_facebook">Facebook</label>
                                <input type="url" id="footer_social_facebook" name="footer_social_facebook"
                                       value="<?= htmlspecialchars($currentSettings['footer_social_facebook'] ?? 'https://facebook.com/tansaeng') ?>">
                            </div>

                            <div class="form-group">
                                <label for="footer_social_instagram">Instagram</label>
                                <input type="url" id="footer_social_instagram" name="footer_social_instagram"
                                       value="<?= htmlspecialchars($currentSettings['footer_social_instagram'] ?? 'https://instagram.com/tansaeng') ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="footer_social_youtube">YouTube</label>
                                <input type="url" id="footer_social_youtube" name="footer_social_youtube"
                                       value="<?= htmlspecialchars($currentSettings['footer_social_youtube'] ?? 'https://youtube.com/@tansaeng') ?>">
                            </div>

                            <div class="form-group">
                                <label for="footer_social_blog">블로그</label>
                                <input type="url" id="footer_social_blog" name="footer_social_blog"
                                       value="<?= htmlspecialchars($currentSettings['footer_social_blog'] ?? 'https://blog.tansaeng.com') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>푸터 메뉴</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="footer_menu_products">제품 메뉴</label>
                                <textarea id="footer_menu_products" name="footer_menu_products" class="form-control" rows="6" placeholder="메뉴명|URL 형식으로 입력하세요&#10;예: 코코피트 배지|/pages/products/coco.php" oninput="updatePreview()"><?= htmlspecialchars(jsonToText($currentSettings['footer_menu_products'] ?? '') ?: "코코피트 배지|/pages/products/coco.php\n펄라이트 배지|/pages/products/perlite.php\n혼합 배지|/pages/products/mixed.php\n제품 비교|/pages/products/compare.php") ?></textarea>
                                <small>형식: <code>메뉴명|URL</code> (한 줄에 하나씩, URL 없으면 # 처리됨)</small>
                            </div>

                            <div class="form-group">
                                <label for="footer_menu_services">서비스 메뉴</label>
                                <textarea id="footer_menu_services" name="footer_menu_services" class="form-control" rows="6" placeholder="메뉴명|URL 형식으로 입력하세요&#10;예: AI 식물분석|/pages/plant_analysis/" oninput="updatePreview()"><?= htmlspecialchars(jsonToText($currentSettings['footer_menu_services'] ?? '') ?: "AI 식물분석|/pages/plant_analysis/\nFAQ|/pages/support/faq.php\n기술지원|/pages/support/technical.php\n1:1 문의|/pages/support/inquiry.php") ?></textarea>
                                <small>형식: <code>메뉴명|URL</code> (한 줄에 하나씩, URL 없으면 # 처리됨)</small>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="footer_menu_company">회사 메뉴</label>
                                <textarea id="footer_menu_company" name="footer_menu_company" class="form-control" rows="4" placeholder="메뉴명|URL 형식으로 입력하세요&#10;예: 회사소개|/pages/company/about.php" oninput="updatePreview()"><?= htmlspecialchars(jsonToText($currentSettings['footer_menu_company'] ?? '') ?: "회사소개|/pages/company/about.php\n연혁|/pages/company/history.php\n오시는길|/pages/company/location.php\n공지사항|/pages/board/") ?></textarea>
                                <small>형식: <code>메뉴명|URL</code> (한 줄에 하나씩, URL 없으면 # 처리됨)</small>
                            </div>

                            <div class="form-group">
                                <label for="footer_menu_legal">법적 정보</label>
                                <textarea id="footer_menu_legal" name="footer_menu_legal" class="form-control" rows="4" placeholder="메뉴명|URL 형식으로 입력하세요&#10;예: 개인정보처리방침|/pages/legal/privacy.php" oninput="updatePreview()"><?= htmlspecialchars(jsonToText($currentSettings['footer_menu_legal'] ?? '') ?: "개인정보처리방침|/pages/legal/privacy.php\n이용약관|/pages/legal/terms.php\n사이트맵|/sitemap.php") ?></textarea>
                                <small>형식: <code>메뉴명|URL</code> (한 줄에 하나씩, URL 없으면 # 처리됨)</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>저작권 정보</h3>

                        <div class="form-group">
                            <label for="footer_copyright">저작권 표시</label>
                            <input type="text" id="footer_copyright" name="footer_copyright"
                                   value="<?= htmlspecialchars($currentSettings['footer_copyright'] ?? '© 2024 탄생(Tansaeng). All rights reserved.') ?>"
                                   oninput="updatePreview()">
                        </div>
                    </div>
                </form>
                    </div>

                    <!-- 오른쪽: 실시간 미리보기 패널 -->
                    <div class="editor-panel preview-panel">
                        <h3>👁️ 실시간 미리보기</h3>

                        <div class="preview-footer">
                            <div class="preview-footer-content">
                                <div class="preview-footer-section">
                                    <h3 id="preview_company_name">탄생</h3>
                                    <p id="preview_company_desc" class="footer-company-desc"></p>
                                    <div class="footer-contact">
                                        <p id="preview_address">📍 </p>
                                        <p id="preview_phone">📞 </p>
                                        <p id="preview_email">✉️ </p>
                                    </div>
                                    <div class="preview-footer-social" id="preview_social">
                                        <!-- Social links will be dynamically added -->
                                    </div>
                                </div>

                                <div class="preview-footer-section">
                                    <h3>제품</h3>
                                    <ul id="preview_products_menu">
                                        <!-- Product menu items will be dynamically added -->
                                    </ul>
                                </div>

                                <div class="preview-footer-section">
                                    <h3>서비스</h3>
                                    <ul id="preview_services_menu">
                                        <!-- Services menu items will be dynamically added -->
                                    </ul>
                                </div>

                                <div class="preview-footer-section">
                                    <h3>회사정보</h3>
                                    <ul id="preview_company_menu">
                                        <!-- Company menu items will be dynamically added -->
                                    </ul>
                                </div>
                            </div>

                            <div class="preview-footer-bottom">
                                <p id="preview_copyright"></p>
                            </div>
                        </div>

                        <div class="preview-info">
                            <h4>💡 미리보기 정보</h4>
                            <p>왼쪽 폼에서 내용을 수정하면 이 미리보기가 실시간으로 업데이트됩니다.</p>
                            <p style="margin-top: 10px; padding: 10px; background: #fff3cd; border-radius: 5px; font-size: 13px;">
                                <strong>Tip:</strong> 변경사항을 저장하려면 상단의 "💾 모두 저장" 버튼을 클릭하세요!
                            </p>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        // HTML 이스케이프 함수
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // 메뉴 텍스트 파싱 함수 (name|url 형식)
        function parseMenuText(text) {
            if (!text) return [];
            const lines = text.split('\n').filter(line => line.trim());
            return lines.map(line => {
                const parts = line.split('|');
                return {
                    name: parts[0] ? parts[0].trim() : '',
                    url: parts[1] ? parts[1].trim() : '#'
                };
            });
        }

        // 미리보기 업데이트 함수
        function updatePreview() {
            // 회사 정보
            const companyDesc = document.getElementById('footer_company_desc').value;
            const address = document.getElementById('footer_address').value;
            const phone = document.getElementById('footer_phone').value;
            const email = document.getElementById('footer_email').value;
            const copyright = document.getElementById('footer_copyright').value;

            document.getElementById('preview_company_desc').textContent = companyDesc;
            document.getElementById('preview_address').innerHTML = '📍 ' + escapeHtml(address);
            document.getElementById('preview_phone').innerHTML = '📞 ' + escapeHtml(phone);
            document.getElementById('preview_email').innerHTML = '✉️ ' + escapeHtml(email);
            document.getElementById('preview_copyright').textContent = copyright;

            // 제품 메뉴
            const productsText = document.getElementById('footer_menu_products').value;
            const productsMenu = parseMenuText(productsText);
            const productsHtml = productsMenu.map(item =>
                `<li><a href="${escapeHtml(item.url)}">${escapeHtml(item.name)}</a></li>`
            ).join('');
            document.getElementById('preview_products_menu').innerHTML = productsHtml || '<li style="color: #999;">메뉴 항목 없음</li>';

            // 서비스 메뉴
            const servicesText = document.getElementById('footer_menu_services').value;
            const servicesMenu = parseMenuText(servicesText);
            const servicesHtml = servicesMenu.map(item =>
                `<li><a href="${escapeHtml(item.url)}">${escapeHtml(item.name)}</a></li>`
            ).join('');
            document.getElementById('preview_services_menu').innerHTML = servicesHtml || '<li style="color: #999;">메뉴 항목 없음</li>';

            // 회사 메뉴
            const companyText = document.getElementById('footer_menu_company').value;
            const companyMenu = parseMenuText(companyText);
            const companyHtml = companyMenu.map(item =>
                `<li><a href="${escapeHtml(item.url)}">${escapeHtml(item.name)}</a></li>`
            ).join('');
            document.getElementById('preview_company_menu').innerHTML = companyHtml || '<li style="color: #999;">메뉴 항목 없음</li>';

            // 소셜 미디어 (실제 값은 현재 저장된 값 사용)
            updateSocialLinks();
        }

        // 소셜 링크 업데이트 (저장된 값에서)
        function updateSocialLinks() {
            const social = document.getElementById('preview_social');
            let socialHtml = '';

            <?php if (!empty($currentSettings['footer_social_youtube'])): ?>
                socialHtml += '<a href="<?= htmlspecialchars($currentSettings['footer_social_youtube']) ?>" target="_blank">📺</a>';
            <?php endif; ?>

            <?php if (!empty($currentSettings['footer_social_instagram'])): ?>
                socialHtml += '<a href="<?= htmlspecialchars($currentSettings['footer_social_instagram']) ?>" target="_blank">📸</a>';
            <?php endif; ?>

            <?php if (!empty($currentSettings['footer_social_facebook'])): ?>
                socialHtml += '<a href="<?= htmlspecialchars($currentSettings['footer_social_facebook']) ?>" target="_blank">👥</a>';
            <?php endif; ?>

            <?php if (!empty($currentSettings['footer_social_blog'])): ?>
                socialHtml += '<a href="<?= htmlspecialchars($currentSettings['footer_social_blog']) ?>" target="_blank">📝</a>';
            <?php endif; ?>

            social.innerHTML = socialHtml || '<span style="color: #999; font-size: 12px;">소셜 링크 없음</span>';
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
            }, 3000);
        }

        // 페이지 로드 시 미리보기 초기화
        document.addEventListener('DOMContentLoaded', function() {
            updatePreview();
        });
    </script>
</body>
</html>
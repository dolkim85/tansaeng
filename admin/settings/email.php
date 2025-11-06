<?php
// Initialize session and auth before any output
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
require_once $base_path . '/classes/Database.php';

$auth = Auth::getInstance();
$auth->requireAdmin();

$success = '';
$error = '';
$test_result = '';

// Handle email settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'test') {
        // Test email sending
        $test_email = trim($_POST['test_email'] ?? '');

        if (empty($test_email)) {
            $error = '테스트 이메일 주소를 입력해주세요.';
        } else {
            try {
                require_once $base_path . '/classes/Mailer.php';

                $mailer = new Mailer();
                $result = $mailer->sendMail(
                    $test_email,
                    '탄생 이메일 설정 테스트',
                    '이메일 설정이 정상적으로 작동하고 있습니다.',
                    '<h2>탄생 이메일 설정 테스트</h2><p>이메일 설정이 정상적으로 작동하고 있습니다.</p>'
                );

                if ($result) {
                    $test_result = '테스트 이메일이 발송되었습니다. 수신함을 확인해주세요.';
                } else {
                    $error = '이메일 발송에 실패했습니다.';
                }
            } catch (Exception $e) {
                $error = '이메일 발송 실패: ' . $e->getMessage();
            }
        }

    } else {
        // Save email settings
        try {
            $pdo = Database::getInstance()->getConnection();

            $settings = [
                'smtp_host' => trim($_POST['smtp_host'] ?? ''),
                'smtp_port' => trim($_POST['smtp_port'] ?? '587'),
                'smtp_username' => trim($_POST['smtp_username'] ?? ''),
                'smtp_password' => trim($_POST['smtp_password'] ?? ''),
                'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
                'from_email' => trim($_POST['from_email'] ?? ''),
                'from_name' => trim($_POST['from_name'] ?? ''),
                'reply_to_email' => trim($_POST['reply_to_email'] ?? ''),
                'email_footer' => trim($_POST['email_footer'] ?? ''),
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
            $success = '이메일 설정이 저장되었습니다.';

        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = '이메일 설정 저장에 실패했습니다: ' . $e->getMessage();
        }
    }
}

// Load current email settings
$email_settings = [];
try {
    $pdo = Database::getInstance()->getConnection();

    $sql = "SELECT setting_key, setting_value FROM site_settings
            WHERE setting_key IN (
                'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password',
                'smtp_encryption', 'from_email', 'from_name',
                'reply_to_email', 'email_footer'
            )";
    $stmt = $pdo->query($sql);
    while ($row = $stmt->fetch()) {
        $email_settings[$row['setting_key']] = $row['setting_value'];
    }

} catch (Exception $e) {
    $error = '이메일 설정을 불러오는데 실패했습니다.';
}

function getSetting($key, $default = '') {
    global $email_settings;
    return $email_settings[$key] ?? $default;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>이메일 설정 - 탄생 관리자</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        .email-container {
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

        .btn-success {
            background-color: #28a745;
            color: white;
        }

        .btn-success:hover {
            background-color: #218838;
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

        .alert-info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }

        .test-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .test-form {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .test-form .form-group {
            flex: 1;
            margin-bottom: 0;
        }

        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .email-container {
                padding: 10px;
            }

            .settings-form {
                padding: 20px;
            }

            .form-actions {
                flex-direction: column;
            }

            .test-form {
                flex-direction: column;
            }

            .test-form .form-group {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>

    <div class="admin-layout">
        <?php include '../includes/admin_sidebar.php'; ?>

        <main class="admin-main">
            <div class="email-container">
                <div class="page-header">
                    <h1 class="page-title">이메일 설정</h1>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if ($test_result): ?>
                    <div class="alert alert-info"><?= htmlspecialchars($test_result) ?></div>
                <?php endif; ?>

                <!-- Test Email Section -->
                <div class="test-section">
                    <h3>📧 이메일 발송 테스트</h3>
                    <p style="color: #666; margin-bottom: 15px;">설정이 올바른지 테스트 이메일을 발송해보세요.</p>
                    <form method="POST" class="test-form">
                        <input type="hidden" name="action" value="test">
                        <div class="form-group">
                            <input type="email" name="test_email" class="form-control"
                                   placeholder="테스트 이메일 주소" required>
                        </div>
                        <button type="submit" class="btn btn-success">테스트 발송</button>
                    </form>
                </div>

                <form method="POST" class="settings-form">
                    <input type="hidden" name="action" value="save">

                    <!-- SMTP Settings -->
                    <div class="section">
                        <h2 class="section-title">📮 SMTP 서버 설정</h2>

                        <div class="form-group">
                            <label class="form-label">SMTP 호스트 *</label>
                            <input type="text" name="smtp_host" class="form-control"
                                   value="<?= htmlspecialchars(getSetting('smtp_host')) ?>"
                                   placeholder="smtp.gmail.com" required>
                            <p class="form-help">Gmail: smtp.gmail.com / Naver: smtp.naver.com</p>
                        </div>

                        <div class="form-group">
                            <label class="form-label">SMTP 포트 *</label>
                            <input type="number" name="smtp_port" class="form-control"
                                   value="<?= htmlspecialchars(getSetting('smtp_port', '587')) ?>"
                                   placeholder="587" required>
                            <p class="form-help">일반적으로 587(TLS) 또는 465(SSL)를 사용합니다.</p>
                        </div>

                        <div class="form-group">
                            <label class="form-label">암호화 방식 *</label>
                            <select name="smtp_encryption" class="form-control">
                                <option value="tls" <?= getSetting('smtp_encryption', 'tls') === 'tls' ? 'selected' : '' ?>>
                                    TLS (권장)
                                </option>
                                <option value="ssl" <?= getSetting('smtp_encryption') === 'ssl' ? 'selected' : '' ?>>
                                    SSL
                                </option>
                                <option value="" <?= getSetting('smtp_encryption') === '' ? 'selected' : '' ?>>
                                    없음
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">SMTP 사용자명 (이메일) *</label>
                            <input type="email" name="smtp_username" class="form-control"
                                   value="<?= htmlspecialchars(getSetting('smtp_username')) ?>"
                                   placeholder="your-email@example.com" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">SMTP 비밀번호 *</label>
                            <input type="password" name="smtp_password" class="form-control"
                                   value="<?= htmlspecialchars(getSetting('smtp_password')) ?>"
                                   placeholder="비밀번호 또는 앱 비밀번호" required>
                            <p class="form-help">Gmail의 경우 앱 비밀번호를 생성하여 사용해야 합니다.</p>
                        </div>

                        <div class="info-box">
                            <strong>💡 Gmail 앱 비밀번호 생성 방법:</strong>
                            <ol style="margin: 10px 0 0 20px;">
                                <li>Google 계정 관리 → 보안</li>
                                <li>2단계 인증 활성화</li>
                                <li>앱 비밀번호 생성</li>
                                <li>생성된 16자리 비밀번호 사용</li>
                            </ol>
                        </div>
                    </div>

                    <!-- Sender Information -->
                    <div class="section">
                        <h2 class="section-title">👤 발신자 정보</h2>

                        <div class="form-group">
                            <label class="form-label">발신자 이메일 *</label>
                            <input type="email" name="from_email" class="form-control"
                                   value="<?= htmlspecialchars(getSetting('from_email')) ?>"
                                   placeholder="noreply@tansaeng.com" required>
                            <p class="form-help">이메일 발송 시 발신자로 표시될 이메일 주소입니다.</p>
                        </div>

                        <div class="form-group">
                            <label class="form-label">발신자 이름 *</label>
                            <input type="text" name="from_name" class="form-control"
                                   value="<?= htmlspecialchars(getSetting('from_name')) ?>"
                                   placeholder="탄생" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">답장 받을 이메일</label>
                            <input type="email" name="reply_to_email" class="form-control"
                                   value="<?= htmlspecialchars(getSetting('reply_to_email')) ?>"
                                   placeholder="support@tansaeng.com">
                            <p class="form-help">수신자가 답장할 때 사용될 이메일 주소입니다.</p>
                        </div>
                    </div>

                    <!-- Email Template -->
                    <div class="section">
                        <h2 class="section-title">📝 이메일 템플릿</h2>

                        <div class="form-group">
                            <label class="form-label">이메일 푸터</label>
                            <textarea name="email_footer" class="form-control" rows="5"
                                      placeholder="이메일 하단에 표시될 내용을 입력하세요"><?= htmlspecialchars(getSetting('email_footer')) ?></textarea>
                            <p class="form-help">모든 이메일 하단에 표시될 회사 정보, 연락처 등을 입력하세요.</p>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="/admin/settings/" class="btn btn-secondary">취소</a>
                        <button type="submit" class="btn btn-primary">설정 저장</button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Show/hide password
        document.querySelectorAll('input[type="password"]').forEach(input => {
            const wrapper = input.parentElement;
            const toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.textContent = '👁️';
            toggle.style.cssText = 'position:absolute; right:10px; top:35px; background:none; border:none; cursor:pointer;';

            wrapper.style.position = 'relative';
            wrapper.appendChild(toggle);

            toggle.addEventListener('click', () => {
                input.type = input.type === 'password' ? 'text' : 'password';
                toggle.textContent = input.type === 'password' ? '👁️' : '🙈';
            });
        });
    </script>
</body>
</html>

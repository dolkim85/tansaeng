<?php
session_start();

require_once __DIR__ . '/../../classes/Database.php';

$error = '';
$success = '';
$step = 'email'; // email, verify, reset

// 이메일 확인 단계
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_email'])) {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = '이메일을 입력해주세요.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '올바른 이메일 주소를 입력해주세요.';
    } else {
        try {
            $db = Database::getInstance();
            $pdo = $db->getConnection();

            $stmt = $pdo->prepare("SELECT id, email, phone, oauth_provider FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // 소셜 로그인 사용자 체크
                if (!empty($user['oauth_provider'])) {
                    $providerName = [
                        'google' => '구글',
                        'kakao' => '카카오',
                        'naver' => '네이버'
                    ][$user['oauth_provider']] ?? $user['oauth_provider'];

                    $error = '해당 이메일은 ' . $providerName . ' 소셜 로그인으로 가입된 계정입니다. ' . $providerName . '로 로그인해주세요.';
                } else {
                    $_SESSION['reset_user_id'] = $user['id'];
                    $_SESSION['reset_email'] = $user['email'];
                    $_SESSION['reset_phone_last4'] = substr($user['phone'], -4);
                    $step = 'verify';
                }
            } else {
                $error = '해당 이메일로 가입된 계정이 없습니다.';
            }
        } catch (Exception $e) {
            error_log('Forgot password error: ' . $e->getMessage());
            $error = '오류가 발생했습니다. 다시 시도해주세요.';
        }
    }
}

// 휴대전화 번호 확인 단계
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_phone'])) {
    $phone = trim($_POST['phone'] ?? '');

    if (!isset($_SESSION['reset_user_id'])) {
        $error = '세션이 만료되었습니다. 다시 시도해주세요.';
        $step = 'email';
    } elseif (empty($phone)) {
        $error = '휴대전화번호를 입력해주세요.';
        $step = 'verify';
    } else {
        try {
            $db = Database::getInstance();
            $pdo = $db->getConnection();

            $stmt = $pdo->prepare("SELECT phone FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['reset_user_id']]);
            $user = $stmt->fetch();

            if ($user && $user['phone'] === $phone) {
                $step = 'reset';
            } else {
                $error = '휴대전화번호가 일치하지 않습니다.';
                $step = 'verify';
            }
        } catch (Exception $e) {
            error_log('Phone verification error: ' . $e->getMessage());
            $error = '오류가 발생했습니다. 다시 시도해주세요.';
            $step = 'verify';
        }
    }
}

// 비밀번호 재설정 단계
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $new_password = $_POST['new_password'] ?? '';
    $new_password_confirm = $_POST['new_password_confirm'] ?? '';

    if (!isset($_SESSION['reset_user_id'])) {
        $error = '세션이 만료되었습니다. 다시 시도해주세요.';
        $step = 'email';
    } elseif (empty($new_password)) {
        $error = '새 비밀번호를 입력해주세요.';
        $step = 'reset';
    } elseif (strlen($new_password) < 8) {
        $error = '비밀번호는 최소 8자 이상이어야 합니다.';
        $step = 'reset';
    } elseif ($new_password !== $new_password_confirm) {
        $error = '비밀번호가 일치하지 않습니다.';
        $step = 'reset';
    } else {
        try {
            $db = Database::getInstance();
            $pdo = $db->getConnection();

            $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$hashedPassword, $_SESSION['reset_user_id']]);

            // 세션 정리
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_phone_last4']);

            $_SESSION['auth_success'] = '비밀번호가 성공적으로 변경되었습니다. 새 비밀번호로 로그인해주세요.';
            header('Location: /pages/auth/login.php');
            exit;

        } catch (Exception $e) {
            error_log('Password reset error: ' . $e->getMessage());
            $error = '비밀번호 변경 중 오류가 발생했습니다. 다시 시도해주세요.';
            $step = 'reset';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>비밀번호 찾기 - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/auth.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>탄생</h1>
                <p>스마트팜 배지 제조회사</p>
            </div>

            <?php if ($step === 'email'): ?>
                <!-- 1단계: 이메일 입력 -->
                <form method="post" class="auth-form">
                    <h2 style="text-align: center;">비밀번호 찾기</h2>
                    <p style="text-align: center; color: #666; margin-bottom: 1.5rem;">
                        가입하신 이메일 주소를 입력해주세요.
                    </p>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="email">이메일 *</label>
                        <input type="email" id="email" name="email" required autofocus>
                    </div>

                    <button type="submit" name="verify_email" class="btn btn-primary btn-full">다음</button>

                    <div class="auth-links">
                        <a href="/pages/auth/login.php">로그인으로 돌아가기</a>
                    </div>
                </form>

            <?php elseif ($step === 'verify'): ?>
                <!-- 2단계: 휴대전화번호 확인 -->
                <form method="post" class="auth-form">
                    <h2 style="text-align: center;">본인 확인</h2>
                    <p style="text-align: center; color: #666; margin-bottom: 1.5rem;">
                        가입하신 휴대전화번호를 입력해주세요.<br>
                        <small style="color: #999;">(뒤 4자리: <?= htmlspecialchars($_SESSION['reset_phone_last4']) ?>)</small>
                    </p>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="phone">휴대전화번호 *</label>
                        <input type="tel" id="phone" name="phone" placeholder="010-1234-5678" required autofocus>
                    </div>

                    <button type="submit" name="verify_phone" class="btn btn-primary btn-full">확인</button>

                    <div class="auth-links">
                        <a href="/pages/auth/forgot_password.php">처음부터 다시</a>
                    </div>
                </form>

            <?php elseif ($step === 'reset'): ?>
                <!-- 3단계: 새 비밀번호 설정 -->
                <form method="post" class="auth-form">
                    <h2 style="text-align: center;">새 비밀번호 설정</h2>
                    <p style="text-align: center; color: #666; margin-bottom: 1.5rem;">
                        새로운 비밀번호를 입력해주세요.
                    </p>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="new_password">새 비밀번호 * (최소 6자)</label>
                        <input type="password" id="new_password" name="new_password" required autofocus>
                    </div>

                    <div class="form-group">
                        <label for="new_password_confirm">새 비밀번호 확인 *</label>
                        <input type="password" id="new_password_confirm" name="new_password_confirm" required>
                    </div>

                    <button type="submit" name="reset_password" class="btn btn-primary btn-full">비밀번호 변경</button>
                </form>
            <?php endif; ?>

            <div class="auth-footer">
                <a href="/">홈으로 돌아가기</a>
            </div>
        </div>
    </div>
</body>
</html>

<style>
.alert {
    padding: 0.75rem 1rem;
    margin-bottom: 1rem;
    border-radius: 6px;
    font-size: 0.9rem;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
</style>

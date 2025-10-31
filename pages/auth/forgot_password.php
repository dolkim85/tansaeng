<?php
session_start();

require_once __DIR__ . '/../../classes/Database.php';

$error = '';
$success = '';
$step = 'email'; // email, verify_code, reset

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

            $stmt = $pdo->prepare("SELECT id, email, phone, name, oauth_provider FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // 소셜 로그인 사용자 체크 (oauth_provider가 NULL이거나 'email'이 아닌 경우)
                if (!empty($user['oauth_provider']) && $user['oauth_provider'] !== 'email') {
                    $providerName = [
                        'google' => '구글',
                        'kakao' => '카카오',
                        'naver' => '네이버'
                    ][$user['oauth_provider']] ?? $user['oauth_provider'];

                    $error = '해당 이메일은 ' . $providerName . ' 소셜 로그인으로 가입된 계정입니다. ' . $providerName . '로 로그인해주세요.';
                } else {
                    // 6자리 인증 코드 생성
                    $verification_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

                    // 세션에 저장
                    $_SESSION['reset_user_id'] = $user['id'];
                    $_SESSION['reset_email'] = $user['email'];
                    $_SESSION['reset_user_name'] = $user['name'];
                    $_SESSION['reset_verification_code'] = $verification_code;
                    $_SESSION['reset_code_time'] = time();

                    // 이메일 발송
                    $to = $user['email'];
                    $subject = '[탄생] 비밀번호 재설정 인증 코드';
                    $message = "
                    안녕하세요, {$user['name']}님.

                    비밀번호 재설정을 위한 인증 코드입니다.

                    인증 코드: {$verification_code}

                    이 코드는 5분간 유효합니다.
                    본인이 요청하지 않았다면 이 메일을 무시하세요.

                    감사합니다.
                    탄생 스마트팜
                    ";

                    $headers = "From: noreply@tansaeng.com\r\n";
                    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

                    if (mail($to, $subject, $message, $headers)) {
                        $success = '인증 코드가 이메일로 전송되었습니다. 이메일을 확인해주세요.';
                        $step = 'verify_code';
                    } else {
                        $error = '이메일 전송에 실패했습니다. 잠시 후 다시 시도해주세요.';
                        error_log('Email send failed for: ' . $user['email']);
                    }
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

// 인증 코드 확인 단계
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_code'])) {
    $input_code = trim($_POST['verification_code'] ?? '');

    if (!isset($_SESSION['reset_user_id']) || !isset($_SESSION['reset_verification_code'])) {
        $error = '세션이 만료되었습니다. 다시 시도해주세요.';
        $step = 'email';
    } elseif (empty($input_code)) {
        $error = '인증 코드를 입력해주세요.';
        $step = 'verify_code';
    } else {
        // 코드 유효시간 확인 (5분)
        $code_age = time() - ($_SESSION['reset_code_time'] ?? 0);
        if ($code_age > 300) {
            $error = '인증 코드가 만료되었습니다. 처음부터 다시 시도해주세요.';
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_user_name']);
            unset($_SESSION['reset_verification_code']);
            unset($_SESSION['reset_code_time']);
            $step = 'email';
        } elseif ($input_code === $_SESSION['reset_verification_code']) {
            // 코드 일치 - 비밀번호 재설정 단계로
            $step = 'reset';
        } else {
            $error = '인증 코드가 일치하지 않습니다. 다시 확인해주세요.';
            $step = 'verify_code';
        }
    }
}

// 인증 코드 재전송
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_code'])) {
    if (isset($_SESSION['reset_email'])) {
        try {
            $db = Database::getInstance();
            $pdo = $db->getConnection();

            $stmt = $pdo->prepare("SELECT id, email, name FROM users WHERE email = ?");
            $stmt->execute([$_SESSION['reset_email']]);
            $user = $stmt->fetch();

            if ($user) {
                // 새 인증 코드 생성
                $verification_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

                $_SESSION['reset_verification_code'] = $verification_code;
                $_SESSION['reset_code_time'] = time();

                // 이메일 발송
                $to = $user['email'];
                $subject = '[탄생] 비밀번호 재설정 인증 코드';
                $message = "
                안녕하세요, {$user['name']}님.

                비밀번호 재설정을 위한 인증 코드입니다.

                인증 코드: {$verification_code}

                이 코드는 5분간 유효합니다.
                본인이 요청하지 않았다면 이 메일을 무시하세요.

                감사합니다.
                탄생 스마트팜
                ";

                $headers = "From: noreply@tansaeng.com\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

                if (mail($to, $subject, $message, $headers)) {
                    $success = '인증 코드가 재전송되었습니다.';
                    $step = 'verify_code';
                } else {
                    $error = '이메일 전송에 실패했습니다.';
                    $step = 'verify_code';
                }
            }
        } catch (Exception $e) {
            error_log('Resend code error: ' . $e->getMessage());
            $error = '오류가 발생했습니다.';
            $step = 'verify_code';
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
            unset($_SESSION['reset_user_name']);
            unset($_SESSION['reset_verification_code']);
            unset($_SESSION['reset_code_time']);

            $_SESSION['auth_success'] = '✅ 비밀번호가 성공적으로 변경되었습니다. 새 비밀번호로 로그인해주세요.';
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
                    <h2 style="text-align: center;">🔑 비밀번호 찾기</h2>
                    <p style="text-align: center; color: #666; margin-bottom: 1.5rem;">
                        이메일로 가입하신 계정의 이메일 주소를 입력해주세요.<br>
                        <small style="color: #999;">(소셜 로그인 계정은 해당 서비스에서 비밀번호를 재설정해주세요.)</small>
                    </p>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="email">이메일 *</label>
                        <input type="email" id="email" name="email" required autofocus placeholder="example@email.com">
                    </div>

                    <button type="submit" name="verify_email" class="btn btn-primary btn-full">인증 코드 받기</button>

                    <div class="auth-links">
                        <a href="/pages/auth/login.php">로그인으로 돌아가기</a>
                    </div>
                </form>

            <?php elseif ($step === 'verify_code'): ?>
                <!-- 2단계: 인증 코드 확인 -->
                <form method="post" class="auth-form">
                    <h2 style="text-align: center;">✉️ 이메일 인증</h2>
                    <p style="text-align: center; color: #666; margin-bottom: 0.5rem;">
                        <strong><?= htmlspecialchars($_SESSION['reset_user_name'] ?? '') ?></strong>님, 안녕하세요!
                    </p>
                    <p style="text-align: center; color: #666; margin-bottom: 1.5rem;">
                        <strong><?= htmlspecialchars($_SESSION['reset_email'] ?? '') ?></strong><br>
                        위 이메일로 전송된 6자리 인증 코드를 입력해주세요.<br>
                        <small style="color: #4CAF50; font-weight: 600;">인증 코드는 5분간 유효합니다</small>
                    </p>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="verification_code">인증 코드 * (6자리)</label>
                        <input type="text" id="verification_code" name="verification_code"
                               pattern="[0-9]{6}" maxlength="6"
                               placeholder="123456" required autofocus
                               style="text-align: center; font-size: 1.5rem; letter-spacing: 0.5rem; font-weight: bold;">
                    </div>

                    <button type="submit" name="verify_code" class="btn btn-primary btn-full">인증 확인</button>

                    <div class="auth-links" style="margin-top: 1rem;">
                        <button type="submit" name="resend_code" class="btn-link">인증 코드 재전송</button>
                        <span style="color: #ccc; margin: 0 0.5rem;">|</span>
                        <a href="/pages/auth/forgot_password.php">처음부터 다시</a>
                    </div>
                </form>

            <?php elseif ($step === 'reset'): ?>
                <!-- 3단계: 새 비밀번호 설정 -->
                <form method="post" class="auth-form">
                    <h2 style="text-align: center;">🔐 새 비밀번호 설정</h2>
                    <p style="text-align: center; color: #666; margin-bottom: 1.5rem;">
                        <strong><?= htmlspecialchars($_SESSION['reset_user_name'] ?? '') ?></strong>님의 새로운 비밀번호를 입력해주세요.<br>
                        <small style="color: #999;">비밀번호는 8자 이상, 영문과 숫자를 조합해주세요.</small>
                    </p>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="new_password">새 비밀번호 * <span style="font-weight: normal; font-size: 0.85rem; color: #666;">(최소 8자)</span></label>
                        <input type="password" id="new_password" name="new_password" required autofocus placeholder="영문, 숫자 조합 8자 이상">
                    </div>

                    <div class="form-group">
                        <label for="new_password_confirm">새 비밀번호 확인 *</label>
                        <input type="password" id="new_password_confirm" name="new_password_confirm" required placeholder="비밀번호를 다시 입력하세요">
                    </div>

                    <button type="submit" name="reset_password" class="btn btn-primary btn-full">✓ 비밀번호 변경 완료</button>

                    <div class="auth-links" style="margin-top: 1rem;">
                        <a href="/pages/auth/forgot_password.php">처음부터 다시</a>
                    </div>
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

.btn-link {
    background: none;
    border: none;
    color: #4CAF50;
    cursor: pointer;
    font-size: 0.9rem;
    text-decoration: underline;
    padding: 0;
}

.btn-link:hover {
    color: #2E7D32;
}
</style>

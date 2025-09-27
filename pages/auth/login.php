<?php
session_start();

// 데이터베이스 연결을 선택적으로 처리
$currentUser = null;
$dbConnected = false;
$socialLogin = null;

try {
    require_once __DIR__ . '/../../classes/Auth.php';
    require_once __DIR__ . '/../../classes/SocialLogin.php';
    $auth = Auth::getInstance();
    $socialLogin = new SocialLogin();
    $dbConnected = true;
    
    if ($auth->isLoggedIn()) {
        header('Location: ' . ($_GET['redirect'] ?? '/'));
        exit;
    }
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
}

// 세션 메시지 처리
$authError = $_SESSION['auth_error'] ?? '';
$authSuccess = $_SESSION['auth_success'] ?? '';
unset($_SESSION['auth_error'], $_SESSION['auth_success']);

$error = '';
$success = '';
$redirect = $_GET['redirect'] ?? '/';

// 소셜 로그인 후 리디렉션을 위해 세션에 저장
if (!empty($redirect) && $redirect !== '/') {
    $_SESSION['redirect_after_login'] = $redirect;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = '이메일과 비밀번호를 입력해주세요.';
    } else {
        // 데이터베이스가 연결된 경우 사용자 로그인 처리
        if ($dbConnected) {
            try {
                if ($auth->login($email, $password)) {
                    // 관리자인 경우 관리자 대시보드로 리디렉션
                    $currentUser = $auth->getCurrentUser();
                    if ($currentUser && $currentUser['role'] === 'admin') {
                        header('Location: /admin/');
                    } else {
                        header('Location: ' . $redirect);
                    }
                    exit;
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        } else {
            $error = '이메일 또는 비밀번호가 올바르지 않습니다.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>로그인 - 탄생</title>
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
            
            <form method="post" class="auth-form">
                <h2>로그인</h2>
                
                <?php if ($error || $authError): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error ?: $authError) ?></div>
                <?php endif; ?>
                
                <?php if ($success || $authSuccess): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success ?: $authSuccess) ?></div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="email">이메일</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="password">비밀번호</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-full">로그인</button>
                
                <?php if (!$dbConnected): ?>
                <div class="demo-notice">
                    <h3>🔧 데모 계정</h3>
                    <p>데이터베이스 설정 완료 전까지 아래 계정으로 테스트하세요:</p>
                    <div class="demo-credentials">
                        <p><strong>이메일:</strong> admin@tangsaeng.com</p>
                        <p><strong>비밀번호:</strong> admin2025</p>
                    </div>
                    <button type="button" onclick="fillAdminCredentials()" class="btn btn-outline btn-sm">
                        자동 입력
                    </button>
                </div>
                <?php endif; ?>
                
                <div class="auth-links">
                    <a href="/pages/auth/register.php">회원가입</a>
                    <a href="/pages/auth/password_reset.php">비밀번호 찾기</a>
                </div>
            </form>

            <!-- Firebase Social Login Section -->
            <div class="social-login-section">
                <div class="divider">
                    <span>또는</span>
                </div>
                
                <div class="social-buttons">
                    <button type="button" class="social-btn google-btn firebase-google-login" data-redirect="<?= htmlspecialchars($redirect) ?>">
                        <span class="social-icon">G</span>
                        Google로 로그인
                    </button>
                </div>
            </div>

            <?php if ($dbConnected && $socialLogin): ?>
            <!-- Traditional Social Login Section -->
            <div class="social-login-section">
                <div class="divider">
                    <span>기타 소셜 로그인</span>
                </div>
                
                <div class="social-buttons">
                    <a href="<?= $socialLogin->getKakaoLoginUrl() ?>" class="social-btn kakao-btn">
                        <span class="social-icon">K</span>
                        카카오로 로그인
                    </a>
                    
                    <a href="<?= $socialLogin->getNaverLoginUrl() ?>" class="social-btn naver-btn">
                        <span class="social-icon">N</span>
                        네이버로 로그인
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="auth-footer">
                <a href="/">홈으로 돌아가기</a>
            </div>
        </div>
    </div>
    
    <script src="/assets/js/auth.js"></script>
    <script type="module" src="/assets/js/firebase-auth.js"></script>
    <script>
        function fillAdminCredentials() {
            document.getElementById('email').value = 'admin@tangsaeng.com';
            document.getElementById('password').value = 'admin2025';
        }
    </script>
</body>
</html>

<style>
.demo-notice {
    background: #E3F2FD;
    border: 2px solid #2196F3;
    padding: 1.5rem;
    border-radius: 8px;
    margin: 1.5rem 0;
    text-align: center;
}

.demo-notice h3 {
    color: #1976D2;
    margin-bottom: 1rem;
}

.demo-notice p {
    color: #555;
    margin-bottom: 1rem;
}

.demo-credentials {
    background: white;
    padding: 1rem;
    border-radius: 6px;
    margin: 1rem 0;
    text-align: left;
    display: inline-block;
}

.demo-credentials p {
    margin: 0.3rem 0;
    font-family: monospace;
    color: #333;
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
}

/* Social Login Styles */
.social-login-section {
    margin-top: 2rem;
}

.divider {
    text-align: center;
    margin: 1.5rem 0;
    position: relative;
}

.divider::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 1px;
    background: #e9ecef;
}

.divider span {
    background: white;
    padding: 0 1rem;
    color: #6c757d;
    font-size: 0.875rem;
}

.social-buttons {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.social-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s ease;
    font-size: 0.9rem;
}

.social-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.social-icon {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 12px;
    color: white;
}

.google-btn {
    color: #333;
    border-color: #dadce0;
}

.google-btn:hover {
    background: #f8f9fa;
}

.google-btn .social-icon {
    background: #4285f4;
}

.kakao-btn {
    color: #3c1e1e;
    background: #fee500;
    border-color: #fee500;
}

.kakao-btn:hover {
    background: #fdd835;
}

.kakao-btn .social-icon {
    background: #3c1e1e;
}

.naver-btn {
    color: white;
    background: #03c75a;
    border-color: #03c75a;
}

.naver-btn:hover {
    background: #02b351;
}

.naver-btn .social-icon {
    background: white;
    color: #03c75a;
}
</style>
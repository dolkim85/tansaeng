<?php
// 소셜 로그인 콜백 처리
$currentUser = null;
$dbConnected = false;
$socialLogin = null;

try {
    require_once __DIR__ . '/../../classes/Auth.php';
    require_once __DIR__ . '/../../classes/SocialLogin.php';
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();
    $db = Database::getInstance();
    $socialLogin = new SocialLogin();
    $dbConnected = true;

    // 이미 로그인된 경우 홈으로 리다이렉트
    if ($currentUser) {
        header('Location: /');
        exit;
    }
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
}

$error = '';
$success = '';

// 소셜 로그인 콜백 처리
if (isset($_GET['provider']) && isset($_GET['code'])) {
    $provider = $_GET['provider'];
    $code = $_GET['code'];
    
    try {
        // 실제 구현에서는 각 소셜 로그인 API를 호출하여 사용자 정보를 가져옵니다.
        // 여기서는 데모 목적으로 기본 처리만 구현합니다.
        $success = $provider . ' 로그인이 성공적으로 연동되었습니다.';
        
        // 실제 구현시에는 여기서 사용자 정보를 데이터베이스에 저장하고 세션을 생성합니다.
        
    } catch (Exception $e) {
        $error = '소셜 로그인 처리 중 오류가 발생했습니다: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>회원가입 - 탄생</title>
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
            
            <div class="social-auth">
                <h2>간편 회원가입</h2>
                <p class="social-description">소셜 계정으로 빠르고 안전하게 가입하세요</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                
                <div class="social-buttons">
                    <button type="button" class="social-btn google firebase-google-login" data-redirect="/">
                        <span class="social-icon">G</span>
                        <span>구글로 시작하기</span>
                    </button>

                    <?php if ($dbConnected && $socialLogin): ?>
                    <button type="button" onclick="handleKakaoRegister(event)" class="social-btn kakao">
                        <span class="social-icon">K</span>
                        <span>카카오톡으로 시작하기</span>
                    </button>

                    <button type="button" onclick="handleNaverRegister(event)" class="social-btn naver">
                        <span class="social-icon">N</span>
                        <span>네이버로 시작하기</span>
                    </button>
                    <?php else: ?>
                    <button onclick="socialLogin('kakao')" class="social-btn kakao">
                        <span class="social-icon">K</span>
                        <span>카카오톡으로 시작하기</span>
                    </button>

                    <button onclick="socialLogin('naver')" class="social-btn naver">
                        <span class="social-icon">N</span>
                        <span>네이버로 시작하기</span>
                    </button>
                    <?php endif; ?>
                </div>
                
                <div class="social-notice">
                    <h3>🔒 안전한 소셜 로그인</h3>
                    <ul>
                        <li>별도의 회원가입 절차 없이 즉시 이용 가능</li>
                        <li>개인정보는 해당 소셜 플랫폼에서 안전하게 관리</li>
                        <li>탄생에서는 최소한의 정보만 수집합니다</li>
                        <li>언제든지 연동을 해제할 수 있습니다</li>
                    </ul>
                </div>
                
                <div class="terms-notice">
                    <p>
                        <input type="checkbox" id="agreeTerms" required>
                        <label for="agreeTerms">
                            <a href="#" onclick="showTerms('service')">이용약관</a> 및 
                            <a href="#" onclick="showTerms('privacy')">개인정보처리방침</a>에 동의합니다
                        </label>
                    </p>
                </div>
                
                <div class="auth-links">
                    <a href="/pages/auth/login.php">이미 계정이 있으신가요? 로그인</a>
                </div>
            </div>
            
            <div class="auth-footer">
                <a href="/">홈으로 돌아가기</a>
            </div>
        </div>
    </div>

    <!-- Terms Modal -->
    <div id="termsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle"></h3>
                <span class="close-modal" onclick="closeTermsModal()">&times;</span>
            </div>
            <div class="modal-body" id="modalContent">
            </div>
            <div class="modal-footer">
                <button onclick="closeTermsModal()" class="btn btn-primary">확인</button>
            </div>
        </div>
    </div>
    
    <script type="module" src="/assets/js/firebase-auth.js"></script>
    <script>
        // 카카오 회원가입 URL
        const kakaoRegisterUrl = '<?= $socialLogin ? $socialLogin->getKakaoLoginUrl() : "#" ?>';
        const naverRegisterUrl = '<?= $socialLogin ? $socialLogin->getNaverLoginUrl() : "#" ?>';

        // 카카오 회원가입 처리
        function handleKakaoRegister(event) {
            console.log('카카오 버튼 클릭됨');
            event.preventDefault();
            const agreeTerms = document.getElementById('agreeTerms');
            console.log('약관 동의 상태:', agreeTerms.checked);
            console.log('카카오 URL:', kakaoRegisterUrl);

            if (!agreeTerms.checked) {
                alert('이용약관 및 개인정보처리방침에 동의해주세요.');
                return;
            }

            if (!kakaoRegisterUrl || kakaoRegisterUrl === '#') {
                alert('카카오 로그인 설정이 완료되지 않았습니다.');
                return;
            }

            console.log('카카오 페이지로 이동 중...');
            window.location.href = kakaoRegisterUrl;
        }

        // 네이버 회원가입 처리
        function handleNaverRegister(event) {
            event.preventDefault();
            const agreeTerms = document.getElementById('agreeTerms');
            if (!agreeTerms.checked) {
                alert('이용약관 및 개인정보처리방침에 동의해주세요.');
                return;
            }
            window.location.href = naverRegisterUrl;
        }

        // Firebase 구글 로그인 버튼에 약관 동의 체크 추가
        document.addEventListener('DOMContentLoaded', function() {
            const firebaseGoogleButton = document.querySelector('.firebase-google-login');
            if (firebaseGoogleButton) {
                firebaseGoogleButton.addEventListener('click', function(e) {
                    const agreeTerms = document.getElementById('agreeTerms');
                    if (!agreeTerms.checked) {
                        e.preventDefault();
                        e.stopPropagation();
                        alert('이용약관 및 개인정보처리방침에 동의해주세요.');
                        return false;
                    }
                }, true); // capture phase에서 처리
            }
        });
        
        function socialLogin(provider) {
            // 약관 동의 확인
            const agreeTerms = document.getElementById('agreeTerms');
            if (!agreeTerms.checked) {
                alert('이용약관 및 개인정보처리방침에 동의해주세요.');
                return;
            }
            
            // 실제 소셜 로그인 URL로 리다이렉트
            let authUrl = '';
            
            switch (provider) {
                case 'kakao':
                    // 카카오톡 OAuth URL (실제 구현시 클라이언트 ID 필요)
                    authUrl = 'https://kauth.kakao.com/oauth/authorize?client_id=YOUR_KAKAO_CLIENT_ID&redirect_uri=' + 
                              encodeURIComponent(window.location.origin + '/pages/auth/register.php?provider=kakao') + 
                              '&response_type=code';
                    break;
                    
                case 'google':
                    // 구글 OAuth URL (실제 구현시 클라이언트 ID 필요)
                    authUrl = 'https://accounts.google.com/oauth2/v2/auth?client_id=YOUR_GOOGLE_CLIENT_ID&redirect_uri=' + 
                              encodeURIComponent(window.location.origin + '/pages/auth/register.php?provider=google') + 
                              '&scope=email profile&response_type=code';
                    break;
                    
                case 'naver':
                    // 네이버 OAuth URL (실제 구현시 클라이언트 ID 필요)
                    authUrl = 'https://nid.naver.com/oauth2.0/authorize?client_id=YOUR_NAVER_CLIENT_ID&redirect_uri=' + 
                              encodeURIComponent(window.location.origin + '/pages/auth/register.php?provider=naver') + 
                              '&response_type=code&state=RANDOM_STATE';
                    break;
            }
            
            // 데모 목적으로 알림만 표시 (실제 구현시에는 주석 해제)
            alert(provider + ' 로그인 기능은 실제 API 키 설정 후 이용 가능합니다.\n현재는 데모 버전입니다.');
            
            // 실제 구현시에는 아래 주석을 해제하세요
            // window.location.href = authUrl;
        }
        
        function showTerms(type) {
            const modal = document.getElementById('termsModal');
            const title = document.getElementById('modalTitle');
            const content = document.getElementById('modalContent');
            
            if (type === 'service') {
                title.textContent = '서비스 이용약관';
                content.innerHTML = `
                    <h4>제1조 (목적)</h4>
                    <p>본 약관은 탄생(이하 "회사")에서 제공하는 스마트팜 관련 서비스의 이용조건 및 절차에 관한 기본적인 사항을 규정함을 목적으로 합니다.</p>
                    
                    <h4>제2조 (정의)</h4>
                    <p>1. "서비스"라 함은 회사가 제공하는 모든 서비스를 의미합니다.</p>
                    <p>2. "회원"이라 함은 본 약관에 따라 회사와 이용계약을 체결하고 서비스를 이용하는 자를 의미합니다.</p>
                    
                    <h4>제3조 (약관의 게시와 개정)</h4>
                    <p>회사는 본 약관을 회원이 쉽게 알 수 있도록 서비스 초기화면에 게시합니다.</p>
                `;
            } else {
                title.textContent = '개인정보처리방침';
                content.innerHTML = `
                    <h4>1. 개인정보의 수집 및 이용목적</h4>
                    <p>회사는 다음의 목적을 위하여 개인정보를 처리합니다.</p>
                    <ul>
                        <li>서비스 제공 및 계약의 이행</li>
                        <li>회원 관리 및 고객상담</li>
                        <li>마케팅 및 광고에의 활용</li>
                    </ul>
                    
                    <h4>2. 수집하는 개인정보 항목</h4>
                    <p>회사는 소셜 로그인을 통해 최소한의 정보만 수집합니다:</p>
                    <ul>
                        <li>필수항목: 이름, 이메일 주소</li>
                        <li>선택항목: 프로필 이미지</li>
                    </ul>
                `;
            }
            
            modal.style.display = 'block';
        }
        
        function closeTermsModal() {
            document.getElementById('termsModal').style.display = 'none';
        }
        
        // 모달 외부 클릭시 닫기
        window.onclick = function(event) {
            const modal = document.getElementById('termsModal');
            if (event.target === modal) {
                closeTermsModal();
            }
        }
    </script>
</body>
</html>

<style>
.social-auth {
    max-width: 400px;
    margin: 0 auto;
}

.social-auth h2 {
    color: #2E7D32;
    text-align: center;
    margin-bottom: 0.5rem;
}

.social-description {
    text-align: center;
    color: #666;
    margin-bottom: 2rem;
}

.social-buttons {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-bottom: 2rem;
}

.social-btn {
    display: flex;
    align-items: center;
    padding: 1rem 1.5rem;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    background: white;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    color: #333;
    font-size: 1rem;
    font-weight: 500;
}

.social-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.social-btn.kakao {
    border-color: #FEE500;
    background: #FEE500;
    color: #3C1E1E;
}

.social-btn.kakao:hover {
    background: #FFEB3B;
}

.social-btn.google {
    border-color: #4285F4;
}

.social-btn.google:hover {
    background: #4285F4;
    color: white;
}

.social-btn.naver {
    border-color: #03C75A;
    background: #03C75A;
    color: white;
}

.social-btn.naver:hover {
    background: #02B351;
}

.social-icon {
    width: 20px;
    height: 20px;
    margin-right: 0.8rem;
}

.social-notice {
    background: #E8F5E8;
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
}

.social-notice h3 {
    color: #2E7D32;
    margin-bottom: 1rem;
    font-size: 1rem;
}

.social-notice ul {
    margin: 0;
    padding-left: 1.2rem;
    color: #555;
}

.social-notice li {
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.terms-notice {
    text-align: center;
    margin-bottom: 2rem;
    padding: 1rem;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    background: #f8f9fa;
}

.terms-notice input[type="checkbox"] {
    margin-right: 0.5rem;
}

.terms-notice a {
    color: #4CAF50;
    text-decoration: underline;
}

.terms-notice a:hover {
    color: #2E7D32;
}

.auth-links {
    text-align: center;
    margin-top: 2rem;
}

.auth-links a {
    color: #4CAF50;
    text-decoration: none;
}

.auth-links a:hover {
    text-decoration: underline;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background: white;
    margin: 5% auto;
    padding: 0;
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    overflow: hidden;
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8f9fa;
}

.modal-header h3 {
    margin: 0;
    color: #2E7D32;
}

.close-modal {
    font-size: 2rem;
    cursor: pointer;
    color: #999;
}

.close-modal:hover {
    color: #333;
}

.modal-body {
    padding: 1.5rem;
    max-height: 50vh;
    overflow-y: auto;
}

.modal-body h4 {
    color: #2E7D32;
    margin: 1.5rem 0 0.5rem 0;
}

.modal-body h4:first-child {
    margin-top: 0;
}

.modal-body p, .modal-body li {
    line-height: 1.6;
    color: #333;
}

.modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid #e0e0e0;
    text-align: right;
    background: #f8f9fa;
}

@media (max-width: 768px) {
    .modal-content {
        width: 95%;
        margin: 10% auto;
    }
    
    .social-btn {
        font-size: 0.9rem;
        padding: 0.8rem 1rem;
    }
}
</style>
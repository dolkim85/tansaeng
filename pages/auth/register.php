<?php
session_start();

require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/SocialLogin.php';

$error = '';
$success = '';

// 이메일 회원가입 처리 (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_register'])) {
    header('Content-Type: application/json');

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    // 선택 항목
    $optional_info_agree = isset($_POST['optional_info_agree']);
    $age_range = $optional_info_agree ? (trim($_POST['age_range'] ?? '')) : null;
    $gender = $optional_info_agree ? (trim($_POST['gender'] ?? '')) : null;

    $terms_agree = isset($_POST['terms_agree']);
    $privacy_agree = isset($_POST['privacy_agree']);

    // 유효성 검사
    if (empty($username) || empty($email) || empty($password) || empty($phone)) {
        echo json_encode(['success' => false, 'message' => '필수 항목을 모두 입력해주세요.']);
        exit;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => '올바른 이메일 주소를 입력해주세요.']);
        exit;
    } elseif (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => '비밀번호는 최소 6자 이상이어야 합니다.']);
        exit;
    } elseif ($password !== $password_confirm) {
        echo json_encode(['success' => false, 'message' => '비밀번호가 일치하지 않습니다.']);
        exit;
    } elseif (!$terms_agree || !$privacy_agree) {
        echo json_encode(['success' => false, 'message' => '필수 약관에 동의해주세요.']);
        exit;
    } else {
        try {
            $db = Database::getInstance();
            $pdo = $db->getConnection();

            // 이메일 중복 확인
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => '이미 사용 중인 이메일입니다.']);
                exit;
            }

            // 회원가입 처리
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, name, phone, address, age_range, gender, role, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'user', CURRENT_TIMESTAMP)
            ");

            $result = $stmt->execute([
                $username,
                $email,
                $hashedPassword,
                $username,
                $phone,
                $address ?: null,
                $age_range,
                $gender
            ]);

            if ($result) {
                $userId = $pdo->lastInsertId();
                error_log('New user registered: ID=' . $userId . ', Email=' . $email);
                echo json_encode(['success' => true, 'message' => '회원가입이 완료되었습니다. 로그인해주세요.']);
            } else {
                throw new Exception('Failed to insert user data');
            }
            exit;
        } catch (PDOException $e) {
            error_log('Database error during registration: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => '데이터베이스 오류: ' . $e->getMessage()]);
            exit;
        } catch (Exception $e) {
            error_log('Register error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => '회원가입 처리 중 오류가 발생했습니다: ' . $e->getMessage()]);
            exit;
        }
    }
}

$socialLogin = new SocialLogin();
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
                <h2 style="text-align: center;">간편 회원가입</h2>
                <p class="social-description">소셜 계정으로 빠르고 안전하게 가입하세요</p>

                <div class="social-buttons">
                    <a href="#" data-url="<?= $socialLogin->getGoogleLoginUrl() ?>" class="social-btn google-btn" onclick="return checkTermsAndRedirect(event, this)">
                        <span class="social-icon">G</span>
                        <span>Google로 시작하기</span>
                    </a>

                    <a href="#" data-url="<?= $socialLogin->getKakaoLoginUrl() ?>" class="social-btn kakao-btn" onclick="return checkTermsAndRedirect(event, this)">
                        <span class="social-icon">K</span>
                        <span>카카오로 시작하기</span>
                    </a>

                    <a href="#" data-url="<?= $socialLogin->getNaverLoginUrl() ?>" class="social-btn naver-btn" onclick="return checkTermsAndRedirect(event, this)">
                        <span class="social-icon">N</span>
                        <span>네이버로 시작하기</span>
                    </a>
                </div>

                <div class="divider">
                    <span>또는</span>
                </div>

                <div style="text-align: center;">
                    <button type="button" class="btn btn-secondary btn-full" onclick="openEmailRegisterModal()">
                        이메일로 회원가입
                    </button>
                </div>

                <!-- 약관 동의 섹션 -->
                <div class="register-terms-section">
                    <div class="form-check" style="border-bottom: 1px solid #dee2e6; padding-bottom: 0.75rem; margin-bottom: 0.75rem;">
                        <input type="checkbox" id="register_all_agree">
                        <label for="register_all_agree" style="font-weight: 600;">
                            전체 동의
                        </label>
                    </div>

                    <div class="form-check">
                        <input type="checkbox" id="register_terms_agree" class="register-required-agree" required>
                        <label for="register_terms_agree">
                            <a href="#" onclick="openTermsModal(event)">이용약관</a>에 동의합니다 (필수)
                        </label>
                    </div>

                    <div class="form-check">
                        <input type="checkbox" id="register_privacy_agree" class="register-required-agree" required>
                        <label for="register_privacy_agree">
                            <a href="#" onclick="openPrivacyModal(event)">개인정보처리방침</a>에 동의합니다 (필수)
                        </label>
                    </div>

                    <div class="form-check">
                        <input type="checkbox" id="register_optional_agree">
                        <label for="register_optional_agree">
                            선택 정보 수집에 동의합니다 (선택)
                        </label>
                    </div>
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

    <!-- 이메일 회원가입 모달 -->
    <div id="emailRegisterModal" class="modal">
        <div class="modal-content" style="max-width: 600px; max-height: 90vh; overflow-y: auto;">
            <div class="modal-header">
                <h3>이메일 회원가입</h3>
                <span class="close-modal" onclick="closeEmailRegisterModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="emailRegisterForm">
                    <input type="hidden" name="ajax_register" value="1">

                    <div id="registerAlert" class="alert" style="display: none;"></div>

                    <div class="form-group">
                        <label for="username">이름 *</label>
                        <input type="text" id="username" name="username" required>
                    </div>

                    <div class="form-group">
                        <label for="email">이메일 *</label>
                        <input type="email" id="email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="password">비밀번호 * (최소 6자)</label>
                        <input type="password" id="password" name="password" required>
                    </div>

                    <div class="form-group">
                        <label for="password_confirm">비밀번호 확인 *</label>
                        <input type="password" id="password_confirm" name="password_confirm" required>
                    </div>

                    <div class="form-group">
                        <label for="phone">휴대전화번호 *</label>
                        <input type="tel" id="phone" name="phone" placeholder="010-1234-5678" required>
                    </div>

                    <div class="form-group">
                        <label for="address">주소</label>
                        <input type="text" id="address" name="address" placeholder="서울시 강남구...">
                    </div>

                    <hr style="margin: 1.5rem 0; border: none; border-top: 1px solid #e9ecef;">

                    <div class="optional-info-section">
                        <h3 style="font-size: 1rem; margin-bottom: 1rem; color: #495057;">개인정보 수집 항목</h3>

                        <div class="info-notice">
                            <p><strong>필수 항목:</strong> 이름, 이메일주소, 휴대전화번호</p>
                            <p><strong>선택 항목:</strong> 연령대, 성별</p>
                        </div>

                        <div class="form-check">
                            <input type="checkbox" id="optional_info_agree" name="optional_info_agree">
                            <label for="optional_info_agree">
                                선택 정보 수집에 동의합니다 (서비스 개선 및 맞춤형 콘텐츠 제공)
                            </label>
                        </div>

                        <div id="optional-fields" style="display: none; margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 6px;">
                            <div class="form-group">
                                <label for="age_range">연령대</label>
                                <select id="age_range" name="age_range">
                                    <option value="">선택하지 않음</option>
                                    <option value="10대">10대</option>
                                    <option value="20대">20대</option>
                                    <option value="30대">30대</option>
                                    <option value="40대">40대</option>
                                    <option value="50대">50대</option>
                                    <option value="60대 이상">60대 이상</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="gender">성별</label>
                                <select id="gender" name="gender">
                                    <option value="">선택하지 않음</option>
                                    <option value="남성">남성</option>
                                    <option value="여성">여성</option>
                                    <option value="기타">기타</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="terms-section">
                        <div class="form-check" style="border-bottom: 1px solid #dee2e6; padding-bottom: 0.75rem; margin-bottom: 0.75rem;">
                            <input type="checkbox" id="all_agree">
                            <label for="all_agree" style="font-weight: 600;">
                                전체 동의
                            </label>
                        </div>

                        <div class="form-check">
                            <input type="checkbox" id="terms_agree" name="terms_agree" class="required-agree" required>
                            <label for="terms_agree">
                                <a href="#" onclick="openTermsModal(event)">이용약관</a>에 동의합니다 (필수)
                            </label>
                        </div>

                        <div class="form-check">
                            <input type="checkbox" id="privacy_agree" name="privacy_agree" class="required-agree" required>
                            <label for="privacy_agree">
                                <a href="#" onclick="openPrivacyModal(event)">개인정보처리방침</a>에 동의합니다 (필수)
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-full">가입하기</button>
                </form>
            </div>
        </div>
    </div>

    <!-- 개인정보처리방침 모달 -->
    <div id="privacyModal" class="modal">
        <div class="modal-content" style="max-width: 800px; max-height: 90vh; overflow-y: auto;">
            <div class="modal-header">
                <h3>개인정보처리방침</h3>
                <span class="close-modal" onclick="closePrivacyModal()">&times;</span>
            </div>
            <div class="modal-body" id="privacyContent">
                <p>로딩 중...</p>
            </div>
            <div class="modal-footer">
                <button onclick="closePrivacyModal()" class="btn btn-primary">확인</button>
            </div>
        </div>
    </div>

    <!-- 이용약관 모달 -->
    <div id="termsModal" class="modal">
        <div class="modal-content" style="max-width: 800px; max-height: 90vh; overflow-y: auto;">
            <div class="modal-header">
                <h3>이용약관</h3>
                <span class="close-modal" onclick="closeTermsModal()">&times;</span>
            </div>
            <div class="modal-body" id="termsContent">
                <p>로딩 중...</p>
            </div>
            <div class="modal-footer">
                <button onclick="closeTermsModal()" class="btn btn-primary">확인</button>
            </div>
        </div>
    </div>

    <script>
        // 소셜 로그인 전 약관 동의 확인
        function checkTermsAndRedirect(event, element) {
            event.preventDefault();

            const termsAgree = document.getElementById('register_terms_agree').checked;
            const privacyAgree = document.getElementById('register_privacy_agree').checked;

            if (!termsAgree || !privacyAgree) {
                alert('필수 약관에 동의해주세요.');
                return false;
            }

            // 약관에 동의했으면 소셜 로그인 URL로 이동
            window.location.href = element.getAttribute('data-url');
            return false;
        }

        // 회원가입 페이지 전체 동의 체크박스
        document.getElementById('register_all_agree').addEventListener('change', function() {
            const requiredCheckboxes = document.querySelectorAll('.register-required-agree');
            const optionalCheckbox = document.getElementById('register_optional_agree');

            if (this.checked) {
                requiredCheckboxes.forEach(cb => cb.checked = true);
                optionalCheckbox.checked = true;
            } else {
                requiredCheckboxes.forEach(cb => cb.checked = false);
                optionalCheckbox.checked = false;
            }
        });

        // 개별 체크박스 변경 시 전체 동의 상태 업데이트
        document.querySelectorAll('.register-required-agree, #register_optional_agree').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allAgree = document.getElementById('register_all_agree');
                const requiredCheckboxes = document.querySelectorAll('.register-required-agree');
                const optionalCheckbox = document.getElementById('register_optional_agree');

                const allRequired = Array.from(requiredCheckboxes).every(cb => cb.checked);
                const optionalChecked = optionalCheckbox.checked;

                allAgree.checked = allRequired && optionalChecked;
            });
        });

        // 이메일 회원가입 모달
        function openEmailRegisterModal() {
            const termsAgree = document.getElementById('register_terms_agree').checked;
            const privacyAgree = document.getElementById('register_privacy_agree').checked;

            if (!termsAgree || !privacyAgree) {
                alert('필수 약관에 동의해주세요.');
                return false;
            }

            document.getElementById('emailRegisterModal').style.display = 'block';
        }

        function closeEmailRegisterModal() {
            document.getElementById('emailRegisterModal').style.display = 'none';
            document.getElementById('emailRegisterForm').reset();
            document.getElementById('registerAlert').style.display = 'none';
            document.getElementById('optional-fields').style.display = 'none';
        }

        // 개인정보처리방침 모달
        function openPrivacyModal(e) {
            if (e) e.preventDefault();
            const modal = document.getElementById('privacyModal');
            modal.style.display = 'block';

            // AJAX로 내용 로드
            fetch('/pages/privacy.php')
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const content = doc.querySelector('.privacy-container');
                    if (content) {
                        // 뒤로가기 링크 제거
                        const backLink = content.querySelector('.back-link');
                        if (backLink) backLink.remove();
                        document.getElementById('privacyContent').innerHTML = content.innerHTML;
                    }
                });
        }

        function closePrivacyModal() {
            document.getElementById('privacyModal').style.display = 'none';
        }

        // 이용약관 모달
        function openTermsModal(e) {
            if (e) e.preventDefault();
            const modal = document.getElementById('termsModal');
            modal.style.display = 'block';

            // AJAX로 내용 로드
            fetch('/pages/terms.php')
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const content = doc.querySelector('.terms-container');
                    if (content) {
                        // 뒤로가기 링크 제거
                        const backLink = content.querySelector('.back-link');
                        if (backLink) backLink.remove();
                        document.getElementById('termsContent').innerHTML = content.innerHTML;
                    }
                });
        }

        function closeTermsModal() {
            document.getElementById('termsModal').style.display = 'none';
        }

        // 모달 외부 클릭시 닫기
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        // 이메일 회원가입 폼 제출
        document.getElementById('emailRegisterForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const alertDiv = document.getElementById('registerAlert');

            fetch('/pages/auth/register.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                alertDiv.style.display = 'block';
                if (data.success) {
                    alertDiv.className = 'alert alert-success';
                    alertDiv.textContent = data.message;
                    setTimeout(() => {
                        closeEmailRegisterModal();
                        window.location.href = '/pages/auth/login.php';
                    }, 1500);
                } else {
                    alertDiv.className = 'alert alert-error';
                    alertDiv.textContent = data.message;
                }
            })
            .catch(error => {
                alertDiv.style.display = 'block';
                alertDiv.className = 'alert alert-error';
                alertDiv.textContent = '오류가 발생했습니다.';
            });
        });

        // 선택 정보 수집 동의 토글
        document.getElementById('optional_info_agree').addEventListener('change', function() {
            const optionalFields = document.getElementById('optional-fields');
            if (this.checked) {
                optionalFields.style.display = 'block';
            } else {
                optionalFields.style.display = 'none';
                document.getElementById('age_range').value = '';
                document.getElementById('gender').value = '';
            }
        });

        // 전체 동의 체크박스
        document.getElementById('all_agree').addEventListener('change', function() {
            const requiredCheckboxes = document.querySelectorAll('.required-agree');
            const optionalCheckbox = document.getElementById('optional_info_agree');

            if (this.checked) {
                requiredCheckboxes.forEach(cb => cb.checked = true);
                optionalCheckbox.checked = true;
                document.getElementById('optional-fields').style.display = 'block';
            } else {
                requiredCheckboxes.forEach(cb => cb.checked = false);
                optionalCheckbox.checked = false;
                document.getElementById('optional-fields').style.display = 'none';
                document.getElementById('age_range').value = '';
                document.getElementById('gender').value = '';
            }
        });

        // 개별 체크박스 변경 시 전체 동의 상태 업데이트
        document.querySelectorAll('.required-agree, #optional_info_agree').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allAgree = document.getElementById('all_agree');
                const requiredCheckboxes = document.querySelectorAll('.required-agree');
                const optionalCheckbox = document.getElementById('optional_info_agree');

                const allRequired = Array.from(requiredCheckboxes).every(cb => cb.checked);
                const optionalChecked = optionalCheckbox.checked;

                allAgree.checked = allRequired && optionalChecked;
            });
        });
    </script>
</body>
</html>

<style>
/* 모달 스타일 */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: #fefefe;
    margin: 2% auto;
    padding: 0;
    border: 1px solid #888;
    width: 90%;
    max-width: 600px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.modal-header {
    padding: 1.5rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 8px 8px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.5rem;
}

.close-modal {
    color: white;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    line-height: 1;
}

.close-modal:hover,
.close-modal:focus {
    color: #ddd;
}

.modal-body {
    padding: 2rem;
}

.modal-footer {
    padding: 1rem 1.5rem;
    background: #f8f9fa;
    border-radius: 0 0 8px 8px;
    text-align: right;
    border-top: 1px solid #dee2e6;
}

/* 소셜 버튼 스타일 */
.social-buttons {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin: 1.5rem 0;
}

.social-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    padding: 0.875rem 1.5rem;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    font-size: 1rem;
}

.social-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.social-icon {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
    color: white;
}

.google-btn {
    color: #333;
    background: white;
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
    position: relative;
}

.social-description {
    text-align: center;
    color: #666;
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

.terms-notice {
    margin-top: 1.5rem;
    text-align: center;
}

.terms-notice a {
    color: #007bff;
    text-decoration: none;
}

.terms-notice a:hover {
    text-decoration: underline;
}

.register-terms-section {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 6px;
    margin: 1.5rem 0;
}

.info-notice {
    background: #e7f3ff;
    padding: 0.75rem 1rem;
    border-radius: 6px;
    margin-bottom: 1rem;
    font-size: 0.9rem;
}

.info-notice p {
    margin: 0.25rem 0;
    color: #495057;
}

.terms-section {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 6px;
    margin: 1.5rem 0;
}

.form-check {
    margin: 0.75rem 0;
}

.form-check input[type="checkbox"] {
    margin-right: 0.5rem;
    width: auto;
}

.form-check label {
    display: inline;
    margin: 0;
    font-weight: normal;
}

.form-check label a {
    color: #007bff;
    text-decoration: none;
}

.form-check label a:hover {
    text-decoration: underline;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

/* 알림 메시지 */
.alert {
    padding: 0.75rem 1rem;
    margin-bottom: 1rem;
    border-radius: 6px;
    font-size: 0.9rem;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Privacy/Terms 모달 내용 스타일 */
.modal-body h1 {
    color: #2c3e50;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #3498db;
    font-size: 1.8rem;
}

.modal-body h2 {
    color: #34495e;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
    font-size: 1.2rem;
}

.modal-body h3 {
    color: #555;
    margin-top: 1rem;
    margin-bottom: 0.5rem;
    font-size: 1rem;
}

.modal-body p {
    line-height: 1.6;
    color: #555;
    margin-bottom: 0.75rem;
}

.modal-body ul {
    margin: 0.75rem 0;
    padding-left: 1.5rem;
}

.modal-body li {
    line-height: 1.6;
    color: #555;
    margin-bottom: 0.5rem;
}

.modal-body .info-box,
.modal-body .required-items,
.modal-body .optional-items {
    padding: 1rem;
    border-radius: 6px;
    margin: 1rem 0;
}

.modal-body .info-box {
    background: #e7f3ff;
    border-left: 4px solid #3498db;
}

.modal-body .required-items {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
}

.modal-body .optional-items {
    background: #d1ecf1;
    border-left: 4px solid #17a2b8;
}
</style>

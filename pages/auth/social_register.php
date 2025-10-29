<?php
session_start();

// 소셜 로그인 임시 데이터 확인
if (!isset($_SESSION['social_temp_user'])) {
    // 소셜 로그인 정보가 없으면 로그인 페이지로
    header('Location: /pages/auth/login.php');
    exit;
}

$socialData = $_SESSION['social_temp_user'];
$provider = $socialData['provider'];
$providerName = [
    'google' => '구글',
    'kakao' => '카카오',
    'naver' => '네이버'
][$provider] ?? $provider;

require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';

$error = '';
$success = '';

// 폼 제출 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    // 선택 항목
    $optional_info_agree = isset($_POST['optional_info_agree']);
    $age_range = $optional_info_agree ? (trim($_POST['age_range'] ?? '')) : null;
    $gender = $optional_info_agree ? (trim($_POST['gender'] ?? '')) : null;

    $termsAgree = isset($_POST['terms_agree']);
    $privacyAgree = isset($_POST['privacy_agree']);

    // 유효성 검사
    if (empty($phone)) {
        $error = '휴대전화번호를 입력해주세요.';
    } elseif (!$termsAgree || !$privacyAgree) {
        $error = '필수 약관에 동의해주세요.';
    } else {
        try {
            $db = Database::getInstance();
            $pdo = $db->getConnection();

            // 소셜 로그인 사용자는 비밀번호가 필요 없으므로 랜덤 해시 생성
            $randomPassword = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

            // 사용자 등록
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, name, phone, address, age_range, gender,
                                   oauth_provider, oauth_id, avatar_url, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");

            $stmt->execute([
                $socialData['username'],
                $socialData['email'],
                $randomPassword,
                $socialData['username'],
                $phone,
                $address ?: null,
                $age_range,
                $gender,
                $provider,
                $socialData['social_id'],
                $socialData['avatar_url'] ?? null
            ]);

            $userId = $pdo->lastInsertId();

            // 세션에 사용자 정보 저장
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_email'] = $socialData['email'];
            $_SESSION['user_name'] = $socialData['username'];
            $_SESSION['user_role'] = 'user';

            // 임시 데이터 제거
            unset($_SESSION['social_temp_user']);

            $_SESSION['auth_success'] = $providerName . ' 계정으로 회원가입이 완료되었습니다.';

            // 리디렉션
            $redirectUrl = $_SESSION['redirect_after_login'] ?? '/';
            unset($_SESSION['redirect_after_login']);

            header('Location: ' . $redirectUrl);
            exit;

        } catch (Exception $e) {
            error_log('Social register error: ' . $e->getMessage());
            $error = '회원가입 처리 중 오류가 발생했습니다.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $providerName ?> 회원가입 - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/auth.css">
    <script src="//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>탄생</h1>
                <p>스마트팜 배지 제조회사</p>
            </div>

            <form method="post" class="auth-form">
                <h2><?= $providerName ?> 회원가입</h2>
                <p class="social-welcome">
                    <?= $providerName ?>로 로그인하셨습니다.<br>
                    추가 정보를 입력해주세요.
                </p>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <!-- 소셜 로그인 정보 표시 -->
                <div class="social-info">
                    <?php if (!empty($socialData['avatar_url'])): ?>
                        <img src="<?= htmlspecialchars($socialData['avatar_url']) ?>" alt="프로필" class="profile-image">
                    <?php endif; ?>
                    <div class="info-item">
                        <label>이름</label>
                        <div class="readonly-value"><?= htmlspecialchars($socialData['username']) ?></div>
                    </div>
                    <div class="info-item">
                        <label>이메일</label>
                        <div class="readonly-value"><?= htmlspecialchars($socialData['email']) ?></div>
                    </div>
                </div>

                <!-- 추가 정보 입력 -->
                <div class="form-group">
                    <label for="phone">휴대전화번호 *</label>
                    <input type="tel" id="phone" name="phone" required>
                </div>

                <div class="form-group">
                    <label for="postcode">우편번호</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="text" id="postcode" name="postcode" readonly style="flex: 1; background: #f8f9fa;">
                        <button type="button" onclick="execDaumPostcode()" class="btn btn-outline" style="white-space: nowrap;">주소 찾기</button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="address">주소</label>
                    <input type="text" id="address" name="address" readonly style="background: #f8f9fa;">
                </div>

                <div class="form-group">
                    <label for="detailAddress">상세주소</label>
                    <input type="text" id="detailAddress" name="detailAddress">
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

                <!-- 약관 동의 -->
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
                            <a href="/pages/terms.php" target="_blank">이용약관</a>에 동의합니다 (필수)
                        </label>
                    </div>

                    <div class="form-check">
                        <input type="checkbox" id="privacy_agree" name="privacy_agree" class="required-agree" required>
                        <label for="privacy_agree">
                            <a href="/pages/privacy.php" target="_blank">개인정보처리방침</a>에 동의합니다 (필수)
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-full">가입 완료</button>

                <div class="auth-links">
                    <a href="/pages/auth/login.php">로그인으로 돌아가기</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Daum 우편번호 API
        function execDaumPostcode() {
            new daum.Postcode({
                oncomplete: function(data) {
                    // 도로명 주소 또는 지번 주소 선택
                    var addr = data.userSelectedType === 'R' ? data.roadAddress : data.jibunAddress;

                    // 우편번호와 주소 입력
                    document.getElementById('postcode').value = data.zonecode;
                    document.getElementById('address').value = addr;

                    // 상세주소 입력 칸으로 포커스 이동
                    document.getElementById('detailAddress').focus();
                }
            }).open();
        }

        // 폼 제출 시 주소 합치기
        document.querySelector('.auth-form').addEventListener('submit', function(e) {
            const postcode = document.getElementById('postcode').value;
            const address = document.getElementById('address').value;
            const detailAddress = document.getElementById('detailAddress').value;

            let fullAddress = '';
            if (postcode) fullAddress += '[' + postcode + '] ';
            if (address) fullAddress += address;
            if (detailAddress) fullAddress += ' ' + detailAddress;

            document.getElementById('address').value = fullAddress.trim();
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

.social-welcome {
    text-align: center;
    margin: 1rem 0 1.5rem;
    color: #666;
    line-height: 1.5;
}

.social-info {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    text-align: center;
}

.profile-image {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    margin-bottom: 1rem;
    border: 3px solid #fff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.info-item {
    margin: 0.75rem 0;
    text-align: left;
}

.info-item label {
    display: block;
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.25rem;
    font-size: 0.875rem;
}

.readonly-value {
    padding: 0.5rem;
    background: white;
    border-radius: 4px;
    color: #212529;
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
</style>

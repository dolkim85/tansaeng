<?php
// Initialize session and auth before any output
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
require_once $base_path . '/classes/User.php';

$auth = Auth::getInstance();
$auth->requireAdmin();

// Get user ID
$userId = intval($_GET['id'] ?? 0);
if (!$userId) {
    header('Location: /admin/users/index.php');
    exit;
}

$userModel = new User();
$userData = $userModel->getUserById($userId);

if (!$userData) {
    header('Location: /admin/users/index.php?error=' . urlencode('사용자를 찾을 수 없습니다.'));
    exit;
}

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>사용자 수정 - 탄생 관리자</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        .edit-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .edit-header {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .edit-header h1 {
            font-size: 1.8rem;
            color: #333;
            margin-bottom: 10px;
        }

        .edit-card {
            background: white;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        .form-label .required {
            color: #dc3545;
            margin-left: 4px;
        }

        .form-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.95rem;
            transition: border-color 0.2s;
        }

        .form-input:focus {
            outline: none;
            border-color: #007bff;
        }

        .form-input:disabled {
            background: #f5f5f5;
            cursor: not-allowed;
        }

        .form-select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.95rem;
            background: white;
            cursor: pointer;
        }

        .form-select:focus {
            outline: none;
            border-color: #007bff;
        }

        .form-checkbox {
            width: auto;
            margin-right: 8px;
        }

        .form-help {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
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

        .info-badge {
            display: inline-block;
            padding: 5px 12px;
            background: #e7f3ff;
            color: #0066cc;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
    </style>
</head>
<body class="admin-body">
    <?php include '../includes/admin_header.php'; ?>

    <div class="admin-container">
        <?php include '../includes/admin_sidebar.php'; ?>

        <main class="admin-main">
            <div class="admin-content">
                <div class="edit-container">
                    <div class="edit-header">
                        <h1>✏️ 사용자 정보 수정</h1>
                    </div>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form id="editUserForm" method="POST">
                        <!-- 기본 정보 -->
                        <div class="edit-card">
                            <h2 class="card-title">기본 정보</h2>

                            <div class="form-group">
                                <label class="form-label">사용자 ID</label>
                                <input type="text" class="form-input" value="<?= $userData['id'] ?>" disabled>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    이름 <span class="required">*</span>
                                </label>
                                <input type="text" class="form-input" name="name" value="<?= htmlspecialchars($userData['name']) ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    이메일 <span class="required">*</span>
                                </label>
                                <input type="email" class="form-input" name="email" value="<?= htmlspecialchars($userData['email']) ?>" required>
                                <?php if (!empty($userData['oauth_provider'])): ?>
                                <div class="form-help">
                                    <span class="info-badge"><?= htmlspecialchars($userData['oauth_provider']) ?> 계정</span>
                                    OAuth 계정은 이메일 변경 시 주의하세요.
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label class="form-label">연락처</label>
                                <input type="tel" class="form-input" name="phone" value="<?= htmlspecialchars($userData['phone'] ?? '') ?>" placeholder="010-0000-0000">
                            </div>

                            <div class="form-group">
                                <label class="form-label">연령대</label>
                                <select class="form-select" name="age_range">
                                    <option value="">선택 안함</option>
                                    <option value="10대" <?= ($userData['age_range'] ?? '') === '10대' ? 'selected' : '' ?>>10대</option>
                                    <option value="20대" <?= ($userData['age_range'] ?? '') === '20대' ? 'selected' : '' ?>>20대</option>
                                    <option value="30대" <?= ($userData['age_range'] ?? '') === '30대' ? 'selected' : '' ?>>30대</option>
                                    <option value="40대" <?= ($userData['age_range'] ?? '') === '40대' ? 'selected' : '' ?>>40대</option>
                                    <option value="50대" <?= ($userData['age_range'] ?? '') === '50대' ? 'selected' : '' ?>>50대</option>
                                    <option value="60대 이상" <?= ($userData['age_range'] ?? '') === '60대 이상' ? 'selected' : '' ?>>60대 이상</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">성별</label>
                                <select class="form-select" name="gender">
                                    <option value="">선택 안함</option>
                                    <option value="남성" <?= ($userData['gender'] ?? '') === '남성' ? 'selected' : '' ?>>남성</option>
                                    <option value="여성" <?= ($userData['gender'] ?? '') === '여성' ? 'selected' : '' ?>>여성</option>
                                    <option value="기타" <?= ($userData['gender'] ?? '') === '기타' ? 'selected' : '' ?>>기타</option>
                                </select>
                            </div>
                        </div>

                        <!-- 권한 설정 -->
                        <div class="edit-card">
                            <h2 class="card-title">권한 설정</h2>

                            <div class="form-group">
                                <label class="form-label">
                                    사용자 레벨 <span class="required">*</span>
                                </label>
                                <select class="form-select" name="user_level" required>
                                    <option value="1" <?= $userData['user_level'] == 1 ? 'selected' : '' ?>>일반 사용자</option>
                                    <option value="2" <?= $userData['user_level'] == 2 ? 'selected' : '' ?>>식물분석 권한자</option>
                                    <option value="9" <?= $userData['user_level'] == 9 ? 'selected' : '' ?>>관리자</option>
                                </select>
                                <div class="form-help">
                                    1: 일반 사용자, 2: 식물분석 권한자, 9: 관리자
                                </div>
                            </div>

                            <div class="form-group">
                                <label>
                                    <input type="checkbox" class="form-checkbox" name="plant_analysis_permission" value="1" <?= $userData['plant_analysis_permission'] ? 'checked' : '' ?>>
                                    <span class="form-label" style="display: inline;">식물분석 권한 부여</span>
                                </label>
                                <div class="form-help">
                                    체크 시 식물 이미지 분석 기능 사용 가능
                                </div>
                            </div>

                            <div class="form-group">
                                <label>
                                    <input type="checkbox" class="form-checkbox" name="is_active" value="1" <?= $userData['is_active'] ? 'checked' : '' ?>>
                                    <span class="form-label" style="display: inline;">계정 활성화</span>
                                </label>
                                <div class="form-help">
                                    비활성화 시 로그인 불가
                                </div>
                            </div>
                        </div>

                        <!-- 버튼 그룹 -->
                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary">💾 저장</button>
                            <a href="detail.php?id=<?= $userData['id'] ?>" class="btn btn-secondary">취소</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script src="/assets/js/main.js"></script>
    <script src="/assets/js/admin.js"></script>
    <script>
        document.getElementById('editUserForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const data = {
                user_id: <?= $userId ?>,
                name: formData.get('name'),
                email: formData.get('email'),
                phone: formData.get('phone'),
                age_range: formData.get('age_range'),
                gender: formData.get('gender'),
                user_level: parseInt(formData.get('user_level')),
                plant_analysis_permission: formData.get('plant_analysis_permission') === '1',
                is_active: formData.get('is_active') === '1'
            };

            try {
                const response = await fetch('/admin/api/update_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    window.location.href = 'detail.php?id=<?= $userId ?>&success=' + encodeURIComponent('사용자 정보가 수정되었습니다.');
                } else {
                    alert('수정 실패: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('수정 중 오류가 발생했습니다.');
            }
        });
    </script>
</body>
</html>

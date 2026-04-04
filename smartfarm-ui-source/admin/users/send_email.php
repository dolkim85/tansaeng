<?php
/**
 * 관리자 > 사용자에게 이메일 발송
 */

$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
require_once $base_path . '/classes/Database.php';
require_once $base_path . '/classes/Mailer.php';

$auth = Auth::getInstance();
$auth->requireAdmin();

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();
$pdo = $db->getConnection();

$success = '';
$error = '';
$sendResults = [];

// 이메일 발송 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $recipients = $_POST['recipients'] ?? 'all';
    $selectedUsers = $_POST['selected_users'] ?? [];

    if (empty($subject)) {
        $error = '제목을 입력해주세요.';
    } elseif (empty($body)) {
        $error = '본문을 입력해주세요.';
    } else {
        try {
            $mailer = new Mailer();

            // 수신자 목록 가져오기
            if ($recipients === 'all') {
                // 전체 사용자 (관리자 제외)
                $stmt = $pdo->query("SELECT email, name FROM users WHERE user_level < 9 AND email IS NOT NULL AND email != ''");
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // 선택한 사용자
                if (empty($selectedUsers)) {
                    $error = '발송할 사용자를 선택해주세요.';
                } else {
                    $placeholders = str_repeat('?,', count($selectedUsers) - 1) . '?';
                    $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id IN ($placeholders) AND email IS NOT NULL AND email != ''");
                    $stmt->execute($selectedUsers);
                    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            }

            if (!$error && !empty($users)) {
                $successCount = 0;
                $failCount = 0;

                foreach ($users as $user) {
                    $result = $mailer->send(
                        $user['email'],
                        $subject,
                        $body,
                        $user['name']
                    );

                    if ($result) {
                        $successCount++;
                        $sendResults[] = [
                            'email' => $user['email'],
                            'name' => $user['name'],
                            'status' => 'success'
                        ];
                    } else {
                        $failCount++;
                        $sendResults[] = [
                            'email' => $user['email'],
                            'name' => $user['name'],
                            'status' => 'failed'
                        ];
                    }
                }

                $success = "총 " . count($users) . "명 중 {$successCount}명에게 이메일을 성공적으로 발송했습니다.";
                if ($failCount > 0) {
                    $error = "{$failCount}명에게는 발송에 실패했습니다.";
                }
            } elseif (!$error) {
                $error = '발송할 사용자가 없습니다.';
            }

        } catch (Exception $e) {
            error_log('Email send error: ' . $e->getMessage());
            $error = '이메일 발송 중 오류가 발생했습니다.';
        }
    }
}

// 사용자 목록 가져오기
$users = [];
try {
    $stmt = $pdo->query("
        SELECT id, email, name, user_level, created_at
        FROM users
        WHERE user_level < 9 AND email IS NOT NULL AND email != ''
        ORDER BY created_at DESC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Users fetch error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>사용자에게 이메일 발송 - 탄생 관리자</title>
    <link rel="stylesheet" href="/admin/assets/css/admin.css">
    <style>
        .email-form-container {
            max-width: 900px;
            margin: 2rem auto;
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }
        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-group textarea {
            min-height: 200px;
            font-family: inherit;
        }
        .recipient-options {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .recipient-options label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }
        .users-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 1rem;
            background: #f9f9f9;
        }
        .user-item {
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            background: white;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .user-item input[type="checkbox"] {
            cursor: pointer;
        }
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }
        .btn-primary {
            background: #4CAF50;
            color: white;
        }
        .btn-primary:hover {
            background: #45a049;
        }
        .btn-secondary {
            background: #666;
            color: white;
            margin-left: 0.5rem;
        }
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
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
        .send-results {
            margin-top: 2rem;
        }
        .result-item {
            padding: 0.5rem 1rem;
            margin-bottom: 0.5rem;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
        }
        .result-success {
            background: #d4edda;
            color: #155724;
        }
        .result-failed {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <header>
            <h1>📧 사용자에게 이메일 발송</h1>
            <div class="header-actions">
                <a href="/admin/users/index.php" class="btn btn-secondary">← 사용자 목록</a>
                <a href="/admin/index.php" class="btn btn-secondary">대시보드</a>
            </div>
        </header>

        <div class="email-form-container">
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label>📮 수신자 선택</label>
                    <div class="recipient-options">
                        <label>
                            <input type="radio" name="recipients" value="all" checked onchange="toggleUserList()">
                            전체 사용자 (<?= count($users) ?>명)
                        </label>
                        <label>
                            <input type="radio" name="recipients" value="selected" onchange="toggleUserList()">
                            선택한 사용자
                        </label>
                    </div>
                </div>

                <div class="form-group" id="users-list-container" style="display: none;">
                    <label>사용자 선택 (전체 선택: <input type="checkbox" id="select-all" onchange="toggleAllUsers()">)</label>
                    <div class="users-list">
                        <?php foreach ($users as $user): ?>
                            <div class="user-item">
                                <input type="checkbox" name="selected_users[]" value="<?= $user['id'] ?>" class="user-checkbox">
                                <strong><?= htmlspecialchars($user['name']) ?></strong>
                                (<?= htmlspecialchars($user['email']) ?>)
                                - 가입일: <?= date('Y-m-d', strtotime($user['created_at'])) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="subject">✉️ 제목 *</label>
                    <input type="text" id="subject" name="subject" required placeholder="이메일 제목을 입력하세요" value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="body">📝 본문 *</label>
                    <textarea id="body" name="body" required placeholder="이메일 본문을 입력하세요"><?= htmlspecialchars($_POST['body'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <button type="submit" name="send_email" class="btn btn-primary">📤 이메일 발송</button>
                    <a href="/admin/users/index.php" class="btn btn-secondary">취소</a>
                </div>
            </form>

            <?php if (!empty($sendResults)): ?>
                <div class="send-results">
                    <h3>📊 발송 결과</h3>
                    <?php foreach ($sendResults as $result): ?>
                        <div class="result-item <?= $result['status'] === 'success' ? 'result-success' : 'result-failed' ?>">
                            <span>
                                <strong><?= htmlspecialchars($result['name']) ?></strong>
                                (<?= htmlspecialchars($result['email']) ?>)
                            </span>
                            <span><?= $result['status'] === 'success' ? '✅ 성공' : '❌ 실패' ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleUserList() {
            const selected = document.querySelector('input[name="recipients"]:checked').value;
            const usersList = document.getElementById('users-list-container');
            usersList.style.display = selected === 'selected' ? 'block' : 'none';
        }

        function toggleAllUsers() {
            const selectAll = document.getElementById('select-all');
            const checkboxes = document.querySelectorAll('.user-checkbox');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
        }
    </script>
</body>
</html>

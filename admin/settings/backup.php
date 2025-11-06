<?php
// Initialize session and auth before any output
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
require_once $base_path . '/classes/Database.php';

$auth = Auth::getInstance();
$auth->requireAdmin();

$success = '';
$error = '';

// Handle backup actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create_db_backup') {
            // Create database backup
            $backup_dir = $base_path . '/backups/database';
            if (!is_dir($backup_dir)) {
                mkdir($backup_dir, 0755, true);
            }

            $filename = 'db_backup_' . date('Y-m-d_H-i-s') . '.sql';
            $filepath = $backup_dir . '/' . $filename;

            // Get database credentials
            require_once $base_path . '/config/database.php';
            $config = new DatabaseConfig();

            // Use mysqldump
            $host = 'localhost';
            $dbname = 'tansaeng_db';
            $username = 'root';
            $password = 'qjawns3445';

            $command = sprintf(
                'mysqldump -h %s -u %s -p%s %s > %s 2>&1',
                escapeshellarg($host),
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($dbname),
                escapeshellarg($filepath)
            );

            exec($command, $output, $return_code);

            if ($return_code === 0 && file_exists($filepath)) {
                $success = '데이터베이스 백업이 생성되었습니다: ' . $filename;
            } else {
                throw new Exception('백업 생성 실패: ' . implode("\n", $output));
            }

        } elseif ($action === 'create_files_backup') {
            // Create files backup
            $backup_dir = $base_path . '/backups/files';
            if (!is_dir($backup_dir)) {
                mkdir($backup_dir, 0755, true);
            }

            $filename = 'files_backup_' . date('Y-m-d_H-i-s') . '.tar.gz';
            $filepath = $backup_dir . '/' . $filename;

            // Backup uploads directory
            $uploads_dir = $base_path . '/uploads';
            if (is_dir($uploads_dir)) {
                $command = sprintf(
                    'tar -czf %s -C %s uploads 2>&1',
                    escapeshellarg($filepath),
                    escapeshellarg($base_path)
                );

                exec($command, $output, $return_code);

                if ($return_code === 0 && file_exists($filepath)) {
                    $success = '파일 백업이 생성되었습니다: ' . $filename;
                } else {
                    throw new Exception('백업 생성 실패');
                }
            } else {
                throw new Exception('업로드 디렉토리가 존재하지 않습니다.');
            }

        } elseif ($action === 'delete_backup') {
            $backup_file = $_POST['backup_file'] ?? '';
            $backup_type = $_POST['backup_type'] ?? '';

            if (empty($backup_file)) {
                throw new Exception('삭제할 파일을 선택해주세요.');
            }

            $backup_dir = $base_path . '/backups/' . ($backup_type === 'database' ? 'database' : 'files');
            $filepath = $backup_dir . '/' . basename($backup_file);

            if (file_exists($filepath)) {
                unlink($filepath);
                $success = '백업 파일이 삭제되었습니다.';
            } else {
                throw new Exception('파일이 존재하지 않습니다.');
            }

        } elseif ($action === 'save_settings') {
            // Save backup settings
            $pdo = Database::getInstance()->getConnection();

            $settings = [
                'backup_auto_enabled' => isset($_POST['backup_auto_enabled']) ? '1' : '0',
                'backup_retention_days' => (int)($_POST['backup_retention_days'] ?? 30),
                'backup_schedule' => $_POST['backup_schedule'] ?? 'daily',
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
            $success = '백업 설정이 저장되었습니다.';
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Load backup settings
$backup_settings = [];
try {
    $pdo = Database::getInstance()->getConnection();

    $sql = "SELECT setting_key, setting_value FROM site_settings
            WHERE setting_key IN ('backup_auto_enabled', 'backup_retention_days', 'backup_schedule')";
    $stmt = $pdo->query($sql);
    while ($row = $stmt->fetch()) {
        $backup_settings[$row['setting_key']] = $row['setting_value'];
    }

} catch (Exception $e) {
    // Continue without settings
}

function getSetting($key, $default = '') {
    global $backup_settings;
    return $backup_settings[$key] ?? $default;
}

// Get existing backups
$db_backups = [];
$file_backups = [];

$db_backup_dir = $base_path . '/backups/database';
if (is_dir($db_backup_dir)) {
    $files = scandir($db_backup_dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && strpos($file, '.sql') !== false) {
            $filepath = $db_backup_dir . '/' . $file;
            $db_backups[] = [
                'name' => $file,
                'size' => filesize($filepath),
                'date' => filemtime($filepath)
            ];
        }
    }
    usort($db_backups, function($a, $b) { return $b['date'] - $a['date']; });
}

$file_backup_dir = $base_path . '/backups/files';
if (is_dir($file_backup_dir)) {
    $files = scandir($file_backup_dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && strpos($file, '.tar.gz') !== false) {
            $filepath = $file_backup_dir . '/' . $file;
            $file_backups[] = [
                'name' => $file,
                'size' => filesize($filepath),
                'date' => filemtime($filepath)
            ];
        }
    }
    usort($file_backups, function($a, $b) { return $b['date'] - $a['date']; });
}

function formatBytes($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>백업 관리 - 탄생 관리자</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        .backup-container {
            max-width: 1200px;
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

        .action-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .action-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }

        .action-card h3 {
            font-size: 1.2rem;
            margin-bottom: 15px;
            color: #333;
        }

        .action-card p {
            color: #666;
            margin-bottom: 20px;
            font-size: 14px;
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

        .btn-success {
            background-color: #28a745;
            color: white;
        }

        .btn-danger {
            background-color: #dc3545;
            color: white;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
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

        .backup-section {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
        }

        .backup-table {
            width: 100%;
            border-collapse: collapse;
        }

        .backup-table th {
            background-color: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }

        .backup-table td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
        }

        .backup-table tr:hover {
            background-color: #f8f9fa;
        }

        .empty-message {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .settings-form {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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

        .form-check {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-check input[type="checkbox"] {
            width: auto;
        }

        @media (max-width: 768px) {
            .backup-container {
                padding: 10px;
            }

            .action-cards {
                grid-template-columns: 1fr;
            }

            .backup-table {
                font-size: 12px;
            }

            .backup-table th,
            .backup-table td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>

    <div class="admin-layout">
        <?php include '../includes/admin_sidebar.php'; ?>

        <main class="admin-main">
            <div class="backup-container">
                <div class="page-header">
                    <h1 class="page-title">백업 관리</h1>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="action-cards">
                    <div class="action-card">
                        <h3>💾 데이터베이스 백업</h3>
                        <p>모든 데이터베이스 내용을 SQL 파일로 백업합니다.</p>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="create_db_backup">
                            <button type="submit" class="btn btn-primary">지금 백업</button>
                        </form>
                    </div>

                    <div class="action-card">
                        <h3>📁 파일 백업</h3>
                        <p>업로드된 모든 파일을 압축하여 백업합니다.</p>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="create_files_backup">
                            <button type="submit" class="btn btn-success">지금 백업</button>
                        </form>
                    </div>
                </div>

                <!-- Database Backups -->
                <div class="backup-section">
                    <h2 class="section-title">데이터베이스 백업 목록</h2>
                    <?php if (empty($db_backups)): ?>
                        <div class="empty-message">생성된 데이터베이스 백업이 없습니다.</div>
                    <?php else: ?>
                        <table class="backup-table">
                            <thead>
                                <tr>
                                    <th>파일명</th>
                                    <th style="width: 120px;">크기</th>
                                    <th style="width: 180px;">생성일시</th>
                                    <th style="width: 150px;">관리</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($db_backups as $backup): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($backup['name']) ?></td>
                                        <td><?= formatBytes($backup['size']) ?></td>
                                        <td><?= date('Y-m-d H:i:s', $backup['date']) ?></td>
                                        <td>
                                            <a href="/backups/database/<?= htmlspecialchars($backup['name']) ?>"
                                               class="btn btn-sm btn-primary" download>다운로드</a>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="delete_backup">
                                                <input type="hidden" name="backup_type" value="database">
                                                <input type="hidden" name="backup_file" value="<?= htmlspecialchars($backup['name']) ?>">
                                                <button type="submit" class="btn btn-sm btn-danger"
                                                        onclick="return confirm('이 백업을 삭제하시겠습니까?')">삭제</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- File Backups -->
                <div class="backup-section">
                    <h2 class="section-title">파일 백업 목록</h2>
                    <?php if (empty($file_backups)): ?>
                        <div class="empty-message">생성된 파일 백업이 없습니다.</div>
                    <?php else: ?>
                        <table class="backup-table">
                            <thead>
                                <tr>
                                    <th>파일명</th>
                                    <th style="width: 120px;">크기</th>
                                    <th style="width: 180px;">생성일시</th>
                                    <th style="width: 150px;">관리</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($file_backups as $backup): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($backup['name']) ?></td>
                                        <td><?= formatBytes($backup['size']) ?></td>
                                        <td><?= date('Y-m-d H:i:s', $backup['date']) ?></td>
                                        <td>
                                            <a href="/backups/files/<?= htmlspecialchars($backup['name']) ?>"
                                               class="btn btn-sm btn-primary" download>다운로드</a>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="delete_backup">
                                                <input type="hidden" name="backup_type" value="files">
                                                <input type="hidden" name="backup_file" value="<?= htmlspecialchars($backup['name']) ?>">
                                                <button type="submit" class="btn btn-sm btn-danger"
                                                        onclick="return confirm('이 백업을 삭제하시겠습니까?')">삭제</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Backup Settings -->
                <div class="settings-form">
                    <h2 class="section-title">⚙️ 백업 설정</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="save_settings">

                        <div class="form-group">
                            <div class="form-check">
                                <input type="checkbox" name="backup_auto_enabled" id="backup_auto"
                                       <?= getSetting('backup_auto_enabled') === '1' ? 'checked' : '' ?>>
                                <label for="backup_auto" class="form-label" style="margin-bottom: 0;">자동 백업 활성화</label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">백업 주기</label>
                            <select name="backup_schedule" class="form-control">
                                <option value="daily" <?= getSetting('backup_schedule', 'daily') === 'daily' ? 'selected' : '' ?>>
                                    매일
                                </option>
                                <option value="weekly" <?= getSetting('backup_schedule') === 'weekly' ? 'selected' : '' ?>>
                                    매주
                                </option>
                                <option value="monthly" <?= getSetting('backup_schedule') === 'monthly' ? 'selected' : '' ?>>
                                    매월
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">백업 보관 기간 (일)</label>
                            <input type="number" name="backup_retention_days" class="form-control"
                                   value="<?= htmlspecialchars(getSetting('backup_retention_days', '30')) ?>"
                                   min="1" max="365">
                        </div>

                        <button type="submit" class="btn btn-primary">설정 저장</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

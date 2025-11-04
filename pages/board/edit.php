<?php
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';

$id = $_GET['id'] ?? 0;
$error = '';
$success = '';
$post = null;
$attachments = [];

// Get board categories
$categories = [];
try {
    $pdo = Database::getInstance()->getConnection();
    $sql = "SELECT * FROM board_categories WHERE status = 'active' ORDER BY sort_order, name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Categories will remain empty array
}

if (!$id) {
    header('Location: index.php');
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();
    $isAdmin = $currentUser && $currentUser['role'] === 'admin';

    $sql = "SELECT b.*, c.name as category_name
            FROM boards b
            JOIN board_categories c ON b.category_id = c.id
            WHERE b.id = ? AND b.status != 'deleted'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $post = $stmt->fetch();

    if (!$post) {
        header('Location: index.php');
        exit;
    }

    // Check edit permission
    if (!$currentUser || ($currentUser['id'] !== $post['user_id'] && !$isAdmin)) {
        header('Location: view.php?id=' . $id);
        exit;
    }

    // Get attachments from attached_files JSON field
    $attachments = [];
    if ($post['attached_files']) {
        $attached_files = json_decode($post['attached_files'], true);
        if (is_array($attached_files)) {
            foreach ($attached_files as $index => $file) {
                $attachments[] = [
                    'id' => $index,
                    'file_path' => $file['path'] ?? '',
                    'original_filename' => $file['name'] ?? '',
                    'file_type' => $file['type'] ?? 'application/octet-stream'
                ];
            }
        }
    }

} catch (Exception $e) {
    $error = '게시글을 불러오는데 실패했습니다.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check permission
    if ($currentUser && ($currentUser['id'] === $post['user_id'] || $isAdmin)) {
        $title = trim($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';
        $category_id = $_POST['category_id'] ?? $post['category_id'];
        $summary = trim($_POST['summary'] ?? '');
        $remove_files = $_POST['remove_files'] ?? [];

        if (empty($title)) {
            $error = '제목을 입력해주세요.';
        } elseif (empty($content)) {
            $error = '내용을 입력해주세요.';
        } elseif (empty($category_id)) {
            $error = '카테고리를 선택해주세요.';
        } else {
            try {
                $pdo->beginTransaction();

                // Get existing attachments
                $existing_attachments = [];
                if ($post['attached_files']) {
                    $existing_attachments = json_decode($post['attached_files'], true);
                    if (!is_array($existing_attachments)) {
                        $existing_attachments = [];
                    }
                }

                // Remove selected files
                if (!empty($remove_files)) {
                    foreach ($remove_files as $file_index) {
                        $file_index = (int)$file_index;
                        if (isset($existing_attachments[$file_index])) {
                            $file_path = $existing_attachments[$file_index]['path'] ?? '';
                            if ($file_path && file_exists(__DIR__ . '/../..' . $file_path)) {
                                unlink(__DIR__ . '/../..' . $file_path);
                            }
                            unset($existing_attachments[$file_index]);
                        }
                    }
                    // Re-index array
                    $existing_attachments = array_values($existing_attachments);
                }

                // Handle new file uploads
                if (!empty($_FILES['attachments']['name'][0])) {
                    $upload_dir = __DIR__ . '/../../uploads/board/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    foreach ($_FILES['attachments']['name'] as $key => $filename) {
                        if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                            $original_filename = $filename;
                            $file_extension = pathinfo($filename, PATHINFO_EXTENSION);
                            $new_filename = uniqid() . '.' . $file_extension;
                            $file_path = $upload_dir . $new_filename;

                            if (move_uploaded_file($_FILES['attachments']['tmp_name'][$key], $file_path)) {
                                $existing_attachments[] = [
                                    'path' => '/uploads/board/' . $new_filename,
                                    'name' => $original_filename,
                                    'type' => $_FILES['attachments']['type'][$key],
                                    'size' => $_FILES['attachments']['size'][$key]
                                ];
                            }
                        }
                    }
                }

                // Update post
                $attached_files_json = !empty($existing_attachments) ? json_encode($existing_attachments) : null;

                $sql = "UPDATE boards SET
                        title = ?,
                        content = ?,
                        category_id = ?,
                        summary = ?,
                        attached_files = ?,
                        updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$title, $content, $category_id, $summary, $attached_files_json, $id]);

                $pdo->commit();
                header("Location: view.php?id=$id");
                exit;

            } catch (Exception $e) {
                $pdo->rollback();
                $error = '게시글 수정에 실패했습니다: ' . $e->getMessage();
            }
        }
    } else {
        $error = '권한이 없습니다.';
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>게시글 수정 - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        .edit-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .edit-header {
            margin-bottom: 30px;
        }

        .edit-title {
            font-size: 2rem;
            margin: 0;
        }

        .edit-form {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }

        .form-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-input:focus {
            border-color: #007bff;
            outline: none;
        }

        .content-editor {
            min-height: 300px;
            resize: vertical;
        }

        .existing-files {
            margin-bottom: 20px;
        }

        .existing-files h4 {
            margin-bottom: 10px;
            color: #333;
        }

        .file-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 5px;
        }

        .file-preview {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
        }

        .file-info {
            flex: 1;
            font-size: 14px;
            color: #666;
        }

        .file-remove-checkbox {
            margin-left: auto;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-primary {
            background-color: #007bff;
            color: white;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-outline {
            background-color: white;
            color: #007bff;
            border: 1px solid #007bff;
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-top: 30px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .alert-danger {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .admin-notice {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .toggle-emoji {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 8px 12px;
            cursor: pointer;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .toggle-emoji:hover {
            background: #e9ecef;
        }

        .emoji-panel {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 10px;
        }

        .emoji-category {
            margin-bottom: 10px;
        }

        .emoji-category h4 {
            margin: 0 0 5px 0;
            font-size: 14px;
            color: #666;
        }

        .emoji-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(40px, 1fr));
            gap: 5px;
        }

        .emoji-btn {
            background: none;
            border: 1px solid transparent;
            border-radius: 4px;
            padding: 5px;
            cursor: pointer;
            font-size: 20px;
            transition: all 0.2s;
        }

        .emoji-btn:hover {
            background: #e9ecef;
            border-color: #dee2e6;
        }

        .file-upload {
            border: 2px dashed #ddd;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s;
        }

        .file-upload:hover {
            border-color: #007bff;
            background: #f0f8ff;
        }

        .file-upload.dragover {
            border-color: #007bff;
            background: #e3f2fd;
        }

        .file-list {
            margin-top: 10px;
        }

        .new-file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 10px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 5px;
        }

        .new-file-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .file-remove {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 3px;
            padding: 2px 8px;
            cursor: pointer;
            font-size: 12px;
        }

        .file-remove:hover {
            background: #c82333;
        }

        @media (max-width: 768px) {
            .edit-container {
                padding: 0 15px;
            }

            .edit-form {
                padding: 15px;
            }

            .edit-title {
                font-size: 1.5rem;
            }

            .emoji-grid {
                grid-template-columns: repeat(auto-fill, minmax(35px, 1fr));
            }

            .emoji-btn {
                font-size: 18px;
                padding: 3px;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main class="edit-container">
        <div class="edit-header">
            <h1 class="edit-title">게시글 수정</h1>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($isAdmin && $currentUser['id'] !== $post['user_id']): ?>
            <div class="admin-notice">
                관리자 권한으로 수정 중입니다.
            </div>
        <?php endif; ?>

        <!-- Edit form -->
        <form class="edit-form" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label">카테고리 *</label>
                <select name="category_id" class="form-input" required>
                    <option value="">카테고리를 선택하세요</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>"
                                <?= $post['category_id'] == $category['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">제목 *</label>
                <input type="text" name="title" class="form-input" required
                       value="<?= htmlspecialchars($post['title']) ?>">
            </div>

            <div class="form-group">
                <label class="form-label">요약 (선택)</label>
                <input type="text" name="summary" class="form-input"
                       value="<?= htmlspecialchars($post['summary'] ?? '') ?>"
                       placeholder="게시글 요약을 입력하세요">
            </div>

            <div class="form-group">
                <label class="form-label">내용 *</label>

                <div class="toggle-emoji" onclick="toggleEmoji()">
                    😊 이모티콘 패널 열기/닫기
                </div>

                <div id="emoji-panel" class="emoji-panel" style="display: none;">
                    <div class="emoji-category">
                        <h4>표정</h4>
                        <div class="emoji-grid">
                            <button type="button" class="emoji-btn" onclick="insertEmoji('😊')">😊</button>
                            <button type="button" class="emoji-btn" onclick="insertEmoji('😂')">😂</button>
                            <button type="button" class="emoji-btn" onclick="insertEmoji('😍')">😍</button>
                            <button type="button" class="emoji-btn" onclick="insertEmoji('🥰')">🥰</button>
                            <button type="button" class="emoji-btn" onclick="insertEmoji('😎')">😎</button>
                            <button type="button" class="emoji-btn" onclick="insertEmoji('🤔')">🤔</button>
                            <button type="button" class="emoji-btn" onclick="insertEmoji('😢')">😢</button>
                            <button type="button" class="emoji-btn" onclick="insertEmoji('😭')">😭</button>
                        </div>
                    </div>

                    <div class="emoji-category">
                        <h4>식물 & 자연</h4>
                        <div class="emoji-grid">
                            <button type="button" class="emoji-btn" onclick="insertEmoji('🌱')">🌱</button>
                            <button type="button" class="emoji-btn" onclick="insertEmoji('🌿')">🌿</button>
                            <button type="button" class="emoji-btn" onclick="insertEmoji('🍃')">🍃</button>
                            <button type="button" class="emoji-btn" onclick="insertEmoji('🌳')">🌳</button>
                            <button type="button" class="emoji-btn" onclick="insertEmoji('🌻')">🌻</button>
                            <button type="button" class="emoji-btn" onclick="insertEmoji('🌹')">🌹</button>
                            <button type="button" class="emoji-btn" onclick="insertEmoji('🌺')">🌺</button>
                            <button type="button" class="emoji-btn" onclick="insertEmoji('🌸')">🌸</button>
                        </div>
                    </div>

                    <div class="emoji-category">
                        <h4>음식</h4>
                        <div class="emoji-grid">
                            <button type="button" class="emoji-btn" onclick="insertEmoji('🥬')">🥬</button>
                            <button type="button" class="emoji-btn" onclick="insertEmoji('🥒')">🥒</button>
                            <button type="button" class="emoji-btn" onclick="insertEmoji('🍅')">🍅</button>
                            <button type="button" class="emoji-btn" onclick="insertEmoji('🥕')">🥕</button>
                            <button type="button" class="emoji-btn" onclick="insertEmoji('🌽')">🌽</button>
                            <button type="button" class="emoji-btn" onclick="insertEmoji('🍓')">🍓</button>
                            <button type="button" class="emoji-btn" onclick="insertEmoji('🥝')">🥝</button>
                            <button type="button" class="emoji-btn" onclick="insertEmoji('🍇')">🍇</button>
                        </div>
                    </div>

                    <div class="emoji-category">
                        <h4>기타</h4>
                        <div class="emoji-grid">
                            <button type="button" class="emoji-btn" onclick="insertEmoji('👍')">👍</button>
                            <button type="button" class="emoji-btn" onclick="insertEmoji('👏')">👏</button>
                            <button type="button" class="emoji-btn" onclick="insertEmoji('❤️')">❤️</button>
                            <button type="button" class="emoji-btn" onclick="insertEmoji('💚')">💚</button>
                            <button type="button" class="emoji-btn" onclick="insertEmoji('⭐')">⭐</button>
                            <button type="button" class="emoji-btn" onclick="insertEmoji('🎉')">🎉</button>
                            <button type="button" class="emoji-btn" onclick="insertEmoji('🔥')">🔥</button>
                            <button type="button" class="emoji-btn" onclick="insertEmoji('💯')">💯</button>
                        </div>
                    </div>
                </div>

                <textarea name="content" class="form-input content-editor" required><?= htmlspecialchars($post['content']) ?></textarea>
            </div>

            <?php if (!empty($attachments)): ?>
                <div class="existing-files">
                    <h4>기존 첨부파일</h4>
                    <?php foreach ($attachments as $attachment): ?>
                        <div class="file-item">
                            <?php if (strpos($attachment['file_type'], 'image/') === 0): ?>
                                <img src="<?= $attachment['file_path'] ?>" alt="" class="file-preview">
                            <?php else: ?>
                                <div class="file-preview" style="background: #333; color: white; display: flex; align-items: center; justify-content: center;">📄</div>
                            <?php endif; ?>
                            <div class="file-info">
                                <?= htmlspecialchars($attachment['original_filename']) ?>
                            </div>
                            <div class="file-remove-checkbox">
                                <label>
                                    <input type="checkbox" name="remove_files[]" value="<?= $attachment['id'] ?>">
                                    삭제
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label">새 첨부파일</label>
                <div class="file-upload" id="fileUpload">
                    <input type="file" name="attachments[]" multiple accept="image/*,video/*"
                           id="fileInput" style="display: none;">
                    <div class="upload-text">
                        <p>파일을 드래그하여 업로드하거나 <strong>클릭</strong>하여 선택하세요</p>
                        <small style="color: #666;">이미지 및 동영상 파일만 업로드 가능합니다 (최대 10MB)</small>
                    </div>
                </div>
                <div class="file-list" id="fileList"></div>
            </div>

            <div class="form-actions">
                <a href="view.php?id=<?= $id ?>" class="btn btn-outline">취소</a>
                <button type="submit" class="btn btn-primary">수정하기</button>
            </div>
        </form>
    </main>

    <?php include '../../includes/footer.php'; ?>
    <script src="/assets/js/main.js"></script>
    <script>
        // Emoji panel toggle
        function toggleEmoji() {
            const panel = document.getElementById('emoji-panel');
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        }

        // Insert emoji at cursor position
        function insertEmoji(emoji) {
            const textarea = document.querySelector('textarea[name="content"]');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const value = textarea.value;

            textarea.value = value.substring(0, start) + emoji + value.substring(end);
            textarea.selectionStart = textarea.selectionEnd = start + emoji.length;
            textarea.focus();
        }

        // File upload handling
        const fileUpload = document.getElementById('fileUpload');
        const fileInput = document.getElementById('fileInput');
        const fileList = document.getElementById('fileList');
        let selectedFiles = [];

        fileUpload.addEventListener('click', () => fileInput.click());

        fileUpload.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileUpload.classList.add('dragover');
        });

        fileUpload.addEventListener('dragleave', () => {
            fileUpload.classList.remove('dragover');
        });

        fileUpload.addEventListener('drop', (e) => {
            e.preventDefault();
            fileUpload.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });

        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });

        function handleFiles(files) {
            for (let file of files) {
                if (file.size > 10 * 1024 * 1024) {
                    alert(`${file.name} 파일이 10MB를 초과합니다.`);
                    continue;
                }

                if (!file.type.startsWith('image/') && !file.type.startsWith('video/')) {
                    alert(`${file.name} 파일은 이미지 또는 동영상 파일이 아닙니다.`);
                    continue;
                }

                selectedFiles.push(file);
                displayFile(file);
            }
            updateFileInput();
        }

        function displayFile(file) {
            const fileItem = document.createElement('div');
            fileItem.className = 'new-file-item';

            const fileInfo = document.createElement('div');
            fileInfo.className = 'new-file-info';

            const icon = file.type.startsWith('image/') ? '🖼️' : '🎬';
            fileInfo.innerHTML = `<span>${icon}</span><span>${file.name} (${formatFileSize(file.size)})</span>`;

            const removeBtn = document.createElement('button');
            removeBtn.className = 'file-remove';
            removeBtn.textContent = '삭제';
            removeBtn.type = 'button';
            removeBtn.onclick = () => removeFile(file, fileItem);

            fileItem.appendChild(fileInfo);
            fileItem.appendChild(removeBtn);
            fileList.appendChild(fileItem);
        }

        function removeFile(file, element) {
            selectedFiles = selectedFiles.filter(f => f !== file);
            element.remove();
            updateFileInput();
        }

        function updateFileInput() {
            const dt = new DataTransfer();
            selectedFiles.forEach(file => dt.items.add(file));
            fileInput.files = dt.files;
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    </script>
</body>
</html>

<?php
// Initialize session and auth before any output
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
$auth = Auth::getInstance();

require_once $base_path . '/classes/Database.php';

$error = '';
$success = '';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $category_id = $_POST['category_id'] ?? 1;
    $summary = trim($_POST['summary'] ?? '');
    $currentUser = $auth->getCurrentUser();
    
    if (empty($title)) {
        $error = '제목을 입력해주세요.';
    } elseif (empty($content)) {
        $error = '내용을 입력해주세요.';
    } elseif (!$currentUser) {
        $error = '로그인이 필요합니다.';
    } elseif (empty($category_id)) {
        $error = '카테고리를 선택해주세요.';
    } else {
        try {
            $pdo = Database::getInstance()->getConnection();
            
            $pdo->beginTransaction();
            
            // Handle file uploads first
            $attached_files = [];
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
                            $attached_files[] = [
                                'name' => $original_filename,
                                'path' => '/uploads/board/' . $new_filename,
                                'size' => $_FILES['attachments']['size'][$key],
                                'type' => $_FILES['attachments']['type'][$key]
                            ];
                        }
                    }
                }
            }

            $sql = "INSERT INTO boards (category_id, user_id, title, content, summary, attached_files)
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $category_id,
                $currentUser['id'],
                $title,
                $content,
                $summary ?: substr(strip_tags($content), 0, 200),
                !empty($attached_files) ? json_encode($attached_files) : null
            ]);
            
            $post_id = $pdo->lastInsertId();
            
            // File upload handled above
            
            $pdo->commit();
            header("Location: view.php?id=$post_id");
            exit;
            
        } catch (Exception $e) {
            $pdo->rollback();
            $error = '게시글 등록에 실패했습니다.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>게시글 작성 - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        .write-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .write-header {
            margin-bottom: 30px;
        }
        
        .write-title {
            font-size: 2rem;
            margin: 0;
        }
        
        .write-form {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .content-editor {
            min-height: 300px;
            resize: vertical;
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
        }
        
        .file-upload.dragover {
            border-color: #007bff;
            background: #e3f2fd;
        }
        
        .file-list {
            margin-top: 10px;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 10px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 5px;
        }
        
        .file-info {
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
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-top: 30px;
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
        
        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .toggle-emoji {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 8px 12px;
            cursor: pointer;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <main class="write-container">
        <div class="write-header">
            <h1 class="write-title">게시글 작성</h1>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <form class="write-form" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label">카테고리 *</label>
                <select name="category_id" class="form-input" style="width: 200px;" required>
                    <option value="">카테고리를 선택하세요</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>" <?= ($_POST['category_id'] ?? '') == $category['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <?php if ($currentUser = $auth->getCurrentUser()): ?>
                <div class="form-group">
                    <label class="form-label">작성자</label>
                    <input type="text" class="form-input" value="<?= htmlspecialchars($currentUser['name']) ?>" readonly>
                </div>
            <?php else: ?>
                <div class="alert alert-danger">
                    게시글 작성을 위해 <a href="/pages/auth/login.php">로그인</a>해주세요.
                </div>
                <script>window.location.href = '/pages/auth/login.php';</script>
            <?php endif; ?>
            
            <div class="form-group">
                <label class="form-label">제목 *</label>
                <input type="text" name="title" class="form-input" required
                       value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">요약 (선택사항)</label>
                <input type="text" name="summary" class="form-input"
                       value="<?= htmlspecialchars($_POST['summary'] ?? '') ?>"
                       placeholder="게시글의 간단한 요약을 입력하세요 (비워두면 자동 생성)">
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
                
                <textarea name="content" class="form-input content-editor" required 
                          placeholder="내용을 입력하세요..."><?= htmlspecialchars($_POST['content'] ?? '') ?></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">첨부파일</label>
                <div class="file-upload" id="fileUpload">
                    <input type="file" name="attachments[]" multiple accept="image/*,video/*" 
                           id="fileInput" style="display: none;">
                    <div class="upload-text">
                        <p>파일을 드래그하여 업로드하거나 <strong>클릭</strong>하여 선택하세요</p>
                        <small>이미지 및 동영상 파일만 업로드 가능합니다 (최대 10MB)</small>
                    </div>
                </div>
                <div class="file-list" id="fileList"></div>
            </div>
            
            <div class="form-actions">
                <a href="index.php" class="btn btn-secondary">목록으로</a>
                <button type="submit" class="btn btn-primary">등록하기</button>
            </div>
        </form>
    </main>
    
    <?php include '../../includes/footer.php'; ?>
    <script src="/assets/js/main.js"></script>
    <script>
        function toggleEmoji() {
            const panel = document.getElementById('emoji-panel');
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        }
        
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
            fileItem.className = 'file-item';
            
            const fileInfo = document.createElement('div');
            fileInfo.className = 'file-info';
            
            const icon = file.type.startsWith('image/') ? '🖼️' : '🎬';
            fileInfo.innerHTML = `<span>${icon}</span><span>${file.name} (${formatFileSize(file.size)})</span>`;
            
            const removeBtn = document.createElement('button');
            removeBtn.className = 'file-remove';
            removeBtn.textContent = '삭제';
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
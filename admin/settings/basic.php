<?php
session_start();

require_once '../../config/database.php';
require_once '../../config/app.php';
require_once '../../classes/Database.php';
require_once '../../classes/Admin.php';

if (!is_admin_logged_in()) {
    redirect('../login.php');
}

$success = '';
$error = '';

if ($_POST) {
    // 기본 설정 저장 로직
    $site_name = $_POST['site_name'] ?? '';
    $site_description = $_POST['site_description'] ?? '';
    $site_keywords = $_POST['site_keywords'] ?? '';
    $admin_email = $_POST['admin_email'] ?? '';
    
    // 설정 저장 (실제 구현시 설정 테이블 필요)
    $success = '기본 설정이 저장되었습니다.';
}

// 현재 설정 불러오기 (기본값 사용)
$settings = [
    'site_name' => APP_NAME,
    'site_description' => '스마트팜 관리 시스템',
    'site_keywords' => '스마트팜, 농업, IoT, 센서',
    'admin_email' => 'admin@tansaeng.com'
];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>기본 설정 - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <nav class="navbar">
        <h1><?= APP_NAME ?></h1>
        <ul class="nav-menu">
            <li><a href="../dashboard.php">대시보드</a></li>
            <li><a href="../crud/plants.php">식물 관리</a></li>
            <li><a href="../crud/sensors.php">센서 데이터</a></li>
            <li><a href="../crud/images.php">이미지 관리</a></li>
            <li><a href="basic.php" class="active">기본 설정</a></li>
        </ul>
        <div class="user-info">
            <span>환영합니다, <?= htmlspecialchars($_SESSION['admin_name']) ?>님</span>
            <a href="../logout.php">로그아웃</a>
        </div>
    </nav>

    <div class="admin-layout">
        <aside class="sidebar">
            <ul class="sidebar-menu">
                <li>
                    <a href="../dashboard.php">
                        <i>📊</i> 대시보드
                    </a>
                </li>
                <li>
                    <a href="../crud/plants.php">
                        <i>🌱</i> 식물 관리
                    </a>
                </li>
                <li>
                    <a href="../crud/sensors.php">
                        <i>📡</i> 센서 데이터
                    </a>
                </li>
                <li>
                    <a href="../crud/images.php">
                        <i>🖼️</i> 이미지 관리
                    </a>
                </li>
                <li class="menu-section">시스템 관리</li>
                <li>
                    <a href="basic.php" class="active">
                        <i>⚙️</i> 기본 설정
                    </a>
                </li>
                <li>
                    <a href="company.php">
                        <i>🏢</i> 회사 소개 관리
                    </a>
                </li>
                <li>
                    <a href="media.php">
                        <i>📁</i> 미디어 관리
                    </a>
                </li>
                <li>
                    <a href="footer.php">
                        <i>📄</i> 푸터 관리
                    </a>
                </li>
                <li>
                    <a href="support.php">
                        <i>🎧</i> 고객지원 관리
                    </a>
                </li>
                <li>
                    <a href="../logout.php">
                        <i>🚪</i> 로그아웃
                    </a>
                </li>
            </ul>
        </aside>
        
        <main >
            <div class="container">
                <div class="header">
                    <h2>기본 설정</h2>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?= $error ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        사이트 기본 설정
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="site_name">사이트 이름</label>
                                    <input type="text" id="site_name" name="site_name" 
                                           value="<?= htmlspecialchars($settings['site_name']) ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="admin_email">관리자 이메일</label>
                                    <input type="email" id="admin_email" name="admin_email" 
                                           value="<?= htmlspecialchars($settings['admin_email']) ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group full-width">
                                <label for="site_description">사이트 설명</label>
                                <textarea id="site_description" name="site_description" 
                                          rows="3"><?= htmlspecialchars($settings['site_description']) ?></textarea>
                            </div>
                            
                            <div class="form-group full-width">
                                <label for="site_keywords">SEO 키워드 (쉼표로 구분)</label>
                                <input type="text" id="site_keywords" name="site_keywords" 
                                       value="<?= htmlspecialchars($settings['site_keywords']) ?>">
                            </div>
                            
                            <button type="submit" class="btn">설정 저장</button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
<?php
/**
 * 관리자 계정 생성 스크립트
 * 사용법: https://www.tansaeng.com/create_admin.php
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// 보안을 위해 실행 후 자동 삭제
$deleteAfterRun = true;

try {
    $pdo = DatabaseConfig::getConnection();

    // 관리자 계정 정보
    $adminData = [
        'email' => 'korea_tansaeng@naver.com',
        'password' => password_hash('qjawns3445', PASSWORD_DEFAULT),
        'name' => '탄생 관리자',
        'phone' => '1588-0000',
        'address' => '서울특별시 강남구',
        'user_level' => 9, // 관리자 레벨
        'role' => 'admin',
        'plant_analysis_permission' => 1,
        'email_verified' => 1,
        'is_active' => 1,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];

    // 기존 관리자 계정 확인
    $existingAdmin = $pdo->prepare("SELECT id FROM users WHERE email = ? OR role = 'admin'");
    $existingAdmin->execute([$adminData['email']]);

    if ($existingAdmin->fetch()) {
        // 기존 관리자 계정 업데이트
        $updateSql = "UPDATE users SET
            password = :password,
            name = :name,
            phone = :phone,
            address = :address,
            user_level = :user_level,
            role = :role,
            plant_analysis_permission = :plant_analysis_permission,
            email_verified = :email_verified,
            is_active = :is_active,
            updated_at = :updated_at
            WHERE email = :email OR role = 'admin'";

        $stmt = $pdo->prepare($updateSql);
        $result = $stmt->execute($adminData);

        if ($result) {
            echo "<h2>✅ 관리자 계정 업데이트 완료!</h2>";
            echo "<p><strong>이메일:</strong> korea_tansaeng@naver.com</p>";
            echo "<p><strong>비밀번호:</strong> qjawns3445</p>";
            echo "<p><strong>권한:</strong> 관리자 (Level 9)</p>";
            echo "<hr>";
            echo "<p><a href='/pages/auth/login.php'>로그인 페이지로 이동</a></p>";
            echo "<p><a href='/admin/'>관리자 페이지로 이동</a></p>";
        }
    } else {
        // 새 관리자 계정 생성
        $columns = implode(', ', array_keys($adminData));
        $placeholders = ':' . implode(', :', array_keys($adminData));

        $insertSql = "INSERT INTO users ($columns) VALUES ($placeholders)";
        $stmt = $pdo->prepare($insertSql);
        $result = $stmt->execute($adminData);

        if ($result) {
            echo "<h2>✅ 관리자 계정 생성 완료!</h2>";
            echo "<p><strong>이메일:</strong> korea_tansaeng@naver.com</p>";
            echo "<p><strong>비밀번호:</strong> qjawns3445</p>";
            echo "<p><strong>권한:</strong> 관리자 (Level 9)</p>";
            echo "<hr>";
            echo "<p><a href='/pages/auth/login.php'>로그인 페이지로 이동</a></p>";
            echo "<p><a href='/admin/'>관리자 페이지로 이동</a></p>";
        }
    }

} catch (Exception $e) {
    echo "<h2>❌ 오류 발생</h2>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";

    // 테이블이 없는 경우 생성
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        echo "<h3>🔧 사용자 테이블 생성 중...</h3>";

        try {
            $createTableSql = "
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                name VARCHAR(100) NOT NULL,
                phone VARCHAR(20),
                address TEXT,
                user_level INT DEFAULT 1,
                role VARCHAR(50) DEFAULT 'user',
                plant_analysis_permission TINYINT DEFAULT 0,
                email_verified TINYINT DEFAULT 0,
                firebase_uid VARCHAR(255),
                is_active TINYINT DEFAULT 1,
                last_login DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_email (email),
                INDEX idx_role (role),
                INDEX idx_firebase_uid (firebase_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ";

            $pdo->exec($createTableSql);
            echo "<p>✅ 사용자 테이블이 생성되었습니다.</p>";
            echo "<p><a href='javascript:location.reload()'>페이지 새로고침</a></p>";

        } catch (Exception $createError) {
            echo "<p>❌ 테이블 생성 실패: " . htmlspecialchars($createError->getMessage()) . "</p>";
        }
    }
}

// 보안을 위해 스크립트 자동 삭제
if ($deleteAfterRun && isset($result) && $result) {
    echo "<hr>";
    echo "<h3>🔐 보안 알림</h3>";
    echo "<p>보안을 위해 이 스크립트를 삭제하겠습니다...</p>";

    if (unlink(__FILE__)) {
        echo "<p>✅ 스크립트가 삭제되었습니다.</p>";
    } else {
        echo "<p>⚠️ 수동으로 create_admin.php 파일을 삭제해주세요.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>관리자 계정 생성 - 탄생</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 { color: #2c3e50; }
        h3 { color: #3498db; }
        p { line-height: 1.6; }
        a {
            color: #3498db;
            text-decoration: none;
            font-weight: bold;
        }
        a:hover { text-decoration: underline; }
        hr { margin: 20px 0; border: none; border-top: 1px solid #eee; }
        .success { color: #27ae60; }
        .error { color: #e74c3c; }
        .warning { color: #f39c12; }
    </style>
</head>
<body>
    <div class="container">
        <!-- PHP 출력이 여기에 표시됩니다 -->
    </div>
</body>
</html>
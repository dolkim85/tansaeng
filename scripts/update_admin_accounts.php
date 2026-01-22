<?php
/**
 * 관리자 계정 비밀번호 변경 및 새 관리자 추가 스크립트
 * 실행 후 반드시 삭제할 것!
 */

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = DatabaseConfig::getConnection();
    echo "데이터베이스 연결 성공\n";

    // 1. korea_tansaeng@naver.com 비밀번호 변경
    $email1 = 'korea_tansaeng@naver.com';
    $newPassword = '@!qjawns3445';
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // 기존 사용자 확인
    $stmt = $pdo->prepare("SELECT id, email, name FROM users WHERE email = ?");
    $stmt->execute([$email1]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        $updateStmt->execute([$hashedPassword, $email1]);
        echo "✅ {$email1} 비밀번호 변경 완료\n";
    } else {
        echo "⚠️ {$email1} 사용자를 찾을 수 없습니다.\n";
    }

    // 2. superjun1985@gmail.com 관리자 추가
    $email2 = 'superjun1985@gmail.com';
    $password2 = '@!qjawns3445';
    $hashedPassword2 = password_hash($password2, PASSWORD_DEFAULT);

    // 이미 존재하는지 확인
    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->execute([$email2]);
    $existingUser = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingUser) {
        // 이미 존재하면 비밀번호와 권한 업데이트
        $updateStmt = $pdo->prepare("UPDATE users SET password = ?, user_level = 9, role = 'admin', plant_analysis_permission = 1 WHERE email = ?");
        $updateStmt->execute([$hashedPassword2, $email2]);
        echo "✅ {$email2} 계정 업데이트 완료 (비밀번호 변경, 관리자 권한 부여)\n";
    } else {
        // 새로 생성
        $insertStmt = $pdo->prepare("INSERT INTO users (email, password, name, user_level, role, plant_analysis_permission, is_active, created_at) VALUES (?, ?, ?, 9, 'admin', 1, 1, NOW())");
        $insertStmt->execute([$email2, $hashedPassword2, '관리자']);
        echo "✅ {$email2} 새 관리자 계정 생성 완료\n";
    }

    echo "\n=== 관리자 계정 목록 ===\n";
    $listStmt = $pdo->query("SELECT id, email, name, user_level, role FROM users WHERE user_level = 9 OR role = 'admin'");
    while ($row = $listStmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: {$row['id']}, Email: {$row['email']}, Name: {$row['name']}, Level: {$row['user_level']}, Role: {$row['role']}\n";
    }

    echo "\n✅ 모든 작업 완료!\n";
    echo "⚠️  보안을 위해 이 스크립트를 삭제하세요: rm /var/www/html/scripts/update_admin_accounts.php\n";

} catch (Exception $e) {
    echo "❌ 오류 발생: " . $e->getMessage() . "\n";
    exit(1);
}
?>

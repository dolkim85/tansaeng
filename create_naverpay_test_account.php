<?php
/**
 * 네이버페이 테스트 계정 생성 스크립트
 * 사용법: php create_naverpay_test_account.php
 */

require_once __DIR__ . '/classes/Database.php';

try {
    $db = Database::getInstance()->getConnection();

    // 계정 정보
    $email = 'naverpay@naver.com';
    $password = 'welldone123';
    $name = '네이버페이 테스트';
    $phone = '010-0000-0000';

    // 비밀번호 해시화
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // 이미 존재하는지 확인
    $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->execute([$email]);

    if ($checkStmt->rowCount() > 0) {
        echo "⚠️  이미 존재하는 계정입니다. 비밀번호를 업데이트합니다.\n";

        $updateStmt = $db->prepare("
            UPDATE users
            SET password = ?,
                name = ?,
                phone = ?,
                updated_at = NOW()
            WHERE email = ?
        ");
        $updateStmt->execute([$hashedPassword, $name, $phone, $email]);

        echo "✅ 계정 정보가 업데이트되었습니다.\n";
    } else {
        echo "🔧 새 테스트 계정을 생성합니다...\n";

        $insertStmt = $db->prepare("
            INSERT INTO users (
                email,
                password,
                name,
                phone,
                role,
                email_verified,
                created_at
            ) VALUES (?, ?, ?, ?, 'user', 1, NOW())
        ");
        $insertStmt->execute([$email, $hashedPassword, $name, $phone]);

        echo "✅ 테스트 계정이 생성되었습니다.\n";
    }

    echo "\n=== 네이버페이 테스트 계정 정보 ===\n";
    echo "이메일: {$email}\n";
    echo "비밀번호: {$password}\n";
    echo "이름: {$name}\n";
    echo "전화번호: {$phone}\n";
    echo "역할: 일반 회원 (user)\n";
    echo "\n이 계정으로 로그인하여 네이버페이 결제 테스트를 진행할 수 있습니다.\n";

} catch (Exception $e) {
    echo "❌ 오류 발생: " . $e->getMessage() . "\n";
    exit(1);
}

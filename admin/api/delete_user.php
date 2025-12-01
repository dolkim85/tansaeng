<?php
/**
 * 관리자 > 사용자 탈퇴 처리 API
 */

header('Content-Type: application/json');

$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
require_once $base_path . '/classes/Database.php';

// 관리자 인증 확인
$auth = Auth::getInstance();
if (!$auth->isAdmin()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => '관리자 권한이 필요합니다.'
    ]);
    exit;
}

try {
    // JSON 데이터 받기
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception('잘못된 요청 데이터입니다.');
    }

    // 필수 필드 확인
    $userId = intval($data['user_id'] ?? 0);
    if (!$userId) {
        throw new Exception('사용자 ID가 필요합니다.');
    }

    // DB 연결
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    // 사용자 존재 확인
    $stmt = $pdo->prepare("SELECT id, email, name, user_level FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('사용자를 찾을 수 없습니다.');
    }

    // 자기 자신은 삭제 불가
    $currentUser = $auth->getCurrentUser();
    if ($userId == $currentUser['id']) {
        throw new Exception('자기 자신은 탈퇴시킬 수 없습니다.');
    }

    // 소프트 삭제: 계정 비활성화 및 정보 마스킹
    // 이메일 중복 방지를 위해 deleted_ 접두사 추가
    $deletedEmail = 'deleted_' . time() . '_' . $user['email'];

    $stmt = $pdo->prepare("
        UPDATE users SET
            name = '탈퇴한 사용자',
            email = ?,
            phone = NULL,
            age_range = NULL,
            gender = NULL,
            is_active = 0,
            plant_analysis_permission = 0,
            updated_at = NOW()
        WHERE id = ?
    ");

    $result = $stmt->execute([$deletedEmail, $userId]);

    if (!$result) {
        throw new Exception('사용자 탈퇴 처리에 실패했습니다.');
    }

    // 로그 기록 (선택사항)
    $logStmt = $pdo->prepare("
        INSERT INTO admin_logs (admin_id, action, target_type, target_id, details, created_at)
        VALUES (?, 'delete_user', 'user', ?, ?, NOW())
    ");

    $logDetails = json_encode([
        'deleted_user_name' => $user['name'],
        'deleted_user_email' => $user['email'],
        'deleted_by' => $currentUser['name']
    ], JSON_UNESCAPED_UNICODE);

    // admin_logs 테이블이 없을 수 있으므로 에러 무시
    @$logStmt->execute([$currentUser['id'], $userId, $logDetails]);

    echo json_encode([
        'success' => true,
        'message' => '사용자가 성공적으로 탈퇴 처리되었습니다.'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

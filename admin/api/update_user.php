<?php
/**
 * 관리자 > 사용자 정보 수정 API
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

    $name = trim($data['name'] ?? '');
    $email = trim($data['email'] ?? '');

    if (empty($name)) {
        throw new Exception('이름을 입력해주세요.');
    }

    if (empty($email)) {
        throw new Exception('이메일을 입력해주세요.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('올바른 이메일 형식이 아닙니다.');
    }

    // DB 연결
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    // 사용자 존재 확인
    $stmt = $pdo->prepare("SELECT id, email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('사용자를 찾을 수 없습니다.');
    }

    // 이메일 중복 확인 (본인 제외)
    if ($email !== $user['email']) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            throw new Exception('이미 사용 중인 이메일입니다.');
        }
    }

    // 선택적 필드
    $phone = trim($data['phone'] ?? '');
    $ageRange = trim($data['age_range'] ?? '');
    $gender = trim($data['gender'] ?? '');
    $userLevel = intval($data['user_level'] ?? 1);
    $plantAnalysisPermission = !empty($data['plant_analysis_permission']) ? 1 : 0;
    $isActive = !empty($data['is_active']) ? 1 : 0;

    // 사용자 정보 업데이트
    $stmt = $pdo->prepare("
        UPDATE users SET
            name = ?,
            email = ?,
            phone = ?,
            age_range = ?,
            gender = ?,
            user_level = ?,
            plant_analysis_permission = ?,
            is_active = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

    $result = $stmt->execute([
        $name,
        $email,
        $phone ?: null,
        $ageRange ?: null,
        $gender ?: null,
        $userLevel,
        $plantAnalysisPermission,
        $isActive,
        $userId
    ]);

    if (!$result) {
        throw new Exception('사용자 정보 수정에 실패했습니다.');
    }

    echo json_encode([
        'success' => true,
        'message' => '사용자 정보가 성공적으로 수정되었습니다.'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

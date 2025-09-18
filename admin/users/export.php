<?php
// Initialize session and auth before any output
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
require_once $base_path . '/classes/Database.php';

$auth = Auth::getInstance();
$auth->requireAdmin();

try {
    $pdo = Database::getInstance()->getConnection();
    
    // Get all users data
    $sql = "SELECT 
                id,
                name,
                email,
                phone,
                birth_date,
                gender,
                address,
                user_level,
                CASE 
                    WHEN user_level = 9 THEN '관리자'
                    WHEN user_level = 2 THEN '프리미엄 회원'
                    WHEN user_level = 1 THEN '일반 회원'
                    ELSE '기타'
                END as user_level_name,
                is_active,
                CASE 
                    WHEN is_active = 1 THEN '활성'
                    ELSE '비활성'
                END as status_name,
                plant_analysis_permission,
                CASE 
                    WHEN plant_analysis_permission = 1 THEN '승인'
                    ELSE '미승인'
                END as plant_analysis_status,
                email_verified_at,
                last_login_at,
                created_at,
                updated_at
            FROM users 
            WHERE user_level < 9
            ORDER BY created_at DESC";
    
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Set headers for Excel download
    $filename = '사용자목록_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for proper Korean display in Excel
    fputs($output, "\xEF\xBB\xBF");
    
    // CSV headers
    $headers = [
        'ID',
        '이름',
        '이메일',
        '전화번호',
        '생년월일',
        '성별',
        '주소',
        '회원등급코드',
        '회원등급',
        '활성여부코드',
        '활성여부',
        '식물분석권한코드',
        '식물분석권한',
        '이메일인증일시',
        '최근로그인일시',
        '가입일시',
        '수정일시'
    ];
    
    fputcsv($output, $headers);
    
    // Add user data
    foreach ($users as $user) {
        $row = [
            $user['id'],
            $user['name'] ?? '',
            $user['email'] ?? '',
            $user['phone'] ?? '',
            $user['birth_date'] ?? '',
            $user['gender'] ?? '',
            $user['address'] ?? '',
            $user['user_level'],
            $user['user_level_name'],
            $user['is_active'],
            $user['status_name'],
            $user['plant_analysis_permission'] ?? 0,
            $user['plant_analysis_status'] ?? '미승인',
            $user['email_verified_at'] ? date('Y-m-d H:i:s', strtotime($user['email_verified_at'])) : '',
            $user['last_login_at'] ? date('Y-m-d H:i:s', strtotime($user['last_login_at'])) : '',
            $user['created_at'] ? date('Y-m-d H:i:s', strtotime($user['created_at'])) : '',
            $user['updated_at'] ? date('Y-m-d H:i:s', strtotime($user['updated_at'])) : ''
        ];
        
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
    
} catch (Exception $e) {
    // If error occurs, redirect back with error message
    header('Location: index.php?error=' . urlencode('데이터 내보내기에 실패했습니다: ' . $e->getMessage()));
    exit;
}
?>
<?php
/**
 * OAuth 디버그 테스트 페이지
 * 데이터베이스 연결 및 users 테이블 구조 확인
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>OAuth 디버그 테스트</h1>";

// 1. Database 클래스 테스트
echo "<h2>1. Database 클래스 로드</h2>";
try {
    require_once __DIR__ . '/classes/Database.php';
    echo "✅ Database 클래스 로드 성공<br>";

    $db = Database::getInstance();
    echo "✅ Database 인스턴스 생성 성공<br>";

    $pdo = $db->getConnection();
    echo "✅ PDO 연결 성공<br>";
} catch (Exception $e) {
    echo "❌ Database 오류: " . $e->getMessage() . "<br>";
    exit;
}

// 2. Users 테이블 구조 확인
echo "<h2>2. Users 테이블 구조</h2>";
try {
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "<td>{$column['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    // OAuth 관련 컬럼 확인
    $hasOAuthProvider = false;
    $hasOAuthId = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'oauth_provider') $hasOAuthProvider = true;
        if ($column['Field'] === 'oauth_id') $hasOAuthId = true;
    }

    echo "<br>";
    if ($hasOAuthProvider && $hasOAuthId) {
        echo "✅ OAuth 컬럼 (oauth_provider, oauth_id) 존재<br>";
    } else {
        echo "❌ OAuth 컬럼 부족:<br>";
        if (!$hasOAuthProvider) echo "  - oauth_provider 컬럼 없음<br>";
        if (!$hasOAuthId) echo "  - oauth_id 컬럼 없음<br>";
    }

} catch (Exception $e) {
    echo "❌ 테이블 구조 확인 오류: " . $e->getMessage() . "<br>";
}

// 3. Auth 클래스 테스트
echo "<h2>3. Auth 클래스</h2>";
try {
    require_once __DIR__ . '/classes/Auth.php';
    echo "✅ Auth 클래스 로드 성공<br>";

    $auth = Auth::getInstance();
    echo "✅ Auth 인스턴스 생성 성공<br>";

    if (method_exists($auth, 'findOrCreateOAuthUser')) {
        echo "✅ findOrCreateOAuthUser 메소드 존재<br>";
    } else {
        echo "❌ findOrCreateOAuthUser 메소드 없음<br>";
    }
} catch (Exception $e) {
    echo "❌ Auth 클래스 오류: " . $e->getMessage() . "<br>";
}

// 4. OAuth Config 테스트
echo "<h2>4. OAuth 설정</h2>";
try {
    $oauthConfig = require __DIR__ . '/config/oauth.php';

    echo "Base URL: " . $oauthConfig['base_url'] . "<br>";
    echo "Kakao Client ID: " . (isset($oauthConfig['kakao']['client_id']) ? substr($oauthConfig['kakao']['client_id'], 0, 10) . '...' : 'Not Set') . "<br>";
    echo "Kakao Client Secret: " . (isset($oauthConfig['kakao']['client_secret']) && !empty($oauthConfig['kakao']['client_secret']) ? '설정됨 (****)' : 'Not Set') . "<br>";
    echo "Kakao Redirect URI: " . $oauthConfig['kakao']['redirect_uri'] . "<br>";

    if (!empty($oauthConfig['kakao']['client_id']) && !empty($oauthConfig['kakao']['client_secret'])) {
        echo "✅ 카카오 OAuth 설정 완료<br>";
    } else {
        echo "❌ 카카오 OAuth 설정 불완전<br>";
    }
} catch (Exception $e) {
    echo "❌ OAuth 설정 로드 오류: " . $e->getMessage() . "<br>";
}

// 5. 기존 OAuth 사용자 확인
echo "<h2>5. 기존 OAuth 사용자</h2>";
try {
    $stmt = $pdo->query("SELECT id, email, name, oauth_provider, oauth_id FROM users WHERE oauth_provider IS NOT NULL AND oauth_provider != '' LIMIT 10");
    $oauthUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($oauthUsers) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Email</th><th>Name</th><th>Provider</th><th>OAuth ID</th></tr>";
        foreach ($oauthUsers as $user) {
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['email']}</td>";
            echo "<td>{$user['name']}</td>";
            echo "<td>{$user['oauth_provider']}</td>";
            echo "<td>" . substr($user['oauth_id'], 0, 10) . "...</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "OAuth 사용자가 없습니다.<br>";
    }
} catch (Exception $e) {
    echo "❌ 사용자 조회 오류: " . $e->getMessage() . "<br>";
}

// 6. 최근 에러 로그 (있다면)
echo "<h2>6. PHP 에러 로그 (최근 10줄)</h2>";
$logPaths = [
    '/var/log/php_errors.log',
    '/var/log/php/error.log',
    ini_get('error_log')
];

foreach ($logPaths as $logPath) {
    if ($logPath && file_exists($logPath) && is_readable($logPath)) {
        echo "<strong>로그 파일: $logPath</strong><br>";
        echo "<pre style='background:#f0f0f0; padding:10px; max-height:300px; overflow:auto;'>";
        echo htmlspecialchars(shell_exec("tail -20 '$logPath' | grep -i 'kakao\|oauth' || echo 'No Kakao/OAuth errors found'"));
        echo "</pre>";
        break;
    }
}

echo "<hr>";
echo "<p><strong>테스트 완료</strong></p>";
echo "<p>이 페이지는 테스트 후 삭제하세요: <code>rm " . __FILE__ . "</code></p>";
?>

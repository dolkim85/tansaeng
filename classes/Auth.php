<?php
require_once 'User.php';
require_once __DIR__ . '/../config/config.php';

class Auth {
    private static $instance = null;
    private $user;

    private function __construct() {
        $this->user = new User();
        $this->initSession();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initSession() {
        if (session_status() === PHP_SESSION_NONE) {
            if (!headers_sent()) {
                ini_set('session.cookie_httponly', 1);
                ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
                ini_set('session.use_only_cookies', 1);
            }
            session_start();
        }

        if (isset($_SESSION['user_id'])) {
            $this->checkSessionExpiry();
        }
    }

    private function checkSessionExpiry() {
        $timeout = SESSION_TIMEOUT;
        
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity'] > $timeout)) {
            $this->logout();
            throw new Exception('세션이 만료되었습니다. 다시 로그인해주세요.');
        }

        $_SESSION['last_activity'] = time();
    }

    public function login($email, $password) {
        try {
            $userData = $this->user->login(
                $email, 
                $password, 
                $this->getClientIP(), 
                $_SERVER['HTTP_USER_AGENT'] ?? null
            );

            $_SESSION['user_id'] = $userData['id'];
            $_SESSION['user_email'] = $userData['email'];
            $_SESSION['user_name'] = $userData['name'];
            $_SESSION['user_level'] = $userData['user_level'] ?? null;
            $_SESSION['role'] = $userData['role'] ?? null;
            $_SESSION['plant_analysis_permission'] = $userData['plant_analysis_permission'] ?? 0;
            $_SESSION['last_activity'] = time();
            $_SESSION['login_time'] = time();

            $this->generateCSRFToken();

            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function logout() {
        session_unset();
        session_destroy();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['user_email'],
            'name' => $_SESSION['user_name'],
            'user_level' => $_SESSION['user_level'] ?? null,
            'role' => $_SESSION['role'] ?? null,
            'plant_analysis_permission' => $_SESSION['plant_analysis_permission'] ?? 0
        ];
    }

    public function getCurrentUserId() {
        return $this->isLoggedIn() ? $_SESSION['user_id'] : null;
    }

    public function hasPlantAnalysisPermission() {
        return $this->isLoggedIn() && $_SESSION['plant_analysis_permission'] == 1;
    }

    public function isAdmin() {
        return $this->isLoggedIn() && $_SESSION['user_level'] == USER_LEVEL_ADMIN;
    }

    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            if ($this->isAjaxRequest()) {
                http_response_code(401);
                echo json_encode(['error' => '로그인이 필요합니다.']);
                exit;
            } else {
                header('Location: /pages/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
                exit;
            }
        }
    }

    public function requirePlantAnalysisPermission() {
        // 로그인 체크를 먼저 하되, 데이터베이스 오류시 세션 기반으로 fallback
        try {
            $this->requireLogin();
        } catch (Exception $e) {
            // 세션 기반으로 체크
            if (!isset($_SESSION['user_id'])) {
                header('Location: /pages/auth/login.php');
                exit;
            }
        }
        
        // 식물분석 권한 체크 (세션 기반 또는 데이터베이스)
        $hasPermission = false;
        
        // 먼저 세션에서 확인
        if (isset($_SESSION['plant_analysis_permission']) && $_SESSION['plant_analysis_permission']) {
            $hasPermission = true;
        }
        
        // 데이터베이스가 가능한 경우에도 확인
        try {
            if (!$hasPermission && $this->hasPlantAnalysisPermission()) {
                $hasPermission = true;
            }
        } catch (Exception $e) {
            // 데이터베이스 오류시 세션만 사용
        }
        
        if (!$hasPermission) {
            if ($this->isAjaxRequest()) {
                http_response_code(403);
                echo json_encode(['error' => '식물분석 권한이 필요합니다.']);
                exit;
            } else {
                header('Location: /pages/plant_analysis/access_denied.php');
                exit;
            }
        }
    }

    public function requireAdmin() {
        $this->requireLogin();
        
        if (!$this->isAdmin()) {
            if ($this->isAjaxRequest()) {
                http_response_code(403);
                echo json_encode(['error' => '관리자 권한이 필요합니다.']);
                exit;
            } else {
                header('Location: /');
                exit;
            }
        }
    }

    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token']) || 
            !isset($_SESSION['csrf_token_time']) ||
            (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_EXPIRE)) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        return $_SESSION['csrf_token'];
    }

    public function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && 
               hash_equals($_SESSION['csrf_token'], $token) &&
               isset($_SESSION['csrf_token_time']) &&
               (time() - $_SESSION['csrf_token_time'] <= CSRF_TOKEN_EXPIRE);
    }

    public function getCSRFToken() {
        return $_SESSION['csrf_token'] ?? $this->generateCSRFToken();
    }

    private function isAjaxRequest() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    private function getClientIP() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    public function refreshUserSession() {
        if ($this->isLoggedIn()) {
            $userData = $this->user->getUserById($_SESSION['user_id']);
            if ($userData) {
                $_SESSION['user_level'] = $userData['user_level'];
                $_SESSION['plant_analysis_permission'] = $userData['plant_analysis_permission'];
            }
        }
    }

    public function updateLastActivity() {
        if ($this->isLoggedIn()) {
            $_SESSION['last_activity'] = time();
        }
    }

    public function getSessionInfo() {
        if (!$this->isLoggedIn()) {
            return null;
        }

        return [
            'login_time' => $_SESSION['login_time'] ?? null,
            'last_activity' => $_SESSION['last_activity'] ?? null,
            'remaining_time' => SESSION_TIMEOUT - (time() - ($_SESSION['last_activity'] ?? time()))
        ];
    }
}
?>
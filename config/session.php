<?php
require_once 'config.php';

class SessionManager {
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        self::checkTimeout();
        self::regenerateId();
    }
    
    public static function checkTimeout() {
        if (isset($_SESSION['LAST_ACTIVITY']) && 
            (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_TIMEOUT)) {
            self::destroy();
            return false;
        }
        $_SESSION['LAST_ACTIVITY'] = time();
        return true;
    }
    
    public static function regenerateId() {
        if (!isset($_SESSION['CREATED'])) {
            $_SESSION['CREATED'] = time();
        } else if (time() - $_SESSION['CREATED'] > 1800) { // 30 minutes
            session_regenerate_id(true);
            $_SESSION['CREATED'] = time();
        }
    }
    
    public static function destroy() {
        if (session_status() !== PHP_SESSION_NONE) {
            session_unset();
            session_destroy();
        }
    }
    
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['user_email']);
    }
    
    public static function isAdmin() {
        return self::isLoggedIn() && 
               isset($_SESSION['user_level']) && 
               $_SESSION['user_level'] == USER_LEVEL_ADMIN;
    }
    
    public static function hasPlantAnalysisPermission() {
        return self::isLoggedIn() && 
               isset($_SESSION['user_level']) && 
               ($_SESSION['user_level'] >= USER_LEVEL_PLANT_ANALYSIS || self::isAdmin());
    }
    
    public static function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    public static function getUserLevel() {
        return $_SESSION['user_level'] ?? USER_LEVEL_GENERAL;
    }
    
    public static function setUser($userData) {
        $_SESSION['user_id'] = $userData['id'];
        $_SESSION['user_email'] = $userData['email'];
        $_SESSION['user_name'] = $userData['name'];
        $_SESSION['user_level'] = $userData['user_level'];
        $_SESSION['plant_analysis_permission'] = $userData['plant_analysis_permission'];
    }
    
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token']) || 
            !isset($_SESSION['csrf_token_time']) ||
            (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_EXPIRE)) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && 
               isset($_SESSION['csrf_token_time']) &&
               (time() - $_SESSION['csrf_token_time'] <= CSRF_TOKEN_EXPIRE) &&
               hash_equals($_SESSION['csrf_token'], $token);
    }
}
?>
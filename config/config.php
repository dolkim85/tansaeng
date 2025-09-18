<?php
// Application Configuration
define('APP_NAME', '탄생');
define('APP_VERSION', '1.0');
define('APP_URL', 'http://localhost:8000');
define('BASE_PATH', __DIR__ . '/../');
define('UPLOAD_PATH', BASE_PATH . 'uploads/');
define('MAX_UPLOAD_SIZE', 50 * 1024 * 1024); // 50MB

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'tansaeng_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Session Configuration
define('SESSION_TIMEOUT', 7200); // 2 hours
define('CSRF_TOKEN_EXPIRE', 3600); // 1 hour

// User Permission Levels
define('USER_LEVEL_GENERAL', 1);
define('USER_LEVEL_PLANT_ANALYSIS', 2);
define('USER_LEVEL_ADMIN', 9);

// File Upload Settings
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'pdf']);
define('ALLOWED_MIME_TYPES', [
    'image/jpeg',
    'image/png', 
    'image/webp',
    'video/mp4',
    'application/pdf'
]);

// Admin Credentials
define('DEFAULT_ADMIN_USER', 'admin@tansaeng.com');
define('DEFAULT_ADMIN_PASS', 'password');

// Raspberry Pi Settings
define('RASPBERRY_PI_TOKEN', bin2hex(random_bytes(32)));
define('RASPBERRY_PI_UPLOAD_PATH', UPLOAD_PATH . 'plant_images/');

// Error Reporting (Production)
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Timezone
date_default_timezone_set('Asia/Seoul');
?>
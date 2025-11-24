<?php
// OPcache 완전 무효화
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache cleared successfully\n";
} else {
    echo "OPcache not enabled\n";
}

// 특정 파일 무효화
$file = '/var/www/html/admin/smartfarm/index.php';
if (function_exists('opcache_invalidate')) {
    opcache_invalidate($file, true);
    echo "Invalidated: $file\n";
}

echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";

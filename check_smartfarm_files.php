<?php
header('Content-Type: application/json');

$distPath = '/var/www/html/smartfarm-ui/dist';
$assetsPath = $distPath . '/assets';

$files = [];

if (is_dir($assetsPath)) {
    $files['assets'] = array_diff(scandir($assetsPath), ['.', '..']);
    $files['dist_root'] = array_diff(scandir($distPath), ['.', '..']);
} else {
    $files['error'] = 'Directory not found';
}

echo json_encode([
    'success' => true,
    'files' => $files,
    'paths' => [
        'dist' => $distPath,
        'assets' => $assetsPath
    ]
]);

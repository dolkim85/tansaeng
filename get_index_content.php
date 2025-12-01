<?php
header('Content-Type: application/json');

$indexFile = '/var/www/html/smartfarm-ui/dist/index.html';

if (file_exists($indexFile)) {
    $content = file_get_contents($indexFile);
    echo json_encode([
        'success' => true,
        'content' => $content,
        'file_time' => date('Y-m-d H:i:s', filemtime($indexFile))
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'File not found'
    ]);
}

<?php
// PHP 업로드 설정 조정 (런타임)
ini_set('upload_max_filesize', '10M');
ini_set('post_max_size', '12M');
ini_set('max_file_uploads', '20');
ini_set('max_execution_time', '120');
ini_set('memory_limit', '256M');

header('Content-Type: application/json');

$settings = [
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_file_uploads' => ini_get('max_file_uploads'),
    'max_execution_time' => ini_get('max_execution_time'),
    'memory_limit' => ini_get('memory_limit'),
    'file_uploads' => ini_get('file_uploads'),
    'upload_tmp_dir' => ini_get('upload_tmp_dir') ?: 'default',
    'max_input_time' => ini_get('max_input_time')
];

// 설정 변환 (바이트 단위)
$settings['upload_max_filesize_bytes'] = return_bytes($settings['upload_max_filesize']);
$settings['post_max_size_bytes'] = return_bytes($settings['post_max_size']);
$settings['memory_limit_bytes'] = return_bytes($settings['memory_limit']);

function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int) $val;
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}

echo json_encode([
    'success' => true,
    'message' => 'PHP 업로드 설정 확인',
    'settings' => $settings,
    'recommended' => [
        'upload_max_filesize' => '10M 이상',
        'post_max_size' => '12M 이상 (upload_max_filesize보다 커야 함)',
        'max_file_uploads' => '20 이상',
        'file_uploads' => 'On이어야 함'
    ],
    'status' => [
        'upload_max_filesize_ok' => $settings['upload_max_filesize_bytes'] >= 10*1024*1024,
        'post_max_size_ok' => $settings['post_max_size_bytes'] >= 12*1024*1024,
        'file_uploads_ok' => $settings['file_uploads'] === '1',
        'overall_ok' => (
            $settings['upload_max_filesize_bytes'] >= 10*1024*1024 &&
            $settings['post_max_size_bytes'] >= 12*1024*1024 &&
            $settings['file_uploads'] === '1'
        )
    ]
], JSON_PRETTY_PRINT);
?>
<?php
// Vercel PHP 호환성을 위한 API 엔드포인트
header('Content-Type: application/json');

// 메인페이지로 리다이렉트
header('Location: /index.php');
exit;
?>
<?php
session_start();

header('Content-Type: application/json');

echo json_encode([
    'session_id' => session_id(),
    'session_data' => $_SESSION,
    'is_logged_in' => !empty($_SESSION['user_id']),
    'user_id' => $_SESSION['user_id'] ?? null,
    'timestamp' => date('c')
], JSON_PRETTY_PRINT);
?>
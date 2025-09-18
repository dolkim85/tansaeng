<?php
// Use Auth class for logout
require_once __DIR__ . '/../classes/Auth.php';
$auth = Auth::getInstance();
$auth->logout();

// Redirect to main login
header('Location: /pages/auth/login.php');
exit;
?>
<?php
require_once __DIR__ . '/../../classes/Auth.php';

$auth = Auth::getInstance();
$auth->logout();

header('Location: /');
exit;
?>
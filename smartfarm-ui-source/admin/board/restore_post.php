<?php
// Initialize session and auth before any output
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
require_once $base_path . '/classes/Database.php';

$auth = Auth::getInstance();
$auth->requireAdmin();

$post_id = $_GET['id'] ?? 0;

if (!$post_id) {
    header('Location: index.php');
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();
    
    // Check if post exists and is deleted
    $sql = "SELECT id, title FROM boards WHERE id = ? AND status = 'deleted'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        header('Location: index.php?error=post_not_found');
        exit;
    }

    // Restore the post
    $sql = "UPDATE boards SET status = 'published', updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$post_id]);
    
    header('Location: index.php?success=restored');
    exit;
    
} catch (Exception $e) {
    header('Location: index.php?error=restore_failed');
    exit;
}
?>
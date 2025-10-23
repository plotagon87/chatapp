<?php
require_once 'includes/config.php';

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Update user status to offline
    $stmt = $conn->prepare("UPDATE users SET status = 'offline', last_seen = NOW() WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    logActivity($user_id, 'User logged out');
    
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy session
    session_destroy();
}

header('Location: index.php');
exit();
?>

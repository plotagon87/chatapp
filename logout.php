<?php
require_once 'includes/config.php';

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Update user status to offline
    $stmt = $conn->prepare("UPDATE users SET status = 'offline', last_seen = NOW() WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    logActivity($user_id, 'User logged out');
    // remove entry from user_sessions table as well
    if (session_id()) {
        $stmt = $conn->prepare("DELETE FROM user_sessions WHERE session_id = ?");
        $stmt->execute([session_id()]);
    }
    // clear remember cookie on this device as well
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    }
    
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy session
    session_destroy();
}

header('Location: index.php');
exit();
?>

<?php
require_once '../includes/config.php';
requireLogin();

header('Content-Type: application/json');

// Update last seen and keep user online
updateLastSeen($_SESSION['user_id']);
updateUserStatus($_SESSION['user_id'], 'online');

echo json_encode([
    'success' => true,
    'message' => 'Status updated',
    'timestamp' => date('Y-m-d H:i:s')
]);
?>

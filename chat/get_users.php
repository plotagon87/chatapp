<?php
require_once '../includes/config.php';
requireLogin();

header('Content-Type: application/json');

$current_user_id = $_SESSION['user_id'];

// Get all users except current user with their last message info
$query = "SELECT u.user_id, u.username, u.full_name, u.profile_picture, u.status, u.last_seen, u.custom_status,
    (SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND sender_id = u.user_id AND is_read = 0) as unread_count,
    (SELECT message_text FROM messages 
     WHERE (sender_id = ? AND receiver_id = u.user_id) OR (sender_id = u.user_id AND receiver_id = ?)
     ORDER BY created_at DESC LIMIT 1) as last_message,
    (SELECT created_at FROM messages 
     WHERE (sender_id = ? AND receiver_id = u.user_id) OR (sender_id = u.user_id AND receiver_id = ?)
     ORDER BY created_at DESC LIMIT 1) as last_message_time
FROM users u
WHERE u.user_id != ?
ORDER BY u.status DESC, last_message_time DESC, u.full_name ASC";

$stmt = $conn->prepare($query);
$stmt->execute([$current_user_id, $current_user_id, $current_user_id, $current_user_id, $current_user_id, $current_user_id]);

$users = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'users' => $users,
    'count' => count($users)
]);
?>
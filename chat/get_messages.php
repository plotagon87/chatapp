<?php
require_once '../includes/config.php';
requireLogin();

header('Content-Type: application/json');

$current_user_id = $_SESSION['user_id'];
$other_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if (empty($other_user_id)) {
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit();
}

// Get messages between current user and the other user
$query = "SELECT m.*, 
          sender.full_name as sender_name, 
          sender.profile_picture as sender_picture,
          receiver.full_name as receiver_name
          FROM messages m
          JOIN users sender ON m.sender_id = sender.user_id
          JOIN users receiver ON m.receiver_id = receiver.user_id
          WHERE (m.sender_id = ? AND m.receiver_id = ?) 
             OR (m.sender_id = ? AND m.receiver_id = ?)
          ORDER BY m.created_at ASC";

$stmt = $conn->prepare($query);
$stmt->execute([$current_user_id, $other_user_id, $other_user_id, $current_user_id]);
$messages = $stmt->fetchAll();

// Mark received messages as read
$update_stmt = $conn->prepare("UPDATE messages SET is_read = 1, read_at = NOW() WHERE receiver_id = ? AND sender_id = ? AND is_read = 0");
$update_stmt->execute([$current_user_id, $other_user_id]);

echo json_encode([
    'success' => true,
    'messages' => $messages,
    'count' => count($messages)
]);
?>

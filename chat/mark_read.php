<?php
require_once '../includes/config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$sender_id = isset($_POST['sender_id']) ? (int)$_POST['sender_id'] : 0;

if (empty($sender_id)) {
    echo json_encode(['success' => false, 'message' => 'Sender ID is required']);
    exit();
}

// Mark all messages from sender as read for current user
$stmt = $conn->prepare("UPDATE messages SET is_read = 1, read_at = NOW() WHERE receiver_id = ? AND sender_id = ? AND is_read = 0");

if ($stmt->execute([$_SESSION['user_id'], $sender_id])) {
    $affected_rows = $stmt->rowCount();
    echo json_encode([
        'success' => true,
        'message' => 'Messages marked as read',
        'updated_count' => $affected_rows
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to mark messages as read'
    ]);
}
?>
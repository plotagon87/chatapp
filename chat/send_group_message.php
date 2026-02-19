<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Validate CSRF token
$csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (!validateCsrfToken($csrf_token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$sender_id = $_SESSION['user_id'];
$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
// allow ciphertext or untrusted content; do not sanitize plain text
$message_text = isset($_POST['message_text']) ? $_POST['message_text'] : '';

// Validation
if (empty($group_id)) {
    echo json_encode(['success' => false, 'message' => 'Group ID is required']);
    exit();
}

if (empty($message_text)) {
    echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
    exit();
}

// Check if user is a member of the group
$member_stmt = $conn->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ?");
$member_stmt->execute([$group_id, $sender_id]);

// Also check if user is the creator
$creator_stmt = $conn->prepare("SELECT created_by FROM group_chats WHERE group_id = ?");
$creator_stmt->execute([$group_id]);
$creator = $creator_stmt->fetch();

if ($member_stmt->rowCount() === 0 && (!$creator || $creator['created_by'] != $sender_id)) {
    echo json_encode(['success' => false, 'message' => 'You are not a member of this group']);
    exit();
}

// Insert message
$stmt = $conn->prepare("INSERT INTO group_messages (group_id, sender_id, message_text, message_type) VALUES (?, ?, ?, 'text')");

if ($stmt->execute([$group_id, $sender_id, $message_text])) {
    $message_id = $conn->lastInsertId();
    
    // Log activity
    logActivity($sender_id, "Sent message to group ID: $group_id");
    
    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully',
        'message_id' => $message_id,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send message'
    ]);
}
?>

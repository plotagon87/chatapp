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
$receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
$message_text = isset($_POST['message_text']) ? sanitize($_POST['message_text']) : '';
$message_type = isset($_POST['message_type']) ? sanitize($_POST['message_type']) : 'text';
$file_path = isset($_POST['file_path']) ? sanitize($_POST['file_path']) : null;

// Validation
if (empty($receiver_id)) {
    echo json_encode(['success' => false, 'message' => 'Receiver ID is required']);
    exit();
}

if (empty($message_text) && empty($file_path)) {
    echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
    exit();
}

// Check if receiver exists
$check_user = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
$check_user->execute([$receiver_id]);

if ($check_user->rowCount() === 0) {
    echo json_encode(['success' => false, 'message' => 'Receiver not found']);
    exit();
}

// Insert message
$stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, message_type, file_path) VALUES (?, ?, ?, ?, ?)");

if ($stmt->execute([$sender_id, $receiver_id, $message_text, $message_type, $file_path])) {
    $message_id = $conn->lastInsertId();
    
    // Create notification for receiver
    $sender_name = $_SESSION['full_name'] ?? 'User';
    $notification_content = "$sender_name sent you a message";
    createNotification($receiver_id, 'message', $notification_content, $message_id);
    
    // Log activity
    logActivity($sender_id, "Sent message to user ID: $receiver_id");
    
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

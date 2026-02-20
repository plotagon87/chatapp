<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrf_token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$message_id = isset($_POST['group_message_id']) ? (int)$_POST['group_message_id'] : 0;
$new_text = isset($_POST['new_text']) ? trim($_POST['new_text']) : '';

if (!$message_id || $new_text === '') {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

$stmt = $conn->prepare("SELECT sender_id, created_at, message_text FROM group_messages WHERE message_id = ?");
$stmt->execute([$message_id]);
$msg = $stmt->fetch();
if (!$msg) {
    echo json_encode(['success' => false, 'message' => 'Message not found']);
    exit();
}

if ($msg['sender_id'] != $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Not allowed']);
    exit();
}

$created = strtotime($msg['created_at']);
if (time() - $created > 180) {
    echo json_encode(['success' => false, 'message' => 'Edit window expired']);
    exit();
}

$update = $conn->prepare("UPDATE group_messages SET message_text = ?, edited_at = NOW(), edited_count = edited_count + 1 WHERE message_id = ?");
if ($update->execute([$new_text, $message_id])) {
    $hist = $conn->prepare("INSERT INTO group_message_edits (group_message_id, old_text, new_text, edited_by) VALUES (?, ?, ?, ?)");
    $hist->execute([$message_id, $msg['message_text'], $new_text, $_SESSION['user_id']]);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save edit']);
}
?>
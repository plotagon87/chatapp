<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}
$csrf_token = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrf_token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit();
}
$message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
$is_group = isset($_POST['is_group']) && $_POST['is_group'] === '1';

if (!$message_id) {
    echo json_encode(['success' => false, 'message' => 'Missing message id']);
    exit();
}

$table = $is_group ? 'group_messages' : 'messages';
$stmt = $conn->prepare("SELECT sender_id FROM $table WHERE message_id = ?");
$stmt->execute([$message_id]);
$msg = $stmt->fetch();
if (!$msg) {
    echo json_encode(['success' => false, 'message' => 'Message not found']);
    exit();
}
if ($msg['sender_id'] != $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit();
}

$upd = $conn->prepare("UPDATE $table SET is_deleted = 1, deleted_at = NOW(), deleted_by = ? WHERE message_id = ?");
if ($upd->execute([$_SESSION['user_id'], $message_id])) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Delete failed']);
}
?>
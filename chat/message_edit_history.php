<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
header('Content-Type: application/json');

$message_id = isset($_GET['message_id']) ? (int)$_GET['message_id'] : 0;
$is_group = isset($_GET['is_group']) && $_GET['is_group'] === '1';

if (!$message_id) {
    echo json_encode(['success' => false, 'message' => 'Missing message id']);
    exit();
}

if ($is_group) {
    $stmt = $conn->prepare("SELECT * FROM group_message_edits WHERE group_message_id = ? ORDER BY edited_at DESC");
} else {
    $stmt = $conn->prepare("SELECT * FROM message_edits WHERE message_id = ? ORDER BY edited_at DESC");
}
$$stmt->execute([$message_id]);
$edits = $stmt->fetchAll();

echo json_encode(['success' => true, 'edits' => $edits]);
?>
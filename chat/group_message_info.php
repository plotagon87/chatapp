<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
header('Content-Type: application/json');

$group_message_id = isset($_GET['group_message_id']) ? (int)$_GET['group_message_id'] : 0;
if (!$group_message_id) {
    echo json_encode(['success' => false, 'message' => 'Missing id']);
    exit();
}

// ensure user is in group
$check = $conn->prepare("SELECT gm.group_id FROM group_messages gm JOIN group_members m ON gm.group_id = m.group_id WHERE gm.message_id = ? AND m.user_id = ?");
$check->execute([$group_message_id, $_SESSION['user_id']]);
if ($check->rowCount() === 0) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$stmt = $conn->prepare("SELECT s.user_id, u.full_name, s.is_delivered, s.delivered_at, s.is_read, s.read_at FROM group_message_status s JOIN users u ON s.user_id = u.user_id WHERE s.group_message_id = ?");
$stmt->execute([$group_message_id]);
$rows = $stmt->fetchAll();

echo json_encode(['success' => true, 'status' => $rows]);
?>
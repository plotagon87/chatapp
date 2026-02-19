<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

header('Content-Type: application/json');

$current_user_id = $_SESSION['user_id'];
$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;

if (empty($group_id)) {
    echo json_encode(['success' => false, 'message' => 'Group ID is required']);
    exit();
}

// Check if user is a member of the group
$member_stmt = $conn->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ?");
$member_stmt->execute([$group_id, $current_user_id]);

// Also check if user is the creator
$creator_stmt = $conn->prepare("SELECT created_by FROM group_chats WHERE group_id = ?");
$creator_stmt->execute([$group_id]);
$creator = $creator_stmt->fetch();

if ($member_stmt->rowCount() === 0 && (!$creator || $creator['created_by'] != $current_user_id)) {
    echo json_encode(['success' => false, 'message' => 'You are not a member of this group']);
    exit();
}

// Get group messages
$query = "SELECT gm.*, 
          u.full_name as sender_name, 
          u.profile_picture as sender_picture
          FROM group_messages gm
          JOIN users u ON gm.sender_id = u.user_id
          WHERE gm.group_id = ?
          ORDER BY gm.created_at ASC";

$stmt = $conn->prepare($query);
$stmt->execute([$group_id]);
$messages = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'messages' => $messages,
    'count' => count($messages)
]);
?>

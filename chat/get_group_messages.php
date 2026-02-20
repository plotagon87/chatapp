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

// Get group messages (include reply snippet, deletion, edit count, pin)
// check for pinned_messages table in case migration has not run
$pinExists = false;
try {
    $chk = $conn->query("SHOW TABLES LIKE 'pinned_messages'");
    $pinExists = (bool) $chk->fetch();
} catch (PDOException $e) {
    // ignore
}

if ($pinExists) {
    $pinSub = "(SELECT 1 FROM pinned_messages pm WHERE pm.group_message_id = gm.message_id AND pm.pinned_by = ?) AS is_pinned";
    $params = [$_SESSION['user_id'], $group_id];
} else {
    $pinSub = "0 AS is_pinned";
    $params = [$group_id];
}

$query = "SELECT gm.*, 
          u.full_name as sender_name, 
          u.profile_picture as sender_picture,
          r.message_text AS reply_text,
          rs.full_name AS reply_sender_name,
          $pinSub
          FROM group_messages gm
          JOIN users u ON gm.sender_id = u.user_id
          LEFT JOIN group_messages r ON gm.reply_to = r.message_id
          LEFT JOIN users rs ON r.sender_id = rs.user_id
          WHERE gm.group_id = ?
          ORDER BY gm.created_at ASC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$messages = $stmt->fetchAll();

// mark delivered/read for current user
if (!empty($messages)) {
    $ids = array_map(fn($m) => $m['message_id'], $messages);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $upd = $conn->prepare("UPDATE group_message_status SET is_delivered = 1, delivered_at = NOW(), is_read = 1, read_at = NOW() WHERE group_message_id IN ($placeholders) AND user_id = ?");
    $upd->execute(array_merge($ids, [$current_user_id]));
}

echo json_encode([
    'success' => true,
    'messages' => $messages,
    'count' => count($messages)
]);
?>

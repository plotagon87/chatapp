<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

header('Content-Type: application/json');

$current_user_id = $_SESSION['user_id'];
$other_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if (empty($other_user_id)) {
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit();
}

// Get messages between current user and the other user, including reply text and pin info
// guard against missing table (migration not executed yet)
$pinExists = false;
try {
    $chk = $conn->query("SHOW TABLES LIKE 'pinned_messages'");
    $pinExists = (bool) $chk->fetch();
} catch (PDOException $e) {
    // ignore
}

if ($pinExists) {
    $pinSub = "(SELECT 1 FROM pinned_messages pm WHERE pm.message_id = m.message_id AND pm.pinned_by = ?) AS is_pinned";
    $params = [$current_user_id, $current_user_id, $other_user_id, $other_user_id, $current_user_id];
} else {
    $pinSub = "0 AS is_pinned";
    // When the pin column is omitted we don't bind the first parameter
    $params = [$current_user_id, $other_user_id, $other_user_id, $current_user_id];
}

$query = "SELECT m.*, 
          sender.full_name as sender_name, 
          sender.profile_picture as sender_picture,
          receiver.full_name as receiver_name,
          -- if this message is a reply, fetch a snippet and sender
          r.message_text AS reply_text,
          r.sender_id AS reply_sender_id,
          rs.full_name AS reply_sender_name,
          -- determine if current user has pinned this message
          $pinSub
          FROM messages m
          JOIN users sender ON m.sender_id = sender.user_id
          JOIN users receiver ON m.receiver_id = receiver.user_id
          LEFT JOIN messages r ON m.reply_to = r.message_id
          LEFT JOIN users rs ON r.sender_id = rs.user_id
          WHERE (m.sender_id = ? AND m.receiver_id = ?) 
             OR (m.sender_id = ? AND m.receiver_id = ?)
          ORDER BY m.created_at ASC";

$stmt = $conn->prepare($query);
// bind parameters; it already contains pinned param if needed
$stmt->execute($params);
$messages = $stmt->fetchAll();

// Get reactions for all messages
foreach ($messages as &$message) {
    // Get reaction summary
    $reactions_stmt = $conn->prepare("
        SELECT reaction_type, COUNT(*) as count,
        GROUP_CONCAT(u.full_name ORDER BY mr.created_at SEPARATOR ', ') as users
        FROM message_reactions mr
        JOIN users u ON mr.user_id = u.user_id
        WHERE mr.message_id = ?
        GROUP BY reaction_type
    ");
    $reactions_stmt->execute([$message['message_id']]);
    $message['reactions'] = $reactions_stmt->fetchAll();
    
    // Check if current user reacted
    $user_reactions_stmt = $conn->prepare("SELECT reaction_type FROM message_reactions WHERE message_id = ? AND user_id = ?");
    $user_reactions_stmt->execute([$message['message_id'], $current_user_id]);
    $message['user_reactions'] = $user_reactions_stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Mark received messages as read
$update_stmt = $conn->prepare("UPDATE messages SET is_read = 1, read_at = NOW() WHERE receiver_id = ? AND sender_id = ? AND is_read = 0");
$update_stmt->execute([$current_user_id, $other_user_id]);

echo json_encode([
    'success' => true,
    'messages' => $messages,
    'count' => count($messages)
]);
?>
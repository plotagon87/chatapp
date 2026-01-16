<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
$reaction_type = isset($_POST['reaction_type']) ? sanitize($_POST['reaction_type']) : '';
$action = isset($_POST['action']) ? sanitize($_POST['action']) : 'add'; // add or remove

if (empty($message_id) || empty($reaction_type)) {
    echo json_encode(['success' => false, 'message' => 'Message ID and reaction type required']);
    exit();
}

// Validate reaction type
$valid_reactions = ['like', 'love', 'haha', 'wow', 'sad', 'angry'];
if (!in_array($reaction_type, $valid_reactions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid reaction type']);
    exit();
}

// Check if message exists
$check_msg = $conn->prepare("SELECT message_id FROM messages WHERE message_id = ?");
$check_msg->execute([$message_id]);
if ($check_msg->rowCount() === 0) {
    echo json_encode(['success' => false, 'message' => 'Message not found']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    if ($action === 'remove') {
        // Remove reaction
        $stmt = $conn->prepare("DELETE FROM message_reactions WHERE message_id = ? AND user_id = ? AND reaction_type = ?");
        $stmt->execute([$message_id, $user_id, $reaction_type]);
        
        $message = 'Reaction removed';
    } else {
        // Check if user already reacted with this type
        $check = $conn->prepare("SELECT reaction_id FROM message_reactions WHERE message_id = ? AND user_id = ? AND reaction_type = ?");
        $check->execute([$message_id, $user_id, $reaction_type]);
        
        if ($check->rowCount() > 0) {
            // Already reacted, remove it (toggle)
            $stmt = $conn->prepare("DELETE FROM message_reactions WHERE message_id = ? AND user_id = ? AND reaction_type = ?");
            $stmt->execute([$message_id, $user_id, $reaction_type]);
            $message = 'Reaction removed';
        } else {
            // Add new reaction
            $stmt = $conn->prepare("INSERT INTO message_reactions (message_id, user_id, reaction_type) VALUES (?, ?, ?)");
            $stmt->execute([$message_id, $user_id, $reaction_type]);
            $message = 'Reaction added';
        }
    }
    
    // Get all reactions for this message
    $reactions_stmt = $conn->prepare("
        SELECT reaction_type, COUNT(*) as count,
        GROUP_CONCAT(u.full_name ORDER BY mr.created_at SEPARATOR ', ') as users
        FROM message_reactions mr
        JOIN users u ON mr.user_id = u.user_id
        WHERE mr.message_id = ?
        GROUP BY reaction_type
    ");
    $reactions_stmt->execute([$message_id]);
    $reactions = $reactions_stmt->fetchAll();
    
    // Check if current user reacted
    $user_reactions_stmt = $conn->prepare("SELECT reaction_type FROM message_reactions WHERE message_id = ? AND user_id = ?");
    $user_reactions_stmt->execute([$message_id, $user_id]);
    $user_reactions = $user_reactions_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'reactions' => $reactions,
        'user_reactions' => $user_reactions
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

header('Content-Type: application/json');

$current_user = $_SESSION['user_id'];
$chat_with = isset($_GET['chat_with']) ? (int)$_GET['chat_with'] : 0;

if (empty($chat_with)) {
    echo json_encode(['success' => false, 'is_typing' => false]);
    exit();
}

try {
    // Check if the other user is typing to current user
    // Clear old typing statuses (older than 5 seconds)
    $conn->exec("DELETE FROM typing_status WHERE last_updated < DATE_SUB(NOW(), INTERVAL 5 SECOND)");
    
    // Check if chat partner is typing
    $stmt = $conn->prepare("
        SELECT u.full_name, ts.is_typing 
        FROM typing_status ts
        JOIN users u ON ts.user_id = u.user_id
        WHERE ts.user_id = ? 
        AND ts.chat_with = ? 
        AND ts.is_typing = 1
        AND ts.last_updated >= DATE_SUB(NOW(), INTERVAL 5 SECOND)
    ");
    
    $stmt->execute([$chat_with, $current_user]);
    $result = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'is_typing' => $result ? true : false,
        'user_name' => $result ? $result['full_name'] : null
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'is_typing' => false,
        'error' => $e->getMessage()
    ]);
}
?>
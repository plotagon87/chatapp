<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$user_id = $_SESSION['user_id'];
$chat_with = isset($_POST['chat_with']) ? (int)$_POST['chat_with'] : 0;
$is_typing = isset($_POST['is_typing']) ? (bool)$_POST['is_typing'] : false;

if (empty($chat_with)) {
    echo json_encode(['success' => false, 'message' => 'Chat partner required']);
    exit();
}

// Create typing_status table if it doesn't exist
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS typing_status (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        chat_with INT NOT NULL,
        is_typing BOOLEAN DEFAULT 0,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_typing (user_id, chat_with),
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (chat_with) REFERENCES users(user_id) ON DELETE CASCADE
    )");
} catch (PDOException $e) {
    // Table might already exist, continue
}

// Update or insert typing status
$stmt = $conn->prepare("
    INSERT INTO typing_status (user_id, chat_with, is_typing, last_updated) 
    VALUES (?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE 
        is_typing = VALUES(is_typing),
        last_updated = NOW()
");

if ($stmt->execute([$user_id, $chat_with, $is_typing ? 1 : 0])) {
    echo json_encode([
        'success' => true,
        'is_typing' => $is_typing
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update typing status'
    ]);
}
?>
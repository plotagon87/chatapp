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
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit();
}

$message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
$is_group = isset($_POST['is_group']) && $_POST['is_group'] === '1';

if (!$message_id) {
    echo json_encode(['success' => false, 'message' => 'Message id required']);
    exit();
}

// determine table
$table = $is_group ? 'group_messages' : 'messages';

// ensure the pinned_messages table exists (might not yet if migrations haven't run)
try {
    $conn->query("SELECT 1 FROM pinned_messages LIMIT 1");
} catch (PDOException $e) {
    // attempt to create the table automatically; FALLBACK to error if that fails
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS pinned_messages (
            pin_id INT PRIMARY KEY AUTO_INCREMENT,
            message_id INT NULL,
            group_message_id INT NULL,
            pinned_by INT NOT NULL,
            pinned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (message_id) REFERENCES messages(message_id) ON DELETE CASCADE,
            FOREIGN KEY (group_message_id) REFERENCES group_messages(message_id) ON DELETE CASCADE,
            FOREIGN KEY (pinned_by) REFERENCES users(user_id) ON DELETE CASCADE,
            UNIQUE KEY unique_pin (message_id, group_message_id, pinned_by)
        )");
        // if creation succeeds nothing else to do
    } catch (PDOException $inner) {
        echo json_encode(['success' => false, 'message' => 'Pinned messages feature not available; missing database table. Run migrations.']);
        exit();
    }
}

// verify message exists and user is sender
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

// toggle pin entry
$check = $conn->prepare("SELECT pin_id FROM pinned_messages WHERE " . ($is_group ? "group_message_id" : "message_id") . " = ? AND pinned_by = ?");
$check->execute([$message_id, $_SESSION['user_id']]);
if ($check->rowCount() > 0) {
    // unpin
    $del = $conn->prepare("DELETE FROM pinned_messages WHERE " . ($is_group ? "group_message_id" : "message_id") . " = ? AND pinned_by = ?");
    $del->execute([$message_id, $_SESSION['user_id']]);
    echo json_encode(['success' => true, 'pinned' => false]);
} else {
    $ins = $conn->prepare("INSERT INTO pinned_messages (" . ($is_group ? "group_message_id" : "message_id") . ", pinned_by) VALUES (?, ?)");
    $ins->execute([$message_id, $_SESSION['user_id']]);
    echo json_encode(['success' => true, 'pinned' => true]);
}
?>
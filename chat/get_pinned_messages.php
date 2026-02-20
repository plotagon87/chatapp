<?php
require_once '../includes/config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$me = $_SESSION['user_id'];
$is_group = isset($_GET['is_group']) && $_GET['is_group'] == '1';

// make sure the pinned_messages table is present, otherwise just return
try {
    $conn->query("SELECT 1 FROM pinned_messages LIMIT 1");
} catch (PDOException $e) {
    echo json_encode(['success' => true, 'messages' => []]);
    exit;
}

try {
    if ($is_group) {
        $group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;
        if (!$group_id) {
            echo json_encode(['success' => false, 'message' => 'Missing group_id']);
            exit;
        }
        // make sure user is a member
        $mem = $conn->prepare("SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?");
        $mem->execute([$group_id, $me]);
        if (!$mem->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Not a group member']);
            exit;
        }

        $stmt = $conn->prepare("
            SELECT gm.message_id, gm.sender_id, gm.message_text, gm.created_at,
                   u.full_name AS sender_name
            FROM pinned_messages pm
            JOIN group_messages gm ON pm.group_message_id = gm.message_id
            JOIN users u ON gm.sender_id = u.user_id
            WHERE pm.pinned_by = ? AND gm.group_id = ?
            ORDER BY pm.pinned_at DESC
        ");
        $stmt->execute([$me, $group_id]);
        $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $chat_with = isset($_GET['chat_with']) ? intval($_GET['chat_with']) : 0;
        if (!$chat_with) {
            echo json_encode(['success' => false, 'message' => 'Missing chat_with']);
            exit;
        }
        $stmt = $conn->prepare("
            SELECT m.message_id, m.sender_id, m.receiver_id, m.message_text, m.created_at,
                   u.full_name AS sender_name
            FROM pinned_messages pm
            JOIN messages m ON pm.message_id = m.message_id
            JOIN users u ON m.sender_id = u.user_id
            WHERE pm.pinned_by = ?
              AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
            ORDER BY pm.pinned_at DESC
        ");
        $stmt->execute([$me, $me, $chat_with, $chat_with, $me]);
        $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode(['success' => true, 'messages' => $msgs]);
} catch (Exception $e) {
    error_log('Pinned messages error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

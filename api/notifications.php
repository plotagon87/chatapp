<?php
require_once '../includes/config.php';
requireLogin();

header('Content-Type: application/json');

// Get unread notifications count
if (isset($_GET['count_only'])) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $data = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'unread_count' => $data['count']
    ]);
    exit();
}

// Get all notifications
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
$stmt->execute([$_SESSION['user_id'], $limit]);

$notifications = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'count' => count($notifications)
]);
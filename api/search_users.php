<?php
require_once '../includes/config.php';
requireLogin();

header('Content-Type: application/json');

$search_term = isset($_GET['q']) ? sanitize($_GET['q']) : '';

if (empty($search_term)) {
    echo json_encode(['success' => false, 'message' => 'Search term is required']);
    exit();
}

// Search users by username, full name, or email
$search_pattern = "%$search_term%";
$stmt = $conn->prepare("SELECT user_id, username, full_name, email, profile_picture, status, last_seen, custom_status 
    FROM users 
    WHERE user_id != ? 
    AND (username LIKE ? OR full_name LIKE ? OR email LIKE ?)
    ORDER BY status DESC, full_name ASC 
    LIMIT 20");
$stmt->execute([$_SESSION['user_id'], $search_pattern, $search_pattern, $search_pattern]);

$users = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'users' => $users,
    'count' => count($users)
]);
?>
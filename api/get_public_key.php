<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
header('Content-Type: application/json');

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if (!$user_id) {
    echo json_encode(['success'=>false,'message'=>'missing user']);
    exit;
}

$stmt = $conn->prepare("SELECT public_key FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$row = $stmt->fetch();

if ($row && $row['public_key']) {
    echo json_encode(['success'=>true,'public_key'=>$row['public_key']]);
} else {
    echo json_encode(['success'=>false,'message'=>'no key']);
}

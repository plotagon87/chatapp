<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Invalid request']);
    exit;
}

// Accept JSON payload or form data
$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data) {
    $data = $_POST;
}

$csrf = isset($data['csrf_token']) ? $data['csrf_token'] : '';
if (!validateCsrfToken($csrf)) {
    echo json_encode(['success'=>false,'message'=>'Invalid CSRF token']);
    exit;
}

$pub = isset($data['public_key']) ? $data['public_key'] : '';

if (!$pub) {
    echo json_encode(['success'=>false,'message'=>'No public key provided']);
    exit;
}

// store directly
$stmt = $conn->prepare("UPDATE users SET public_key = ? WHERE user_id = ?");
if ($stmt->execute([$pub, $_SESSION['user_id']])) {
    // also update session or cache if needed
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false,'message'=>'Database error']);
}

<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Validate CSRF token for security
$csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (!validateCsrfToken($csrf_token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit();
}

$file = $_FILES['file'];
$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;

if (empty($group_id)) {
    echo json_encode(['success' => false, 'message' => 'Group ID is required']);
    exit();
}

// Check if user is a member of the group
$member_stmt = $conn->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ?");
$member_stmt->execute([$group_id, $_SESSION['user_id']]);

// Also check if user is the creator
$creator_stmt = $conn->prepare("SELECT created_by FROM group_chats WHERE group_id = ?");
$creator_stmt->execute([$group_id]);
$creator = $creator_stmt->fetch();

if ($member_stmt->rowCount() === 0 && (!$creator || $creator['created_by'] != $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You are not a member of this group']);
    exit();
}

// SECURITY: Validate file size
if ($file['size'] > MAX_FILE_SIZE) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds limit (10MB)']);
    exit();
}

// SECURITY: Get real file extension
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// SECURITY: Validate file type
if (!in_array($file_extension, ALLOWED_FILE_TYPES)) {
    echo json_encode(['success' => false, 'message' => 'File type not allowed. Allowed: ' . implode(', ', ALLOWED_FILE_TYPES)]);
    exit();
}

// SECURITY: Additional MIME type check
$allowed_mime_types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'txt' => 'text/plain',
    'zip' => 'application/zip',
    // audio mime types for voice
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav',
    'ogg' => 'audio/ogg',
    'm4a' => 'audio/mp4',
    'flac' => 'audio/flac',
    'webm' => 'audio/webm'
];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$detected_mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (isset($allowed_mime_types[$file_extension])) {
    $expected_mime = $allowed_mime_types[$file_extension];
    if (strpos($detected_mime, explode('/', $expected_mime)[0]) === false) {
        echo json_encode(['success' => false, 'message' => 'File content does not match extension']);
        exit();
    }
}

// Determine message type
$message_type = 'file';
if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
    $message_type = 'image';
    $upload_dir = UPLOAD_PATH . 'images/';
} elseif (in_array($file_extension, ['mp3','wav','ogg','m4a','flac','webm'])) {
    $message_type = 'voice';
    $upload_dir = UPLOAD_PATH . 'voice/';
} else {
    $upload_dir = UPLOAD_PATH . 'files/';
}

// Create upload directory if needed
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename
$unique_id = uniqid() . '_' . bin2hex(random_bytes(8));
$new_filename = $unique_id . '.' . $file_extension;
$upload_path = $upload_dir . $new_filename;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $upload_path)) {
    $relative_path = ($message_type === 'image' ? 'images/' : 'files/') . $new_filename;
    $original_filename = sanitize(basename($file['name']));
    
    $sender_id = $_SESSION['user_id'];
    $message_text = "Shared a file: " . $original_filename;
    
    $stmt = $conn->prepare("INSERT INTO group_messages (group_id, sender_id, message_text, message_type, file_path) VALUES (?, ?, ?, ?, ?)");
    
    if ($stmt->execute([$group_id, $sender_id, $message_text, $message_type, $relative_path])) {
        $message_id = $conn->lastInsertId();
        
        logActivity($sender_id, "Uploaded file to group ID: $group_id");
        
        echo json_encode([
            'success' => true,
            'message' => 'File uploaded successfully',
            'message_id' => $message_id,
            'file_path' => $relative_path,
            'file_type' => $message_type
        ]);
    } else {
        unlink($upload_path);
        echo json_encode(['success' => false, 'message' => 'Failed to save file information']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
}
?>

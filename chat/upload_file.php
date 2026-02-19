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
$receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;

if (empty($receiver_id)) {
    echo json_encode(['success' => false, 'message' => 'Receiver ID is required']);
    exit();
}

// SECURITY: Validate file size
if ($file['size'] > MAX_FILE_SIZE) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds limit (10MB)']);
    exit();
}

// SECURITY: Get real file extension (not from filename!)
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// SECURITY: Validate file type (very important!)
if (!in_array($file_extension, ALLOWED_FILE_TYPES)) {
    echo json_encode(['success' => false, 'message' => 'File type not allowed. Allowed: ' . implode(', ', ALLOWED_FILE_TYPES)]);
    exit();
}

// SECURITY: Additional check - verify MIME type matches extension
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
    // audio mime types for voice messages
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav',
    'ogg' => 'audio/ogg',
    'm4a' => 'audio/mp4',
    'flac' => 'audio/flac',
    'webm' => 'audio/webm'
];

// Get actual file MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$detected_mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

// Check if detected MIME matches expected MIME for this extension
if (isset($allowed_mime_types[$file_extension])) {
    $expected_mime = $allowed_mime_types[$file_extension];
    // Some files might have alternate MIME types, so we check loosely
    if (strpos($detected_mime, explode('/', $expected_mime)[0]) === false) {
        echo json_encode(['success' => false, 'message' => 'File content does not match extension']);
        exit();
    }
}

// Determine message type based on extension
$message_type = 'file';
// treat image, audio (voice) and default file separately
if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
    $message_type = 'image';
    $upload_dir = UPLOAD_PATH . 'images/';
} elseif (in_array($file_extension, ['mp3','wav','ogg','m4a','flac','webm'])) {
    // audio formats are considered "voice" messages
    $message_type = 'voice';
    $upload_dir = UPLOAD_PATH . 'voice/';
} else {
    $upload_dir = UPLOAD_PATH . 'files/';
}

// Create upload directory if it doesn't exist
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// SECURITY: Generate unique filename to prevent overwrites and path traversal
// Use uniqid + random bytes for extra uniqueness
$unique_id = uniqid() . '_' . bin2hex(random_bytes(8));
$new_filename = $unique_id . '.' . $file_extension;
$upload_path = $upload_dir . $new_filename;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $upload_path)) {
    // Store relative path in database
    $relative_path = ($message_type === 'image' ? 'images/' : 'files/') . $new_filename;
    
    // SECURITY: Sanitize the original filename for display
    $original_filename = sanitize(basename($file['name']));
    
    // Insert message with file
    $sender_id = $_SESSION['user_id'];
    $message_text = "Shared a file: " . $original_filename;
    
    $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, message_type, file_path) VALUES (?, ?, ?, ?, ?)");
    
    if ($stmt->execute([$sender_id, $receiver_id, $message_text, $message_type, $relative_path])) {
        $message_id = $conn->lastInsertId();
        
        // Create notification
        $sender_name = $_SESSION['full_name'] ?? 'User';
        $notification_content = "$sender_name shared a file with you";
        createNotification($receiver_id, 'message', $notification_content, $message_id);
        
        // Log activity
        logActivity($sender_id, "Uploaded file to user ID: $receiver_id");
        
        echo json_encode([
            'success' => true,
            'message' => 'File uploaded successfully',
            'message_id' => $message_id,
            'file_path' => $relative_path,
            'file_type' => $message_type
        ]);
    } else {
        // Delete uploaded file if database insert fails
        unlink($upload_path);
        echo json_encode(['success' => false, 'message' => 'Failed to save file information']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
}
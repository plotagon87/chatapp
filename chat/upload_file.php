<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
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

// Validate file size
if ($file['size'] > MAX_FILE_SIZE) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds limit (10MB)']);
    exit();
}

// Get file extension
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// Validate file type
if (!in_array($file_extension, ALLOWED_FILE_TYPES)) {
    echo json_encode(['success' => false, 'message' => 'File type not allowed']);
    exit();
}

// Determine file type category
$message_type = 'file';
if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
    $message_type = 'image';
    $upload_dir = UPLOAD_PATH . 'images/';
} else {
    $upload_dir = UPLOAD_PATH . 'files/';
}

// Create upload directory if it doesn't exist
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename
$new_filename = uniqid() . '_' . time() . '.' . $file_extension;
$upload_path = $upload_dir . $new_filename;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $upload_path)) {
    // Store relative path in database
    $relative_path = ($message_type === 'image' ? 'images/' : 'files/') . $new_filename;
    
    // Insert message with file
    $sender_id = $_SESSION['user_id'];
    $message_text = "Shared a file: " . $file['name'];
    
    $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, message_type, file_path) VALUES (?, ?, ?, ?, ?)");
    
    if ($stmt->execute([$sender_id, $receiver_id, $message_text, $message_type, $relative_path])) {
        $message_id = $conn->lastInsertId();
        
        // Create notification
        $sender_name = $_SESSION['full_name'];
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
?>

<?php
// ==========================================
// chat/upload_file.php - COMPLETE WORKING VERSION
// Purpose: Handle file uploads with category support
// ==========================================

// Include configuration and authentication
require_once __DIR__ . '/../includes/config.php';

// Ensure user is logged in (this function checks session)
requireLogin();

// Set response header to JSON (so JavaScript can parse it)
header('Content-Type: application/json');

// ==========================================
// STEP 1: VALIDATE REQUEST METHOD
// ==========================================
// Only accept POST requests (GET, PUT, DELETE are not allowed)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Send error response and stop execution
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method. Only POST is allowed.'
    ]);
    exit(); // Stop script execution
}

// ==========================================
// STEP 2: VALIDATE FILE UPLOAD
// ==========================================
// Check if file was uploaded without errors
// $_FILES is a PHP superglobal array containing uploaded file info
if (!isset($_FILES['file'])) {
    echo json_encode([
        'success' => false,
        'message' => 'No file was uploaded. Please select a file.'
    ]);
    exit();
}

// Check for upload errors (PHP automatically detects these)
if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $error_messages = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
    ];
    
    $error_code = $_FILES['file']['error'];
    $error_msg = isset($error_messages[$error_code]) 
                 ? $error_messages[$error_code] 
                 : 'Unknown upload error';
    
    echo json_encode([
        'success' => false,
        'message' => $error_msg
    ]);
    exit();
}

// ==========================================
// STEP 3: GET AND VALIDATE INPUT DATA
// ==========================================

// Get the uploaded file information
$file = $_FILES['file'];

// Get receiver ID (who will receive this file)
// (int) casts to integer to prevent SQL injection
$receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;

// Get file category (photos, documents, audio, other)
// htmlspecialchars() prevents XSS (Cross-Site Scripting) attacks
$category = isset($_POST['category']) 
            ? htmlspecialchars($_POST['category'], ENT_QUOTES, 'UTF-8') 
            : 'other';

// Validate receiver ID exists
if (empty($receiver_id) || $receiver_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid receiver ID. Please select a user to send to.'
    ]);
    exit();
}

// Get sender ID from session (current logged-in user)
$sender_id = $_SESSION['user_id'];

// ==========================================
// STEP 4: VALIDATE FILE SIZE
// ==========================================
// Define maximum file size (10MB = 10 * 1024 * 1024 bytes)
$max_file_size = 10 * 1024 * 1024; // 10 MB in bytes

if ($file['size'] > $max_file_size) {
    // Calculate size in MB for user-friendly message
    $size_mb = round($file['size'] / (1024 * 1024), 2);
    
    echo json_encode([
        'success' => false,
        'message' => "File is too large ({$size_mb}MB). Maximum allowed size is 10MB."
    ]);
    exit();
}

// ==========================================
// STEP 5: VALIDATE FILE TYPE BY CATEGORY
// ==========================================

// Get file extension (e.g., 'jpg' from 'photo.jpg')
// pathinfo() extracts information about a file path
// PATHINFO_EXTENSION gets just the extension part
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// Define allowed extensions for each category
// This prevents users from uploading dangerous file types
$allowed_extensions = [
    'photos' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
    'documents' => ['pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'rtf'],
    'audio' => ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac'],
    'other' => ['zip', 'rar', '7z', 'tar', 'gz']
];

// Check if category exists and file extension is allowed
if (!isset($allowed_extensions[$category])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid category. Please select a valid file category.'
    ]);
    exit();
}

if (!in_array($file_extension, $allowed_extensions[$category])) {
    // Build list of allowed extensions for error message
    $allowed_list = implode(', ', $allowed_extensions[$category]);
    
    echo json_encode([
        'success' => false,
        'message' => "Invalid file type for {$category}. Allowed types: {$allowed_list}"
    ]);
    exit();
}

// ==========================================
// STEP 6: VALIDATE MIME TYPE (Extra Security)
// ==========================================
// MIME type = Multipurpose Internet Mail Extensions type
// It's the file's actual content type (can't be faked easily)

// Get MIME type from uploaded file
$mime_type = mime_content_type($file['tmp_name']);

// Define allowed MIME types for each category
$allowed_mimes = [
    'photos' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'],
    'documents' => [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation'
    ],
    'audio' => ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4', 'audio/aac', 'audio/flac'],
    'other' => [
        'application/zip',
        'application/x-rar-compressed',
        'application/x-7z-compressed',
        'application/x-tar',
        'application/gzip'
    ]
];

// Validate MIME type matches category
if (!in_array($mime_type, $allowed_mimes[$category])) {
    echo json_encode([
        'success' => false,
        'message' => 'File content type does not match the selected category. Upload failed for security reasons.'
    ]);
    exit();
}

// ==========================================
// STEP 7: DETERMINE MESSAGE TYPE
// ==========================================
// Map category to database message type
$message_type_map = [
    'photos' => 'image',        // Will display as image in chat
    'documents' => 'file',      // Will display as downloadable file
    'audio' => 'voice',         // Will display as audio player
    'other' => 'file'           // Will display as downloadable file
];

$message_type = $message_type_map[$category];

// ==========================================
// STEP 8: SETUP UPLOAD DIRECTORY
// ==========================================
// Determine folder based on message type
if ($message_type === 'image') {
    $upload_dir = __DIR__ . '/../uploads/images/';
} elseif ($message_type === 'voice') {
    $upload_dir = __DIR__ . '/../uploads/voice/';
} else {
    $upload_dir = __DIR__ . '/../uploads/files/';
}

// Create directory if it doesn't exist
// 0755 = permissions (owner can read/write/execute, others can read/execute)
// true = create parent directories if needed
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create upload directory. Please contact administrator.'
        ]);
        exit();
    }
}

// ==========================================
// STEP 9: GENERATE UNIQUE FILENAME
// ==========================================
// Create unique filename to prevent conflicts and overwrites
// Format: uniqueid_timestamp_originalname.extension
// uniqid() generates a unique ID based on current time in microseconds
$unique_id = uniqid('', true); // true = more entropy (randomness)
$timestamp = time(); // Current Unix timestamp
$safe_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
$new_filename = $unique_id . '_' . $timestamp . '_' . $safe_filename . '.' . $file_extension;

// Full path where file will be saved
$upload_path = $upload_dir . $new_filename;

// ==========================================
// STEP 10: UPLOAD FILE TO SERVER
// ==========================================
// move_uploaded_file() is the ONLY safe way to move uploaded files
// It verifies the file was actually uploaded via HTTP POST
if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save file to server. Please try again.'
    ]);
    exit();
}

// ==========================================
// STEP 11: SAVE TO DATABASE
// ==========================================

// Calculate relative path (path from uploads folder)
if ($message_type === 'image') {
    $relative_path = 'images/' . $new_filename;
} elseif ($message_type === 'voice') {
    $relative_path = 'voice/' . $new_filename;
} else {
    $relative_path = 'files/' . $new_filename;
}

// Create message text (what displays in chat)
$original_filename = basename($file['name']);
$message_text = "📎 Shared " . $category . ": " . $original_filename;

// Prepare SQL statement (prevents SQL injection)
// ? = placeholder for values (will be bound safely)
$stmt = $conn->prepare("
    INSERT INTO messages 
    (sender_id, receiver_id, message_text, message_type, file_path, created_at) 
    VALUES (?, ?, ?, ?, ?, NOW())
");

// Check if statement preparation succeeded
if (!$stmt) {
    // If database query fails, delete uploaded file (cleanup)
    unlink($upload_path);
    
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $conn->error
    ]);
    exit();
}

// Bind parameters to SQL statement (safe way to insert data)
// "iisss" = data types: i=integer, s=string
$stmt->bind_param("iisss", $sender_id, $receiver_id, $message_text, $message_type, $relative_path);

// Execute the statement
if (!$stmt->execute()) {
    // If insert fails, delete uploaded file (cleanup)
    unlink($upload_path);
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save message: ' . $stmt->error
    ]);
    exit();
}

// Get the ID of the inserted message
$message_id = $stmt->insert_id;

// Close statement (free up resources)
$stmt->close();

// ==========================================
// STEP 12: CREATE NOTIFICATION (Optional)
// ==========================================
// Notify receiver they have a new file
// This function should be defined in config.php or functions.php

if (function_exists('createNotification')) {
    $sender_name = $_SESSION['full_name'] ?? 'Someone';
    $notification_text = "{$sender_name} sent you a file";
    createNotification($receiver_id, 'message', $notification_text, $message_id);
}

// ==========================================
// STEP 13: LOG ACTIVITY (Optional)
// ==========================================
// Keep track of file uploads for security/debugging

if (function_exists('logActivity')) {
    logActivity($sender_id, "Uploaded {$category} file: {$original_filename} to user ID: {$receiver_id}");
}

// ==========================================
// STEP 14: SEND SUCCESS RESPONSE
// ==========================================
echo json_encode([
    'success' => true,
    'message' => 'File uploaded successfully!',
    'data' => [
        'message_id' => $message_id,
        'file_path' => $relative_path,
        'file_type' => $message_type,
        'category' => $category,
        'filename' => $original_filename,
        'file_size' => $file['size']
    ]
]);

exit();
?>
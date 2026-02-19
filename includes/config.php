<?php
/**
 * Database Configuration File
 * LAN Chat Application - PDO Version
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure a CSRF token is available for forms and XHR
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        // fallback
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(mt_rand());
        }
    }
}

// Database Configuration
// values may be overridden by environment variables (useful for Docker)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'lan_chat_db');

// Application Configuration
// Construct BASE_URL dynamically from the current request
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . ($basePath === '' || $basePath === '/' ? '' : $basePath) . '/');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 10485760); // 10MB
// allowed file extensions (used for upload validation).
// audio files are included so users can send/record voice messages.
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'txt', 'zip', 'mp3', 'wav', 'ogg', 'm4a', 'flac', 'webm']);

// Create PDO database connection
try {
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
 
// Load system settings into $settings (non-fatal if table doesn't exist yet)
$settings = [];
try {
    $stmt = $conn->query("SHOW TABLES LIKE 'system_settings'");
    if ($stmt && $stmt->fetch()) {
        $sstmt = $conn->query("SELECT setting_key, setting_value FROM system_settings");
        $rows = $sstmt->fetchAll();
        foreach ($rows as $r) {
            $settings[$r['setting_key']] = $r['setting_value'];
        }
    }
} catch (PDOException $e) {
    // ignore - table may not exist yet
}

/**
 * Helper to read settings loaded from `system_settings` table
 */
function getSetting($key, $default = null) {
    global $settings;
    return isset($settings[$key]) ? $settings[$key] : $default;
}

/**
 * Validate CSRF Token
 */
function validateCsrfToken($token) {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input data
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user is admin
 */
function isAdmin() {
    if (!isset($_SESSION['user_id'])) return false;
    $user = getUserData($_SESSION['user_id']);
    return ($user && $user['role'] === 'admin');
}

/**
 * Redirect to login if not authenticated
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit();
    }
}

/**
 * Redirect to dashboard if already logged in
 */
function redirectIfLoggedIn() {
    if (isLoggedIn()) {
        header('Location: dashboard.php');
        exit();
    }
}

/**
 * Update user's last seen timestamp
 */
function updateLastSeen($user_id) {
    global $conn;
    $stmt = $conn->prepare("UPDATE users SET last_seen = NOW() WHERE user_id = ?");
    $stmt->execute([$user_id]);
}

/**
 * Update user's online status
 */
function updateUserStatus($user_id, $status) {
    global $conn;
    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
    $stmt->execute([$status, $user_id]);
}

/**
 * Log user activity
 */
function logActivity($user_id, $action) {
    global $conn;
    $ip = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, ip_address, user_agent) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $action, $ip, $user_agent]);
}

/**
 * Get user data by ID
 */
function getUserData($user_id) {
    global $conn;
    // include public_key for E2EE support (nullable)
    $stmt = $conn->prepare(
        "SELECT user_id, username, email, full_name, profile_picture, role, status, custom_status, theme_preference, last_seen, created_at, public_key FROM users WHERE user_id = ?"
    );
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

/**
 * Format time ago
 */
function timeAgo($timestamp) {
    $time = strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return "just now";
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . " min" . ($mins > 1 ? "s" : "") . " ago";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    } else {
        return date("M d, Y", $time);
    }
}

/**
 * Create notification
 *
 * @param int $user_id
 * @param string $type one of 'message', 'group_invite', 'announcement', 'system', or 'presentation'
 * @param string $content HTML/text to display
 * @param int|null $related_id optional related record (message id, presentation id, etc.)
 */
function createNotification($user_id, $type, $content, $related_id = null) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, notification_type, content, related_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $type, $content, $related_id]);
}

// Update user status to online when they access any page (if logged in)
if (isLoggedIn()) {
    updateUserStatus($_SESSION['user_id'], 'online');
    updateLastSeen($_SESSION['user_id']);
}
?>

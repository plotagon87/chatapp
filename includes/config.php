<?php
// Minimal application config and helpers
// Starts session, creates a PDO $conn, and defines small helper functions used across pages.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection (assuming default XAMPP MySQL settings). Adjust credentials as needed.
try {
    $dbHost = '127.0.0.1';
    $dbName = 'lan_chat_db';
    $dbUser = 'root';
    $dbPass = '';
    $conn = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    // In production, log error instead of displaying
    die('Database connection failed: ' . $e->getMessage());
}

// Basic sanitizer
function sanitize($value) {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

// If user is already logged in, redirect to dashboard
function redirectIfLoggedIn() {
    if (!empty($_SESSION['user_id'])) {
        header('Location: dashboard.php');
        exit();
    }
}

// Stub: update user status in DB
function updateUserStatus($userId, $status) {
    global $conn;
    try {
        $stmt = $conn->prepare('UPDATE users SET status = ? WHERE user_id = ?');
        $stmt->execute([$status, $userId]);
    } catch (Exception $e) {
        // ignore failures for now
    }
}

// Stub: log activity to a simple table or file
function logActivity($userId, $action) {
    global $conn;
    try {
        $stmt = $conn->prepare('INSERT INTO activity_log (user_id, action, created_at) VALUES (?, ?, NOW())');
        $stmt->execute([$userId, $action]);
    } catch (Exception $e) {
        // ignore failures - optional: write to file
    }
}

// Ensure the helper functions are available when this file is included
return;
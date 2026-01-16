<?php
require_once 'includes/config.php';
requireLogin();

$user = getUserData($_SESSION['user_id']);
$success = '';
$error = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All password fields are required';
    } elseif (strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match';
    } else {
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch();
        
        if (password_verify($current_password, $user_data['password'])) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            
            if ($update_stmt->execute([$hashed_password, $_SESSION['user_id']])) {
                $success = 'Password changed successfully';
                logActivity($_SESSION['user_id'], 'Changed password');
            } else {
                $error = 'Failed to change password';
            }
        } else {
            $error = 'Current password is incorrect';
        }
    }
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $status = sanitize($_POST['status']);
    $custom_status = sanitize($_POST['custom_status']);
    
    $stmt = $conn->prepare("UPDATE users SET status = ?, custom_status = ? WHERE user_id = ?");
    
    if ($stmt->execute([$status, $custom_status, $_SESSION['user_id']])) {
        $success = 'Status updated successfully';
        $user = getUserData($_SESSION['user_id']);
    } else {
        $error = 'Failed to update status';
    }
}

// Handle theme change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_theme'])) {
    $theme = sanitize($_POST['theme_preference']);
    
    $stmt = $conn->prepare("UPDATE users SET theme_preference = ? WHERE user_id = ?");
    
    if ($stmt->execute([$theme, $_SESSION['user_id']])) {
        $success = 'Theme updated successfully';
        $user = getUserData($_SESSION['user_id']);
    } else {
        $error = 'Failed to update theme';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Settings - LAN Chat</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <!-- Top Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="flex items-center space-x-2">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        <span class="text-xl font-bold text-gray-800">LAN Chat</span>
                    </a>
                </div>
                <a href="dashboard.php" class="text-purple-600 hover:text-purple-800 font-semibold">
                    ← Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-4xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">Settings</h1>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <div class="space-y-6">
            <!-- Status Settings -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <svg class="w-6 h-6 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Status & Availability
                </h2>
                <form method="POST">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                Your Status
                            </label>
                            <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                                <option value="online" <?php echo $user['status'] === 'online' ? 'selected' : ''; ?>>🟢 Online</option>
                                <option value="away" <?php echo $user['status'] === 'away' ? 'selected' : ''; ?>>🟡 Away</option>
                                <option value="busy" <?php echo $user['status'] === 'busy' ? 'selected' : ''; ?>>🔴 Busy</option>
                                <option value="offline" <?php echo $user['status'] === 'offline' ? 'selected' : ''; ?>>⚫ Offline</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                Custom Status Message
                            </label>
                            <input 
                                type="text" 
                                name="custom_status" 
                                value="<?php echo htmlspecialchars($user['custom_status'] ?? ''); ?>"
                                placeholder="e.g., In a meeting, Coding, At lunch..."
                                maxlength="100"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                            >
                        </div>
                        <button type="submit" name="update_status" class="bg-purple-600 text-white font-bold py-2 px-6 rounded-lg hover:bg-purple-700">
                            Update Status
                        </button>
                    </div>
                </form>
            </div>

            <!-- Theme Settings -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <svg class="w-6 h-6 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                    </svg>
                    Appearance
                </h2>
                <form method="POST">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                Theme Preference
                            </label>
                            <select name="theme_preference" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                                <option value="light" <?php echo $user['theme_preference'] === 'light' ? 'selected' : ''; ?>>☀️ Light Mode</option>
                                <option value="dark" <?php echo $user['theme_preference'] === 'dark' ? 'selected' : ''; ?>>🌙 Dark Mode</option>
                                <option value="auto" <?php echo $user['theme_preference'] === 'auto' ? 'selected' : ''; ?>>🔄 Auto (System)</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Theme customization coming soon!</p>
                        </div>
                        <button type="submit" name="update_theme" class="bg-purple-600 text-white font-bold py-2 px-6 rounded-lg hover:bg-purple-700">
                            Save Theme
                        </button>
                    </div>
                </form>
            </div>

            <!-- Security Settings -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <svg class="w-6 h-6 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                    Change Password
                </h2>
                <form method="POST">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                Current Password
                            </label>
                            <input 
                                type="password" 
                                name="current_password" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                                required
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                New Password
                            </label>
                            <input 
                                type="password" 
                                name="new_password" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                                required
                            >
                            <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                Confirm New Password
                            </label>
                            <input 
                                type="password" 
                                name="confirm_password" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                                required
                            >
                        </div>
                        <button type="submit" name="change_password" class="bg-purple-600 text-white font-bold py-2 px-6 rounded-lg hover:bg-purple-700">
                            Change Password
                        </button>
                    </div>
                </form>
            </div>

            <!-- Account Information -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <svg class="w-6 h-6 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Account Information
                </h2>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between py-2 border-b border-gray-200">
                        <span class="font-semibold text-gray-700">Username:</span>
                        <span class="text-gray-600"><?php echo htmlspecialchars($user['username']); ?></span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-gray-200">
                        <span class="font-semibold text-gray-700">Email:</span>
                        <span class="text-gray-600"><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-gray-200">
                        <span class="font-semibold text-gray-700">Role:</span>
                        <span class="text-gray-600"><?php echo ucfirst($user['role']); ?></span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-gray-200">
                        <span class="font-semibold text-gray-700">Member Since:</span>
                        <span class="text-gray-600"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
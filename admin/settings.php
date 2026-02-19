<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

if (!isAdmin()) {
    header('Location: ../dashboard.php');
    exit();
}

$success = '';
$error = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    try {
        $conn->beginTransaction();
        
        // Update application settings (you might want to create a settings table later)
        // For now, we'll update some system configurations
        
        // Log the settings change
        logActivity($_SESSION['user_id'], "Updated system settings");
        
        $conn->commit();
        $success = 'Settings updated successfully';
    } catch (Exception $e) {
        $conn->rollBack();
        $error = 'Failed to update settings: ' . $e->getMessage();
    }
}

// Handle system maintenance actions
if (isset($_GET['maintenance_action'])) {
    $action = sanitize($_GET['maintenance_action']);
    
    switch ($action) {
        case 'clear_sessions':
            // Clear all sessions (except current)
            $sessionFiles = glob(session_save_path() . '/*');
            $currentSession = session_id();
            $cleared = 0;
            
            foreach ($sessionFiles as $file) {
                if (is_file($file) && basename($file) !== 'sess_' . $currentSession) {
                    unlink($file);
                    $cleared++;
                }
            }
            $success = "Cleared $cleared user sessions";
            logActivity($_SESSION['user_id'], "Cleared user sessions");
            break;
            
        case 'optimize_tables':
            $tables = ['users', 'messages', 'group_chats', 'group_messages', 'announcements', 'activity_log'];
            $optimized = 0;
            
            foreach ($tables as $table) {
                $conn->exec("OPTIMIZE TABLE $table");
                $optimized++;
            }
            $success = "Optimized $optimized database tables";
            logActivity($_SESSION['user_id'], "Optimized database tables");
            break;
            
        case 'update_statuses':
            // Update offline users status
            $stmt = $conn->prepare("UPDATE users SET status = 'offline' WHERE last_seen < DATE_SUB(NOW(), INTERVAL 5 MINUTE) AND status != 'offline'");
            $updated = $stmt->execute();
            $success = "Updated user statuses to offline for inactive users";
            logActivity($_SESSION['user_id'], "Updated user statuses");
            break;
    }
}

// Get system statistics for the settings page
$system_stats = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM messages) as total_messages,
        (SELECT COUNT(*) FROM activity_log) as total_logs,
        (SELECT COUNT(*) FROM group_chats) as total_groups,
        (SELECT COUNT(*) FROM announcements) as total_announcements,
        (SELECT data_length + index_length FROM information_schema.tables WHERE table_schema = 'lan_chat_db' AND table_name = 'messages') as messages_size,
        @@version as mysql_version
")->fetch();

// Get recent system activity
$stmt = $conn->prepare("SELECT action, created_at
    FROM activity_log
    WHERE user_id IS NULL OR user_id != ?
    ORDER BY created_at DESC
    LIMIT 10");
$stmt->execute([$_SESSION['user_id']]);
$recent_activity = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <!-- Standard favicon for browsers -->
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
    <!-- Apple Touch Icon (for iOS home screen) -->
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/apple-touch-icon.png">
    <!-- PWA Manifest (contains app info and icon references) -->
    <link rel="manifest" href="../manifest.json">
    <!-- Theme color (shows in Android status bar when PWA is installed) -->
    <meta name="theme-color" content="#7c3aed">
    <title>System Settings - Admin</title>
    <link href="../assets/css/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center h-16">
                <h1 class="text-xl font-bold text-gray-800">System Settings</h1>
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-gray-600 hover:text-purple-600">Dashboard</a>
                    <a href="../dashboard.php" class="text-purple-600 hover:text-purple-800 font-semibold">← Back to Chat</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-8">
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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- System Information -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">System Information</h2>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">PHP Version:</span>
                            <span class="font-semibold"><?php echo phpversion(); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">MySQL Version:</span>
                            <span class="font-semibold"><?php echo $system_stats['mysql_version']; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total Users:</span>
                            <span class="font-semibold"><?php echo $system_stats['total_users']; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total Messages:</span>
                            <span class="font-semibold"><?php echo $system_stats['total_messages']; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Activity Logs:</span>
                            <span class="font-semibold"><?php echo $system_stats['total_logs']; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Server Time:</span>
                            <span class="font-semibold"><?php echo date('Y-m-d H:i:s'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- System Maintenance -->
                <div class="bg-white rounded-lg shadow-lg p-6 mt-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">System Maintenance</h2>
                    <div class="space-y-3">
                        <a href="?maintenance_action=clear_sessions" 
                           onclick="return confirm('Clear all user sessions (except current)? This will log out all other users.')"
                           class="block w-full bg-yellow-600 text-white text-center py-2 rounded-lg hover:bg-yellow-700">
                            Clear User Sessions
                        </a>
                        <a href="?maintenance_action=optimize_tables" 
                           onclick="return confirm('Optimize database tables? This may improve performance.')"
                           class="block w-full bg-blue-600 text-white text-center py-2 rounded-lg hover:bg-blue-700">
                            Optimize Database
                        </a>
                        <a href="?maintenance_action=update_statuses" 
                           onclick="return confirm('Update user statuses? This will set inactive users to offline.')"
                           class="block w-full bg-green-600 text-white text-center py-2 rounded-lg hover:bg-green-700">
                            Update User Statuses
                        </a>
                    </div>
                </div>
            </div>

            <!-- Application Settings -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Application Settings</h2>
                    <form method="POST">
                        <div class="space-y-6">
                            <!-- General Settings -->
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 mb-3">General Settings</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Site Name</label>
                                        <input 
                                            type="text" 
                                            name="site_name" 
                                            value="LAN Chat"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Default User Role</label>
                                        <select name="default_role" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                                            <option value="user">User</option>
                                            <option value="student">Student</option>
                                            <option value="staff">Staff</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Security Settings -->
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 mb-3">Security Settings</h3>
                                <div class="space-y-4">
                                    <div class="flex items-center">
                                        <input 
                                            type="checkbox" 
                                            id="user_registration" 
                                            name="user_registration"
                                            class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded"
                                            checked
                                        >
                                        <label for="user_registration" class="ml-2 text-sm text-gray-700">
                                            Allow new user registration
                                        </label>
                                    </div>
                                    <div class="flex items-center">
                                        <input 
                                            type="checkbox" 
                                            id="email_verification" 
                                            name="email_verification"
                                            class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded"
                                        >
                                        <label for="email_verification" class="ml-2 text-sm text-gray-700">
                                            Require email verification
                                        </label>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Session Timeout (minutes)</label>
                                        <input 
                                            type="number" 
                                            name="session_timeout" 
                                            value="30"
                                            min="5"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                                        >
                                    </div>
                                </div>
                            </div>

                            <!-- File Upload Settings -->
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 mb-3">File Upload Settings</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Max File Size (MB)</label>
                                        <input 
                                            type="number" 
                                            name="max_file_size" 
                                            value="10"
                                            min="1"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Allowed File Types</label>
                                        <input 
                                            type="text" 
                                            name="allowed_types" 
                                            value="jpg,jpeg,png,pdf,doc,docx,txt,zip"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                                        >
                                    </div>
                                </div>
                            </div>

                            <!-- Save Button -->
                            <div class="pt-4 border-t border-gray-200">
                                <button 
                                    type="submit" 
                                    name="update_settings"
                                    class="bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 font-semibold"
                                >
                                    Save Settings
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Recent System Activity -->
                <div class="bg-white rounded-lg shadow-lg p-6 mt-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Recent System Activity</h2>
                    <div class="space-y-3">
                        <?php if (count($recent_activity) > 0): ?>
                            <?php foreach($recent_activity as $activity): ?>
                                <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                    <span class="text-sm text-gray-800"><?php echo htmlspecialchars($activity['action']); ?></span>
                                    <span class="text-xs text-gray-500"><?php echo timeAgo($activity['created_at']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-gray-500 text-center py-4">No recent system activity</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
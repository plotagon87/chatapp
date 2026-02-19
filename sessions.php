<?php
require_once 'includes/config.php';
requireLogin();

$user = getUserData($_SESSION['user_id']);
$error = '';
$success = '';

// Handle actions: logout others or kill specific session
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token';
    } else {
        if (isset($_POST['logout_others'])) {
            clearOtherUserSessions(false);
            logActivity($_SESSION['user_id'], 'Logged out other sessions');
            $success = 'Other devices have been logged out';
        } elseif (isset($_POST['kill_session']) && !empty($_POST['session_id'])) {
            $sid = $_POST['session_id'];
            if ($sid === session_id()) {
                // can't kill current session here
            } else {
                killUserSession($sid);
                logActivity($_SESSION['user_id'], 'Terminated session ' . substr($sid,0,8));
                $success = 'Selected session terminated';
            }
        }
    }
}

$sessions = getUserSessions($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/tailwind.min.css" rel="stylesheet">
    <title>Session Management - LAN Chat</title>
</head>
<body class="bg-gray-100">
    <!-- Top nav (reuse from settings) -->
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
                <a href="settings.php" class="text-purple-600 hover:text-purple-800 font-semibold">
                    ← Back to Settings
                </a>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Session Management</h1>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="mb-6">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <button name="logout_others" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700" onclick="return confirm('Log out all other sessions?');">
                    Log out of other devices
                </button>
            </form>
        </div>

        <h2 class="text-xl font-semibold mb-4">Active Sessions</h2>
        <table class="w-full bg-white rounded-lg shadow">
            <thead>
                <tr class="bg-gray-100">
                    <th class="px-4 py-2 text-left text-sm">IP Address</th>
                    <th class="px-4 py-2 text-left text-sm">User Agent</th>
                    <th class="px-4 py-2 text-left text-sm">Last Activity</th>
                    <th class="px-4 py-2 text-left text-sm">Created</th>
                    <th class="px-4 py-2 text-left text-sm">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($sessions as $s): ?>
                    <tr class="border-t">
                        <td class="px-4 py-2 text-sm"><?php echo htmlspecialchars($s['ip_address'] ?: ''); ?></td>
                        <td class="px-4 py-2 text-sm break-words"><?php echo htmlspecialchars($s['user_agent']); ?></td>
                        <td class="px-4 py-2 text-sm"><?php echo date('Y-m-d H:i:s', strtotime($s['last_activity'])); ?></td>
                        <td class="px-4 py-2 text-sm"><?php echo date('Y-m-d H:i:s', strtotime($s['created_at'])); ?></td>
                        <td class="px-4 py-2 text-sm">
                            <?php if ($s['session_id'] !== session_id()): ?>
                                <form method="POST" class="inline-block">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="session_id" value="<?php echo htmlspecialchars($s['session_id']); ?>">
                                    <button name="kill_session" class="text-red-600 hover:underline" onclick="return confirm('Terminate this session?');">Logout</button>
                                </form>
                            <?php else: ?>
                                <span class="text-gray-500">Current</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    </div>
</body>
</html>

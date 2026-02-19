<?php
require_once 'includes/config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$error = '';
success = '';

// Handle export or deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['export_data'])) {
        // gather user data
        $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $export = [];
        $export['user'] = $stmt->fetch();

        // messages where user is sender or receiver
        $mstmt = $conn->prepare("SELECT * FROM messages WHERE sender_id = ? OR receiver_id = ?");
        $mstmt->execute([$user_id, $user_id]);
        $export['messages'] = $mstmt->fetchAll();

        // group memberships
        $gstmt = $conn->prepare("SELECT * FROM group_members WHERE user_id = ?");
        $gstmt->execute([$user_id]);
        $export['group_memberships'] = $gstmt->fetchAll();

        // notifications
        $nstmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ?");
        $nstmt->execute([$user_id]);
        $export['notifications'] = $nstmt->fetchAll();

        // other related data could be added here following cascade rules

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="user_data_' . $user_id . '.json"');
        echo json_encode($export, JSON_PRETTY_PRINT);
        exit();
    } elseif (isset($_POST['delete_account'])) {
        // delete the account (cascades will remove related rows)
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        if ($stmt->execute([$user_id])) {
            logActivity($user_id, 'User requested account deletion');
            // clear session and redirect out
            $_SESSION = [];
            session_destroy();
            header('Location: index.php');
            exit();
        } else {
            $error = 'Failed to delete account';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/tailwind.min.css" rel="stylesheet">
    <title>Privacy Dashboard - LAN Chat</title>
</head>
<body class="bg-gray-100">
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
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Privacy & Data</h1>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="mb-8">
            <form method="POST">
                <button name="export_data" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
                    Download My Data
                </button>
            </form>
        </div>

        <div class="border-t pt-6">
            <h2 class="text-xl font-semibold mb-4">Account Removal</h2>
            <form method="POST" onsubmit="return confirm('Are you sure you want to permanently delete your account and all associated data? This cannot be undone.');">
                <button name="delete_account" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                    Delete My Account
                </button>
            </form>
        </div>
    </div>
</body>
</html>

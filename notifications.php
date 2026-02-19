<?php
require_once 'includes/config.php';
requireLogin();

// fetch user, unread count
$current_user = getUserData($_SESSION['user_id']);
// count unread before marking (used for badge) and then reset to 0 after
$unread_stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
$unread_stmt->execute([$_SESSION['user_id']]);
$unread_count = $unread_stmt->fetch()['unread_count'];

// mark all as read now that the user is viewing the center
$markStmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
$markStmt->execute([$_SESSION['user_id']]);
// after marking, reset unread_count to zero for display
$unread_count = 0;

// fetch notifications list
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notifications</title>
    <link rel="stylesheet" href="assets/css/tailwind.min.css">
    <style>
        .notification-badge { background: #3b82f6 !important; }
        .notification-content a { color: #3b82f6; text-decoration: underline; }
    </style>
    <script>window.baseUrl = '<?php echo BASE_URL; ?>'; const currentUserId = <?php echo (int)$_SESSION['user_id']; ?>;</script>
</head>
<body class="bg-gray-100">
    <!-- top navigation bar (copied from dashboard.php) -->
    <nav class="bg-white shadow-lg fixed top-0 left-0 right-0 z-30">
        <div class="px-4">
            <div class="flex justify-between items-center h-16">
                
                <!-- LEFT: Hamburger menu (MOBILE ONLY) -->
                <button class="sidebar-toggle lg:hidden text-gray-600 hover:text-purple-600 p-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                
                <!-- CENTER: Logo and App Name -->
                <div class="flex items-center space-x-2 lg:space-x-4">
                    <svg class="w-6 h-6 lg:w-8 lg:h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                    <span class="text-lg lg:text-xl font-bold text-gray-800">LAN Chat</span>
                </div>
                
                <!-- RIGHT: Notifications and User Menu -->
                <div class="flex items-center space-x-2 lg:space-x-6">
                    <!-- Notification Bell -->
                    <div class="relative">
                        <a href="notifications.php" class="text-gray-600 hover:text-purple-600 relative p-2">
                            <svg class="w-5 h-5 lg:w-6 lg:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                            </svg>
                            <?php if ($unread_count > 0): ?>
                                <span class="notification-badge"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                    
                    <!-- User Profile -->
                    <div class="flex items-center space-x-2 lg:space-x-3">
                        <img src="uploads/profiles/<?php echo htmlspecialchars($current_user['profile_picture']); ?>" 
                             alt="Profile" 
                             class="w-8 h-8 lg:w-10 lg:h-10 rounded-full border-2 border-purple-500"
                             onerror="this.src='assets/images/default.png'">
                        
                        <!-- User name - HIDDEN ON MOBILE -->
                        <div class="hidden md:block">
                            <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($current_user['full_name']); ?></p>
                            <p class="text-xs text-gray-500"><?php echo ucfirst($current_user['role']); ?></p>
                        </div>
                        
                        <!-- Dropdown Arrow -->
                        <div class="relative z-50">
                            <button id="userMenuBtn" type="button" class="text-gray-600 hover:text-purple-600 p-2 focus:outline-none focus:ring-2 focus:ring-purple-300 rounded cursor-pointer transition-colors" aria-haspopup="true" aria-expanded="false">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div id="userMenu" class="menu-hidden absolute right-0 mt-1 w-48 bg-white rounded-lg shadow-2xl z-50 border border-gray-200 top-full">
                                <div class="py-1">
                                    <a href="profile.php" class="block px-4 py-3 text-sm text-gray-700 hover:bg-purple-50 hover:text-purple-900 transition-colors">Profile</a>
                                    <a href="settings.php" class="block px-4 py-3 text-sm text-gray-700 hover:bg-purple-50 hover:text-purple-900 transition-colors">Settings</a>
                                    <a href="presentation_settings.php" class="block px-4 py-3 text-sm text-gray-700 hover:bg-purple-50 hover:text-purple-900 transition-colors">Presentation Settings</a>
                                    <a href="groups.php" class="block px-4 py-3 text-sm text-gray-700 hover:bg-purple-50 hover:text-purple-900 transition-colors">Groups</a>
                                    <?php if (isAdmin()): ?>
                                        <a href="admin/dashboard.php" class="block px-4 py-3 text-sm text-purple-600 font-semibold hover:bg-purple-50 hover:text-purple-900 transition-colors border-t border-gray-200">Admin Panel</a>
                                    <?php endif; ?>
                                    <a href="logout.php" onclick="localStorage.removeItem('e2ee_private_jwk');" class="block px-4 py-3 text-sm text-red-600 hover:bg-red-50 hover:text-red-900 transition-colors border/t border-gray-200">Logout</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="pt-20 max-w-4xl mx-auto py-8">
        <h1 class="text-2xl font-bold mb-4">Notification Center</h1>
        <?php if (empty($notifications)): ?>
            <p class="text-gray-600">You have no notifications.</p>
        <?php else: ?>
            <ul class="list-disc pl-5 space-y-2">
                <?php foreach($notifications as $n): ?>
                    <li class="">
                        <div class="flex justify-between">
                            <div class="notification-content">
                                <?php echo $n['content']; // content may include links ?>
                            </div>
                            <span class="text-xs text-gray-500 ml-4"><?php echo $n['created_at']; ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <script src="assets/js/chat.js"></script>
</body>
</html>
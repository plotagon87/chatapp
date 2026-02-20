<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';
requireLogin();

// Get current user data
$current_user = getUserData($_SESSION['user_id']);

// Determine if dark mode should be applied
$is_dark_mode = false;
if (isset($current_user['theme_preference'])) {
    if ($current_user['theme_preference'] === 'dark') {
        $is_dark_mode = true;
    } elseif ($current_user['theme_preference'] === 'auto') {
        // Check system preference
        $is_dark_mode = isset($_SERVER['HTTP_SEC_CH_UA_DARK']) || 
                        (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'Dark') !== false) ||
                        (function_exists('preg_match') && preg_match('/\(.*Dark.*\)/i', $_SERVER['HTTP_USER_AGENT'] ?? ''));
    }
}

// Get all users except current user
$users_stmt = $conn->prepare("SELECT user_id, username, full_name, profile_picture, status, last_seen 
                FROM users 
                WHERE user_id != ? 
                ORDER BY status DESC, full_name ASC");
$users_stmt->execute([$_SESSION['user_id']]);
$users = $users_stmt->fetchAll();

// Get recent announcements
$announcements_stmt = $conn->query("SELECT a.*, u.full_name as author 
                        FROM announcements a 
                        JOIN users u ON a.created_by = u.user_id 
                        WHERE a.is_active = 1 
                        ORDER BY a.created_at DESC 
                        LIMIT 3");
$announcements = $announcements_stmt->fetchAll();

// Get unread message count
$unread_stmt = $conn->prepare("SELECT COUNT(*) as unread_count 
                 FROM messages 
                 WHERE receiver_id = ? AND is_read = 0");
$unread_stmt->execute([$_SESSION['user_id']]);
$unread_count = $unread_stmt->fetch()['unread_count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <!-- ============================================ -->
    <!-- MOBILE OPTIMIZATION: Proper viewport settings -->
    <!-- ============================================ -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <!-- Standard favicon for browsers -->
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
    <!-- Apple Touch Icon (for iOS home screen) -->
    <link rel="apple-touch-icon" sizes="180x180" href="assets/images/apple-touch-icon.png">
    <!-- PWA Manifest (contains app info and icon references) -->
    <link rel="manifest" href="manifest.json">
    <!-- Theme color (shows in Android status bar when PWA is installed) -->
    <meta name="theme-color" content="#7c3aed">
    <title>Dashboard - LAN Chat</title>
    <link href="assets/css/tailwind.min.css" rel="stylesheet">
    <link href="assets/css/mobile.css" rel="stylesheet">
    
    <!-- ============================================ -->
    <!-- GLOBAL JAVASCRIPT VARIABLES (DON'T CHANGE) -->
    <!-- ============================================ -->
    <script>
        // Define variables in global scope
        const currentUserId = <?php echo (int)$_SESSION['user_id']; ?>;
        const csrfToken = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
        const baseUrl = '<?php echo BASE_URL; ?>';
        const userRole = '<?php echo htmlspecialchars($current_user['role'] ?? 'user'); ?>';
        const isAdmin = <?php echo isAdmin() ? 'true' : 'false'; ?>;
        
        // CRITICAL: Also expose these variables on the window object for external scripts (like chat.js)
        // This ensures chat.js can access them via window.csrfToken, window.baseUrl, etc.
        window.currentUserId = currentUserId;
        window.csrfToken = csrfToken;
        window.baseUrl = baseUrl;
        window.userRole = userRole;
        window.isAdmin = isAdmin;
        
        console.log('=== DASHBOARD VARIABLES ===');
        console.log('currentUserId:', currentUserId);
        console.log('csrfToken:', csrfToken ? 'SET ✓' : 'NOT SET ✗');
        console.log('baseUrl:', baseUrl);
        console.log('userRole:', userRole);
        console.log('isAdmin:', isAdmin);
        console.log('window.csrfToken:', window.csrfToken ? 'SET ✓' : 'NOT SET ✗');
        console.log('==========================');
    </script>
    
    <style>
        /* ============================================ */
        /* MOBILE-FIRST RESPONSIVE STYLES */
        /* ============================================ */
        
        /* Fixed navbar - account for it in body */
        body {
            padding-top: 64px; /* Height of navbar */
        }
        
        /* Ensure dropdown works in navbar */
        nav {
            overflow: visible !important;
        }
        
        #userMenu {
            position: absolute !important;
            z-index: 9999 !important;
            top: 100% !important;
            right: 0 !important;
        }
        
        /* Custom hidden state for menu */
        #userMenu.menu-hidden {
            display: none !important;
            visibility: hidden !important;
        }
        
        #userMenu:not(.menu-hidden) {
            display: block !important;
            visibility: visible !important;
        }
        
        /* ============================================ */
        /* SIDEBAR MOBILE MENU */
        /* Default: Hidden off-screen on mobile */
        /* ============================================ */
        .user-sidebar {
            position: fixed;
            left: -100%;
            top: 0;
            height: 100vh;
            width: 85%;
            max-width: 320px;
            z-index: 50;
            transition: left 0.3s ease;
            background: white;
            overflow-y: auto;
        }
        
        /* When active, slide in from left */
        .user-sidebar.active {
            left: 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        /* Dark overlay when sidebar is open */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 40;
        }
        
        .sidebar-overlay.active {
            display: block;
        }
        
        /* Desktop: Show sidebar normally */
        @media (min-width: 1024px) {
            .user-sidebar {
                position: relative;
                left: 0;
                width: 100%;
                height: auto;
                max-width: none;
            }
            
            .sidebar-toggle {
                display: none !important;
            }
            
            .sidebar-overlay {
                display: none !important;
            }
        }
        
        /* ============================================ */
        /* CHAT CONTAINER RESPONSIVE HEIGHTS */
        /* ============================================ */
        .chat-container {
            height: calc(100vh - 140px); /* Account for navbar + padding */
        }
        
        @media (min-width: 1024px) {
            .chat-container {
                height: calc(100vh - 120px);
            }
        }
        
        /* User list scrollable area */
        .user-list {
            height: calc(100vh - 250px);
            overflow-y: auto;
        }
        
        @media (min-width: 1024px) {
            .user-list {
                height: calc(100vh - 200px);
            }
        }
        
        /* Chat messages scrollable area */
        .chat-messages {
            height: calc(100% - 140px);
            overflow-y: auto;
        }
        
        /* ============================================ */
        /* STATUS INDICATORS */
        /* ============================================ */
        .online-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        
        .online { background-color: #10b981; }
        .offline { background-color: #6b7280; }
        .busy { background-color: #f59e0b; }
        .away { background-color: #8b5cf6; }
        
        /* ============================================ */
        /* MESSAGE BUBBLES - RESPONSIVE */
        /* Mobile: 85% width, Desktop: 70% width */
        /* ============================================ */
        .message-bubble {
            max-width: 85%;
            word-wrap: break-word;
            font-size: 0.9375rem; /* 15px for better mobile readability */
        }
        
        @media (min-width: 768px) {
            .message-bubble {
                max-width: 70%;
                font-size: 1rem; /* 16px on desktop */
            }
        }
        
        /* Hide scrollbar but keep functionality */
        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }
        
        .scrollbar-hide {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        
        /* Notification badge (blue to grab attention) */
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #3b82f6; /* blue */
            color: white;
            border-radius: 50%;
            min-width: 20px;
            height: 20px;
            font-size: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 5px;
        }
        
        /* ============================================ */
        /* MOBILE TOUCH TARGETS */
        /* Ensure buttons are at least 44x44px */
        /* ============================================ */
        @media (max-width: 640px) {
            .user-item {
                padding: 1rem;
                min-height: 70px;
            }
            
            button, .btn {
                min-height: 44px;
                min-width: 44px;
            }
        }

        /* badge shown on user listing when there are unread messages */
        .user-item {
            position: relative;
        }
        .unread-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            background: #ef4444; /* red */
            color: white;
            border-radius: 9999px;
            padding: 2px 6px;
            font-size: 10px;
            font-weight: bold;
            line-height: 1;
        }
        
        /* Prevent iOS zoom on input focus */
        input[type="text"],
        input[type="email"],
        input[type="password"],
        textarea {
            font-size: 16px !important;
        }
        
        /* ============================================ */
        /* EMOJI PICKER STYLES */
        /* ============================================ */
        #emojiPicker {
            /* Position emoji picker above input */
            position: absolute;
            bottom: 70px;
            left: 10px;
            z-index: 100;
        }
        
        #emojiPicker::-webkit-scrollbar {
            width: 8px;
        }
        
        #emojiPicker::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        #emojiPicker::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        
        #emojiPicker::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        .emoji-btn:hover {
            transform: scale(1.2);
            transition: transform 0.1s;
        }
        
        /* ============================================ */
        /* FILE UPLOAD MODAL STYLES */
        /* ============================================ */
        .file-category-tab {
            transition: all 0.3s ease;
        }
        
        .file-preview-item {
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* ============================================ */
        /* MESSAGE STYLES (Enhanced for Emojis) */
        /* ============================================ */
        .message-bubble {
            word-break: break-word;
            /* Ensure emojis render at proper size */
            font-size: 16px;
            line-height: 1.5;
        }
        
        /* Make standalone emojis larger */
        .message-bubble:has(> :only-child) {
            font-size: 48px;
            line-height: 1;
        }
        
        /* sent/read tick icons next to timestamp */
        .tick-sent {
            color: #9ca3af; /* gray-400 */
            font-size: 0.75rem;
            line-height: 1;
        }
        .tick-read {
            color: #3b82f6; /* blue-500 */
            font-size: 0.75rem;
            line-height: 1;
        }
    </style>
</head>
<body class="<?php echo $is_dark_mode ? 'bg-gray-900' : 'bg-gray-100'; ?>">
    
    <!-- ============================================ -->
    <!-- TOP NAVIGATION BAR - FIXED -->
    <!-- ============================================ -->
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
                            
                            <!-- Dropdown Menu -->
                            <div id="userMenu" class="menu-hidden absolute right-0 mt-1 w-48 bg-white rounded-lg shadow-2xl z-50 border border-gray-200 top-full">
                                <div class="py-1">
                                    <a href="profile.php" class="block px-4 py-3 text-sm text-gray-700 hover:bg-purple-50 hover:text-purple-900 transition-colors">Profile</a>
                                    <a href="settings.php" class="block px-4 py-3 text-sm text-gray-700 hover:bg-purple-50 hover:text-purple-900 transition-colors">Settings</a>
                                    <a href="presentation_settings.php" class="block px-4 py-3 text-sm text-gray-700 hover:bg-purple-50 hover:text-purple-900 transition-colors">Presentation Settings</a>
                                    <a href="groups.php" class="block px-4 py-3 text-sm text-gray-700 hover:bg-purple-50 hover:text-purple-900 transition-colors">Groups</a>
                                    <?php if (isAdmin()): ?>
                                        <a href="admin/dashboard.php" class="block px-4 py-3 text-sm text-purple-600 font-semibold hover:bg-purple-50 hover:text-purple-900 transition-colors border-t border-gray-200">Admin Panel</a>
                                    <?php endif; ?>
                                    <a href="logout.php" onclick="localStorage.removeItem('e2ee_private_jwk');" class="block px-4 py-3 text-sm text-red-600 hover:bg-red-50 hover:text-red-900 transition-colors border-t border-gray-200">Logout</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- ============================================ -->
    <!-- SIDEBAR OVERLAY (MOBILE ONLY) -->
    <!-- Darkens background when sidebar is open -->
    <!-- ============================================ -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- DEBUG: Admin Status -->
    <div style="display: none;" id="debugInfo">
        isAdmin: <?php echo isAdmin() ? 'true' : 'false'; ?> | 
        Role: <?php echo htmlspecialchars($current_user['role'] ?? 'unknown'); ?> | 
        UserID: <?php echo $_SESSION['user_id']; ?>
    </div>

    <!-- ============================================ -->
    <!-- MAIN CONTENT AREA -->
    <!-- ============================================ -->
    <div class="max-w-7xl mx-auto px-4 py-6">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            
            <!-- ============================================ -->
            <!-- LEFT SIDEBAR: USERS LIST -->
            <!-- Mobile: Slide-in menu, Desktop: Normal sidebar -->
            <!-- ============================================ -->
            <div class="user-sidebar lg:col-span-1 bg-white rounded-lg shadow-lg" id="userSidebar">
                
                <!-- Mobile Header with Close Button -->
                <div class="lg:hidden flex justify-between items-center p-4 border-b border-gray-200">
                    <h2 class="text-lg font-bold text-gray-800">Users</h2>
                    <button class="sidebar-close text-gray-600 hover:text-gray-800 p-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <!-- Desktop Header (Hidden on Mobile) -->
                <div class="hidden lg:block p-4">
                    <h2 class="text-lg font-bold text-gray-800 mb-3">Active Users</h2>
                    <input 
                        type="text" 
                        id="searchUsers" 
                        placeholder="Search users..." 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm"
                    >
                </div>
                
                <!-- Mobile Search Bar -->
                <div class="lg:hidden p-4 border-b border-gray-200">
                    <input 
                        type="text" 
                        id="searchUsersMobile" 
                        placeholder="Search users..." 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm"
                    >
                </div>
                
                <!-- Scrollable User List -->
                <div class="user-list scrollbar-hide p-2 lg:p-0">
                    <?php foreach($users as $user): ?>
                        <div class="user-item p-3 hover:bg-gray-50 rounded-lg cursor-pointer mb-2 border border-gray-100 lg:mx-2" 
                            data-user-id="<?php echo $user['user_id']; ?>"
                            data-username="<?php echo htmlspecialchars($user['username']); ?>"
                            data-fullname="<?php echo htmlspecialchars($user['full_name']); ?>"
                            data-profile-picture="<?php echo htmlspecialchars($user['profile_picture']); ?>">
                            <div class="flex items-center space-x-3">
                                <!-- Profile Picture with Status Dot -->
                                <div class="relative flex-shrink-0">
                                    <?php
                                    $profile_pic = !empty($user['profile_picture']) ? $user['profile_picture'] : 'default.png';
                                    $profile_path = "uploads/profiles/" . $profile_pic;
                                    $default_path = "assets/images/default.png";
                                    ?>
                                    <img src="<?php echo $profile_path; ?>" 
                                        alt="<?php echo htmlspecialchars($user['full_name']); ?>" 
                                        class="w-12 h-12 rounded-full"
                                        onerror="if(this.src != '<?php echo $default_path; ?>') this.src='<?php echo $default_path; ?>';">
                                    <span class="online-dot <?php echo $user['status']; ?> absolute bottom-0 right-0 border-2 border-white"></span>
                                    <?php if (!empty($user['unread_count']) && $user['unread_count'] > 0): ?>
                                        <span class="unread-badge"><?php echo $user['unread_count']; ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- User Info -->
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-sm text-gray-800 truncate"><?php echo htmlspecialchars($user['full_name']); ?></p>
                                    <p class="text-xs text-gray-500 truncate">
                                        <?php 
                                        if ($user['status'] === 'online') {
                                            echo 'Online';
                                        } else {
                                            echo timeAgo($user['last_seen']);
                                        }
                                        ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- MAIN AREA: CHAT WINDOW AND ANNOUNCEMENTS -->
            <!-- ============================================ -->
            <div class="lg:col-span-3 space-y-6">
                
                <!-- Announcements Banner -->
                <?php if (count($announcements) > 0): ?>
                    <div class="bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg shadow-lg p-4 text-white" id="announcementsContainer">
                        <h3 class="font-bold text-base lg:text-lg mb-3 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path>
                            </svg>
                            Announcements
                        </h3>
                        <?php foreach($announcements as $announcement): ?>
                            <div class="bg-white bg-opacity-20 rounded p-3 mb-2 announcement-item" 
                                data-announcement-id="<?php echo $announcement['announcement_id']; ?>"
                                data-is-welcome="<?php echo isset($announcement['is_welcome']) && $announcement['is_welcome'] ? 'true' : 'false'; ?>">
                                <p class="font-semibold text-sm lg:text-base"><?php echo htmlspecialchars($announcement['title']); ?></p>
                                <p class="text-xs lg:text-sm opacity-90"><?php echo htmlspecialchars($announcement['content']); ?></p>
                                <p class="text-xs opacity-75 mt-1">By <?php echo htmlspecialchars($announcement['author']); ?> • <?php echo timeAgo($announcement['created_at']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- ============================================ -->
                <!-- CHAT WINDOW -->
                <!-- ============================================ -->
                <div class="bg-white rounded-lg shadow-lg chat-container">
                    
                    <!-- Chat Header -->
                    <div id="chatHeader" class="border-b border-gray-200 p-3 lg:p-4 bg-gray-50 rounded-t-lg">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="text-gray-500 flex items-center">
                                    <svg class="w-5 h-5 lg:w-6 lg:h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                    </svg>
                                    <span class="text-sm lg:text-base">Select a user to start chatting</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Messages Display Area -->
                    <div id="chatMessages" class="chat-messages p-3 lg:p-4 bg-gray-50">
                        <div class="flex items-center justify-center h-full text-gray-400">
                            <div class="text-center">
                                <svg class="w-16 h-16 lg:w-20 lg:h-20 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                </svg>
                                <p class="text-base lg:text-lg">No conversation selected</p>
                                <p class="text-sm">Choose a user from the list to start messaging</p>
                            </div>
                        </div>
                    </div>

                                                <!-- Message Input Area -->
                            <div id="messageInputArea" class="hidden border-t border-gray-200 p-4 bg-white rounded-b-lg">
                                <form id="messageForm" class="flex items-center space-x-2">
                                    <input type="hidden" id="receiverId" value="">
                                    <input type="file" id="fileInput" class="hidden" accept="*">
                                    
                                    <!-- Audio Record Button -->
                                    <button type="button" id="audioRecordBtn" class="text-gray-500 hover:text-purple-600 flex-shrink-0" title="Record audio">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 1v4m0 14v4m4-10a4 4 0 01-8 0V7a4 4 0 018 0z" />
                                        </svg>
                                    </button>
                                    <span id="recordingTimer" class="ml-2 text-xs text-gray-600"></span>
                                    
                                    <!-- File Upload Button -->
                                    <button type="button" id="fileUploadBtn" class="text-gray-500 hover:text-purple-600 flex-shrink-0">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                        </svg>
                                    </button>
                                    
                                    <!-- Emoji Button -->
                                    <button type="button" id="emojiBtn" class="text-gray-500 hover:text-purple-600 flex-shrink-0">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </button>
                                    
                                    <!-- Message Input -->
                                    <input 
                                        type="text" 
                                        id="messageInput" 
                                        placeholder="Type a message..." 
                                        class="flex-1 px-4 py-2 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-purple-500"
                                        autocomplete="off"
                                    >
                                    
                                    <!-- Send Button -->
                                    <button 
                                        type="submit" 
                                        class="bg-purple-600 text-white p-2 rounded-full hover:bg-purple-700 transition duration-200 flex-shrink-0">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                        </svg>
                                    </button>
                                </form>
                            </div>

                            <!-- Info / Message Details Modal -->
                            <div id="infoModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
                                <div class="bg-white rounded-lg shadow-2xl w-full max-w-md p-6 relative">
                                    <button onclick="window.simpleChat.hideInfoModal()" class="absolute top-3 right-3 text-gray-500 hover:text-red-600">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                    <h3 id="infoModalTitle" class="text-xl font-bold text-gray-800 mb-4"></h3>
                                    <pre id="infoModalContent" class="text-sm whitespace-pre-wrap"></pre>
                                </div>
                            </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ============================================ -->
    <!-- JAVASCRIPT -->
    <!-- ============================================ -->
    
    <!-- Welcome Announcements Auto-Hide Script -->
    <script>
        // ============================================
        // WELCOME ANNOUNCEMENTS - AUTO HIDE AFTER 15 SECONDS FOR NEW USERS
        // ============================================
        window.addEventListener('load', function() {
            // Check if user is new (account created within last 7 days)
            const userCreatedAt = new Date(<?php echo strtotime($current_user['created_at']) * 1000; ?>);
            const now = new Date();
            const daysOld = (now - userCreatedAt) / (1000 * 60 * 60 * 24);
            const isNewUser = daysOld <= 7;
            
            console.log('User age: ' + daysOld.toFixed(1) + ' days, Is new user:', isNewUser);
            
            if (isNewUser) {
                // Find welcome announcements
                const welcomeAnnouncements = document.querySelectorAll('.announcement-item[data-is-welcome="true"]');
                console.log('Found ' + welcomeAnnouncements.length + ' welcome announcements');
                
                welcomeAnnouncements.forEach(function(announcement) {
                    // Show for 15 seconds then fade out and hide
                    const timeoutMs = 15000; // 15 seconds
                    
                    setTimeout(function() {
                        // Add fade-out animation
                        announcement.style.transition = 'opacity 0.5s ease-out';
                        announcement.style.opacity = '0';
                        
                        // Remove from DOM after fade completes
                        setTimeout(function() {
                            announcement.style.display = 'none';
                        }, 500);
                    }, timeoutMs);
                });
            }
        });
    </script>
    
    <!-- User Menu Toggle Script -->
    <script>
        // ============================================
        // USER DROPDOWN MENU - SIMPLIFIED
        // ============================================
        window.addEventListener('load', function() {
            const userMenuBtn = document.getElementById('userMenuBtn');
            const userMenu = document.getElementById('userMenu');
            
            console.log('Menu setup: btn=', !!userMenuBtn, 'menu=', !!userMenu);
            
            if (!userMenuBtn || !userMenu) {
                console.error('Menu elements not found!');
                return;
            }
            
            // Toggle menu on button click
            userMenuBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                userMenu.classList.toggle('menu-hidden');
            });
            
            // Close when clicking outside
            document.addEventListener('click', function(e) {
                if (e.target !== userMenuBtn && !userMenuBtn.contains(e.target) && !userMenu.contains(e.target)) {
                    userMenu.classList.add('menu-hidden');
                }
            });
        });
    
        
        // ============================================
        // MOBILE SIDEBAR TOGGLE
        // ============================================
        const sidebarToggle = document.querySelector('.sidebar-toggle');
        const sidebar = document.getElementById('userSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const sidebarClose = document.querySelector('.sidebar-close');
        
        function toggleSidebar() {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }
        
        // Open sidebar
        sidebarToggle?.addEventListener('click', toggleSidebar);
        
        // Close sidebar when clicking overlay
        overlay?.addEventListener('click', toggleSidebar);
        
        // Close sidebar when clicking close button
        sidebarClose?.addEventListener('click', toggleSidebar);
        
        // Close sidebar when user is selected (mobile only)
        document.addEventListener('click', (e) => {
            if (e.target.closest('.user-item') && window.innerWidth < 1024) {
                setTimeout(() => {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                }, 300); // Small delay for better UX
            }
        });
        
        // ============================================
        // MOBILE SEARCH SYNC
        // Sync desktop and mobile search boxes
        // ============================================
        const searchDesktop = document.getElementById('searchUsers');
        const searchMobile = document.getElementById('searchUsersMobile');
        
        if (searchDesktop && searchMobile) {
            searchDesktop.addEventListener('input', (e) => {
                searchMobile.value = e.target.value;
            });
            
            searchMobile.addEventListener('input', (e) => {
                searchDesktop.value = e.target.value;
            });
        }
    </script>
    
    <!-- Load E2EE helpers first -->
    <script src="assets/js/e2ee.js?v=<?php echo time(); ?>"></script>
    <!-- Load chat.js -->
    <script src="assets/js/chat.js?v=<?php echo time(); ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            if (window.e2eeEnabled) {
                if (!localStorage.getItem('e2ee_private_jwk')) {
                    try {
                        await generateKeyPairAndUpload();
                        console.log('🔐 Generated E2EE key pair');
                    } catch (err) {
                        console.error('E2EE key generation failed:', err);
                    }
                }
            } else {
                console.warn('E2EE not available; skipping key generation');
            }
        });
    </script>
</body>
</html>
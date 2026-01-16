<?php
require_once 'includes/config.php';
requireLogin();

// Get current user data
$current_user = getUserData($_SESSION['user_id']);

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - LAN Chat</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    
    <!-- FIXED: Proper JavaScript variable initialization -->
    <!-- These variables MUST be set BEFORE chat.js loads -->
    <script>
        // ========================================
        // IMPORTANT: Global JavaScript Variables
        // ========================================
        // These variables are used by chat.js to:
        // 1. Know who the current user is (for message alignment)
        // 2. Send CSRF tokens with requests (for security)
        // 3. Build correct API URLs (for AJAX calls)
        // ========================================
        
        // Current logged-in user ID (from PHP session)
        // This is used to determine if a message is "sent" or "received"
        const currentUserId = <?php echo (int)$_SESSION['user_id']; ?>;
        
        // CSRF token for form security
        // This prevents Cross-Site Request Forgery attacks
        const csrfToken = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
        
        // Base URL of the application
        // Used to build URLs for AJAX requests (e.g., "chat/send_message.php")
        const baseUrl = '<?php echo BASE_URL; ?>';
        
        // ========================================
        // DEBUG: Check if variables are set correctly
        // ========================================
        console.log('=== DASHBOARD VARIABLES (CHECK THESE!) ===');
        console.log('currentUserId:', currentUserId);
        console.log('currentUserId type:', typeof currentUserId);
        console.log('csrfToken:', csrfToken ? 'SET ✓' : 'NOT SET ✗');
        console.log('baseUrl:', baseUrl);
        console.log('==========================================');
        
        // Error check: Make sure currentUserId is a valid number
        if (!currentUserId || currentUserId === 0) {
            console.error('❌ CRITICAL ERROR: currentUserId is not set!');
            console.error('This will cause message alignment to fail.');
            console.error('Check that $_SESSION[user_id] is set in PHP.');
        } else {
            console.log('✅ currentUserId is set correctly:', currentUserId);
        }
        
        // Error check: Make sure baseUrl ends with a slash
        if (!baseUrl.endsWith('/')) {
            console.warn('⚠️ WARNING: baseUrl should end with a slash');
        } else {
            console.log('✅ baseUrl is set correctly:', baseUrl);
        }
    </script>
    
    <style>
        /* Chat container takes up most of the viewport height */
        .chat-container {
            height: calc(100vh - 120px);
        }
        
        /* User list scrollable area */
        .user-list {
            height: calc(100vh - 200px);
            overflow-y: auto; /* Allow scrolling if many users */
        }
        
        /* Chat messages scrollable area */
        .chat-messages {
            height: calc(100% - 140px);
            overflow-y: auto; /* Allow scrolling through messages */
        }
        
        /* Status indicator dot (online/offline/etc) */
        .online-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%; /* Make it circular */
            display: inline-block;
            margin-right: 5px;
        }
        
        /* Different colors for different statuses */
        .online { background-color: #10b981; }   /* Green for online */
        .offline { background-color: #6b7280; }  /* Gray for offline */
        .busy { background-color: #f59e0b; }     /* Orange for busy */
        .away { background-color: #8b5cf6; }     /* Purple for away */
        
        /* Message bubble styling */
        .message-bubble {
            max-width: 70%; /* Don't let messages take full width */
            word-wrap: break-word; /* Break long words */
        }
        
        /* Hide scrollbar but keep functionality */
        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }
        
        /* Notification badge (red circle with number) */
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444; /* Red background */
            color: white;
            border-radius: 50%; /* Circular */
            width: 20px;
            height: 20px;
            font-size: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Top Navigation Bar -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center h-16">
                <!-- Left side: Logo and app name -->
                <div class="flex items-center space-x-4">
                    <!-- Chat icon (SVG) -->
                    <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                    <span class="text-xl font-bold text-gray-800">LAN Chat</span>
                </div>
                
                <!-- Right side: User info and menu -->
                <div class="flex items-center space-x-6">
                    <!-- Notification bell icon -->
                    <div class="relative">
                        <button class="text-gray-600 hover:text-purple-600 relative">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                            </svg>
                            <!-- Show unread count if there are unread messages -->
                            <?php if ($unread_count > 0): ?>
                                <span class="notification-badge"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </button>
                    </div>
                    
                    <!-- User profile section with dropdown -->
                    <div class="flex items-center space-x-3">
                        <!-- User profile picture -->
                        <img src="uploads/profiles/<?php echo htmlspecialchars($current_user['profile_picture']); ?>" 
                             alt="Profile" 
                             class="w-10 h-10 rounded-full border-2 border-purple-500"
                             onerror="this.src='assets/images/default.png'">
                        
                        <!-- User name and role (hidden on mobile) -->
                        <div class="hidden md:block">
                            <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($current_user['full_name']); ?></p>
                            <p class="text-xs text-gray-500"><?php echo ucfirst($current_user['role']); ?></p>
                        </div>
                        
                        <!-- Dropdown menu button -->
                        <div class="relative">
                            <button id="userMenuBtn" class="text-gray-600 hover:text-purple-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            
                            <!-- Dropdown menu (hidden by default) -->
                            <div id="userMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl z-50">
                                <a href="profile.php" class="block px-4 py-2 text-gray-800 hover:bg-purple-50">Profile</a>
                                <a href="settings.php" class="block px-4 py-2 text-gray-800 hover:bg-purple-50">Settings</a>
                                <a href="groups.php" class="block px-4 py-2 text-gray-800 hover:bg-purple-50">Groups</a>
                                <!-- Show admin panel link only if user is admin -->
                                <?php if (isAdmin()): ?>
                                    <a href="admin/dashboard.php" class="block px-4 py-2 text-gray-800 hover:bg-purple-50">Admin Panel</a>
                                <?php endif; ?>
                                <hr class="my-1">
                                <a href="logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50">Logout</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content Area -->
    <div class="max-w-7xl mx-auto px-4 py-6">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- LEFT SIDEBAR: Users List -->
            <div class="lg:col-span-1 bg-white rounded-lg shadow-lg p-4">
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-800 mb-3">Active Users</h2>
                    <!-- Search box to filter users -->
                    <input 
                        type="text" 
                        id="searchUsers" 
                        placeholder="Search users..." 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm"
                    >
                </div>
                
                <!-- Scrollable list of users -->
                <div class="user-list scrollbar-hide">
                    <?php foreach($users as $user): ?>
                        <!-- Each user item (clickable) -->
                        <!-- data-* attributes store user info for JavaScript to use -->
                        <div class="user-item p-3 hover:bg-gray-50 rounded-lg cursor-pointer mb-2 border border-gray-100" 
                            data-user-id="<?php echo $user['user_id']; ?>"
                            data-username="<?php echo htmlspecialchars($user['username']); ?>"
                            data-fullname="<?php echo htmlspecialchars($user['full_name']); ?>"
                            data-profile-picture="<?php echo htmlspecialchars($user['profile_picture']); ?>">
                            <div class="flex items-center space-x-3">
                                <!-- User profile picture with online status indicator -->
                                <div class="relative">
                                    <?php
                                    // Determine profile picture path
                                    $profile_pic = !empty($user['profile_picture']) ? $user['profile_picture'] : 'default.png';
                                    $profile_path = "uploads/profiles/" . $profile_pic;
                                    $default_path = "assets/images/default.png";
                                    ?>
                                    <img src="<?php echo $profile_path; ?>" 
                                        alt="<?php echo htmlspecialchars($user['full_name']); ?>" 
                                        class="w-12 h-12 rounded-full"
                                        onerror="if(this.src != '<?php echo $default_path; ?>') this.src='<?php echo $default_path; ?>';">
                                    <!-- Status dot (green/gray/etc) -->
                                    <span class="online-dot <?php echo $user['status']; ?> absolute bottom-0 right-0 border-2 border-white"></span>
                                </div>
                                
                                <!-- User name and status text -->
                                <div class="flex-1">
                                    <p class="font-semibold text-sm text-gray-800"><?php echo htmlspecialchars($user['full_name']); ?></p>
                                    <p class="text-xs text-gray-500">
                                        <?php 
                                        // Show "Online" or time since last seen
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

            <!-- MAIN AREA: Chat Window and Announcements -->
            <div class="lg:col-span-3 space-y-6">
                <!-- Announcements Banner (if any exist) -->
                <?php if (count($announcements) > 0): ?>
                    <div class="bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg shadow-lg p-4 text-white">
                        <h3 class="font-bold text-lg mb-3 flex items-center">
                            <!-- Megaphone icon -->
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path>
                            </svg>
                            Announcements
                        </h3>
                        <!-- Display each announcement -->
                        <?php foreach($announcements as $announcement): ?>
                            <div class="bg-white bg-opacity-20 rounded p-3 mb-2">
                                <p class="font-semibold"><?php echo htmlspecialchars($announcement['title']); ?></p>
                                <p class="text-sm opacity-90"><?php echo htmlspecialchars($announcement['content']); ?></p>
                                <p class="text-xs opacity-75 mt-1">By <?php echo htmlspecialchars($announcement['author']); ?> • <?php echo timeAgo($announcement['created_at']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Chat Window -->
                <div class="bg-white rounded-lg shadow-lg chat-container">
                    <!-- Chat Header (shows selected user info) -->
                    <div id="chatHeader" class="border-b border-gray-200 p-4 bg-gray-50 rounded-t-lg">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <!-- Default state: No user selected -->
                                <div class="text-gray-500 flex items-center">
                                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                    </svg>
                                    <span>Select a user to start chatting</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Messages Display Area -->
                    <div id="chatMessages" class="chat-messages p-4 bg-gray-50">
                        <!-- Default state: No conversation selected -->
                        <div class="flex items-center justify-center h-full text-gray-400">
                            <div class="text-center">
                                <!-- Large chat icon -->
                                <svg class="w-20 h-20 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                </svg>
                                <p class="text-lg">No conversation selected</p>
                                <p class="text-sm">Choose a user from the list to start messaging</p>
                            </div>
                        </div>
                    </div>

                    <!-- Message Input Area (hidden until user selected) -->
                    <div id="messageInputArea" class="hidden border-t border-gray-200 p-4 bg-white rounded-b-lg">
                        <form id="messageForm" class="flex items-center space-x-3">
                            <!-- Hidden input to store the ID of who we're chatting with -->
                            <input type="hidden" id="receiverId" value="">
                            
                            <!-- Text input for typing message -->
                            <input 
                                type="text" 
                                id="messageInput" 
                                placeholder="Type a message..." 
                                class="flex-1 px-4 py-2 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-purple-500"
                                autocomplete="off"
                            >
                            
                            <!-- Send button -->
                            <button 
                                type="submit" 
                                class="bg-purple-600 text-white p-2 rounded-full hover:bg-purple-700 transition duration-200">
                                <!-- Paper plane icon -->
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Load chat.js AFTER the variables are defined -->
    <!-- The ?v= part forces browser to reload if file changes -->
    <script src="assets/js/chat.js?v=<?php echo time(); ?>"></script>
</body>
</html>
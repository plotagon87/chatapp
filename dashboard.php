<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - LAN Chat</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    
    <!-- ================================================================ -->
    <!-- CRITICAL: JAVASCRIPT VARIABLES FOR CHAT FUNCTIONALITY -->
    <!-- ================================================================ -->
    <!-- This section MUST come BEFORE chat.js loads -->
    <!-- It sets up the global variables that chat.js needs to work -->
    <script>
        // ============================================================
        // EXPLANATION: WHY WE USE window.VARIABLE_NAME
        // ============================================================
        // In JavaScript, 'window' is the global object in browsers
        // When we write: window.currentUserId = 5
        // We're creating a GLOBAL variable that can be accessed from ANY script
        // This allows chat.js to read: window.currentUserId
        
        // ============================================================
        // VARIABLE 1: currentUserId (MOST CRITICAL FOR MESSAGE ALIGNMENT)
        // ============================================================
        // This is the ID of the person who is currently logged in
        // PHP reads it from $_SESSION['user_id'] which was set during login
        // JavaScript will use this to determine if a message is "mine" or "theirs"
        
        window.currentUserId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>;
        
        // ============================================================
        // VARIABLE 2: csrfToken (SECURITY TOKEN)
        // ============================================================
        // CSRF = Cross-Site Request Forgery
        // This token prevents malicious websites from making requests
        // It's like a secret password that proves the request came from your site
        
        window.csrfToken = '<?php echo isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : ''; ?>';
        
        // ============================================================
        // VARIABLE 3: baseUrl (APPLICATION ROOT PATH)
        // ============================================================
        // This is the root URL of your application
        // Example: http://192.168.1.184/chatapp/
        // JavaScript uses this to construct API request URLs
        
        window.baseUrl = '<?php 
            // Check if BASE_URL constant exists (from config.php)
            if (defined('BASE_URL')) {
                echo BASE_URL;
            } else {
                // Fallback: Build URL from server variables if BASE_URL not defined
                // $_SERVER['HTTPS'] tells us if connection is secure (https vs http)
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                
                // $_SERVER['HTTP_HOST'] is the domain/IP (e.g., 192.168.1.184)
                $host = $_SERVER['HTTP_HOST'];
                
                // dirname() gets the directory path, rtrim() removes trailing slashes
                $path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
                
                // Combine all parts: http://192.168.1.184/chatapp/
                echo $protocol . '://' . $host . $path . '/';
            }
        ?>';
        
        // ============================================================
        // DEBUG LOGGING (HELPS YOU SEE IF VARIABLES ARE SET CORRECTLY)
        // ============================================================
        // This writes information to the browser console (F12 to see it)
        // You can check if all variables have the correct values
        
        console.log('=== DASHBOARD VARIABLES (CHECK THESE!) ===');
        console.log('currentUserId:', window.currentUserId);
        console.log('currentUserId type:', typeof window.currentUserId);
        console.log('csrfToken:', window.csrfToken ? 'SET ✓' : 'NOT SET ✗');
        console.log('baseUrl:', window.baseUrl);
        console.log('==========================================');
        
        // ============================================================
        // VALIDATION: CHECK IF CRITICAL VARIABLES ARE MISSING
        // ============================================================
        // This helps catch problems early by warning you in the console
        
        if (window.currentUserId === null || window.currentUserId === undefined) {
            console.error('❌ CRITICAL ERROR: currentUserId is not set!');
            console.error('This will cause message alignment to fail.');
            console.error('Check that $_SESSION[user_id] is set in PHP.');
        } else {
            console.log('✅ currentUserId is set correctly:', window.currentUserId);
        }
        
        if (!window.baseUrl) {
            console.warn('⚠️ WARNING: baseUrl is empty or undefined');
        } else {
            console.log('✅ baseUrl is set correctly:', window.baseUrl);
        }
    </script>
    
    <!-- ================================================================ -->
    <!-- STYLING FOR CHAT INTERFACE -->
    <!-- ================================================================ -->
    <style>
        /* Container for entire chat area */
        .chat-container {
            /* calc() does math: 100vh (full viewport height) minus 120px for nav/footer */
            height: calc(100vh - 120px);
        }
        
        /* User list in sidebar */
        .user-list {
            height: calc(100vh - 200px);
            /* overflow-y: auto means show scrollbar when content is too tall */
            overflow-y: auto;
        }
        
        /* Messages display area */
        .chat-messages {
            height: calc(100% - 140px);
            overflow-y: auto;
        }
        
        /* Online status indicator dot */
        .online-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%; /* Makes it circular */
            display: inline-block;
            margin-right: 5px;
        }
        
        /* Status colors */
        .online { background-color: #10b981; }   /* Green = online */
        .offline { background-color: #6b7280; }  /* Gray = offline */
        .busy { background-color: #f59e0b; }     /* Orange = busy */
        .away { background-color: #8b5cf6; }     /* Purple = away */
        
        /* Message bubble styling */
        .message-bubble {
            max-width: 70%; /* Messages take up at most 70% of width */
            word-wrap: break-word; /* Long words wrap to next line */
        }
        
        /* Hide scrollbar but keep functionality */
        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }
        
        /* Notification badge (unread count) */
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
    <!-- Top Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-4">
                    <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                    <span class="text-xl font-bold text-gray-800">LAN Chat</span>
                </div>
                
                <div class="flex items-center space-x-6">
                    <!-- Notifications -->
                    <div class="relative">
                        <button class="text-gray-600 hover:text-purple-600 relative">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                            </svg>
                            <?php if ($unread_count > 0): ?>
                                <span class="notification-badge"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </button>
                    </div>
                    
                    <!-- User Menu -->
                    <div class="flex items-center space-x-3">
                        <img src="uploads/profiles/<?php echo htmlspecialchars($current_user['profile_picture']); ?>" 
                             alt="Profile" 
                             class="w-10 h-10 rounded-full border-2 border-purple-500"
                             onerror="this.src='assets/images/default.png'">
                        <div class="hidden md:block">
                            <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($current_user['full_name']); ?></p>
                            <p class="text-xs text-gray-500"><?php echo ucfirst($current_user['role']); ?></p>
                        </div>
                        <div class="relative">
                            <button id="userMenuBtn" class="text-gray-600 hover:text-purple-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div id="userMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl z-50">
                                <a href="profile.php" class="block px-4 py-2 text-gray-800 hover:bg-purple-50">Profile</a>
                                <a href="settings.php" class="block px-4 py-2 text-gray-800 hover:bg-purple-50">Settings</a>
                                <a href="groups.php" class="block px-4 py-2 text-gray-800 hover:bg-purple-50">Groups</a>
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

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-6">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Sidebar - Users List -->
            <div class="lg:col-span-1 bg-white rounded-lg shadow-lg p-4">
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-800 mb-3">Active Users</h2>
                    <input 
                        type="text" 
                        id="searchUsers" 
                        placeholder="Search users..." 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm"
                    >
                </div>
                
                <div class="user-list scrollbar-hide">
                                            
                        <?php foreach($users as $user): ?>
                            <div class="user-item p-3 hover:bg-gray-50 rounded-lg cursor-pointer mb-2 border border-gray-100" 
                                data-user-id="<?php echo $user['user_id']; ?>"
                                data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                data-fullname="<?php echo htmlspecialchars($user['full_name']); ?>"
                                data-profile-picture="<?php echo htmlspecialchars($user['profile_picture']); ?>">
                                <div class="flex items-center space-x-3">
                                    <div class="relative">
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
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-semibold text-sm text-gray-800"><?php echo htmlspecialchars($user['full_name']); ?></p>
                                        <p class="text-xs text-gray-500">
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

            <!-- Main Chat Area -->
            <div class="lg:col-span-3 space-y-6">
                <!-- Announcements -->
                <?php if (count($announcements) > 0): ?>
                    <div class="bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg shadow-lg p-4 text-white">
                        <h3 class="font-bold text-lg mb-3 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path>
                            </svg>
                            Announcements
                        </h3>
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
                    <!-- Chat Header -->
                    <div id="chatHeader" class="border-b border-gray-200 p-4 bg-gray-50 rounded-t-lg">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="text-gray-500 flex items-center">
                                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                    </svg>
                                    <span>Select a user to start chatting</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Messages Area -->
                    <div id="chatMessages" class="chat-messages p-4 bg-gray-50">
                        <div class="flex items-center justify-center h-full text-gray-400">
                            <div class="text-center">
                                <svg class="w-20 h-20 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                </svg>
                                <p class="text-lg">No conversation selected</p>
                                <p class="text-sm">Choose a user from the list to start messaging</p>
                            </div>
                        </div>
                    </div>

                    <!-- Message Input -->
                    
                            <!-- In dashboard.php, simplify the message input -->
                            <div id="messageInputArea" class="hidden border-t border-gray-200 p-4 bg-white rounded-b-lg">
                                <form id="messageForm" class="flex items-center space-x-3">
                                    <input type="hidden" id="receiverId" value="">
                                    
                                    <!-- Temporarily disable file upload -->
                                    <!--
                                    <button type="button" id="fileUploadBtn" class="text-gray-500 hover:text-purple-600">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                        </svg>
                                    </button>
                                    <input type="file" id="fileInput" class="hidden">
                                    -->
                                    
                                    <!-- Temporarily disable emoji -->
                                    <!--
                                    <button type="button" id="emojiBtn" class="text-gray-500 hover:text-purple-600">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </button>
                                    -->
                                    
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
                                        class="bg-purple-600 text-white p-2 rounded-full hover:bg-purple-700 transition duration-200">
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
    <script src="assets/js/chat.js?v=<?php echo time(); ?>"></script>
</body>
</html>
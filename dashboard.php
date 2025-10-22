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
    <style>
        .chat-container {
            height: calc(100vh - 120px);
        }
        .user-list {
            height: calc(100vh - 200px);
            overflow-y: auto;
        }
        .chat-messages {
            height: calc(100% - 140px);
            overflow-y: auto;
        }
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
        
        .message-bubble {
            max-width: 70%;
            word-wrap: break-word;
        }
        
        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
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
                        <div class="user-item p-3 hover:bg-

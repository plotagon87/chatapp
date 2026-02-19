<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

// Check if user is actually admin
if (!isAdmin()) {
    header('Location: ../dashboard.php');
    exit();
}

// Get current user data
$current_user = getUserData($_SESSION['user_id']);

// Get admin statistics
$stats_stmt = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM users WHERE status = 'online') as online_users,
        (SELECT COUNT(*) FROM messages WHERE DATE(created_at) = CURDATE()) as today_messages,
        (SELECT COUNT(*) FROM announcements WHERE is_active = 1) as active_announcements,
        (SELECT COUNT(*) FROM group_chats) as total_groups,
        (SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as new_users_week
");
$stats = $stats_stmt->fetch();

// Get recent activities
$activities_stmt = $conn->query("
    SELECT 'user' as type, username, full_name, created_at 
    FROM users 
    ORDER BY created_at DESC 
    LIMIT 5
");
$recent_activities = $activities_stmt->fetchAll();
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
    <title>Admin Dashboard - LAN Chat</title>
        <script>
            // Pass PHP variables to JavaScript
            const currentUserId = <?php echo $_SESSION['user_id']; ?>;
            const csrfToken = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
            const baseUrl = '<?php echo BASE_URL; ?>';
        </script>
    <link href="../assets/css/tailwind.min.css" rel="stylesheet">
    <style>
        .stat-card {
            transition: transform 0.2s ease-in-out;
            cursor: pointer;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .clickable-card {
            cursor: pointer;
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    <span class="text-xl font-bold text-gray-800">LAN Chat Admin</span>
                </div>
                
                <div class="flex items-center space-x-6">
                    <!-- User Menu -->
                    <div class="flex items-center space-x-3">
                        <img src="../uploads/profiles/<?php echo htmlspecialchars($current_user['profile_picture']); ?>" 
                             alt="Profile" 
                             class="w-10 h-10 rounded-full border-2 border-purple-500"
                             onerror="this.src='../assets/images/default.png'">
                        <div class="hidden md:block">
                            <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($current_user['full_name']); ?> (Admin)</p>
                            <p class="text-xs text-gray-500">Administrator</p>
                        </div>
                        <div class="relative">
                            <button id="userMenuBtn" class="text-gray-600 hover:text-purple-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div id="userMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl z-50">
                                <a href="../profile.php" class="block px-4 py-2 text-gray-800 hover:bg-purple-50">Profile</a>
                                <a href="../settings.php" class="block px-4 py-2 text-gray-800 hover:bg-purple-50">Settings</a>
                                <a href="../dashboard.php" class="block px-4 py-2 text-gray-800 hover:bg-purple-50">User Dashboard</a>
                                <hr class="my-1">
                                <a href="../logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50">Logout</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Welcome Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Admin Dashboard</h1>
            <p class="text-gray-600">Welcome back, <?php echo htmlspecialchars($current_user['full_name']); ?></p>
        </div>

        <!-- Statistics Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <!-- Total Users - Clickable -->
            <div class="stat-card bg-white rounded-lg shadow p-6 clickable-card" onclick="window.location.href='view_users.php'">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Users</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_users']; ?></p>
                        <p class="text-xs text-gray-500 mt-1">Click to view all users</p>
                    </div>
                </div>
            </div>

            <!-- Online Users - Clickable -->
            <div class="stat-card bg-white rounded-lg shadow p-6 clickable-card" onclick="window.location.href='view_online_users.php'">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.636 18.364a9 9 0 010-12.728m12.728 0a9 9 0 010 12.728m-9.9-2.829a5 5 0 010-7.07m7.072 0a5 5 0 010 7.07M13 12a1 1 0 11-2 0 1 1 0 012 0z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Online Users</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['online_users']; ?></p>
                        <p class="text-xs text-gray-500 mt-1">Click to view online users</p>
                    </div>
                </div>
            </div>

            <!-- Today's Messages -->
            <div class="stat-card bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Today's Messages</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['today_messages']; ?></p>
                    </div>
                </div>
            </div>

            <!-- Active Announcements -->
            <div class="stat-card bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Active Announcements</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['active_announcements']; ?></p>
                    </div>
                </div>
            </div>

            <!-- Total Groups - Clickable -->
            <div class="stat-card bg-white rounded-lg shadow p-6 clickable-card" onclick="window.location.href='view_groups.php'">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-indigo-100 text-indigo-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Groups</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_groups']; ?></p>
                        <p class="text-xs text-gray-500 mt-1">Click to view all groups</p>
                    </div>
                </div>
            </div>

            <!-- New Users (Week) - Clickable -->
            <div class="stat-card bg-white rounded-lg shadow p-6 clickable-card" onclick="window.location.href='view_new_users.php'">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-pink-100 text-pink-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">New Users (Week)</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['new_users_week']; ?></p>
                        <p class="text-xs text-gray-500 mt-1">Click to view new users</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <a href="manage_users.php" class="bg-white rounded-lg shadow p-6 hover:shadow-md transition-shadow text-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600 inline-block">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-gray-800 mt-3">User Management</h3>
                <p class="text-sm text-gray-600">Manage users & permissions</p>
            </a>

            <a href="announcements.php" class="bg-white rounded-lg shadow p-6 hover:shadow-md transition-shadow text-center">
                <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 inline-block">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-gray-800 mt-3">Announcements</h3>
                <p class="text-sm text-gray-600">Create & manage announcements</p>
            </a>

            <a href="system_logs.php" class="bg-white rounded-lg shadow p-6 hover:shadow-md transition-shadow text-center">
                <div class="p-3 rounded-full bg-green-100 text-green-600 inline-block">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-gray-800 mt-3">System Logs</h3>
                <p class="text-sm text-gray-600">View system activity</p>
            </a>

            <a href="settings.php" class="bg-white rounded-lg shadow p-6 hover:shadow-md transition-shadow text-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-600 inline-block">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-gray-800 mt-3">System Settings</h3>
                <p class="text-sm text-gray-600">Configure system options</p>
            </a>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Activity</h3>
            <div class="space-y-3">
                <?php if (count($recent_activities) > 0): ?>
                    <?php foreach($recent_activities as $activity): ?>
                        <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                            <div class="p-2 rounded-full bg-blue-100 text-blue-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-800">New user registered: <?php echo htmlspecialchars($activity['full_name']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo timeAgo($activity['created_at']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-500 text-center py-4">No recent activity</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // User menu toggle
        document.getElementById('userMenuBtn').addEventListener('click', function() {
            document.getElementById('userMenu').classList.toggle('hidden');
        });

        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#userMenuBtn') && !e.target.closest('#userMenu')) {
                document.getElementById('userMenu').classList.add('hidden');
            }
        });

        // Add click effect to clickable cards
        document.querySelectorAll('.clickable-card').forEach(card => {
            card.addEventListener('click', function() {
                this.style.transform = 'translateY(-1px)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 150);
            });
        });
    </script>
    <script src="../assets/js/chat.js"></script>
</body>
</html>
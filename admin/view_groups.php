<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

if (!isAdmin()) {
    header('Location: ../dashboard.php');
    exit();
}

// Pagination
$perPage = 15;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// Build search and filter conditions
$whereConditions = [];
$params = [];

// Search by group name or description
if (!empty($_GET['search'])) {
    $search = '%' . sanitize($_GET['search']) . '%';
    $whereConditions[] = "(g.group_name LIKE ? OR g.group_description LIKE ?)";
    $params[] = $search;
    $params[] = $search;
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count for pagination
$countStmt = $conn->prepare("SELECT COUNT(*) FROM group_chats g $whereClause");
$countStmt->execute($params);
$totalGroups = (int)$countStmt->fetchColumn();

// Get groups with member counts and creator info
$groupsStmt = $conn->prepare("
    SELECT 
        g.*, 
        u.username as creator_username,
        u.full_name as creator_name,
        u.profile_picture as creator_avatar,
        (SELECT COUNT(*) FROM group_members WHERE group_id = g.group_id) as member_count,
        (SELECT COUNT(*) FROM group_messages WHERE group_id = g.group_id) as message_count,
        (SELECT MAX(created_at) FROM group_messages WHERE group_id = g.group_id) as last_activity
    FROM group_chats g 
    LEFT JOIN users u ON g.created_by = u.user_id 
    $whereClause 
    ORDER BY g.created_at DESC 
    LIMIT $perPage OFFSET $offset
");
// Use direct interpolation for LIMIT/OFFSET since they are safe integers and PDO::ATTR_EMULATE_PREPARES is false
$groupsStmt->execute($params);
$groups = $groupsStmt->fetchAll();
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
    <title>All Groups - Admin</title>
    <link href="../assets/css/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center h-16">
                <h1 class="text-xl font-bold text-gray-800">All Groups</h1>
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-gray-600 hover:text-purple-600">Dashboard</a>
                    <a href="../dashboard.php" class="text-purple-600 hover:text-purple-800 font-semibold">← Back to Chat</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Search Groups</h2>
            <form method="GET" class="flex space-x-4">
                <!-- Search -->
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input 
                        type="text" 
                        name="search" 
                        value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                        placeholder="Group name or description..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                    >
                </div>

                <!-- Buttons -->
                <div class="flex items-end space-x-2">
                    <button type="submit" class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700">
                        Search
                    </button>
                    <a href="view_groups.php" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700">
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Groups Grid -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-800">All Groups (<?php echo $totalGroups; ?> total)</h2>
                <span class="text-sm text-gray-600">Page <?php echo $page; ?> of <?php echo ceil($totalGroups / $perPage); ?></span>
            </div>

            <div class="p-6">
                <?php if (count($groups) === 0): ?>
                    <div class="text-center py-12">
                        <svg class="w-20 h-20 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        <h3 class="text-lg font-semibold text-gray-600 mb-2">No Groups Found</h3>
                        <p class="text-gray-500">No groups match your search criteria.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach($groups as $group): ?>
                            <div class="border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex-1">
                                        <h3 class="font-bold text-gray-800 text-xl mb-2"><?php echo htmlspecialchars($group['group_name']); ?></h3>
                                        <?php if ($group['group_description']): ?>
                                            <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars($group['group_description']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center flex-shrink-0 ml-4">
                                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                        </svg>
                                    </div>
                                </div>
                                
                                <!-- Group Stats -->
                                <div class="grid grid-cols-3 gap-4 mb-4 text-center">
                                    <div>
                                        <p class="text-2xl font-bold text-gray-800"><?php echo $group['member_count']; ?></p>
                                        <p class="text-xs text-gray-500">Members</p>
                                    </div>
                                    <div>
                                        <p class="text-2xl font-bold text-gray-800"><?php echo $group['message_count']; ?></p>
                                        <p class="text-xs text-gray-500">Messages</p>
                                    </div>
                                    <div>
                                        <p class="text-lg font-bold text-gray-800">
                                            <?php echo $group['last_activity'] ? timeAgo($group['last_activity']) : 'No activity'; ?>
                                        </p>
                                        <p class="text-xs text-gray-500">Last Active</p>
                                    </div>
                                </div>
                                
                                <!-- Creator Info -->
                                <div class="flex items-center justify-between text-sm text-gray-500 mb-4">
                                    <div class="flex items-center">
                                        <img src="../uploads/profiles/<?php echo htmlspecialchars($group['creator_avatar']); ?>" 
                                             alt="Creator" 
                                             class="w-6 h-6 rounded-full mr-2"
                                             onerror="this.src='../assets/images/default.png'">
                                        <span>by <?php echo htmlspecialchars($group['creator_name']); ?></span>
                                    </div>
                                    <span><?php echo date('M d, Y', strtotime($group['created_at'])); ?></span>
                                </div>
                                
                                <!-- Group Actions -->
                                <div class="flex space-x-2">
                                    <a href="../group_chat.php?id=<?php echo $group['group_id']; ?>" 
                                       class="flex-1 text-center bg-indigo-600 text-white py-2 rounded-lg hover:bg-indigo-700 font-semibold text-sm">
                                        View Group
                                    </a>
                                    <button onclick="viewGroupMembers(<?php echo $group['group_id']; ?>)" 
                                            class="bg-gray-600 text-white px-3 py-2 rounded-lg hover:bg-gray-700 text-sm">
                                        Members
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalGroups > $perPage): ?>
                <div class="px-6 py-4 border-t border-gray-200">
                    <div class="flex justify-center space-x-2">
                        <?php $totalPages = (int) ceil($totalGroups / $perPage); ?>
                        
                        <!-- Previous Page -->
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="px-3 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                                ← Previous
                            </a>
                        <?php endif; ?>

                        <!-- Page Numbers -->
                        <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $p])); ?>" class="px-3 py-1 rounded <?php echo $p === $page ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                                <?php echo $p; ?>
                            </a>
                        <?php endfor; ?>

                        <!-- Next Page -->
                        <?php if ($page < $totalPages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="px-3 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                                Next →
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function viewGroupMembers(groupId) {
            alert('Group members functionality would show members list for group ID: ' + groupId + '\n\nThis feature can be extended to show a modal with group members.');
            // You can implement a modal or redirect to a group members page here
        }
    </script>
</body>
</html>

<?php
require_once 'includes/config.php';
requireLogin();

$success = '';
$error = '';

// Handle create group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_group'])) {
    $group_name = sanitize($_POST['group_name']);
    $group_description = sanitize($_POST['group_description']);
    
    if (empty($group_name)) {
        $error = 'Group name is required';
    } else {
        $stmt = $conn->prepare("INSERT INTO group_chats (group_name, group_description, created_by) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $group_name, $group_description, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            $group_id = $stmt->insert_id;
            
            // Add creator as admin member
            $member_stmt = $conn->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'admin')");
            $member_stmt->bind_param("ii", $group_id, $_SESSION['user_id']);
            $member_stmt->execute();
            $member_stmt->close();
            
            $success = 'Group created successfully';
            logActivity($_SESSION['user_id'], "Created group: $group_name");
        } else {
            $error = 'Failed to create group';
        }
        $stmt->close();
    }
}

// Get user's groups
$my_groups = $conn->prepare("SELECT g.*, u.full_name as creator_name,
    (SELECT COUNT(*) FROM group_members WHERE group_id = g.group_id) as member_count,
    (SELECT COUNT(*) FROM group_messages WHERE group_id = g.group_id) as message_count
    FROM group_chats g
    JOIN users u ON g.created_by = u.user_id
    JOIN group_members gm ON g.group_id = gm.group_id
    WHERE gm.user_id = ?
    ORDER BY g.created_at DESC");
$my_groups->bind_param("i", $_SESSION['user_id']);
$my_groups->execute();
$groups_result = $my_groups->get_result();

// Get all available groups to join
$available_groups = $conn->prepare("SELECT g.*, u.full_name as creator_name,
    (SELECT COUNT(*) FROM group_members WHERE group_id = g.group_id) as member_count
    FROM group_chats g
    JOIN users u ON g.created_by = u.user_id
    WHERE g.group_id NOT IN (SELECT group_id FROM group_members WHERE user_id = ?)
    ORDER BY g.created_at DESC
    LIMIT 10");
$available_groups->bind_param("i", $_SESSION['user_id']);
$available_groups->execute();
$available_result = $available_groups->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Chats - LAN Chat</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="flex items-center space-x-2">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        <span class="text-xl font-bold text-gray-800">Group Chats</span>
                    </a>
                </div>
                <a href="dashboard.php" class="text-purple-600 hover:text-purple-800 font-semibold">
                    ← Back to Dashboard
                </a>
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
            <!-- Create Group Form -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                        <svg class="w-6 h-6 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Create New Group
                    </h2>
                    <form method="POST">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Group Name *</label>
                                <input 
                                    type="text" 
                                    name="group_name" 
                                    maxlength="100"
                                    placeholder="e.g., Team Project"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                                    required
                                >
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Description</label>
                                <textarea 
                                    name="group_description" 
                                    rows="3"
                                    placeholder="What's this group about?"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                                ></textarea>
                            </div>
                            <button 
                                type="submit" 
                                name="create_group"
                                class="w-full bg-purple-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-purple-700"
                            >
                                Create Group
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Available Groups -->
                <div class="bg-white rounded-lg shadow-lg p-6 mt-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Available Groups</h2>
                    <div class="space-y-3">
                        <?php if ($available_result->num_rows === 0): ?>
                            <p class="text-gray-500 text-sm text-center py-4">No available groups</p>
                        <?php else: ?>
                            <?php while($group = $available_result->fetch_assoc()): ?>
                                <div class="border border-gray-200 rounded-lg p-3">
                                    <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($group['group_name']); ?></h3>
                                    <p class="text-xs text-gray-500 mt-1"><?php echo $group['member_count']; ?> members</p>
                                    <a href="group_chat.php?join=<?php echo $group['group_id']; ?>" 
                                       class="mt-2 block text-center bg-purple-100 text-purple-700 py-1 rounded hover:bg-purple-200 text-sm font-semibold">
                                        Join Group
                                    </a>
                                </div>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- My Groups -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">My Groups</h2>
                    
                    <?php if ($groups_result->num_rows === 0): ?>
                        <div class="text-center py-12">
                            <svg class="w-20 h-20 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            <h3 class="text-lg font-semibold text-gray-600 mb-2">No Groups Yet</h3>
                            <p class="text-gray-500">Create a group or join an existing one to get started!</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php while($group = $groups_result->fetch_assoc()): ?>
                                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                    <div class="flex items-start justify-between mb-3">
                                        <div class="flex-1">
                                            <h3 class="font-bold text-gray-800 text-lg"><?php echo htmlspecialchars($group['group_name']); ?></h3>
                                            <?php if ($group['group_description']): ?>
                                                <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($group['group_description']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center flex-shrink-0">
                                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center justify-between text-sm text-gray-500 mb-3">
                                        <span><?php echo $group['member_count']; ?> members</span>
                                        <span>•</span>
                                        <span><?php echo $group['message_count']; ?> messages</span>
                                        <span>•</span>
                                        <span>by <?php echo htmlspecialchars($group['creator_name']); ?></span>
                                    </div>
                                    
                                    <a href="group_chat.php?id=<?php echo $group['group_id']; ?>" 
                                       class="block text-center bg-purple-600 text-white py-2 rounded-lg hover:bg-purple-700 font-semibold">
                                        Open Chat
                                    </a>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
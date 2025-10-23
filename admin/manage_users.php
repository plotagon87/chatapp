<?php
require_once '../includes/config.php';
requireLogin();

if (!isAdmin()) {
    header('Location: ../dashboard.php');
    exit();
}

$success = '';
$error = '';

// Handle user deletion
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $user_id = (int)$_GET['delete'];
    if ($user_id !== $_SESSION['user_id']) { // Can't delete yourself
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        if ($stmt->execute()) {
            $success = 'User deleted successfully';
            logActivity($_SESSION['user_id'], "Deleted user ID: $user_id");
        } else {
            $error = 'Failed to delete user';
        }
        $stmt->close();
    } else {
        $error = 'You cannot delete your own account';
    }
}

// Handle role change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role'])) {
    $user_id = (int)$_POST['user_id'];
    $new_role = sanitize($_POST['role']);
    
    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE user_id = ?");
    $stmt->execute([$new_role, $user_id]);
    if ($stmt->execute()) {
        $success = 'User role updated successfully';
        logActivity($_SESSION['user_id'], "Changed role for user ID: $user_id to $new_role");
    } else {
        $error = 'Failed to update role';
    }
    $stmt->close();
}

// Get all users
$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-4">
                    <h1 class="text-xl font-bold text-gray-800">User Management</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-gray-600 hover:text-purple-600">Dashboard</a>
                    <a href="../dashboard.php" class="text-purple-600 hover:text-purple-800 font-semibold">← Back to Chat</a>
                </div>
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

        <!-- Users Table -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-800">All Users</h2>
                <span class="text-sm text-gray-600"><?php echo $users->num_rows; ?> total users</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Joined</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php while($user = $users->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <img src="../uploads/profiles/<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                                             alt="Profile" 
                                             class="w-10 h-10 rounded-full mr-3"
                                             onerror="this.src='../assets/images/default.png'">
                                        <div>
                                            <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($user['full_name']); ?></p>
                                            <p class="text-sm text-gray-500">@<?php echo htmlspecialchars($user['username']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <select name="role" onchange="this.form.submit()" class="text-sm border border-gray-300 rounded px-2 py-1 <?php echo $user['user_id'] === $_SESSION['user_id'] ? 'bg-gray-100' : ''; ?>" <?php echo $user['user_id'] === $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                            <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                            <option value="student" <?php echo $user['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                                            <option value="staff" <?php echo $user['role'] === 'staff' ? 'selected' : ''; ?>>Staff</option>
                                            <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        </select>
                                        <input type="hidden" name="change_role" value="1">
                                    </form>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $status_colors = [
                                        'online' => 'bg-green-100 text-green-800',
                                        'offline' => 'bg-gray-100 text-gray-800',
                                        'busy' => 'bg-red-100 text-red-800',
                                        'away' => 'bg-yellow-100 text-yellow-800'
                                    ];
                                    $color = $status_colors[$user['status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $color; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php if ($user['user_id'] !== $_SESSION['user_id']): ?>
                                        <a href="?delete=<?php echo $user['user_id']; ?>" 
                                           onclick="return confirm('Are you sure you want to delete this user?')"
                                           class="text-red-600 hover:text-red-800 font-semibold">
                                            Delete
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-400">Current User</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
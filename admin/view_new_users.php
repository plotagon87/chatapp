<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

if (!isAdmin()) {
    header('Location: ../dashboard.php');
    exit();
}

// Pagination
$perPage = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// Build search and filter conditions for new users (last 7 days)
$whereConditions = ["created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"];
$params = [];

// Search by username, email, or full name
if (!empty($_GET['search'])) {
    $search = '%' . sanitize($_GET['search']) . '%';
    $whereConditions[] = "(username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

// Filter by role
if (!empty($_GET['role']) && $_GET['role'] !== 'all') {
    $whereConditions[] = "role = ?";
    $params[] = sanitize($_GET['role']);
}

// Filter by status
if (!empty($_GET['status']) && $_GET['status'] !== 'all') {
    $whereConditions[] = "status = ?";
    $params[] = sanitize($_GET['status']);
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Get total count for pagination
$countStmt = $conn->prepare("SELECT COUNT(*) FROM users $whereClause");
$countStmt->execute($params);
$totalUsers = (int)$countStmt->fetchColumn();

// Get new users with pagination
$usersStmt = $conn->prepare("SELECT * FROM users $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?");
$params[] = $perPage;
$params[] = $offset;
$usersStmt->execute($params);
$users = $usersStmt->fetchAll();

// Get unique roles and statuses for filters
$rolesStmt = $conn->query("SELECT DISTINCT role FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY role");
$roles = $rolesStmt->fetchAll(PDO::FETCH_COLUMN);

$statusesStmt = $conn->query("SELECT DISTINCT status FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY status");
$statuses = $statusesStmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#7c3aed">
    <link rel="apple-touch-icon" href="../assets/images/icon-192.png">
    <title>New Users - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center h-16">
                <h1 class="text-xl font-bold text-gray-800">New Users (Last 7 Days)</h1>
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
            <h2 class="text-lg font-bold text-gray-800 mb-4">Filter New Users</h2>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Search -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input 
                        type="text" 
                        name="search" 
                        value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                        placeholder="Username, email, name..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                    >
                </div>

                <!-- Role Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                    <select name="role" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        <option value="all">All Roles</option>
                        <?php foreach($roles as $role): ?>
                            <option value="<?php echo $role; ?>" <?php echo ($_GET['role'] ?? '') === $role ? 'selected' : ''; ?>>
                                <?php echo ucfirst($role); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Status Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        <option value="all">All Statuses</option>
                        <?php foreach($statuses as $status): ?>
                            <option value="<?php echo $status; ?>" <?php echo ($_GET['status'] ?? '') === $status ? 'selected' : ''; ?>>
                                <?php echo ucfirst($status); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Buttons -->
                <div class="flex items-end space-x-2">
                    <button type="submit" class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700">
                        Apply Filters
                    </button>
                    <a href="view_new_users.php" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700">
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- New Users Table -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-800">New Users (<?php echo $totalUsers; ?> registered in last 7 days)</h2>
                <span class="text-sm text-gray-600">Page <?php echo $page; ?> of <?php echo ceil($totalUsers / $perPage); ?></span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Seen</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Registered</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (count($users) === 0): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                    No new users found in the last 7 days
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($users as $user): ?>
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
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                            <?php echo $user['role'] === 'admin' ? 'bg-purple-100 text-purple-800' : 
                                                   ($user['role'] === 'staff' ? 'bg-blue-100 text-blue-800' : 
                                                   ($user['role'] === 'student' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800')); ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
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
                                        <?php echo timeAgo($user['last_seen']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <?php echo date('M d, Y H:i', strtotime($user['created_at'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalUsers > $perPage): ?>
                <div class="px-6 py-4 border-t border-gray-200">
                    <div class="flex justify-center space-x-2">
                        <?php $totalPages = (int) ceil($totalUsers / $perPage); ?>
                        
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
</body>
</html>
<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

if (!isAdmin()) {
    header('Location: ../dashboard.php');
    exit();
}

// Handle log clearing
if (isset($_GET['clear_logs']) && $_GET['clear_logs'] === '1') {
    $conn->query("TRUNCATE TABLE activity_log");
    $success = 'All logs have been cleared successfully';
    logActivity($_SESSION['user_id'], "Cleared all system logs");
}

// Handle export logs
if (isset($_GET['export']) && $_GET['export'] === '1') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="system_logs_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'User', 'Action', 'IP Address', 'User Agent', 'Timestamp']);
    
    $logs_stmt = $conn->query("
        SELECT al.*, u.username, u.full_name 
        FROM activity_log al 
        LEFT JOIN users u ON al.user_id = u.user_id 
        ORDER BY al.created_at DESC
    ");
    
    while ($log = $logs_stmt->fetch()) {
        fputcsv($output, [
            $log['log_id'],
            $log['full_name'] ? $log['full_name'] . ' (@' . $log['username'] . ')' : 'System',
            $log['action'],
            $log['ip_address'],
            substr($log['user_agent'], 0, 50) . '...',
            $log['created_at']
        ]);
    }
    fclose($output);
    exit();
}

// Pagination
$perPage = 50;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// Build search and filter conditions
$whereConditions = [];
$params = [];

// Search by action or username
if (!empty($_GET['search'])) {
    $search = '%' . sanitize($_GET['search']) . '%';
    $whereConditions[] = "(al.action LIKE ? OR u.username LIKE ? OR u.full_name LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

// Filter by user
if (!empty($_GET['user_id']) && $_GET['user_id'] !== 'all') {
    $whereConditions[] = "al.user_id = ?";
    $params[] = (int)$_GET['user_id'];
}

// Filter by date range
if (!empty($_GET['date_from'])) {
    $whereConditions[] = "DATE(al.created_at) >= ?";
    $params[] = sanitize($_GET['date_from']);
}

if (!empty($_GET['date_to'])) {
    $whereConditions[] = "DATE(al.created_at) <= ?";
    $params[] = sanitize($_GET['date_to']);
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count for pagination
$countStmt = $conn->prepare("SELECT COUNT(*) FROM activity_log al LEFT JOIN users u ON al.user_id = u.user_id $whereClause");
$countStmt->execute($params);
$totalLogs = (int)$countStmt->fetchColumn();

// Get logs with pagination
$logsStmt = $conn->prepare("
    SELECT al.*, u.username, u.full_name, u.profile_picture 
    FROM activity_log al 
    LEFT JOIN users u ON al.user_id = u.user_id 
    $whereClause 
    ORDER BY al.created_at DESC 
    LIMIT ? OFFSET ?
");

$params[] = $perPage;
$params[] = $offset;
$logsStmt->execute($params);
$logs = $logsStmt->fetchAll();

// Get distinct users for filter dropdown
$usersStmt = $conn->query("SELECT user_id, username, full_name FROM users ORDER BY full_name");
$allUsers = $usersStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>System Logs - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center h-16">
                <h1 class="text-xl font-bold text-gray-800">System Logs</h1>
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-gray-600 hover:text-purple-600">Dashboard</a>
                    <a href="../dashboard.php" class="text-purple-600 hover:text-purple-800 font-semibold">← Back to Chat</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-8">
        <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <!-- Filters and Actions -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
                <h2 class="text-lg font-bold text-gray-800">Filter Logs</h2>
                <div class="flex flex-col lg:flex-row space-y-2 lg:space-y-0 lg:space-x-4">
                    <a href="?export=1" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-center">
                        Export CSV
                    </a>
                    <a href="?clear_logs=1" 
                       onclick="return confirm('Are you sure you want to clear ALL system logs? This action cannot be undone.')"
                       class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-center">
                        Clear All Logs
                    </a>
                </div>
            </div>

            <form method="GET" class="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Search -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input 
                        type="text" 
                        name="search" 
                        value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                        placeholder="Action, username..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                    >
                </div>

                <!-- User Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">User</label>
                    <select name="user_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        <option value="all">All Users</option>
                        <?php foreach($allUsers as $user): ?>
                            <option value="<?php echo $user['user_id']; ?>" <?php echo ($_GET['user_id'] ?? '') == $user['user_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Date From -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                    <input 
                        type="date" 
                        name="date_from" 
                        value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                    >
                </div>

                <!-- Date To -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                    <input 
                        type="date" 
                        name="date_to" 
                        value="<?php echo htmlspecialchars($_GET['date_to'] ?? ''); ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                    >
                </div>

                <div class="md:col-span-2 lg:col-span-4 flex space-x-2">
                    <button type="submit" class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700">
                        Apply Filters
                    </button>
                    <a href="system_logs.php" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700">
                        Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <!-- Logs Table -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-800">Activity Logs</h2>
                <span class="text-sm text-gray-600"><?php echo $totalLogs; ?> total entries</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (count($logs) === 0): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                    No logs found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($logs as $log): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        #<?php echo $log['log_id']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($log['user_id']): ?>
                                            <div class="flex items-center">
                                                <img src="../uploads/profiles/<?php echo htmlspecialchars($log['profile_picture']); ?>" 
                                                     alt="Profile" 
                                                     class="w-8 h-8 rounded-full mr-3"
                                                     onerror="this.src='../assets/images/default.png'">
                                                <div>
                                                    <p class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($log['full_name']); ?></p>
                                                    <p class="text-xs text-gray-500">@<?php echo htmlspecialchars($log['username']); ?></p>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-500 text-sm">System</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-sm text-gray-800"><?php echo htmlspecialchars($log['action']); ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <?php echo htmlspecialchars($log['ip_address']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalLogs > $perPage): ?>
                <div class="px-6 py-4 border-t border-gray-200">
                    <div class="flex justify-center space-x-2">
                        <?php $totalPages = (int) ceil($totalLogs / $perPage); ?>
                        
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
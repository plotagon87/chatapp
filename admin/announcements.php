<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

if (!isAdmin()) {
    header('Location: ../dashboard.php');
    exit();
}

$success = '';
$error = '';

// Handle new announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_announcement'])) {
    $title = sanitize($_POST['title']);
    $content = sanitize($_POST['content']);
    $priority = sanitize($_POST['priority']);
    $expires_at = !empty($_POST['expires_at']) ? sanitize($_POST['expires_at']) : null;
    
    if (empty($title) || empty($content)) {
        $error = 'Title and content are required';
    } else {
        $stmt = $conn->prepare("INSERT INTO announcements (title, content, created_by, priority, expires_at) VALUES (?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$title, $content, $_SESSION['user_id'], $priority, $expires_at])) {
            $success = 'Announcement created successfully';
            logActivity($_SESSION['user_id'], "Created announcement: $title");
        } else {
            $error = 'Failed to create announcement';
        }
    }
}

// Handle delete announcement
if (isset($_GET['delete'])) {
    $announcement_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM announcements WHERE announcement_id = ?");
    $ok = $stmt->execute([$announcement_id]);
    if ($ok) {
        $success = 'Announcement deleted successfully';
        logActivity($_SESSION['user_id'], "Deleted announcement ID: $announcement_id");
    } else {
        $error = 'Failed to delete announcement';
    }
}

// Handle toggle active status
if (isset($_GET['toggle'])) {
    $announcement_id = (int)$_GET['toggle'];
    $stmt = $conn->prepare("UPDATE announcements SET is_active = NOT is_active WHERE announcement_id = ?");
    $stmt->execute([$announcement_id]);
    $success = 'Announcement status updated';
}

// Pagination for announcements (avoid loading everything at once)
$perPage = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

$countStmt = $conn->query("SELECT COUNT(*) FROM announcements");
$totalAnnouncements = (int) $countStmt->fetchColumn();

$annStmt = $conn->prepare("SELECT a.*, u.full_name as author_name 
    FROM announcements a 
    JOIN users u ON a.created_by = u.user_id 
    ORDER BY a.created_at DESC 
    LIMIT ? OFFSET ?");
$annStmt->bindValue(1, $perPage, PDO::PARAM_INT);
$annStmt->bindValue(2, $offset, PDO::PARAM_INT);
$annStmt->execute();
$announcements = $annStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Manage Announcements - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center h-16">
                <h1 class="text-xl font-bold text-gray-800">Announcements Management</h1>
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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Create Announcement Form -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Create Announcement</h2>
                    <form method="POST">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Title *</label>
                                <input 
                                    type="text" 
                                    name="title" 
                                    maxlength="200"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                                    required
                                >
                            </div>
                            
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Content *</label>
                                <textarea 
                                    name="content" 
                                    rows="5"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                                    required
                                ></textarea>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Priority</label>
                                <select name="priority" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Expires At (Optional)</label>
                                <input 
                                    type="datetime-local" 
                                    name="expires_at"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                                >
                            </div>
                            
                            <button 
                                type="submit" 
                                name="create_announcement"
                                class="w-full bg-purple-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-purple-700"
                            >
                                Create Announcement
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Announcements List -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">All Announcements</h2>
                    <div class="space-y-4">
                        <?php if (count($announcements) === 0): ?>
                            <p class="text-gray-500 text-center py-8">No announcements yet</p>
                        <?php else: ?>
                            <?php foreach($announcements as $announcement): ?>
                                <div class="border border-gray-200 rounded-lg p-4 <?php echo !$announcement['is_active'] ? 'bg-gray-50 opacity-60' : ''; ?>">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-2 mb-1">
                                                <h3 class="font-bold text-gray-800"><?php echo htmlspecialchars($announcement['title']); ?></h3>
                                                <?php
                                                $priority_colors = [
                                                    'low' => 'bg-gray-100 text-gray-800',
                                                    'medium' => 'bg-blue-100 text-blue-800',
                                                    'high' => 'bg-orange-100 text-orange-800',
                                                    'urgent' => 'bg-red-100 text-red-800'
                                                ];
                                                $color = $priority_colors[$announcement['priority']];
                                                ?>
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $color; ?>">
                                                    <?php echo ucfirst($announcement['priority']); ?>
                                                </span>
                                                <?php if (!$announcement['is_active']): ?>
                                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-200 text-gray-600">
                                                        Inactive
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-gray-600 text-sm mb-2"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                                            <div class="flex items-center space-x-4 text-xs text-gray-500">
                                                <span>By <?php echo htmlspecialchars($announcement['author_name']); ?></span>
                                                <span>•</span>
                                                <span><?php echo timeAgo($announcement['created_at']); ?></span>
                                                <?php if ($announcement['expires_at']): ?>
                                                    <span>•</span>
                                                    <span>Expires: <?php echo date('M d, Y', strtotime($announcement['expires_at'])); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="flex space-x-2 ml-4">
                                            <a href="?toggle=<?php echo $announcement['announcement_id']; ?>" 
                                               class="text-blue-600 hover:text-blue-800 text-sm font-semibold">
                                                <?php echo $announcement['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                            </a>
                                            <a href="?delete=<?php echo $announcement['announcement_id']; ?>" 
                                               onclick="return confirm('Delete this announcement?')"
                                               class="text-red-600 hover:text-red-800 text-sm font-semibold">
                                                Delete
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <!-- Pagination -->
                    <?php if ($totalAnnouncements > $perPage): ?>
                        <div class="mt-4 flex justify-center space-x-2">
                            <?php $totalPages = (int) ceil($totalAnnouncements / $perPage); ?>
                            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                <a href="?page=<?php echo $p; ?>" class="px-3 py-1 rounded <?php echo $p === $page ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-700'; ?>"><?php echo $p; ?></a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
require_once 'includes/config.php';
requireLogin();

// standard variables for nav
$current_user = getUserData($_SESSION['user_id']);
$unread_stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
$unread_stmt->execute([$_SESSION['user_id']]);
$unread_count = $unread_stmt->fetch()['unread_count'];

// fetch or create a presentation for this user
$stmt = $conn->prepare("SELECT * FROM presentations WHERE presenter_id = ? LIMIT 1");
$stmt->execute([$current_user['user_id']]);
$presentation = $stmt->fetch();

if (!$presentation) {
    // create a blank entry so frontend can work
    $stmt2 = $conn->prepare("INSERT INTO presentations (presenter_id, title) VALUES (?,?)");
    $stmt2->execute([$current_user['user_id'], 'Untitled Presentation']);
    $presentation_id = $conn->lastInsertId();
    $stmt->execute([$current_user['user_id']]);
    $presentation = $stmt->fetch();
}

// load authorized users and all users for selection
$viewerStmt = $conn->prepare("SELECT pv.id, pv.user_id, pv.group_id, pv.approved, u.full_name, u.username, g.group_name FROM presentation_viewers pv LEFT JOIN users u ON pv.user_id = u.user_id LEFT JOIN group_chats g ON pv.group_id = g.group_id WHERE pv.presentation_id = ?");
$viewerStmt->execute([$presentation['presentation_id']]);
$authorized = $viewerStmt->fetchAll();

$allUsersStmt = $conn->prepare("SELECT user_id, full_name, username FROM users WHERE user_id != ? ORDER BY full_name");
$allUsersStmt->execute([$current_user['user_id']]);
$allUsers = $allUsersStmt->fetchAll();

// Fetch all groups (both for presenter to select and to show invited groups)
$allGroupsStmt = $conn->prepare("SELECT g.group_id, g.group_name, COUNT(gm.user_id) as member_count FROM group_chats g LEFT JOIN group_members gm ON g.group_id = gm.group_id GROUP BY g.group_id ORDER BY g.group_name");
$allGroupsStmt->execute();
$allGroups = $allGroupsStmt->fetchAll();

// load files
$filesStmt = $conn->prepare("SELECT * FROM presentation_files WHERE presentation_id = ? ORDER BY slide_number");
$filesStmt->execute([$presentation['presentation_id']]);
$files = $filesStmt->fetchAll();

// announcements
$annStmt = $conn->prepare("SELECT * FROM presentation_announcements WHERE presentation_id = ? ORDER BY created_at DESC");
$annStmt->execute([$presentation['presentation_id']]);
$announcements = $annStmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Presentation Settings</title>
    <style>
        /* notification badge color matches dashboard */
        .notification-badge { background: #3b82f6 !important; }
    </style>
    <link rel="stylesheet" href="assets/css/tailwind.min.css">
    <script>window.baseUrl = '<?php echo BASE_URL; ?>';
            const currentUserId = <?php echo (int)$_SESSION['user_id']; ?>;
            window.currentUserId = currentUserId;
</script>
</head>
<body class="bg-gray-100">
    <!-- top nav (same as dashboard) -->
    <nav class="bg-white shadow-lg fixed top-0 left-0 right-0 z-30">
        <div class="px-4">
            <div class="flex justify-between items-center h-16">
                <button class="sidebar-toggle lg:hidden text-gray-600 hover:text-purple-600 p-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                <div class="flex items-center space-x-2 lg:space-x-4">
                    <svg class="w-6 h-6 lg:w-8 lg:h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                    <span class="text-lg lg:text-xl font-bold text-gray-800">LAN Chat</span>
                </div>
                <div class="flex items-center space-x-2 lg:space-x-6">
                    <div class="relative">
                        <a href="notifications.php" class="text-gray-600 hover:text-purple-600 relative p-2">
                            <svg class="w-5 h-5 lg:w-6 lg:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                            </svg>
                            <?php if ($unread_count > 0): ?>
                                <span class="notification-badge"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                    <div class="flex items-center space-x-2 lg:space-x-3">
                        <img src="uploads/profiles/<?php echo htmlspecialchars($current_user['profile_picture']); ?>" 
                             alt="Profile" 
                             class="w-8 h-8 lg:w-10 lg:h-10 rounded-full border-2 border-purple-500"
                             onerror="this.src='assets/images/default.png'">
                        <div class="hidden md:block">
                            <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($current_user['full_name']); ?></p>
                            <p class="text-xs text-gray-500"><?php echo ucfirst($current_user['role']); ?></p>
                        </div>
                        <div class="relative z-50">
                            <button id="userMenuBtn" type="button" class="text-gray-600 hover:text-purple-600 p-2 focus:outline-none focus:ring-2 focus:ring-purple-300 rounded cursor-pointer transition-colors" aria-haspopup="true" aria-expanded="false">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div id="userMenu" class="menu-hidden absolute right-0 mt-1 w-48 bg-white rounded-lg shadow-2xl z-50 border border-gray-200 top-full">
                                <div class="py-1">
                                    <a href="profile.php" class="block px-4 py-3 text-sm text-gray-700 hover:bg-purple-50 hover:text-purple-900 transition-colors">Profile</a>
                                    <a href="settings.php" class="block px-4 py-3 text-sm text-gray-700 hover:bg-purple-50 hover:text-purple-900 transition-colors">Settings</a>
                                    <a href="presentation_settings.php" class="block px-4 py-3 text-sm text-gray-700 hover:bg-purple-50 hover:text-purple-900 transition-colors">Presentation Settings</a>
                                    <a href="groups.php" class="block px-4 py-3 text-sm text-gray-700 hover:bg-purple-50 hover:text-purple-900 transition-colors">Groups</a>
                                    <?php if (isAdmin()): ?>
                                        <a href="admin/dashboard.php" class="block px-4 py-3 text-sm text-purple-600 font-semibold hover:bg-purple-50 hover:text-purple-900 transition-colors border-t border-gray-200">Admin Panel</a>
                                    <?php endif; ?>
                                    <a href="logout.php" onclick="localStorage.removeItem('e2ee_private_jwk');" class="block px-4 py-3 text-sm text-red-600 hover:bg-red-50 hover:text-red-900 transition-colors border/t border-gray-200">Logout</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- push content down so nav doesn't obscure -->
    <div class="pt-20"></div>

<div class="max-w-4xl mx-auto py-8">
    <h1 class="text-2xl font-bold mb-4">Presentation Settings</h1>
    <div class="bg-white p-6 rounded-lg shadow">
        <label class="block mb-2 font-semibold">Title</label>
        <input type="text" id="presentationTitle" class="w-full border rounded px-3 py-2" value="<?php echo htmlspecialchars($presentation['title']); ?>">
        <div class="mt-4">
            <button id="saveTitleBtn" class="px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700">Save</button>
            <button id="toggleActiveBtn" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 ml-2"><?php echo $presentation['is_active'] ? 'Stop Presentation' : 'Start Presentation'; ?></button>
        </div>
        <div class="mt-4">
            <label class="inline-flex items-center">
                <input type="checkbox" id="allowDownload" class="form-checkbox" <?php echo $presentation['allow_download'] ? 'checked' : ''; ?>>
                <span class="ml-2">Allow viewers to download after presentation ends</span>
            </label>
        </div>
    </div>

    <div class="mt-8 bg-white p-6 rounded-lg shadow">
        <h2 class="text-xl font-semibold mb-3">Upload Slides / Files</h2>
        <form id="uploadForm" enctype="multipart/form-data">
            <input type="file" name="slideFile" id="slideFile">
            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 ml-2">Upload</button>
        </form>
        <ul id="slideList" class="mt-4 list-disc pl-5">
            <?php foreach($files as $f): ?>
                <li data-file-id="<?php echo $f['file_id']; ?>"><?php echo htmlspecialchars(basename($f['file_path'])); ?> (slide <?php echo $f['slide_number']; ?>)</li>
            <?php endforeach;?>
        </ul>
    </div>

    <div class="mt-8 bg-white p-6 rounded-lg shadow">
        <h2 class="text-xl font-semibold mb-3">Add Viewers</h2>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
            <!-- Add Individual Users -->
            <div>
                <label class="block mb-2 font-semibold text-sm">Add Individual User</label>
                <select id="userSelect" class="w-full border rounded px-3 py-2" placeholder="Search users...">
                    <option value="">-- Type to search users --</option>
                    <?php foreach($allUsers as $u): ?>
                        <option value="<?php echo $u['user_id']; ?>" data-type="user"><?php echo htmlspecialchars($u['full_name'] . ' (' . $u['username'] . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Add Groups -->
            <div>
                <label class="block mb-2 font-semibold text-sm">Add Entire Group</label>
                <select id="groupSelect" class="w-full border rounded px-3 py-2" placeholder="Search groups...">
                    <option value="">-- Type to search groups --</option>
                    <?php foreach($allGroups as $g): ?>
                        <option value="<?php echo $g['group_id']; ?>" data-type="group"><?php echo htmlspecialchars($g['group_name'] . ' (' . $g['member_count'] . ' members)'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="mt-8 bg-white p-6 rounded-lg shadow">
        <h2 class="text-xl font-semibold mb-3">Authorized Viewers (Users & Groups)</h2>
        <div id="authorizedContainer" class="space-y-3">
            <?php foreach($authorized as $a): ?>
                <?php if ($a['user_id']): ?>
                    <div data-viewer-id="<?php echo $a['id']; ?>" data-viewer-type="user" class="flex justify-between items-center p-3 bg-gray-50 rounded">
                        <div>
                            <span class="font-medium"><?php echo htmlspecialchars($a['full_name']); ?></span>
                            <span class="text-xs text-gray-500 ml-2">[User] <?php echo $a['approved'] ? '✓ Approved' : '⏳ Pending'; ?></span>
                        </div>
                        <div class="space-x-2">
                            <button class="approveBtn px-2 py-1 text-xs bg-purple-500 text-white rounded hover:bg-purple-600">Toggle</button>
                            <button class="removeBtn px-2 py-1 text-xs bg-red-500 text-white rounded hover:bg-red-600">Remove</button>
                        </div>
                    </div>
                <?php elseif ($a['group_id']): ?>
                    <div data-viewer-id="<?php echo $a['id']; ?>" data-viewer-type="group" class="flex justify-between items-center p-3 bg-blue-50 rounded border-l-4 border-blue-500">
                        <div>
                            <span class="font-medium">📁 <?php echo htmlspecialchars($a['group_name']); ?></span>
                            <span class="text-xs text-gray-500 ml-2">[Group] <?php echo $a['approved'] ? '✓ Approved' : '⏳ Pending'; ?></span>
                        </div>
                        <div class="space-x-2">
                            <button class="approveBtn px-2 py-1 text-xs bg-purple-500 text-white rounded hover:bg-purple-600">Toggle</button>
                            <button class="removeBtn px-2 py-1 text-xs bg-red-500 text-white rounded hover:bg-red-600">Remove</button>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php if (empty($authorized)): ?>
            <p class="text-gray-500 italic">No viewers added yet. Add users or groups above.</p>
        <?php endif; ?>
    </div>
        <form id="announcementForm">
            <textarea id="announcementContent" class="w-full border rounded px-3 py-2" rows="3" placeholder="Type announcement here..."></textarea>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 mt-2">Post</button>
        </form>
        <ul id="announcementList" class="mt-4 list-disc pl-5">
            <?php foreach($announcements as $ann): ?>
                <li><?php echo htmlspecialchars($ann['content']); ?> <span class="text-xs text-gray-500"><?php echo $ann['created_at']; ?></span></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="mt-8 bg-white p-6 rounded-lg shadow">
        <h2 class="text-xl font-semibold mb-3">Presenter Controls</h2>
        <div id="slideControls">
            <button id="prevSlideBtn" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">Previous</button>
            <button id="nextSlideBtn" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 ml-2">Next</button>
            <span class="ml-4">Current slide: <span id="currentSlideDisplay"><?php echo $presentation['current_slide']; ?></span></span>
        </div>
        <div id="previewArea" class="mt-6">
            <p class="text-gray-500">Preview of current slide will appear here when presentation is active.</p>
        </div>
    </div>
</div>

<script>var csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
            var presentationId = <?php echo (int)$presentation['presentation_id']; ?>;
    </script>
<script src="assets/js/chat.js"></script>
<script src="assets/js/presentation.js"></script>
</body>
</html>

<?php
require_once 'includes/config.php';
requireLogin();

// Get current user data for theme
$current_user = getUserData($_SESSION['user_id']);

// Determine if dark mode should be applied
$is_dark_mode = false;
if (isset($current_user['theme_preference'])) {
    if ($current_user['theme_preference'] === 'dark') {
        $is_dark_mode = true;
    } elseif ($current_user['theme_preference'] === 'auto') {
        $is_dark_mode = isset($_SERVER['HTTP_SEC_CH_UA_DARK']) || 
                        (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'Dark') !== false);
    }
}

$success = '';
$error = '';
$group = null;
$messages = [];
$members = [];

// Get group_id from URL (either id= or join=)
$group_id = 0;
if (isset($_GET['join'])) {
    $group_id = (int)$_GET['join'];
} elseif (isset($_GET['id'])) {
    $group_id = (int)$_GET['id'];
}

// Handle join group
if (isset($_GET['join']) && $group_id > 0) {
    $check_stmt = $conn->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ?");
    $check_stmt->execute([$group_id, $_SESSION['user_id']]);
    
    if ($check_stmt->rowCount() === 0) {
        $join_stmt = $conn->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'member')");
        if ($join_stmt->execute([$group_id, $_SESSION['user_id']])) {
            $success = 'You have joined the group!';
            logActivity($_SESSION['user_id'], "Joined group ID: $group_id");
        } else {
            $error = 'Failed to join group';
        }
    } else {
        $success = 'You are already a member of this group';
    }
}

// Handle leave group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leave_group'])) {
    $leave_group_id = (int)$_POST['group_id'];
    
    $group_info_stmt = $conn->prepare("SELECT group_name FROM group_chats WHERE group_id = ?");
    $group_info_stmt->execute([$leave_group_id]);
    $group_info = $group_info_stmt->fetch();
    
    if ($group_info && $group_info['created_by'] == $_SESSION['user_id']) {
        $error = 'You cannot leave a group you created. Delete it instead.';
    } else {
        $leave_stmt = $conn->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
        if ($leave_stmt->execute([$leave_group_id, $_SESSION['user_id']])) {
            $success = 'You have left the group';
            logActivity($_SESSION['user_id'], "Left group ID: $leave_group_id");
            header('Location: groups.php');
            exit();
        } else {
            $error = 'Failed to leave group';
        }
    }
}

// Handle send message (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_send_message']) && $group_id > 0) {
    header('Content-Type: application/json');
    
    $message_text = sanitize($_POST['message_text'] ?? '');
    
    if (!empty($message_text)) {
        $insert_stmt = $conn->prepare("INSERT INTO group_messages (group_id, sender_id, message_text, message_type) VALUES (?, ?, ?, 'text')");
        if ($insert_stmt->execute([$group_id, $_SESSION['user_id'], $message_text])) {
            echo json_encode(['success' => true, 'message' => 'Message sent']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send message']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
    }
    exit();
}

// Get group details and messages
if ($group_id > 0) {
    $group_stmt = $conn->prepare("SELECT g.*, u.full_name as creator_name 
                                   FROM group_chats g 
                                   JOIN users u ON g.created_by = u.user_id 
                                   WHERE g.group_id = ?");
    $group_stmt->execute([$group_id]);
    $group = $group_stmt->fetch();
    
    if ($group) {
        $member_stmt = $conn->prepare("SELECT * FROM group_members WHERE group_id = ? AND user_id = ?");
        $member_stmt->execute([$group_id, $_SESSION['user_id']]);
        $membership = $member_stmt->fetch();
        
        if (!$membership && $group['created_by'] != $_SESSION['user_id']) {
            $error = 'You are not a member of this group';
            $group = null;
        } else {
            $messages_stmt = $conn->prepare("
                SELECT gm.*, u.full_name as sender_name, u.profile_picture as sender_picture
                FROM group_messages gm
                JOIN users u ON gm.sender_id = u.user_id
                WHERE gm.group_id = ?
                ORDER BY gm.created_at ASC
            ");
            $messages_stmt->execute([$group_id]);
            $messages = $messages_stmt->fetchAll();
            
            $members_stmt = $conn->prepare("
                SELECT gm.*, u.username, u.full_name, u.profile_picture, u.status
                FROM group_members gm
                JOIN users u ON gm.user_id = u.user_id
                WHERE gm.group_id = ?
                ORDER BY gm.role DESC, u.full_name ASC
            ");
            $members_stmt->execute([$group_id]);
            $members = $members_stmt->fetchAll();
        }
    } else {
        $error = 'Group not found';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/images/apple-touch-icon.png">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#7c3aed">
    <title><?php echo $group ? htmlspecialchars($group['group_name']) . ' - Group Chat' : 'Group Chat'; ?> - LAN Chat</title>
    <link href="assets/css/tailwind.min.css" rel="stylesheet">
    <script>
        const currentUserId = <?php echo $_SESSION['user_id']; ?>;
        const csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
        const baseUrl = '<?php echo BASE_URL; ?>';
        const groupId = <?php echo $group_id; ?>;
        
        // Emoji categories
        const emojiCategories = {
            'Smileys': { emojis: ['😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '🙃', '😉', '😊', '😇', '🥰', '😍', '🤩', '😘', '😗', '😚', '😙', '😋', '😛', '😜', '🤪', '😝', '🤑', '🤗', '🤭', '🤫', '🤔', '🤐', '🤨', '😐', '😑', '😶', '🙄', '😏', '😣', '😥', '😌', '😔', '😪', '🤤', '😴', '😷', '🤒', '🤕'], icon: '😊' },
            'Hearts': { emojis: ['❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔', '❣️', '💕', '💞', '💓', '💗', '💖', '💘', '💝', '💟', '💌', '💋'], icon: '❤️' },
            'Gestures': { emojis: ['👋', '🤚', '🖐️', '✋', '🖖', '👌', '🤌', '🤏', '✌️', '🤞', '🫰', '🤟', '🤘', '🤙', '👍', '👎', '👊', '✊', '👏', '🙌', '👐', '🤲', '🤝', '🙏'], icon: '👍' },
            'Objects': { emojis: ['📱', '💻', '⌨️', '🖥️', '🖨️', '🖱️', '📷', '📸', '📹', '🎥', '📞', '☎️', '📟', '📠', '📺', '📻', '🎙️', '🎚️', '🎛️', '⏱️', '⏲️', '⏰', '🕰️', '⌚', '📡'], icon: '📱' },
            'Symbols': { emojis: ['✅', '❌', '⭐', '🌟', '💫', '✨', '⚡', '☄️', '💥', '🔥', '🌪️', '🌈', '☀️', '🌤️', '⛅', '🌥️', '☁️', '🌦️', '🌧️', '⛈️', '🌩️', '🌨️', '❄️', '☃️', '⛄', '🌬️'], icon: '✨' }
        };
        
        // File categories
        const fileCategories = {
            'Photos': { icon: '📷', extensions: ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'] },
            'Documents': { icon: '📄', extensions: ['pdf', 'doc', 'docx', 'txt', 'xlsx', 'xls'] },
            'Audio': { icon: '🎵', extensions: ['mp3', 'wav', 'ogg', 'm4a', 'flac'] },
            'Other': { icon: '📎', extensions: ['zip', 'rar', '7z', 'tar', 'gz'] }
        };
    </script>
    <style>
        body { <?php echo $is_dark_mode ? 'background: #111827;' : ''; ?> }
        .dark-bg { <?php echo $is_dark_mode ? 'background: #1f2937; color: #f3f4f6;' : ''; ?> }
        .dark-card { <?php echo $is_dark_mode ? 'background: #374151;' : ''; ?> }
        .dark-text { <?php echo $is_dark_mode ? 'color: #f3f4f6;' : ''; ?> }
        .dark-input { <?php echo $is_dark_mode ? 'background: #4b5563; border-color: #6b7280; color: #f3f4f6;' : ''; ?> }
        .dark-border { <?php echo $is_dark_mode ? 'border-color: #4b5563;' : ''; ?> }
    </style>
</head>
<body class="<?php echo $is_dark_mode ? 'bg-gray-900' : 'bg-gray-100'; ?> h-screen flex flex-col">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg flex-shrink-0">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-4">
                    <a href="groups.php" class="flex items-center space-x-2">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        <span class="text-xl font-bold text-gray-800">Group Chat</span>
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="groups.php" class="text-purple-600 hover:text-purple-800 font-semibold">
                        ← Back to Groups
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 mx-4 mt-4 rounded">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 mx-4 mt-4 rounded">
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <?php if ($group): ?>
    <!-- Main Chat Area -->
    <div class="flex-1 flex overflow-hidden">
        <!-- Chat Messages Area -->
        <div class="flex-1 flex flex-col">
            <!-- Group Header -->
            <div class="bg-white border-b border-gray-200 p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($group['group_name']); ?></h2>
                            <p class="text-sm text-gray-500"><?php echo count($members); ?> members • Created by <?php echo htmlspecialchars($group['creator_name']); ?></p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button onclick="toggleMembersPanel()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700 font-semibold">
                            <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                            Members
                        </button>
                        <?php if ($group['created_by'] != $_SESSION['user_id']): ?>
                        <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to leave this group?');">
                            <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                            <button type="submit" name="leave_group" class="px-4 py-2 bg-red-100 hover:bg-red-200 rounded-lg text-red-700 font-semibold">
                                Leave Group
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($group['group_description']): ?>
                <p class="text-sm text-gray-600 mt-2"><?php echo htmlspecialchars($group['group_description']); ?></p>
                <?php endif; ?>
            </div>

            <!-- Messages Container -->
            <div id="chatMessages" class="flex-1 overflow-y-auto p-4 <?php echo $is_dark_mode ? 'bg-gray-800' : 'bg-gray-50'; ?>">
                <?php if (count($messages) === 0): ?>
                    <div class="flex items-center justify-center h-full text-gray-400">
                        <div class="text-center">
                            <svg class="w-20 h-20 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            <p class="text-lg">No messages yet</p>
                            <p class="text-sm">Be the first to send a message!</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $msg): 
                        $isSent = $msg['sender_id'] == $_SESSION['user_id'];
                        $isFile = in_array($msg['message_type'], ['file', 'image']);
                    ?>
                    <div class="flex <?php echo $isSent ? 'justify-end' : 'justify-start'; ?> mb-4">
                        <div class="flex items-end <?php echo $isSent ? 'flex-row-reverse' : ''; ?> space-x-2 max-w-[70%]">
                            <img src="uploads/profiles/<?php echo $msg['sender_picture']; ?>" 
                                 alt="<?php echo htmlspecialchars($msg['sender_name']); ?>"
                                 class="w-8 h-8 rounded-full flex-shrink-0"
                                 onerror="this.src='assets/images/default.png'">
                            <div class="<?php echo $isSent ? 'bg-purple-600 text-white' : 'bg-white border border-gray-200 text-gray-800'; ?> rounded-lg p-3 shadow">
                                <?php if (!$isSent): ?>
                                <p class="text-xs font-semibold text-purple-600 mb-1"><?php echo htmlspecialchars($msg['sender_name']); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($isFile && $msg['file_path']): ?>
                                    <?php if ($msg['message_type'] === 'image'): ?>
                                    <img src="uploads/<?php echo htmlspecialchars($msg['file_path']); ?>" alt="Image" class="max-w-xs rounded" onclick="window.open(this.src, '_blank')">
                                    <?php else: ?>
                                    <a href="uploads/<?php echo htmlspecialchars($msg['file_path']); ?>" download class="flex items-center space-x-2 text-purple-600 hover:text-purple-800">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        <span>Download file</span>
                                    </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                <p class="break-words"><?php echo htmlspecialchars($msg['message_text']); ?></p>
                                <?php endif; ?>
                                
                                <p class="text-xs <?php echo $isSent ? 'text-purple-200' : 'text-gray-500'; ?> mt-1">
                                    <?php echo date('M d, h:i A', strtotime($msg['created_at'])); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Message Input -->
            <div class="bg-white border-t border-gray-200 p-4">
                <form id="messageForm" class="flex items-center space-x-3">
                    <!-- File Upload Button -->
                    <button type="button" onclick="showFileUploadModal()" class="text-gray-500 hover:text-purple-600 p-2 rounded-full hover:bg-purple-50 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                        </svg>
                    </button>
                    
                    <!-- Emoji Button -->
                    <button type="button" onclick="toggleEmojiPicker()" class="text-gray-500 hover:text-purple-600 p-2 rounded-full hover:bg-purple-50 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </button>
                    
                    <input type="text" 
                           id="messageInput"
                           placeholder="Type a message..." 
                           class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 <?php echo $is_dark_mode ? 'dark-input' : ''; ?>"
                           autocomplete="off">
                    <button type="submit" 
                            class="px-6 py-3 bg-purple-600 text-white font-bold rounded-lg hover:bg-purple-700 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                        </svg>
                    </button>
                </form>
                
                <!-- Emoji Picker -->
                <div id="emojiPicker" class="hidden fixed bottom-20 right-4 bg-white rounded-lg shadow-2xl z-50 w-80 max-h-64 overflow-hidden border border-gray-200">
                    <div class="flex border-b border-gray-200 bg-gray-50 px-2 py-2">
                        <button type="button" onclick="switchEmojiCategory('Smileys')" class="emoji-category-tab flex-1 px-2 py-2 text-center rounded hover:bg-gray-200 transition text-xl bg-gray-200" data-category="Smileys">😊</button>
                        <button type="button" onclick="switchEmojiCategory('Hearts')" class="emoji-category-tab flex-1 px-2 py-2 text-center rounded hover:bg-gray-200 transition text-xl" data-category="Hearts">❤️</button>
                        <button type="button" onclick="switchEmojiCategory('Gestures')" class="emoji-category-tab flex-1 px-2 py-2 text-center rounded hover:bg-gray-200 transition text-xl" data-category="Gestures">👍</button>
                        <button type="button" onclick="switchEmojiCategory('Objects')" class="emoji-category-tab flex-1 px-2 py-2 text-center rounded hover:bg-gray-200 transition text-xl" data-category="Objects">📱</button>
                        <button type="button" onclick="switchEmojiCategory('Symbols')" class="emoji-category-tab flex-1 px-2 py-2 text-center rounded hover:bg-gray-200 transition text-xl" data-category="Symbols">✨</button>
                    </div>
                    <div class="overflow-y-auto p-3 max-h-44" id="emojiGrid">
                        <!-- Emojis will be inserted here by JavaScript -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Members Sidebar -->
        <div id="membersPanel" class="w-72 bg-white border-l border-gray-200 hidden lg:flex flex-col">
            <div class="p-4 border-b border-gray-200">
                <h3 class="text-lg font-bold text-gray-800">Group Members</h3>
                <p class="text-sm text-gray-500"><?php echo count($members); ?> members</p>
            </div>
            <div class="flex-1 overflow-y-auto p-4">
                <div class="space-y-3">
                    <?php foreach ($members as $member): ?>
                    <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50">
                        <div class="relative">
                            <img src="uploads/profiles/<?php echo $member['profile_picture']; ?>" 
                                 alt="<?php echo htmlspecialchars($member['full_name']); ?>"
                                 class="w-10 h-10 rounded-full"
                                 onerror="this.src='assets/images/default.png'">
                            <span class="absolute bottom-0 right-0 w-3 h-3 bg-<?php echo $member['status'] === 'online' ? 'green' : 'gray'; ?>-500 border-2 border-white rounded-full"></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-gray-800 truncate"><?php echo htmlspecialchars($member['full_name']); ?></p>
                            <p class="text-xs text-gray-500">
                                <?php if ($member['role'] === 'admin'): ?>
                                <span class="text-purple-600 font-semibold">Admin</span>
                                <?php else: ?>
                                Member
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- File Upload Modal -->
    <div id="fileUploadModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">Upload File</h3>
                <button onclick="hideFileUploadModal()" class="text-gray-500 hover:text-red-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <!-- Category Tabs -->
            <div class="flex border-b border-gray-200 mb-4">
                <?php $first = true; foreach(['Photos', 'Documents', 'Audio', 'Other'] as $cat): ?>
                <button type="button" onclick="switchFileCategory('<?php echo $cat; ?>')" class="file-category-tab flex-1 px-3 py-3 text-center border-b-2 <?php echo $first ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500'; ?>" data-category="<?php echo $cat; ?>">
                    <span class="text-xl"><?php echo ['Photos' => '📷', 'Documents' => '📄', 'Audio' => '🎵', 'Other' => '📎'][$cat]; ?></span>
                    <span class="block text-xs"><?php echo $cat; ?></span>
                </button>
                <?php $first = false; endforeach; ?>
            </div>
            
            <!-- Upload Area -->
            <div id="uploadArea" class="border-2 border-dashed border-purple-300 rounded-lg p-8 text-center bg-purple-50">
                <input type="file" id="fileInput" class="hidden" onchange="handleFileSelect(event)">
                <div class="text-4xl mb-2">📁</div>
                <p class="text-gray-700 font-semibold mb-2">Drag files here or click to browse</p>
                <button type="button" onclick="document.getElementById('fileInput').click()" class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition">
                    Choose File
                </button>
                <p class="text-xs text-gray-500 mt-3">Max 10MB per file</p>
            </div>
            
            <!-- Selected File Preview -->
            <div id="filePreview" class="hidden mt-4 p-3 bg-gray-50 rounded-lg">
                <div class="flex items-center justify-between">
                    <span id="fileName" class="text-gray-800 font-medium"></span>
                    <button onclick="clearFileSelection()" class="text-red-500 hover:text-red-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- Upload Button -->
            <button onclick="uploadFile()" id="uploadBtn" class="w-full mt-4 bg-purple-600 text-white py-3 rounded-lg hover:bg-purple-700 transition font-semibold disabled:opacity-50" disabled>
                Upload to Group
            </button>
        </div>
    </div>

    <script>
        let selectedFile = null;

        // Toggle members panel
        function toggleMembersPanel() {
            document.getElementById('membersPanel').classList.toggle('hidden');
        }

        // Auto-scroll to bottom
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('chatMessages');
            container.scrollTop = container.scrollHeight;
            renderEmojiPicker();
        });

        // Render emoji picker
        function renderEmojiPicker() {
            const grid = document.getElementById('emojiGrid');
            const firstCat = Object.keys(emojiCategories)[0];
            let html = '<div id="emoji-grid-' + firstCat + '" class="grid grid-cols-8 gap-1">';
            emojiCategories[firstCat].emojis.forEach(emoji => {
                html += '<button type="button" onclick="insertEmoji(\'' + emoji + '\')" class="text-2xl p-1 hover:bg-gray-100 rounded">' + emoji + '</button>';
            });
            html += '</div>';
            Object.keys(emojiCategories).slice(1).forEach(cat => {
                html += '<div id="emoji-grid-' + cat + '" class="grid grid-cols-8 gap-1 hidden">';
                emojiCategories[cat].emojis.forEach(emoji => {
                    html += '<button type="button" onclick="insertEmoji(\'' + emoji + '\')" class="text-2xl p-1 hover:bg-gray-100 rounded">' + emoji + '</button>';
                });
                html += '</div>';
            });
            grid.innerHTML = html;
        }

        // Toggle emoji picker
        function toggleEmojiPicker() {
            document.getElementById('emojiPicker').classList.toggle('hidden');
        }

        // Switch emoji category
        function switchEmojiCategory(category) {
            document.querySelectorAll('.emoji-grid').forEach(g => g.classList.add('hidden'));
            document.getElementById('emoji-grid-' + category).classList.remove('hidden');
            document.querySelectorAll('.emoji-category-tab').forEach(t => {
                t.classList.remove('bg-gray-200');
                if (t.dataset.category === category) t.classList.add('bg-gray-200');
            });
        }

        // Insert emoji
        function insertEmoji(emoji) {
            const input = document.getElementById('messageInput');
            input.value += emoji;
            input.focus();
        }

        // File upload modal
        function showFileUploadModal() {
            document.getElementById('fileUploadModal').classList.remove('hidden');
        }

        function hideFileUploadModal() {
            document.getElementById('fileUploadModal').classList.add('hidden');
            clearFileSelection();
        }

        function handleFileSelect(event) {
            const file = event.target.files[0];
            if (file) {
                if (file.size > 10485760) {
                    alert('File size exceeds 10MB limit');
                    return;
                }
                selectedFile = file;
                document.getElementById('filePreview').classList.remove('hidden');
                document.getElementById('fileName').textContent = file.name;
                document.getElementById('uploadBtn').disabled = false;
            }
        }

        function clearFileSelection() {
            selectedFile = null;
            document.getElementById('filePreview').classList.add('hidden');
            document.getElementById('fileInput').value = '';
            document.getElementById('uploadBtn').disabled = true;
        }

        function uploadFile() {
            if (!selectedFile) return;
            
            const formData = new FormData();
            formData.append('file', selectedFile);
            formData.append('group_id', groupId);
            formData.append('csrf_token', csrfToken);
            
            document.getElementById('uploadBtn').textContent = 'Uploading...';
            document.getElementById('uploadBtn').disabled = true;
            
            fetch(baseUrl + 'chat/upload_group_file.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    hideFileUploadModal();
                    location.reload();
                } else {
                    alert(data.message || 'Upload failed');
                }
                document.getElementById('uploadBtn').textContent = 'Upload to Group';
                document.getElementById('uploadBtn').disabled = !selectedFile;
            })
            .catch(err => {
                alert('Upload failed');
                document.getElementById('uploadBtn').textContent = 'Upload to Group';
                document.getElementById('uploadBtn').disabled = !selectedFile;
            });
        }

        // Send message form
        document.getElementById('messageForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            
            if (!message) return;
            
            const formData = new FormData();
            formData.append('ajax_send_message', '1');
            formData.append('message_text', message);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    input.value = '';
                    location.reload();
                }
            });
        });

        // Auto-refresh
        setInterval(function() {
            if (window.location.search.includes('id=')) {
                location.reload();
            }
        }, 5000);
    </script>
    <?php else: ?>
    <!-- No group selected -->
    <div class="flex-1 flex items-center justify-center">
        <div class="text-center">
            <svg class="w-24 h-24 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
            <h2 class="text-2xl font-bold text-gray-600 mb-2">No Group Selected</h2>
            <p class="text-gray-500 mb-4">Select a group from the groups page to start chatting</p>
            <a href="groups.php" class="inline-block px-6 py-3 bg-purple-600 text-white font-bold rounded-lg hover:bg-purple-700 transition">
                View Groups
            </a>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>

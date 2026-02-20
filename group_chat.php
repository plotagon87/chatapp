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
    $reply_to = isset($_POST['reply_to']) ? (int)$_POST['reply_to'] : null;
    
    if (!empty($message_text)) {
        if ($reply_to) {
            $insert_stmt = $conn->prepare("INSERT INTO group_messages (group_id, sender_id, message_text, message_type, reply_to) VALUES (?, ?, ?, 'text', ?)");
            $params = [$group_id, $_SESSION['user_id'], $message_text, $reply_to];
        } else {
            $insert_stmt = $conn->prepare("INSERT INTO group_messages (group_id, sender_id, message_text, message_type) VALUES (?, ?, ?, 'text')");
            $params = [$group_id, $_SESSION['user_id'], $message_text];
        }
        if ($insert_stmt->execute($params)) {
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
            // check whether the pinned_messages table exists so we can avoid
            // crashing on a downgraded/old schema.
            $pinExists = false;
            try {
                $chk = $conn->query("SHOW TABLES LIKE 'pinned_messages'");
                $pinExists = (bool) $chk->fetch();
            } catch (PDOException $e) {
                // ignore - we'll just treat the table as missing
            }

            if ($pinExists) {
                $pinSub = "(SELECT 1 FROM pinned_messages pm WHERE pm.group_message_id = gm.message_id AND pm.pinned_by = ?) AS is_pinned";
                $params = [$_SESSION['user_id'], $group_id];
            } else {
                $pinSub = "0 AS is_pinned";
                $params = [$group_id];
            }

            $messages_stmt = $conn->prepare("
                SELECT gm.*, u.full_name as sender_name, u.profile_picture as sender_picture,\n                       r.message_text AS reply_text,\n                       rs.full_name AS reply_sender_name,\n                       $pinSub
                FROM group_messages gm
                JOIN users u ON gm.sender_id = u.user_id
                LEFT JOIN group_messages r ON gm.reply_to = r.message_id
                LEFT JOIN users rs ON r.sender_id = rs.user_id
                WHERE gm.group_id = ?
                ORDER BY gm.created_at ASC
            ");
            $messages_stmt->execute($params);
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
            'Audio': { icon: '🎵', extensions: ['mp3', 'wav', 'ogg', 'm4a', 'flac', 'webm'] },
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
                        <button onclick="showPinnedMessages(groupId)" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700 font-semibold" title="View pinned messages">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M4 2a1 1 0 00-1 1v3a1 1 0 00.293.707L9 12l1 4 4-4 5.707-5.707A1 1 0 0019 6V3a1 1 0 00-1-1H4z"/>
                            </svg>
                        </button>
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
                        // deletion
                        if (!empty($msg['is_deleted'])) {
                            // render using the same layout as a regular message so it doesn't appear oversized
                            $bubbleClass = $isSent ? 'bg-purple-600 text-white' : 'bg-white border border-gray-200 text-gray-800';
                            echo '<div class="flex ' . ($isSent ? 'justify-end' : 'justify-start') . ' mb-4" data-msg-id="' . $msg['message_id'] . '">';
                            echo '<div class="flex items-end ' . ($isSent ? 'flex-row-reverse' : '') . ' space-x-2 max-w-[70%]">';
                            // show sender avatar for consistency
                            echo '<img src="uploads/profiles/' . $msg['sender_picture'] . '" ' 
                                . 'alt="' . htmlspecialchars($msg['sender_name']) . '" '
                                . 'class="w-8 h-8 rounded-full flex-shrink-0" '
                                . 'onerror="this.src=\'assets/images/default.png\'">';
                            echo '<div class="relative group message-bubble ' . $bubbleClass . ' italic opacity-75 rounded-lg p-3 shadow">';
                            echo '<p>This message has been deleted</p>';
                            echo '</div></div></div>';
                            continue;
                        }
                        $isFile = in_array($msg['message_type'], ['file', 'image']);
                        $isVoice = $msg['message_type'] === 'voice';
                        $pinHtml = !empty($msg['is_pinned']) ? '<svg class="w-4 h-4 ml-1 inline text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M5 2a1 1 0 00-1 1v4H3a1 1 0 000 2h1v5a1 1 0 001 1h3v3a1 1 0 002 0v-3h3a1 1 0 001-1v-5h1a1 1 0 100-2h-1V3a1 1 0 00-1-1H5z"/></svg>' : '';
                        $replyHtml = '';
                        if (!empty($msg['reply_text'])) {
                            $replyHtml = '<div class="bg-gray-100 text-gray-700 rounded p-2 mb-2 text-sm border-l-2 border-gray-300"><strong>' . htmlspecialchars($msg['reply_sender_name'] ?: 'Unknown') . ':</strong> ' . htmlspecialchars($msg['reply_text']) . '</div>';
                        }
                        $editedLabel = '';
                        if (!empty($msg['edited_count'])) {
                            $editedLabel = ' <span class="text-xs italic">(edited)</span>';
                        }
                    ?>
                    <div class="flex <?php echo $isSent ? 'justify-end' : 'justify-start'; ?> mb-4" data-msg-id="<?php echo $msg['message_id']; ?>"<?php if (!empty($msg['edited_count'])) echo ' data-edited="1"'; ?><?php if (!empty($msg['is_pinned'])) echo ' data-pinned="1"'; ?>>
                        <div class="flex items-end <?php echo $isSent ? 'flex-row-reverse' : ''; ?> space-x-2 max-w-[70%]">
                            <img src="uploads/profiles/<?php echo $msg['sender_picture']; ?>" 
                                 alt="<?php echo htmlspecialchars($msg['sender_name']); ?>"
                                 class="w-8 h-8 rounded-full flex-shrink-0"
                                 onerror="this.src='assets/images/default.png'">
                            <div class="relative group message-bubble <?php echo $isSent ? 'bg-purple-600 text-white' : 'bg-white border border-gray-200 text-gray-800'; ?> rounded-lg p-3 shadow">
                                <?php if (!$isSent): ?>
                                <p class="text-xs font-semibold text-purple-600 mb-1"><?php echo htmlspecialchars($msg['sender_name']); ?></p>
                                <?php endif; ?>
                                <?php echo $pinHtml; ?>
                                <?php echo $replyHtml; ?>
                                
                                <?php if ($isVoice && $msg['file_path']): ?>
                                    <?php
                                        $ext = pathinfo($msg['file_path'], PATHINFO_EXTENSION);
                                        $mime = 'audio/' . $ext;
                                        if ($ext === 'webm') $mime = 'audio/webm;codecs=opus';
                                        if ($ext === 'ogg') $mime = 'audio/ogg;codecs=opus';
                                    ?>
                                    <audio controls class="max-w-xs">
                                        <source src="uploads/<?php echo htmlspecialchars($msg['file_path']); ?>" type="<?php echo $mime; ?>">
                                        Your browser does not support audio playback. <a href="uploads/<?php echo htmlspecialchars($msg['file_path']); ?>" download>Download</a>
                                    </audio>
                                <?php elseif ($isFile && $msg['file_path']): ?>
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
                                <p class="break-words"><?php echo htmlspecialchars($msg['message_text']); ?><?php if (!empty($msg['edited_count'])): ?> <span class="text-xs italic cursor-pointer text-purple-200" onclick="showEditHistoryGroup(<?php echo $msg['message_id']; ?>)">(edited)</span><?php endif; ?></p>
                                <?php endif; ?>
                                
                                <p class="text-xs <?php echo $isSent ? 'text-purple-200' : 'text-gray-500'; ?> mt-1">
                                    <?php echo date('M d, h:i A', strtotime($msg['created_at'])); ?>
                                </p>
                                <!-- reactions display -->
                                <?php
                                if (!empty($msg['reactions'])) {
                                    echo '<div class="message-reactions flex flex-wrap gap-1 mt-2">';
                                    foreach ($msg['reactions'] as $reaction) {
                                        $type = $reaction['reaction_type'];
                                        $count = $reaction['count'];
                                        $userReacted = in_array($type, $msg['user_reactions']);
                                        $emoji = ['like'=>'👍','love'=>'❤️','haha'=>'😂','wow'=>'😮','sad'=>'😢','angry'=>'😠'][$type] ?? $type;
                                        $badgeClass = $userReacted ? 'bg-purple-100 border-purple-300' : 'bg-gray-100 border-gray-300';
                                        echo "<span class=\"inline-flex items-center space-x-1 px-2 py-1 rounded-full text-xs $badgeClass border cursor-pointer hover:scale-110 transition-transform\" onclick=\"addReaction({$msg['message_id']}, '$type')\" title=\"Reacted with $type\">";
                                        echo "<span>$emoji</span><span class=\"font-semibold\">$count</span></span>";
                                    }
                                    echo '</div>'; 
                                } else {
                                    echo '<div class="message-reactions"></div>';
                                }
                                ?>
                                <!-- reaction button -->
                                <button onclick="showReactionPicker(<?php echo $msg['message_id']; ?>, event)" class="absolute -top-2 -right-2 bg-white border border-gray-300 rounded-full p-1 shadow-md opacity-0 group-hover:opacity-100 transition-opacity hover:scale-110" title="Add reaction">
                                    <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Message Input -->
            <div class="bg-white border-t border-gray-200 p-4">
                <form id="messageForm" class="flex items-center space-x-3">
                    <!-- Audio Record Button -->
                    <button type="button" id="audioRecordBtn" class="text-gray-500 hover:text-purple-600 p-2 rounded-full hover:bg-purple-50 transition" title="Record audio">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 1v4m0 14v4m4-10a4 4 0 01-8 0V7a4 4 0 018 0z" />
                        </svg>
                    </button>
                    <span id="recordingTimer" class="ml-2 text-xs text-gray-600"></span>
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

    <!-- Info / Message Details Modal -->
    <div id="infoModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-md p-6 relative">
            <button onclick="hideInfoModal()" class="absolute top-3 right-3 text-gray-500 hover:text-red-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
            <h3 id="infoModalTitle" class="text-xl font-bold text-gray-800 mb-4"></h3>
            <pre id="infoModalContent" class="text-sm whitespace-pre-wrap"></pre>
        </div>
    </div>

    <script>
        let selectedFile = null;
        // audio recording state
        let isRecording = false;
        let mediaRecorder = null;
        let recordedChunks = [];
        // recording helpers
        let maxRecordingSeconds = 60; // 1 minute cap
        let recordingTimer = null;
        let recordingTimeElapsed = 0;

        // Toggle members panel
        function toggleMembersPanel() {
            document.getElementById('membersPanel').classList.toggle('hidden');
        }

        // start voice recording
        function startRecordingGroup() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert('Audio recording not supported');
                return;
            }
            navigator.mediaDevices.getUserMedia({ audio: true }).then(stream => {
                recordedChunks = [];
                // choose mime type
                let mime = 'audio/webm';
                if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
                    mime = 'audio/webm;codecs=opus';
                } else if (MediaRecorder.isTypeSupported('audio/ogg;codecs=opus')) {
                    mime = 'audio/ogg;codecs=opus';
                }
                try {
                    mediaRecorder = new MediaRecorder(stream, { mimeType: mime });
                } catch (e) {
                    console.warn('could not set mime type for recorder', e);
                    mediaRecorder = new MediaRecorder(stream);
                }
                mediaRecorder.ondataavailable = e => {
                    if (e.data.size > 0) recordedChunks.push(e.data);
                };
                mediaRecorder.onstop = () => {
                    stream.getTracks().forEach(t => t.stop());
                    handleRecordingCompleteGroup();
                };
                mediaRecorder.start();
                isRecording = true;
                recordingTimeElapsed = 0;
                recordingTimer = setInterval(() => {
                    recordingTimeElapsed++;
                    updateRecordingTimerGroup();
                    if (recordingTimeElapsed >= maxRecordingSeconds) {
                        stopRecordingGroup();
                        alert('Maximum recording duration reached');
                    }
                }, 1000);
                updateRecordingButtonGroup();
            }).catch(err => {
                console.error('mic error', err);
                alert('Microphone access denied');
            });
        }

        function stopRecordingGroup() {
            if (mediaRecorder && isRecording) {
                mediaRecorder.stop();
                isRecording = false;
                if (recordingTimer) {
                    clearInterval(recordingTimer);
                    recordingTimer = null;
                }
                updateRecordingButtonGroup();
                updateRecordingTimerGroup();
            }
        }

        function handleRecordingCompleteGroup() {
            if (recordedChunks.length === 0) return;
            // determine mime/extension from recorder if available
            let mime = mediaRecorder && mediaRecorder.mimeType ? mediaRecorder.mimeType : 'audio/webm';
            const ext = mime.includes('ogg') ? 'ogg' : 'webm';
            const blob = new Blob(recordedChunks, { type: mime });
            const file = new File([blob], `voice_${Date.now()}.${ext}`, { type: mime });
            selectedFile = file;
            // show in preview area with waveform
            document.getElementById('filePreview').classList.remove('hidden');
            document.getElementById('filePreview').innerHTML = `
                <audio controls class="w-full"><source src="${URL.createObjectURL(file)}" type="${mime}">Your browser does not support audio playback.</audio>
                <canvas class="waveform-canvas mt-2 w-full h-12"></canvas>`;
            drawWaveformGroup(file, document.querySelector('#filePreview .waveform-canvas'));
            document.getElementById('uploadBtn').disabled = false;
        }

        function updateRecordingButtonGroup() {
            const btn = document.getElementById('audioRecordBtn');
            if (!btn) return;
            if (isRecording) {
                btn.innerHTML = `<svg class="w-6 h-6 text-red-600" fill="currentColor" viewBox="0 0 24 24"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>`;
                btn.title = 'Stop recording';
            } else {
                btn.innerHTML = `<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 1v4m0 14v4m4-10a4 4 0 01-8 0V7a4 4 0 018 0z"/></svg>`;
                btn.title = 'Record audio';
            }
            updateRecordingTimerGroup();
        }


        // Auto-scroll to bottom
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('chatMessages');
            container.scrollTop = container.scrollHeight;
            renderEmojiPicker();

            // bind audio button if present
            const audioBtn = document.getElementById('audioRecordBtn');
            if (audioBtn) {
                audioBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (isRecording) {
                        stopRecordingGroup();
                    } else {
                        startRecordingGroup();
                    }
                });
                updateRecordingButtonGroup();
            }
        });

        // waveform helper, timer, compatibility
        function updateRecordingTimerGroup() {
            const span = document.getElementById('recordingTimer');
            if (!span) return;
            span.textContent = isRecording ? `${recordingTimeElapsed}s` : '';
        }

        function canPlayAudioTypeGroup(mime) {
            const a = document.createElement('audio');
            return !!(a.canPlayType && a.canPlayType(mime));
        }

        function drawWaveformGroup(file, canvas) {
            if (!canvas || !file) return;
            const reader = new FileReader();
            reader.onload = (e) => {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                audioCtx.decodeAudioData(e.target.result, (buffer) => {
                    const data = buffer.getChannelData(0);
                    const step = Math.ceil(data.length / canvas.width);
                    const amp = canvas.height / 2;
                    const ctx = canvas.getContext('2d');
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    ctx.fillStyle = '#a78bfa';
                    for (let i = 0; i < canvas.width; i++) {
                        let min = 1.0;
                        let max = -1.0;
                        for (let j = 0; j < step; j++) {
                            const datum = data[(i * step) + j];
                            if (datum < min) min = datum;
                            if (datum > max) max = datum;
                        }
                        ctx.fillRect(i, (1 + min) * amp, 1, Math.max(1, (max - min) * amp));
                    }
                });
            };
            reader.readAsArrayBuffer(file);
        }

        // ============================================
        // MESSAGE REACTIONS (group-specific globals)
        // ============================================
        function showReactionPicker(messageId, event) {
            event.stopPropagation();
            const existing = document.getElementById('reactionPicker');
            if (existing) {
                existing.remove();
                return;
            }
            const picker = document.createElement('div');
            picker.id = 'reactionPicker';
            picker.className = 'absolute bg-white rounded-lg shadow-2xl p-2 z-50 border border-gray-200 flex space-x-1';
            // position near click but keep within viewport
            let left = event.pageX;
            let top = event.pageY - 50;
            const pad = 10;
            const pickerWidth = 200; // approximate
            const pickerHeight = 60;
            if (left + pickerWidth + pad > window.innerWidth) {
                left = window.innerWidth - pickerWidth - pad;
            }
            if (top < pad) {
                top = pad;
            } else if (top + pickerHeight + pad > window.innerHeight) {
                top = window.innerHeight - pickerHeight - pad;
            }
            picker.style.left = left + 'px';
            picker.style.top = top + 'px';
            const reactionEmojis = { 'like':'👍','love':'❤️','haha':'😂','wow':'😮','sad':'😢','angry':'😠' };
            let html = '';
            for (const [type, emoji] of Object.entries(reactionEmojis)) {
                html += `<button onclick="addReaction(${messageId}, '${type}')" class="text-2xl hover:scale-125 transition-transform p-1 rounded hover:bg-gray-100" title="${type}">${emoji}</button>`;
            }
            picker.innerHTML = html;
            document.body.appendChild(picker);
            setTimeout(() => {
                document.addEventListener('click', function closeReactionPicker(e) {
                    if (!e.target.closest('#reactionPicker')) {
                        picker.remove();
                        document.removeEventListener('click', closeReactionPicker);
                    }
                });
            }, 100);
        }

        async function addReaction(messageId, reactionType) {
            const picker = document.getElementById('reactionPicker');
            if (picker) picker.remove();

            // disable while processing
            const msgElem = document.querySelector(`[data-msg-id="${messageId}"] .message-reactions`);
            if (msgElem) {
                msgElem.classList.add('opacity-50','pointer-events-none');
            }

            try {
                let action = 'add';
                if (msgElem) {
                    const badge = msgElem.querySelector(`span[onclick*="addReaction(${messageId}, '${reactionType}')"]`);
                    if (badge && badge.classList.contains('bg-purple-100')) {
                        action = 'remove';
                    }
                }

                const formData = new FormData();
                formData.append('message_id', messageId);
                formData.append('reaction_type', reactionType);
                formData.append('action', action);
                formData.append('csrf_token', csrfToken);
                const response = await fetch(`${baseUrl}chat/add_reaction.php`, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    updateMessageReactions(messageId, data.reactions, data.user_reactions);
                } else {
                    console.error('Failed to add reaction:', data.message);
                }
            } catch (error) {
                console.error('Reaction error:', error);
            } finally {
                if (msgElem) {
                    msgElem.classList.remove('opacity-50','pointer-events-none');
                }
            }
        }

        // ------------------------------------------------
        // CONTEXT MENU FOR GROUP MESSAGES
        // ------------------------------------------------
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('chatMessages');
            if (container) {
                container.addEventListener('contextmenu', function(e) {
                    const bubble = e.target.closest('.message-bubble');
                    if (!bubble) return;
                    e.preventDefault();
                    const msgElem = bubble.closest('[data-msg-id]');
                    if (!msgElem) return;
                    const msgId = msgElem.getAttribute('data-msg-id');
                    const isSent = bubble.classList.contains('bg-purple-600');
                    showGroupContextMenu(msgId, e.pageX, e.pageY, isSent);
                });
                document.addEventListener('click', () => { const m=document.getElementById('groupContextMenu'); if(m) m.remove(); });
            }
        });

        function showGroupContextMenu(messageId, x, y, isSent) {
            const existing = document.getElementById('groupContextMenu');
            if (existing) existing.remove();
            const menu = document.createElement('div');
            menu.id = 'groupContextMenu';
            menu.className = 'absolute bg-white border border-gray-200 rounded shadow-lg z-50 text-sm';
            menu.style.left = x + 'px';
            menu.style.top = y + 'px';
            const ul = document.createElement('ul');
            ul.className = 'py-1';
            const addItem = (label, handler, disabled=false) => {
                const li = document.createElement('li');
                li.className = `px-4 py-2 hover:bg-gray-100 cursor-pointer ${disabled?'opacity-50 pointer-events-none':''}`;
                li.textContent = label;
                li.addEventListener('click', e => { e.stopPropagation(); handler(); });
                ul.appendChild(li);
            };
            let allowEdit = true;
            if (isSent) {
                // compute timestamp on element
                const msgElem = document.querySelector(`[data-msg-id="${messageId}"]`);
                if (msgElem) {
                    const timeElem = msgElem.querySelector('.text-xs');
                    if (timeElem) {
                        const parsed = new Date(timeElem.textContent.trim());
                        if (!isNaN(parsed) && Date.now() - parsed.getTime() > 3 * 60 * 1000) {
                            allowEdit = false;
                        }
                    }
                }
                addItem('Edit', () => editGroupMessage(messageId), !allowEdit);
            }
            addItem('Reply', () => replyToMessage(messageId));
            // show history if message has edits
            if ((() => {
                const el = document.querySelector(`[data-msg-id="${messageId}"]`);
                return el && el.dataset.edited;
            })()) {
                addItem('View Edit History', () => showEditHistoryGroup(messageId));
            }
            // pin/unpin label based on state
            {
                const el = document.querySelector(`[data-msg-id="${messageId}"]`);
                const pinned = el && el.dataset.pinned;
                addItem(pinned ? 'Unpin' : 'Pin', () => togglePin(messageId, true));
            }
            addItem('Delete', () => deleteMessage(messageId, true));
            addItem('Message Info', () => showGroupMessageInfo(messageId));
            menu.appendChild(ul);
            document.body.appendChild(menu);
        }

        async function editGroupMessage(messageId) {
            const msgElem = document.querySelector(`[data-msg-id="${messageId}"] p.break-words`);
            if (!msgElem) return;
            const old = msgElem.textContent;
            const nxt = prompt('Edit your message (3 minutes allowed):', old);
            if (nxt === null || nxt === old) return;
            const form = new FormData();
            form.append('group_message_id', messageId);
            form.append('new_text', nxt);
            form.append('csrf_token', csrfToken);
            const res = await fetch(`${baseUrl}chat/edit_group_message.php`, { method: 'POST', body: form });
            const d = await res.json();
            if (d.success) {
                msgElem.textContent = nxt + ' (edited)';
            } else {
                alert(d.message || 'Failed to edit');
            }
        }

        function showGroupMessageInfo(messageId) {
            fetch(`${baseUrl}chat/group_message_info.php?group_message_id=${messageId}`)
                .then(r=>r.json())
                .then(d=>{
                    if (d.success) {
                        let text = 'Status:\n';
                        d.status.forEach(s=>{
                            text += `${s.full_name}: delivered=${s.is_delivered}?${s.delivered_at}: read=${s.is_read}?${s.read_at}\n`;
                        });
                        showInfoModal('Message Info', text);
                    }
                });
        }
        function showEditHistoryGroup(messageId) {
            fetch(`${baseUrl}chat/message_edit_history.php?message_id=${messageId}&is_group=1`)
                .then(r=>r.json())
                .then(d=>{
                    if (d.success) {
                        let txt = 'Edit history:\n';
                        d.edits.forEach(e=>{
                            txt += `${e.edited_at} by ${e.edited_by}: ${e.old_text} → ${e.new_text}\n`;
                        });
                        showInfoModal('Edit History', txt);
                    }
                });
        }

        function showInfoModal(title, content) {
            const modal = document.getElementById('infoModal');
            if (!modal) return;
            document.getElementById('infoModalTitle').textContent = title;
            document.getElementById('infoModalContent').textContent = content;
            modal.classList.remove('hidden');
        }
        function hideInfoModal() {
            const modal = document.getElementById('infoModal');
            if (modal) modal.classList.add('hidden');
        }
        // close modal when clicking outside content
        document.addEventListener('click', (e) => {
            const modal = document.getElementById('infoModal');
            if (modal && e.target === modal) {
                hideInfoModal();
            }
        });

        function showPinnedMessages(groupId) {
            fetch(`${baseUrl}chat/get_pinned_messages.php?is_group=1&group_id=${groupId}`)
                .then(r=>r.json())
                .then(d=>{
                    if (d.success) {
                        let txt = '';
                        d.messages.forEach(m=>{
                            txt += `${m.sender_name} (${m.created_at}): ${m.message_text}\n`;
                        });
                        showInfoModal('Pinned Messages', txt || 'No pinned messages');
                    }
                });
        }
        async function togglePin(messageId, isGroup) {
            try {
                const form = new FormData();
                form.append('message_id', messageId);
                form.append('is_group', isGroup ? '1' : '0');
                form.append('csrf_token', csrfToken);
                const res = await fetch(`${baseUrl}chat/toggle_pin.php`, {method:'POST', body: form});
                const data = await res.json();
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Pin failed');
                }
            } catch (err) {
                console.error(err);
            }
        }
        function replyToMessage(messageId) {
            const msgElem = document.querySelector(`[data-msg-id="${messageId}"]`);
            if (!msgElem) return;
            const text = msgElem.querySelector('p.break-words')?.textContent || '';
            const inputArea = document.getElementById('messageInputArea');
            const existing = document.getElementById('replyBanner');
            if (existing) existing.remove();
            const banner = document.createElement('div');
            banner.id = 'replyBanner';
            banner.className = 'bg-gray-100 border-l-4 border-gray-400 p-2 mb-2 flex justify-between items-center';
            banner.innerHTML = `<span class="text-sm truncate">Replying to: ${text}</span><button class="text-red-500 ml-2">×</button>`;
            banner.querySelector('button').addEventListener('click', () => banner.remove());
            inputArea.insertBefore(banner, inputArea.firstChild);
            inputArea.dataset.replyTo = messageId;
            document.getElementById('messageInput').focus();
        }
        async function deleteMessage(messageId, isGroup) {
            if (!confirm('Are you sure you want to delete this message?')) return;
            try {
                const form = new FormData();
                form.append('message_id', messageId);
                form.append('is_group', isGroup ? '1' : '0');
                form.append('csrf_token', csrfToken);
                const res = await fetch(`${baseUrl}chat/delete_message.php`, {method:'POST', body: form});
                const d = await res.json();
                if (d.success) {
                    location.reload();
                } else {
                    alert(d.message || 'Delete failed');
                }
            } catch (err) {
                console.error(err);
            }
        }

        function updateMessageReactions(messageId, reactions, userReactions) {
            const msgElem = document.querySelector(`[data-msg-id="${messageId}"]`);
            if (!msgElem) return;
            let container = msgElem.querySelector('.message-reactions');
            if (!container) {
                container = document.createElement('div');
                container.className = 'message-reactions flex flex-wrap gap-1 mt-2';
                msgElem.appendChild(container);
            }
            container.innerHTML = '';
            reactions.forEach(r => {
                const isUser = userReactions && userReactions.includes(r.reaction_type);
                const emojiMap = { 'like':'👍','love':'❤️','haha':'😂','wow':'😮','sad':'😢','angry':'😠' };
                const emoji = emojiMap[r.reaction_type] || r.reaction_type;
                const badge = document.createElement('span');
                badge.className = `inline-flex items-center space-x-1 px-2 py-1 rounded-full text-xs ${isUser? 'bg-purple-100 border-purple-300' : 'bg-gray-100 border-gray-300'} border cursor-pointer hover:scale-110 transition-transform`;
                badge.onclick = () => addReaction(messageId, r.reaction_type);
                badge.title = r.reaction_type;
                badge.innerHTML = `<span>${emoji}</span><span class="font-semibold">${r.count}</span>`;
                container.appendChild(badge);
            });
        }
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
                // if audio, show player + waveform else just show file name
                if (file.type.startsWith('audio/')) {
                    document.getElementById('filePreview').innerHTML = `
                        <audio controls class="w-full">
                            <source src="${URL.createObjectURL(file)}" type="${file.type}">
                            Your browser does not support audio playback.
                        </audio>
                        <canvas class="waveform-canvas mt-2 w-full h-12"></canvas>`;
                    const canvas = document.querySelector('#filePreview .waveform-canvas');
                    drawWaveformGroup(file, canvas);
                } else {
                    document.getElementById('filePreview').innerHTML = '';
                    document.getElementById('fileName').textContent = file.name;
                }
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
            // include reply id if present
            const replyTo = document.getElementById('messageInputArea')?.dataset.replyTo;
            if (replyTo) {
                formData.append('reply_to', replyTo);
            }
            formData.append('csrf_token', csrfToken);
            
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
    <script src="assets/js/e2ee.js?v=<?php echo time(); ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            if (window.e2eeEnabled) {
                if (!localStorage.getItem('e2ee_private_jwk')) {
                    try {
                        await generateKeyPairAndUpload();
                        console.log('🔐 Generated E2EE key pair (group page)');
                    } catch (err) {
                        console.error('E2EE key generation failed:', err);
                    }
                }
            } else {
                console.warn('E2EE not available; skipping key gen on group page');
            }
        });
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

<?php
require_once 'includes/config.php';
requireLogin();

$user = getUserData($_SESSION['user_id']);
$success = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $custom_status = sanitize($_POST['custom_status']);
    
    // Validate
    if (empty($full_name) || empty($email)) {
        $error = 'Name and email are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } else {
        // Check if email is already taken by another user
        $check_email = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $check_email->execute([$email, $_SESSION['user_id']]);
        
        if ($check_email->rowCount() > 0) {
            $error = 'Email already in use';
        } else {
            // Handle profile picture upload
            $profile_picture = $user['profile_picture'];
            
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['profile_picture'];
                $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                // Validate file type
                if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                    // Validate file size (5MB max)
                    if ($file['size'] <= 5242880) {
                        $upload_dir = 'uploads/profiles/';
                        
                        // Create directory if it doesn't exist
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        // Generate unique filename
                        $new_filename = 'user_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
                        $upload_path = $upload_dir . $new_filename;
                        
                        // Move uploaded file
                        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                            // Delete old profile picture if not default
                            if ($user['profile_picture'] !== 'default.png' && 
                                file_exists($upload_dir . $user['profile_picture'])) {
                                @unlink($upload_dir . $user['profile_picture']);
                            }
                            
                            $profile_picture = $new_filename;
                        } else {
                            $error = 'Failed to upload profile picture';
                        }
                    } else {
                        $error = 'Profile picture must be less than 5MB';
                    }
                } else {
                    $error = 'Profile picture must be JPG, PNG, or GIF';
                }
            }
            
            // Update profile only if no upload errors
            if (empty($error)) {
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, custom_status = ?, profile_picture = ? WHERE user_id = ?");
                
                if ($stmt->execute([$full_name, $email, $custom_status, $profile_picture, $_SESSION['user_id']])) {
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['profile_picture'] = $profile_picture;
                    $success = 'Profile updated successfully';
                    $user = getUserData($_SESSION['user_id']); // Refresh user data
                    logActivity($_SESSION['user_id'], 'Updated profile');
                } else {
                    $error = 'Failed to update profile';
                }
            }
        }
    }
}

// Get user statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM messages WHERE sender_id = ?) as messages_sent,
    (SELECT COUNT(*) FROM messages WHERE receiver_id = ?) as messages_received,
    (SELECT COUNT(*) FROM group_members WHERE user_id = ?) as groups_joined";
$stmt = $conn->prepare($stats_query);
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - LAN Chat</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <!-- Top Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="flex items-center space-x-2">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        <span class="text-xl font-bold text-gray-800">LAN Chat</span>
                    </a>
                </div>
                <a href="dashboard.php" class="text-purple-600 hover:text-purple-800 font-semibold">
                    ← Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Profile Card -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <div class="text-center">
                        <div class="relative inline-block">
                            <?php
                            $profile_pic_path = ($user['profile_picture'] && $user['profile_picture'] !== 'default.png') 
                                ? 'uploads/profiles/' . htmlspecialchars($user['profile_picture'])
                                : 'assets/images/default.png';
                            ?>
                            <img src="<?php echo $profile_pic_path; ?>" 
                                 alt="Profile" 
                                 class="w-32 h-32 rounded-full mx-auto border-4 border-purple-500"
                                 onerror="this.src='assets/images/default.png'">
                            <span class="absolute bottom-2 right-2 w-6 h-6 bg-green-500 border-4 border-white rounded-full"></span>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-800 mt-4"><?php echo htmlspecialchars($user['full_name']); ?></h2>
                        <p class="text-gray-600">@<?php echo htmlspecialchars($user['username']); ?></p>
                        <span class="inline-block mt-2 px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-sm font-semibold">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                        <?php if ($user['custom_status']): ?>
                            <p class="mt-3 text-sm text-gray-600 italic">
                                "<?php echo htmlspecialchars($user['custom_status']); ?>"
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- Statistics -->
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <h3 class="font-semibold text-gray-800 mb-3">Statistics</h3>
                        <div class="space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Messages Sent</span>
                                <span class="font-bold text-purple-600"><?php echo $stats['messages_sent']; ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Messages Received</span>
                                <span class="font-bold text-purple-600"><?php echo $stats['messages_received']; ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Groups Joined</span>
                                <span class="font-bold text-purple-600"><?php echo $stats['groups_joined']; ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Account Info -->
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <h3 class="font-semibold text-gray-800 mb-3">Account Info</h3>
                        <div class="space-y-2 text-sm">
                            <p class="text-gray-600">
                                <span class="font-semibold">Joined:</span> 
                                <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                            </p>
                            <p class="text-gray-600">
                                <span class="font-semibold">Last Seen:</span> 
                                <?php echo timeAgo($user['last_seen']); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Profile Form -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">Edit Profile</h2>

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

                    <form method="POST" enctype="multipart/form-data">
                        <div class="space-y-4">
                            <!-- Profile Picture -->
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">
                                    Profile Picture
                                </label>
                                <input 
                                    type="file" 
                                    name="profile_picture" 
                                    accept="image/jpeg,image/png,image/gif,image/jpg"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                                >
                                <p class="text-xs text-gray-500 mt-1">Supported: JPG, PNG, GIF (Max 5MB)</p>
                            </div>

                            <!-- Full Name -->
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">
                                    Full Name *
                                </label>
                                <input 
                                    type="text" 
                                    name="full_name" 
                                    value="<?php echo htmlspecialchars($user['full_name']); ?>"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                                    required
                                >
                            </div>

                            <!-- Email -->
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">
                                    Email Address *
                                </label>
                                <input 
                                    type="email" 
                                    name="email" 
                                    value="<?php echo htmlspecialchars($user['email']); ?>"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                                    required
                                >
                            </div>

                            <!-- Username (Read-only) -->
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">
                                    Username
                                </label>
                                <input 
                                    type="text" 
                                    value="<?php echo htmlspecialchars($user['username']); ?>"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100"
                                    readonly
                                >
                                <p class="text-xs text-gray-500 mt-1">Username cannot be changed</p>
                            </div>

                            <!-- Custom Status -->
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">
                                    Custom Status
                                </label>
                                <input 
                                    type="text" 
                                    name="custom_status" 
                                    value="<?php echo htmlspecialchars($user['custom_status'] ?? ''); ?>"
                                    placeholder="e.g., Busy coding..."
                                    maxlength="100"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                                >
                            </div>

                            <!-- Submit Button -->
                            <div class="pt-4">
                                <button 
                                    type="submit" 
                                    class="w-full bg-purple-600 text-white font-bold py-3 px-4 rounded-lg hover:bg-purple-700 transition duration-200">
                                    Update Profile
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Change Password Section -->
                    <div class="mt-8 pt-8 border-t border-gray-200">
                        <h3 class="text-xl font-bold text-gray-800 mb-4">Security</h3>
                        <a href="settings.php" class="inline-block bg-gray-200 text-gray-800 font-semibold py-2 px-4 rounded-lg hover:bg-gray-300 transition duration-200">
                            Change Password
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
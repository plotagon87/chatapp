<?php
require_once 'includes/config.php';
requireLogin();

$pid = intval($_GET['pid'] ?? 0);
if ($pid <= 0) {
    die('Invalid presentation ID');
}

// check authorization
$stmt = $conn->prepare("SELECT p.*, u.full_name as presenter_name FROM presentations p JOIN users u ON p.presenter_id=u.user_id WHERE p.presentation_id = ?");
$stmt->execute([$pid]);
$p = $stmt->fetch();
if (!$p) {
    die('Presentation not found');
}
$isPresenter = ($p['presenter_id'] == $_SESSION['user_id']);
$allowed = $isPresenter;
$invitedPending = false;
$invitedByGroup = false;

if (!$allowed) {
    // Check individual user invitation
    $aStmt = $conn->prepare("SELECT approved FROM presentation_viewers WHERE presentation_id = ? AND user_id = ? AND group_id IS NULL");
    $aStmt->execute([$pid, $_SESSION['user_id']]);
    $row = $aStmt->fetch();
    
    if ($row) {
        // User has been individually invited
        if ($row['approved']) {
            $allowed = true;
        } else {
            $invitedPending = true;
        }
    } else {
        // Check group-based invitation
        $gStmt = $conn->prepare("SELECT pv.approved FROM presentation_viewers pv 
                               INNER JOIN group_members gm ON pv.group_id = gm.group_id 
                               WHERE pv.presentation_id = ? AND gm.user_id = ? AND gm.group_id IS NOT NULL");
        $gStmt->execute([$pid, $_SESSION['user_id']]);
        $groupRow = $gStmt->fetch();
        
        if ($groupRow) {
            // User is member of invited group
            $invitedByGroup = true;
            if ($groupRow['approved']) {
                $allowed = true;
            } else {
                $invitedPending = true;
            }
        }
    }
}

// if user is invited but not yet accepted, show decision UI and exit early
if (!$allowed && $invitedPending) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Presentation Invitation</title>
        <link rel="stylesheet" href="assets/css/tailwind.min.css">
        <script>window.baseUrl = '<?php echo BASE_URL; ?>'; const currentUserId = <?php echo (int)$_SESSION['user_id']; ?>;</script>
    </head>
    <body class="bg-gray-100">
        <div class="max-w-2xl mx-auto py-20 text-center">
            <h1 class="text-2xl font-bold mb-4">You're invited!</h1>
            <p class="mb-6">The presenter has invited you to view this presentation. Do you accept?</p>
            <button id="acceptBtn" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 mr-4">Accept</button>
            <button id="declineBtn" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Decline</button>
        </div>
        <script>
            document.getElementById('acceptBtn').addEventListener('click', function(){ respond(true); });
            document.getElementById('declineBtn').addEventListener('click', function(){ respond(false); });
            function respond(accept){
                fetch(window.baseUrl + 'api/presentation_api.php', {
                    method:'POST',
                    headers:{'Content-Type':'application/x-www-form-urlencoded'},
                    body:'action=respond_invite&presentation_id='+<?php echo $pid; ?>+'&accept='+ (accept?1:0) + '&csrf_token=' + encodeURIComponent('<?php echo $_SESSION['csrf_token']; ?>')
                }).then(r=>r.json()).then(res=>{
                    if (res.success) {
                        if (accept) location.reload();
                        else window.location.href = 'dashboard.php';
                    }
                });
            }
        </script>
    </body>
    </html>
    <?php
    exit;
}

if (!$allowed) {
    die('You are not authorized to view this presentation');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Presentation - <?php echo htmlspecialchars($p['title']); ?></title>
    <link rel="stylesheet" href="assets/css/tailwind.min.css">
    <style>
        body { margin: 0; padding: 0; overflow: hidden; }
        .presentation-container { display: flex; flex-direction: column; height: 100vh; width: 100vw; }
        .presentation-header { flex-shrink: 0; background: white; border-bottom: 1px solid #e5e7eb; padding: 12px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .presentation-content { flex-grow: 1; overflow: auto; background: #f3f4f6; display: flex; align-items: center; justify-content: center; }
        #viewerArea { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; }
        #viewerArea embed { max-width: 100%; max-height: 100%; }
        .presentation-controls { flex-shrink: 0; background: white; border-top: 1px solid #e5e7eb; padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; }
    </style>
    <script>window.baseUrl = '<?php echo BASE_URL; ?>';
            const currentUserId = <?php echo (int)$_SESSION['user_id']; ?>;
            window.currentUserId = currentUserId;
    </script>
</head>
<body class="bg-gray-900">
    <div class="presentation-container">
        <!-- Header with title and info -->
        <div class="presentation-header">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-lg font-bold"><?php echo htmlspecialchars($p['title']); ?></h1>
                    <p class="text-xs text-gray-600">Presenter: <?php echo htmlspecialchars($p['presenter_name']); ?></p>
                </div>
                <a href="dashboard.php" class="px-3 py-1 bg-gray-300 text-gray-800 rounded text-sm hover:bg-gray-400">← Back</a>
            </div>
        </div>

        <!-- Main presentation content area -->
        <div class="presentation-content">
            <div id="viewerArea">
                <p class="text-white text-center">Loading slides...</p>
            </div>
        </div>

        <!-- Announcements -->
        <div id="announcementsContainer" class="fixed top-20 right-4 max-w-sm z-50"></div>

        <!-- Controls -->
        <div class="presentation-controls">
            <div class="flex space-x-3">
                <?php if ($isPresenter): ?>
                    <button id="prevSlideBtn" class="px-3 py-1 bg-gray-600 text-white rounded hover:bg-gray-700 text-sm">← Prev</button>
                    <button id="nextSlideBtn" class="px-3 py-1 bg-gray-600 text-white rounded hover:bg-gray-700 text-sm">Next →</button>
                    <span class="text-sm font-semibold">Slide <span id="currentSlideDisplay"><?php echo $p['current_slide']; ?></span></span>
                <?php endif; ?>
            </div>

        </div>
    </div>

<script>var csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';</script>
<script src="assets/js/chat.js"></script>
<script src="assets/js/presentation.js"></script>
</body>
</html>

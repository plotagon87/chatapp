<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}
$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'save_title':
        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            echo json_encode(['success' => false, 'error' => 'Title required']);
            break;
        }
        $stmt = $conn->prepare("UPDATE presentations SET title = ? WHERE presenter_id = ?");
        $stmt->execute([$title, $userId]);
        echo json_encode(['success' => true]);
        break;
    case 'toggle_active':
        $stmt = $conn->prepare("SELECT is_active FROM presentations WHERE presenter_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $current = $stmt->fetchColumn();
        $new = $current ? 0 : 1;
        $stmt2 = $conn->prepare("UPDATE presentations SET is_active = ? WHERE presenter_id = ?");
        $stmt2->execute([$new, $userId]);
        echo json_encode(['success' => true, 'is_active' => (bool)$new]);
        break;
    case 'toggle_download':
        $val = isset($_POST['allow']) && $_POST['allow'] === '1' ? 1 : 0;
        $stmt = $conn->prepare("UPDATE presentations SET allow_download = ? WHERE presenter_id = ?");
        $stmt->execute([$val, $userId]);
        echo json_encode(['success' => true]);
        break;
    case 'upload_file':
        if (!isset($_FILES['slideFile'])) {
            echo json_encode(['success' => false, 'error' => 'No file']);
            break;
        }
        $presStmt = $conn->prepare("SELECT presentation_id FROM presentations WHERE presenter_id = ? LIMIT 1");
        $presStmt->execute([$userId]);
        $presId = $presStmt->fetchColumn();
        if (!$presId) {
            echo json_encode(['success' => false, 'error' => 'Presentation not found']);
            break;
        }
        $file = $_FILES['slideFile'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $targetDir = UPLOAD_PATH . 'presentations/';
        if (!file_exists($targetDir)) mkdir($targetDir, 0755, true);
        $filename = uniqid() . '_' . basename($file['name']);
        $targetPath = $targetDir . $filename;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            echo json_encode(['success' => false, 'error' => 'Upload failed']);
            break;
        }
        // determine next slide number
        $numStmt = $conn->prepare("SELECT COALESCE(MAX(slide_number),0)+1 FROM presentation_files WHERE presentation_id = ?");
        $numStmt->execute([$presId]);
        $slideNum = $numStmt->fetchColumn();
        $ins = $conn->prepare("INSERT INTO presentation_files (presentation_id,file_path,slide_number) VALUES (?,?,?)");
        $ins->execute([$presId, 'uploads/presentations/' . $filename, $slideNum]);
        echo json_encode(['success' => true, 'file'=>['id'=>$conn->lastInsertId(),'name'=>$filename,'slide'=>$slideNum]]);
        break;
    case 'add_viewer':
        $presenterStmt = $conn->prepare("SELECT presentation_id, title FROM presentations WHERE presenter_id = ?");
        $presenterStmt->execute([$userId]);
        $rowPres = $presenterStmt->fetch(PDO::FETCH_ASSOC);
        $presId = $rowPres['presentation_id'] ?? null;
        $presTitle = $rowPres['title'] ?? 'your presentation';
        if (!$presId) {
            echo json_encode(['success'=>false,'error'=>'No presentation']);
            break;
        }
        $uid = intval($_POST['user_id'] ?? 0);
        if ($uid <= 0) {
            echo json_encode(['success'=>false,'error'=>'Invalid user']);
            break;
        }
        // upsert viewer as pending
        $up = $conn->prepare("INSERT INTO presentation_viewers (presentation_id,user_id,group_id,approved) VALUES (?,?,NULL,0) ON DUPLICATE KEY UPDATE approved=approved");
        $up->execute([$presId,$uid]);

        // send notification with link
        $presenterName = getUserData($userId)['full_name'];
        $link = BASE_URL . "presentation_view.php?pid=" . $presId;
        $content = "You have been invited by " . htmlspecialchars($presenterName) . " to attend the presentation &quot;" . htmlspecialchars($presTitle) . "&quot;. <a href=\"" . $link . "\">Click here to respond</a>";
        createNotification($uid, 'presentation', $content, $presId);

        echo json_encode(['success'=>true]);
        break;
    
    case 'add_group':
        $presenterStmt = $conn->prepare("SELECT presentation_id, title FROM presentations WHERE presenter_id = ?");
        $presenterStmt->execute([$userId]);
        $rowPres = $presenterStmt->fetch(PDO::FETCH_ASSOC);
        $presId = $rowPres['presentation_id'] ?? null;
        $presTitle = $rowPres['title'] ?? 'your presentation';
        if (!$presId) {
            echo json_encode(['success'=>false,'error'=>'No presentation']);
            break;
        }
        $gid = intval($_POST['group_id'] ?? 0);
        if ($gid <= 0) {
            echo json_encode(['success'=>false,'error'=>'Invalid group']);
            break;
        }
        
        // upsert group viewer as pending
        $up = $conn->prepare("INSERT INTO presentation_viewers (presentation_id,user_id,group_id,approved) VALUES (?,NULL,?,0) ON DUPLICATE KEY UPDATE approved=approved");
        $up->execute([$presId,$gid]);
        
        // Get all group members and send them notifications
        $membersStmt = $conn->prepare("SELECT u.user_id, u.full_name FROM group_members gm JOIN users u ON gm.user_id = u.user_id WHERE gm.group_id = ?");
        $membersStmt->execute([$gid]);
        $members = $membersStmt->fetchAll();
        
        $presenterName = getUserData($userId)['full_name'];
        $link = BASE_URL . "presentation_view.php?pid=" . $presId;
        foreach ($members as $member) {
            $content = "You have been invited (via group) by " . htmlspecialchars($presenterName) . " to attend the presentation &quot;" . htmlspecialchars($presTitle) . "&quot;. <a href=\"" . $link . "\">Click here to respond</a>";
            createNotification($member['user_id'], 'presentation', $content, $presId);
        }
        
        echo json_encode(['success'=>true,'members_notified'=>count($members)]);
        break;
    case 'toggle_approval':
        $uid = intval($_POST['user_id'] ?? 0);
        if ($uid <= 0) {
            echo json_encode(['success'=>false,'error'=>'invalid']);
            break;
        }
        $presId = $conn->query("SELECT presentation_id FROM presentations WHERE presenter_id=$userId")->fetchColumn();
        $stmt = $conn->prepare("UPDATE presentation_viewers SET approved = NOT approved WHERE presentation_id = ? AND user_id = ?");
        $stmt->execute([$presId,$uid]);
        // return current state
        $state = $conn->prepare("SELECT approved FROM presentation_viewers WHERE presentation_id = ? AND user_id = ?");
        $state->execute([$presId,$uid]);
        $approved = $state->fetchColumn();
        echo json_encode(['success'=>true,'approved'=>$approved]);
        break;
    case 'remove_viewer':
        $uid = intval($_POST['user_id'] ?? 0);
        $presId = $conn->query("SELECT presentation_id FROM presentations WHERE presenter_id=$userId")->fetchColumn();
        $stmt = $conn->prepare("DELETE FROM presentation_viewers WHERE presentation_id = ? AND user_id = ?");
        $stmt->execute([$presId,$uid]);
        echo json_encode(['success'=>true]);
        break;
    
    case 'toggle_approval_id':
        $viewerId = intval($_POST['viewer_id'] ?? 0);
        if (!$viewerId) {
            echo json_encode(['success'=>false,'error'=>'invalid viewer id']);
            break;
        }
        $stmt = $conn->prepare("UPDATE presentation_viewers SET approved = NOT approved WHERE id = ?");
        $stmt->execute([$viewerId]);
        echo json_encode(['success'=>true]);
        break;
    
    case 'remove_viewer_id':
        $viewerId = intval($_POST['viewer_id'] ?? 0);
        if (!$viewerId) {
            echo json_encode(['success'=>false,'error'=>'invalid viewer id']);
            break;
        }
        $checkStmt = $conn->prepare("SELECT p.presenter_id FROM presentation_viewers pv JOIN presentations p ON pv.presentation_id = p.presentation_id WHERE pv.id = ?");
        $checkStmt->execute([$viewerId]);
        $presenterCheck = $checkStmt->fetch();
        if ($presenterCheck && $presenterCheck['presenter_id'] == $userId) {
            $stmt = $conn->prepare("DELETE FROM presentation_viewers WHERE id = ?");
            $stmt->execute([$viewerId]);
            echo json_encode(['success'=>true]);
        } else {
            echo json_encode(['success'=>false,'error'=>'unauthorized']);
        }
        break;
    
    case 'add_announcement':
        $content = trim($_POST['content'] ?? '');
        if ($content === '') {
            echo json_encode(['success'=>false,'error'=>'Content empty']);
            break;
        }
        $presId = $conn->query("SELECT presentation_id FROM presentations WHERE presenter_id=$userId")->fetchColumn();
        $expires = date('Y-m-d H:i:s', time()+30);
        $ins = $conn->prepare("INSERT INTO presentation_announcements (presentation_id,content,expires_at) VALUES (?,?,?)");
        $ins->execute([$presId,$content,$expires]);
        echo json_encode(['success'=>true,'announcement'=>['content'=>$content,'expires_at'=>$expires,'created_at'=>date('Y-m-d H:i:s')]]);
        break;
    case 'respond_invite':
        $pid = intval($_POST['presentation_id'] ?? 0);
        $accept = isset($_POST['accept']) && $_POST['accept'] === '1';
        if (!$pid) {
            echo json_encode(['success'=>false,'error'=>'invalid presentation']);
            break;
        }
        // Check if user/group is invited and either accept or decline
        if ($accept) {
            $stmt = $conn->prepare("UPDATE presentation_viewers SET approved = 1 WHERE presentation_id = ? AND (user_id = ? OR group_id IN (SELECT group_id FROM group_members WHERE user_id = ?))");
            $stmt->execute([$pid, $userId, $userId]);
            
            // notify presenter of acceptance
            $presInfo = $conn->prepare("SELECT presenter_id FROM presentations WHERE presentation_id = ?");
            $presInfo->execute([$pid]);
            $presenterId = $presInfo->fetchColumn();
            if ($presenterId) {
                $uinfo = getUserData($userId);
                createNotification($presenterId, 'system', htmlspecialchars($uinfo['full_name']) . " has accepted your presentation invitation.", $pid);
            }
        } else {
            $stmt = $conn->prepare("DELETE FROM presentation_viewers WHERE presentation_id = ? AND (user_id = ? OR group_id IN (SELECT group_id FROM group_members WHERE user_id = ?))");
            $stmt->execute([$pid, $userId, $userId]);
            
            // notify presenter of decline
            $presInfo = $conn->prepare("SELECT presenter_id FROM presentations WHERE presentation_id = ?");
            $presInfo->execute([$pid]);
            $presenterId = $presInfo->fetchColumn();
            if ($presenterId) {
                $uinfo = getUserData($userId);
                createNotification($presenterId, 'system', htmlspecialchars($uinfo['full_name']) . " has declined your presentation invitation.", $pid);
            }
        }
        echo json_encode(['success'=>true,'accepted'=>$accept]);
        break;

    case 'change_slide':
        $dir = $_POST['dir'] ?? '';
        $presId = $conn->query("SELECT presentation_id FROM presentations WHERE presenter_id=$userId")->fetchColumn();
        $curr = $conn->query("SELECT current_slide FROM presentations WHERE presentation_id=$presId")->fetchColumn();
        $new = $curr;
        if ($dir === 'next') {
            $new = $curr + 1;
        } elseif ($dir === 'prev' && $curr > 1) {
            $new = $curr -1;
        }
        $upd = $conn->prepare("UPDATE presentations SET current_slide = ? WHERE presentation_id = ?");
        $upd->execute([$new,$presId]);
        echo json_encode(['success'=>true,'current_slide'=>$new]);
        break;
    case 'get_status':
        // used by viewers to poll
        $pid = intval($_GET['presentation_id'] ?? 0);
        if (!$pid) {
            echo json_encode(['success'=>false,'error'=>'no id']);
            break;
        }
        $pres = $conn->prepare("SELECT p.*, u.full_name as presenter_name FROM presentations p JOIN users u ON p.presenter_id=u.user_id WHERE p.presentation_id=?");
        $pres->execute([$pid]);
        $p = $pres->fetch();
        if (!$p) {
            echo json_encode(['success'=>false,'error'=>'invalid']);
            break;
        }
        // check authorization
        $allowed = false;
        if ($p['presenter_id'] == $userId) {
            $allowed = true;
        } else {
            $aStmt = $conn->prepare("SELECT approved FROM presentation_viewers WHERE presentation_id=? AND user_id=?");
            $aStmt->execute([$pid,$userId]);
            $row = $aStmt->fetch();
            if ($row && $row['approved']) $allowed = true;
        }
        if (!$allowed) {
            echo json_encode(['success'=>false,'error'=>'not authorized']);
            break;
        }
        // gather current slide, files, announcements
        $filesq = $conn->prepare("SELECT slide_number,file_path FROM presentation_files WHERE presentation_id=? ORDER BY slide_number");
        $filesq->execute([$pid]);
        $files = $filesq->fetchAll();
        $annq = $conn->prepare("SELECT * FROM presentation_announcements WHERE presentation_id=? AND expires_at > NOW() ORDER BY created_at DESC");
        $annq->execute([$pid]);
        $anns = $annq->fetchAll();

        // Add flag indicating if current user is presenter
        $isPresenter = ($p['presenter_id'] == $userId);
        echo json_encode(['success'=>true,'presentation'=>$p,'files'=>$files,'announcements'=>$anns,'isPresenter'=>$isPresenter]);
        break;
    default:
        echo json_encode(['success'=>false,'error'=>'unknown action']);
}

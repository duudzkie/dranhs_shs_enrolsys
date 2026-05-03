<?php
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header('Location: admin.php');
    exit;
}

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'dranhswin';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Ensure user_id column exists in advisers_accounts (MySQL 5.7 safe)
$_col_chk = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='dranhswin' AND TABLE_NAME='advisers_accounts' AND COLUMN_NAME='user_id'");
if ($_col_chk && $_col_chk->num_rows === 0) {
    $conn->query("ALTER TABLE advisers_accounts ADD COLUMN user_id INT NULL");
}

$toast_message = '';
$toast_type = 'success';

// Function to compress and resize image
function compressImage($source, $destination, $quality = 80, $maxWidth = 800, $maxHeight = 800) {
    $info = getimagesize($source);
    if (!$info) return false;

    $mime = $info['mime'];
    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $image = imagecreatefrompng($source);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($source);
            break;
        default:
            return false;
    }

    // Get original dimensions
    $width = imagesx($image);
    $height = imagesy($image);

    // Calculate new dimensions
    if ($width > $height) {
        if ($width > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = ($height / $width) * $maxWidth;
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }
    } else {
        if ($height > $maxHeight) {
            $newHeight = $maxHeight;
            $newWidth = ($width / $height) * $maxHeight;
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }
    }

    // Create new image
    $newImage = imagecreatetruecolor($newWidth, $newHeight);

    // Preserve transparency for PNG
    if ($mime == 'image/png') {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
        imagefill($newImage, 0, 0, $transparent);
    }

    // Resize
    imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    // Save compressed image
    $success = false;
    if ($mime == 'image/jpeg') {
        $success = imagejpeg($newImage, $destination, $quality);
    } elseif ($mime == 'image/png') {
        $success = imagepng($newImage, $destination, 9 - round($quality / 10)); // PNG quality is 0-9, lower is better
    } elseif ($mime == 'image/webp') {
        $success = imagewebp($newImage, $destination, $quality);
    }

    // Clean up memory
    imagedestroy($image);
    imagedestroy($newImage);

    return $success;
}

// Basic action handlers (Add/Edit/Delete with admin password confirmation)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $currentAdminId = $_SESSION['user_id'] ?? null;
        $adminPassword = $_POST['admin_password'] ?? '';
        $isAdminAuthenticated = false;

        if ($currentAdminId && !empty($adminPassword)) {
            $checkAuthStmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $checkAuthStmt->bind_param('i', $currentAdminId);
            $checkAuthStmt->execute();
            $authResult = $checkAuthStmt->get_result();
            if ($authResult && ($authRow = $authResult->fetch_assoc())) {
                if (password_verify($adminPassword, $authRow['password'])) {
                    $isAdminAuthenticated = true;
                }
            }
            $checkAuthStmt->close();
        }

        if ($_POST['action'] === 'view_password') {
            if (!$isAdminAuthenticated) {
                $toast_message = 'Admin password incorrect. Cannot reveal user password.';
                $toast_type = 'error';
            } else {
                $targetId = intval($_POST['target_user_id'] ?? 0);
                $stmt = $conn->prepare("SELECT username, password FROM users WHERE id = ?");
                $stmt->bind_param('i', $targetId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && ($row = $result->fetch_assoc())) {
                    $toast_message = 'Password hash for user ' . htmlspecialchars($row['username'], ENT_QUOTES) . ': ' . htmlspecialchars($row['password'], ENT_QUOTES);
                    $toast_type = 'success';
                } else {
                    $toast_message = 'User not found.';
                    $toast_type = 'error';
                }
                $stmt->close();
            }
        } elseif ($_POST['action'] === 'edit_user') {
            if (!$isAdminAuthenticated) {
                $toast_message = 'Admin password incorrect. Edit canceled.';
                $toast_type = 'error';
            } else {
                $targetId = intval($_POST['target_user_id'] ?? 0);
                $newRole = trim($_POST['new_role'] ?? '');
                $newUsername = trim($_POST['new_username'] ?? '');
                $newPassword = trim($_POST['new_password'] ?? '');

                if (empty($newRole) && empty($newUsername) && empty($newPassword)) {
                    $toast_message = 'No changes were specified for edit.';
                    $toast_type = 'error';
                } else {
                    $updates = [];
                    $params = '';
                    $values = [];
                    if (!empty($newRole)) {
                        $updates[] = 'role = ?';
                        $params .= 's';
                        $values[] = $newRole;
                    }
                    if (!empty($newUsername)) {
                        $updates[] = 'username = ?';
                        $params .= 's';
                        $values[] = $newUsername;
                    }
                    if (!empty($newPassword)) {
                        $updates[] = 'password = ?';
                        $params .= 's';
                        $values[] = password_hash($newPassword, PASSWORD_DEFAULT);
                    }

                    if (!empty($updates)) {
                        $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';
                        $params .= 'i';
                        $values[] = $targetId;

                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param($params, ...$values);
                        if ($stmt->execute()) {
                            $toast_message = 'User updated successfully.';
                            $toast_type = 'success';
                        } else {
                            $toast_message = 'Failed to update user.';
                            $toast_type = 'error';
                        }
                        $stmt->close();
                    }
                }
            }
        } elseif ($_POST['action'] === 'delete_user') {
            if (!$isAdminAuthenticated) {
                $toast_message = 'Admin password incorrect. Delete canceled.';
                $toast_type = 'error';
            } else {
                $userId = intval($_POST['user_id'] ?? 0);
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param('i', $userId);
                if ($stmt->execute()) {
                    $toast_message = 'User deleted successfully.';
                    $toast_type = 'success';
                } else {
                    $toast_message = 'Failed to delete user.';
                    $toast_type = 'error';
                }
                $stmt->close();
            }
        } elseif ($_POST['action'] === 'delete_adviser') {
            if (!$isAdminAuthenticated) {
                $toast_message = 'Admin password incorrect. Delete adviser canceled.';
                $toast_type = 'error';
            } else {
                $advId = intval($_POST['adviser_id']);
                $stmt = $conn->prepare("DELETE FROM advisers_accounts WHERE id = ?");
                $stmt->bind_param('i', $advId);
                if ($stmt->execute()) {
                    $toast_message = 'Adviser deleted successfully.';
                    $toast_type = 'success';
                } else {
                    $toast_message = 'Failed to delete adviser.';
                    $toast_type = 'error';
                }
                $stmt->close();
            }
        } elseif ($_POST['action'] === 'new_account') {
            if (!$isAdminAuthenticated) {
                $toast_message = 'Admin password incorrect. New account creation canceled.';
                $toast_type = 'error';
            } else {
                $newUsername = trim($_POST['new_username'] ?? '');
                $newPassword = trim($_POST['new_password'] ?? '');
                $newRole     = trim($_POST['new_role']     ?? '');
                $adviserLink = (int)($_POST['adviser_link'] ?? 0);

                if (empty($newUsername) || empty($newPassword) || empty($newRole)) {
                    $toast_message = 'Please fill in username, password, and role.';
                    $toast_type = 'error';
                } else {
                    $hashedPwd = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                    $stmt->bind_param('sss', $newUsername, $hashedPwd, $newRole);
                    if ($stmt->execute()) {
                        $newUserId = $conn->insert_id;
                        $toast_message = 'New user account created successfully.';
                        $toast_type = 'success';

                        // If adviser role, link to advisers_accounts
                        if ($newRole === 'adviser' && $adviserLink > 0) {
                            // Ensure user_id column exists
                            $conn->query("ALTER TABLE advisers_accounts ADD COLUMN IF NOT EXISTS user_id INT NULL");
                            $lnk = $conn->prepare("UPDATE advisers_accounts SET user_id = ? WHERE id = ?");
                            if ($lnk) { $lnk->bind_param("ii", $newUserId, $adviserLink); $lnk->execute(); $lnk->close(); }
                            $toast_message = 'Adviser account created and linked successfully.';
                        }
                    } else {
                        $toast_message = 'Failed to create account. Username might already exist.';
                        $toast_type = 'error';
                    }
                    $stmt->close();
                }
            }
        } elseif ($_POST['action'] === 'update_adviser') {
            if (!$isAdminAuthenticated) {
                $toast_message = 'Admin password incorrect. Update canceled.';
                $toast_type = 'error';
            } else {
                $advId = intval($_POST['adviser_id'] ?? 0);
                $newAdviserName = trim($_POST['adviser_name'] ?? '');

                if (empty($newAdviserName)) {
                    $toast_message = 'Please provide an adviser name.';
                    $toast_type = 'error';
                } else {
                    $stmt = $conn->prepare("UPDATE advisers_accounts SET name = ? WHERE id = ?");
                    $stmt->bind_param('si', $newAdviserName, $advId);
                    if ($stmt->execute()) {
                        $toast_message = 'Adviser name updated successfully.';
                        $toast_type = 'success';
                    } else {
                        $toast_message = 'Failed to update adviser name.';
                        $toast_type = 'error';
                    }
                    $stmt->close();
                }
            }
        } elseif ($_POST['action'] === 'upload_adviser_image') {
            if (!$isAdminAuthenticated) {
                $toast_message = 'Admin password incorrect. Upload canceled.';
                $toast_type = 'error';
            } else {
                $advId = intval($_POST['adviser_id'] ?? 0);
                
                if (!isset($_FILES['adviser_photo']) || $_FILES['adviser_photo']['error'] !== UPLOAD_ERR_OK) {
                    $toast_message = 'No file selected or upload error.';
                    $toast_type = 'error';
                } else {
                    $file = $_FILES['adviser_photo'];
                    $fileTmp = $file['tmp_name'];
                    $fileSize = $file['size'];
                    $fileType = mime_content_type($fileTmp);

                    // Validate file type
                    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                    if (!in_array($fileType, $allowedTypes)) {
                        $toast_message = 'Invalid file type. Only JPG, PNG, and WebP are allowed.';
                        $toast_type = 'error';
                    } elseif ($fileSize > 5 * 1024 * 1024) { // 5MB limit
                        $toast_message = 'File size exceeds 5MB limit.';
                        $toast_type = 'error';
                    } else {
                        $uploadDir = __DIR__ . '/../uploads/advisers/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }

                        // Delete old avatar file before saving new one
                        $oldAvatar = $conn->prepare("SELECT avatar FROM advisers_accounts WHERE id = ?");
                        if ($oldAvatar) {
                            $oldAvatar->bind_param('i', $advId);
                            $oldAvatar->execute();
                            $oldRow = $oldAvatar->get_result()->fetch_assoc();
                            $oldAvatar->close();
                            if (!empty($oldRow['avatar'])) {
                                $oldPath = __DIR__ . '/../' . $oldRow['avatar'];
                                if (file_exists($oldPath)) @unlink($oldPath);
                            }
                        }

                        $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $newFileName = 'adviser_' . $advId . '.' . $fileExt; // fixed name, no timestamp
                        $filePath = $uploadDir . $newFileName;
                        $relativePath = 'uploads/advisers/' . $newFileName;

                        // Compress and resize image
                        if (compressImage($fileTmp, $filePath, 80, 400, 400)) {  // Smaller for avatars
                            $stmt = $conn->prepare("UPDATE advisers_accounts SET avatar = ? WHERE id = ?");
                            $stmt->bind_param('si', $relativePath, $advId);
                            if ($stmt->execute()) {
                                $toast_message = 'Adviser profile photo uploaded and compressed successfully.';
                                $toast_type = 'success';
                            } else {
                                unlink($filePath);
                                $toast_message = 'Failed to save photo path in database.';
                                $toast_type = 'error';
                            }
                            $stmt->close();
                        } else {
                            $toast_message = 'Failed to process and compress image.';
                            $toast_type = 'error';
                        }
                    }
                }
            }
        }
    }
}

// Fetch users
$users = [];
$userResult = $conn->query("SELECT id, username, fullname, role FROM users ORDER BY role ASC, username ASC");
if ($userResult) {
    while ($row = $userResult->fetch_assoc()) {
        $users[] = $row;
    }
}

// Fetch faculty advisers
$advisers = [];
$advResult = $conn->query("SELECT a.id, a.name, a.avatar, a.user_id, c.section_name
    FROM advisers_accounts a
    LEFT JOIN classrooms c ON c.adviser_id = a.id
    ORDER BY a.name ASC");
if ($advResult) {
    while ($row = $advResult->fetch_assoc()) {
        $advisers[] = $row;
    }
}
?>

<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden min-h-[600px] flex flex-col relative w-full">
    <?php if ($toast_message): ?>
    <div id="toast" class="absolute top-4 right-4 z-50 <?php echo ($toast_type === 'error' ? 'bg-rose-100 border-rose-300 text-rose-800' : 'bg-emerald-100 border-emerald-300 text-emerald-800'); ?> px-4 py-3 rounded-lg shadow-lg flex items-center gap-3">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        <span class="font-bold text-sm"><?php echo htmlspecialchars($toast_message); ?></span>
    </div>
    <script>setTimeout(() => {const t=document.getElementById('toast'); if(t) t.style.display='none';}, 3000);</script>
    <?php endif; ?>

    <div class="flex overflow-x-auto border-b border-slate-100 bg-slate-50/50 sidebar-scroll shrink-0">
        <button id="tab-btn-accounts" onclick="switchAccountTab('accounts')" class="px-6 py-4 font-bold text-sm text-dranhs-green border-b-2 border-dranhs-green bg-white shrink-0">Accounts</button>
        <button id="tab-btn-advisers" onclick="switchAccountTab('advisers')" class="px-6 py-4 font-bold text-sm text-slate-500 hover:text-slate-800 hover:bg-slate-50 transition-colors shrink-0">Advisers</button>
    </div>

    <div class="flex-1 p-6 lg:p-8">
        <div id="tab-accounts" class="block">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-2xl font-heading font-black text-dranhs-dark">User Accounts</h2>
                    <p class="text-sm text-slate-500">Displaying existing accounts with roles including admin, evaluator, and encoder.</p>
                </div>
                <button type="button" onclick="openNewAccountModal()" class="bg-dranhs-green hover:bg-emerald-600 text-white font-bold px-4 py-2 rounded-lg shadow-sm transition">+ New Account</button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 border border-slate-100 rounded-xl">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">#</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Username</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Fullname</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Password</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Role</th>
                            <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-slate-100">
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="4" class="px-4 py-5 text-sm text-slate-500 text-center">No user accounts found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $index => $user): ?>
                                <tr>
                                    <td class="px-4 py-3 text-sm font-semibold"><?php echo $index + 1; ?></td>
                                    <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td class="px-4 py-3 text-sm text-slate-600"><?php echo htmlspecialchars($user['fullname'] ?? '—'); ?></td>
                                    <td class="px-4 py-3 text-sm text-slate-500 tracking-widest" style="letter-spacing:0.23rem; cursor:pointer;" onclick="openRevealPasswordModal(<?php echo $user['id']; ?>, '<?php echo addslashes($user['username']); ?>')">••••••••</td>
                                    <td class="px-4 py-3 text-sm capitalize"><?php echo htmlspecialchars($user['role']); ?></td>
                                    <td class="px-4 py-3 text-sm text-right">
                                        <div class="inline-flex items-center justify-end gap-2">
                                            <button type="button" onclick="openConfirmModal('edit_user', <?php echo $user['id']; ?>, '<?php echo addslashes($user['username']); ?>', '<?php echo htmlspecialchars($user['role'], ENT_QUOTES); ?>')" class="text-slate-400 hover:text-blue-600 transition" title="Edit Account">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 11l6 6L5 23l-2-2 6-6z"/></svg>
                                            </button>
                                            <button type="button" onclick="openConfirmModal('delete_user', <?php echo $user['id']; ?>, '<?php echo addslashes($user['username']); ?>', '')" class="text-slate-400 hover:text-red-500 transition" title="Delete User">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="tab-advisers" class="hidden">
            <h2 class="text-2xl font-heading font-black text-dranhs-dark mb-4">Faculty Advisers</h2>
            <p class="text-sm text-slate-500 mb-6">Manage registered faculty advisers moved from the section registry.</p>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 border border-slate-100 rounded-xl">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">#</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Adviser</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Avatar</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Section</th>
                            <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-slate-100">
                        <?php if (empty($advisers)): ?>
                            <tr>
                                <td colspan="5" class="px-4 py-5 text-sm text-slate-500 text-center">No faculty advisers found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($advisers as $index => $adv): ?>
                                <tr>
                                    <td class="px-4 py-3 text-sm font-semibold"><?php echo $index + 1; ?></td>
                                    <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($adv['name']); ?></td>
                                    <td class="px-4 py-3">
                                        <?php if (!empty($adv['avatar'])): ?>
                                            <img src="../<?php echo htmlspecialchars($adv['avatar']); ?>" alt="Avatar" class="w-10 h-10 rounded-full object-cover">
                                        <?php else: ?>
                                            <div class="w-10 h-10 rounded-full bg-slate-200 flex items-center justify-center text-xs text-slate-500">No Photo</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-600"><?php echo htmlspecialchars($adv['section_name'] ?? ''); ?></td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="inline-flex items-center justify-end gap-2">
                                            <button type="button" onclick="openUploadPhotoModal(<?php echo $adv['id']; ?>, '<?php echo addslashes($adv['name']); ?>')" class="text-slate-400 hover:text-dranhs-green transition" title="Update Adviser Photo">
                                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h.26a2 2 0 001.557-.757l.912-1.088A2 2 0 019.738 2h4.524a2 2 0 011.95 1.155l.806 1.617a2 2 0 001.793 1.123H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                            </button>
                                            <button type="button" onclick="openEditAdviserModal(<?php echo $adv['id']; ?>, '<?php echo addslashes($adv['name']); ?>')" class="text-slate-400 hover:text-blue-600 transition" title="Edit Adviser">
                                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            </button>
                                            <button type="button" onclick="openConfirmModal('delete_adviser', <?php echo $adv['id']; ?>, '<?php echo addslashes($adv['name']); ?>', '')" class="text-slate-400 hover:text-red-500 transition" title="Delete Adviser">
                                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Admin verification modal for view/edit/delete actions -->
<div id="admin-action-modal" class="fixed inset-0 z-[100] hidden flex bg-slate-900/40 backdrop-blur-sm items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-lg shadow-xl p-6">
        <h3 id="modal-title" class="text-lg font-bold text-dranhs-dark mb-3">Confirm Action</h3>
        <p id="modal-description" class="text-sm text-slate-600 mb-4">Please enter your admin password to proceed.</p>
        <form id="admin-action-form" method="POST" action="?page=account" enctype="multipart/form-data" class="space-y-3">
            <input type="hidden" name="action" id="modal-action" value="">
            <input type="hidden" name="target_user_id" id="modal-target-user-id" value="">
            <input type="hidden" name="adviser_id" id="modal-adviser-id" value="">
            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($_SESSION['user_id'] ?? 0); ?>">

            <div id="modal-role-group" class="hidden">
                <label class="text-xs font-bold uppercase tracking-wider text-slate-500">New Role</label>
                <select name="new_role" id="modal-new-role" class="form-input" onchange="toggleAdviserLinkGroup(this.value)">
                    <option value="">-- keep current --</option>
                    <option value="admin">admin</option>
                    <option value="evaluator">evaluator</option>
                    <option value="encoder">encoder</option>
                    <option value="adviser">adviser</option>
                </select>
            </div>

            <div id="modal-adviser-link-group" class="hidden">
                <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Link to Adviser</label>
                <select name="adviser_link" id="modal-adviser-link" class="form-input">
                    <option value="">-- Select Adviser --</option>
                    <?php foreach ($advisers as $adv): ?>
                    <option value="<?php echo (int)$adv['id']; ?>"
                        <?php echo !empty($adv['user_id']) ? 'disabled style="color:#94a3b8"' : ''; ?>>
                        <?php echo htmlspecialchars($adv['name']); ?>
                        <?php echo !empty($adv['user_id']) ? ' (already linked)' : ''; ?>
                        <?php echo !empty($adv['section_name']) ? ' — ' . htmlspecialchars($adv['section_name']) : ''; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-slate-400 mt-1">Links this account to an adviser so they can only see their assigned section.</p>
            </div>

            <div id="modal-username-group" class="hidden">
                <label class="text-xs font-bold uppercase tracking-wider text-slate-500">New Username</label>
                <input type="text" name="new_username" id="modal-new-username" class="form-input" placeholder="New username (optional)">
            </div>

            <div id="modal-password-group" class="hidden">
                <label class="text-xs font-bold uppercase tracking-wider text-slate-500">New Password</label>
                <div class="relative">
                    <input type="text" name="new_password" id="modal-new-password" class="form-input pr-10" placeholder="New password">
                    <button type="button" onclick="generateRandomPassword()" class="absolute right-1.5 top-1.5 text-slate-500 hover:text-dranhs-green" title="Generate random password">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4m10 0h2a2 2 0 012 2v5a2 2 0 01-2 2h-2m-10 0H6a2 2 0 01-2-2v-5a2 2 0 012-2h2"/></svg>
                    </button>
                </div>
            </div>

            <div id="modal-adviser-name-group" class="hidden">
                <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Adviser Name</label>
                <input type="text" name="adviser_name" id="modal-adviser-name" class="form-input" placeholder="Enter adviser name">
            </div>

            <div id="modal-photo-group" class="hidden">
                <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Profile Photo</label>
                <input type="file" name="adviser_photo" id="modal-adviser-photo" class="form-input" accept="image/jpeg,image/jpg,image/png,image/webp">
                <p class="text-xs text-slate-500 mt-1">JPG, PNG, or WebP (max 5MB)</p>
            </div>

            <div>
                <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Admin Password</label>
                <input type="password" name="admin_password" id="modal-admin-password" class="form-input" placeholder="Enter your password" required>
            </div>

            <div class="flex items-center justify-end gap-2">
                <button type="button" onclick="closeAdminModal()" class="px-4 py-2 text-sm font-bold border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-100">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-bold bg-dranhs-green text-white rounded-lg hover:bg-emerald-700">Confirm</button>
            </div>
        </form>
    </div>
</div>

<script>
function switchAccountTab(tab){
    const accounts = document.getElementById('tab-accounts');
    const advisers = document.getElementById('tab-advisers');
    const btnAccounts = document.getElementById('tab-btn-accounts');
    const btnAdvisers = document.getElementById('tab-btn-advisers');

    if(tab === 'advisers'){
        accounts.classList.add('hidden');
        advisers.classList.remove('hidden');
        btnAccounts.classList.remove('text-dranhs-green','border-dranhs-green','bg-white');
        btnAccounts.classList.add('text-slate-500');
        btnAdvisers.classList.add('text-dranhs-green','border-dranhs-green','bg-white');
        btnAdvisers.classList.remove('text-slate-500');
    } else {
        advisers.classList.add('hidden');
        accounts.classList.remove('hidden');
        btnAdvisers.classList.remove('text-dranhs-green','border-dranhs-green','bg-white');
        btnAdvisers.classList.add('text-slate-500');
        btnAccounts.classList.add('text-dranhs-green','border-dranhs-green','bg-white');
        btnAccounts.classList.remove('text-slate-500');
    }
}

function openNewAccountModal(){
    document.getElementById('admin-action-modal').classList.remove('hidden');
    document.getElementById('modal-title').textContent = 'Create New Account';
    document.getElementById('modal-description').textContent = 'Fill in account details and enter admin password to add a new user.';
    document.getElementById('modal-action').value = 'new_account';
    document.getElementById('modal-target-user-id').value = '';
    document.getElementById('modal-adviser-id').value = '';
    document.getElementById('modal-role-group').classList.remove('hidden');
    document.getElementById('modal-username-group').classList.remove('hidden');
    document.getElementById('modal-password-group').classList.remove('hidden');
    document.getElementById('modal-adviser-name-group').classList.add('hidden');
    document.getElementById('modal-photo-group').classList.add('hidden');
    document.getElementById('modal-adviser-link-group').classList.add('hidden');
    document.getElementById('modal-new-username').value = '';
    document.getElementById('modal-new-password').value = '';
    document.getElementById('modal-new-role').value = 'admin';
    document.getElementById('modal-admin-password').value = '';
}

function toggleAdviserLinkGroup(role) {
    const grp = document.getElementById('modal-adviser-link-group');
    if (role === 'adviser') {
        grp.classList.remove('hidden');
    } else {
        grp.classList.add('hidden');
    }
}

function openRevealPasswordModal(userId, userName){
    document.getElementById('admin-action-modal').classList.remove('hidden');
    document.getElementById('modal-title').textContent = 'Reveal Password for ' + userName;
    document.getElementById('modal-description').textContent = 'Enter your admin password to view the password hash for this user.';
    document.getElementById('modal-action').value = 'view_password';
    document.getElementById('modal-target-user-id').value = userId;
    document.getElementById('modal-adviser-id').value = '';
    document.getElementById('modal-role-group').classList.add('hidden');
    document.getElementById('modal-username-group').classList.add('hidden');
    document.getElementById('modal-password-group').classList.add('hidden');
    document.getElementById('modal-adviser-name-group').classList.add('hidden');
    document.getElementById('modal-photo-group').classList.add('hidden');
    document.getElementById('modal-admin-password').value = '';
}

function openConfirmModal(action, userId, userName, currentRole){
    document.getElementById('admin-action-modal').classList.remove('hidden');
    document.getElementById('modal-target-user-id').value = userId;
    document.getElementById('modal-admin-password').value = '';

    if(action === 'edit_user'){
        document.getElementById('modal-title').textContent = 'Edit User: ' + userName;
        document.getElementById('modal-description').textContent = 'Enter your admin password to confirm editing this user.';
        document.getElementById('modal-action').value = 'edit_user';
        document.getElementById('modal-role-group').classList.remove('hidden');
        document.getElementById('modal-username-group').classList.remove('hidden');
        document.getElementById('modal-password-group').classList.remove('hidden');
        document.getElementById('modal-adviser-name-group').classList.add('hidden');
        document.getElementById('modal-photo-group').classList.add('hidden');
        document.getElementById('modal-new-role').value = currentRole;
        document.getElementById('modal-new-username').value = userName;
        document.getElementById('modal-new-password').value = '';
    } else if(action === 'delete_user'){
        document.getElementById('modal-title').textContent = 'Delete User: ' + userName;
        document.getElementById('modal-description').textContent = 'Enter your admin password to permanently delete this user.';
        document.getElementById('modal-action').value = 'delete_user';
        document.getElementById('modal-role-group').classList.add('hidden');
        document.getElementById('modal-username-group').classList.add('hidden');
        document.getElementById('modal-password-group').classList.add('hidden');
        document.getElementById('modal-adviser-name-group').classList.add('hidden');
        document.getElementById('modal-photo-group').classList.add('hidden');
        document.getElementById('modal-adviser-id').value = '';
    } else if(action === 'delete_adviser'){
        document.getElementById('modal-title').textContent = 'Delete Adviser: ' + userName;
        document.getElementById('modal-description').textContent = 'Enter your admin password to permanently delete this adviser.';
        document.getElementById('modal-action').value = 'delete_adviser';
        document.getElementById('modal-role-group').classList.add('hidden');
        document.getElementById('modal-username-group').classList.add('hidden');
        document.getElementById('modal-password-group').classList.add('hidden');
        document.getElementById('modal-adviser-name-group').classList.add('hidden');
        document.getElementById('modal-photo-group').classList.add('hidden');
        document.getElementById('modal-adviser-id').value = userId;
        document.getElementById('modal-target-user-id').value = '';
    }
}

function openUploadPhotoModal(advId, advName){
    document.getElementById('admin-action-modal').classList.remove('hidden');
    document.getElementById('modal-title').textContent = 'Upload Photo for: ' + advName;
    document.getElementById('modal-description').textContent = 'Select a profile photo and enter your admin password to upload.';
    document.getElementById('modal-action').value = 'upload_adviser_image';
    document.getElementById('modal-adviser-id').value = advId;
    document.getElementById('modal-target-user-id').value = '';
    document.getElementById('modal-role-group').classList.add('hidden');
    document.getElementById('modal-username-group').classList.add('hidden');
    document.getElementById('modal-password-group').classList.add('hidden');
    document.getElementById('modal-adviser-name-group').classList.add('hidden');
    document.getElementById('modal-photo-group').classList.remove('hidden');
    document.getElementById('modal-adviser-photo').value = '';
    document.getElementById('modal-admin-password').value = '';
}

function openEditAdviserModal(advId, advName){
    document.getElementById('admin-action-modal').classList.remove('hidden');
    document.getElementById('modal-title').textContent = 'Edit Adviser: ' + advName;
    document.getElementById('modal-description').textContent = 'Update the adviser name and enter admin password to confirm.';
    document.getElementById('modal-action').value = 'update_adviser';
    document.getElementById('modal-adviser-id').value = advId;
    document.getElementById('modal-target-user-id').value = '';
    document.getElementById('modal-role-group').classList.add('hidden');
    document.getElementById('modal-username-group').classList.add('hidden');
    document.getElementById('modal-password-group').classList.add('hidden');
    document.getElementById('modal-adviser-name-group').classList.remove('hidden');
    document.getElementById('modal-photo-group').classList.add('hidden');
    document.getElementById('modal-adviser-name').value = advName;
    document.getElementById('modal-admin-password').value = '';
}

function generateRandomPassword(){
    const length = 12;
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_+-=[]{}|;:,.<>?';
    let pass = '';
    for(let i=0;i<length;i++) pass += chars[Math.floor(Math.random() * chars.length)];
    document.getElementById('modal-new-password').value = pass;
}

function closeAdminModal(){
    document.getElementById('admin-action-modal').classList.add('hidden');
    // Reset form
    document.getElementById('admin-action-form').reset();
    // Hide all field groups
    document.getElementById('modal-role-group').classList.add('hidden');
    document.getElementById('modal-username-group').classList.add('hidden');
    document.getElementById('modal-password-group').classList.add('hidden');
    document.getElementById('modal-adviser-name-group').classList.add('hidden');
    document.getElementById('modal-photo-group').classList.add('hidden');
}
</script>

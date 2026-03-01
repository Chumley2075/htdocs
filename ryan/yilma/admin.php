<?php
session_start();

$DEV_MODE = false;
$message = '';
$messageType = 'info';
$displayName = 'Administrator (Mock)';
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'users';

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$db = null;
$currentUser = isset($_SESSION['valid_user']) ? $_SESSION['valid_user'] : '';
$canManageUsers = true;
$canManageFaces = true;
$canManageDoors = true;
$canViewLogs = true;

if (!$DEV_MODE) {
    require_once '../database.php';
    $db = new database();
    $db->ensureAdminTables();

    if (!isset($_SESSION['valid_user'])) {
        header('Location: ./index.php');
        exit();
    }

    if (!$db->isAdmin($_SESSION['valid_user'])) {
        header('Location: ./mainMenu.php');
        exit();
    }

    $displayName = $db->getUserInfo($_SESSION['valid_user'], 'full_name');

    $perm = $db->getUserPermissions($_SESSION['valid_user']);
    $canManageUsers = ((int)$perm['can_manage_users'] === 1) || $db->isAdmin($_SESSION['valid_user']);
    $canManageFaces = ((int)$perm['can_manage_faces'] === 1) || $db->isAdmin($_SESSION['valid_user']);
    $canManageDoors = ((int)$perm['can_manage_doors'] === 1) || $db->isAdmin($_SESSION['valid_user']);
    $canViewLogs = ((int)$perm['can_view_logs'] === 1) || $db->isAdmin($_SESSION['valid_user']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = isset($_POST['action']) ? $_POST['action'] : '';

        if ($action === 'create_user') {
            $activeTab = 'users';
            if (!$canManageUsers) {
                $message = 'You do not have permission to create users.';
                $messageType = 'error';
            } else {
                $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
                $full_name = trim(isset($_POST['full_name']) ? $_POST['full_name'] : '');
                $password = trim(isset($_POST['password']) ? $_POST['password'] : '');

                $is_prof = isset($_POST['is_prof']) ? 1 : 0;
                $is_admin = isset($_POST['is_admin']) ? 1 : 0;
                $is_student = isset($_POST['is_student']) ? 1 : 0;

                $perm_manage_users = isset($_POST['can_manage_users']) ? 1 : 0;
                $perm_manage_faces = isset($_POST['can_manage_faces']) ? 1 : 0;
                $perm_manage_doors = isset($_POST['can_manage_doors']) ? 1 : 0;
                $perm_view_logs = isset($_POST['can_view_logs']) ? 1 : 0;

                if ($username === '' || !preg_match('/^[A-Za-z0-9_]{3,40}$/', $username)) {
                    $message = 'Username must be 3-40 chars and only letters, numbers, underscore.';
                    $messageType = 'error';
                } elseif ($full_name === '') {
                    $message = 'Full name is required.';
                    $messageType = 'error';
                } elseif (($is_prof + $is_admin + $is_student) === 0) {
                    $message = 'Select at least one role.';
                    $messageType = 'error';
                } else {
                    $passwordHash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : '';
                    $result = $db->createUser($username, $full_name, $passwordHash, $is_prof, $is_admin, $is_student);

                    if ($result === 'Error') {
                        $message = 'Username already exists.';
                        $messageType = 'error';
                    } elseif ($result === true) {
                        $db->updateUserRolesAndPermissions(
                            $username,
                            $is_prof,
                            $is_admin,
                            $is_student,
                            $perm_manage_users,
                            $perm_manage_faces,
                            $perm_manage_doors,
                            $perm_view_logs
                        );
                        $db->logAdminEvent(
                            $_SESSION['valid_user'],
                            'user_created',
                            $username,
                            'roles(admin=' . $is_admin . ',prof=' . $is_prof . ',student=' . $is_student . ')'
                        );
                        $message = 'User created successfully.';
                        $messageType = 'success';
                    } else {
                        $message = 'Could not create user.';
                        $messageType = 'error';
                    }
                }
            }
        } elseif ($action === 'update_user') {
            $activeTab = 'users';
            if (!$canManageUsers) {
                $message = 'You do not have permission to update users.';
                $messageType = 'error';
            } else {
                $targetUser = trim(isset($_POST['target_username']) ? $_POST['target_username'] : '');
                $is_prof = isset($_POST['is_prof']) ? 1 : 0;
                $is_admin = isset($_POST['is_admin']) ? 1 : 0;
                $is_student = isset($_POST['is_student']) ? 1 : 0;

                $perm_manage_users = isset($_POST['can_manage_users']) ? 1 : 0;
                $perm_manage_faces = isset($_POST['can_manage_faces']) ? 1 : 0;
                $perm_manage_doors = isset($_POST['can_manage_doors']) ? 1 : 0;
                $perm_view_logs = isset($_POST['can_view_logs']) ? 1 : 0;

                if ($targetUser === '') {
                    $message = 'Invalid target user.';
                    $messageType = 'error';
                } elseif (($is_prof + $is_admin + $is_student) === 0) {
                    $message = 'A user must keep at least one role.';
                    $messageType = 'error';
                } elseif ($targetUser === $_SESSION['valid_user'] && $is_admin === 0) {
                    $message = 'You cannot remove your own admin role.';
                    $messageType = 'error';
                } else {
                    $db->updateUserRolesAndPermissions(
                        $targetUser,
                        $is_prof,
                        $is_admin,
                        $is_student,
                        $perm_manage_users,
                        $perm_manage_faces,
                        $perm_manage_doors,
                        $perm_view_logs
                    );
                    $db->logAdminEvent(
                        $_SESSION['valid_user'],
                        'user_updated',
                        $targetUser,
                        'roles/admin permissions updated'
                    );
                    $message = 'User roles/permissions updated.';
                    $messageType = 'success';
                }
            }
        } elseif ($action === 'delete_user') {
            $activeTab = 'users';
            if (!$canManageUsers) {
                $message = 'You do not have permission to delete users.';
                $messageType = 'error';
            } else {
                $targetUser = trim(isset($_POST['target_username']) ? $_POST['target_username'] : '');
                if ($targetUser === '' || $targetUser === $_SESSION['valid_user']) {
                    $message = 'Invalid delete request.';
                    $messageType = 'error';
                } else {
                    $db->deleteUserByUsername($targetUser);
                    $db->logAdminEvent($_SESSION['valid_user'], 'user_deleted', $targetUser, 'Deleted from admin page');
                    $message = 'User deleted successfully.';
                    $messageType = 'success';
                }
            }
        } elseif ($action === 'door_lock_until_authorized') {
            $activeTab = 'doors';
            if (!$canManageDoors) {
                $message = 'You do not have permission to control doors.';
                $messageType = 'error';
            } else {
                $doorId = trim(isset($_POST['door_id']) ? $_POST['door_id'] : '');
                $roomNumber = isset($_POST['room_number']) ? (int)$_POST['room_number'] : null;
                if ($doorId === '') {
                    $message = 'Invalid door selected.';
                    $messageType = 'error';
                } else {
                    $db->setDoorState($doorId, 1, 'locked_until_authorized', 'Remotely locked until professor/admin face scan', $_SESSION['valid_user'], $roomNumber);
                    $db->logAdminEvent($_SESSION['valid_user'], 'door_locked_remote', null, 'Door ' . $doorId . ' locked until professor/admin face scan');
                    $message = 'Door ' . $doorId . ' locked. It will stay locked until an admin/professor face scan is recognized at that door.';
                    $messageType = 'success';
                }
            }
        } elseif ($action === 'door_unlock_remote') {
            $activeTab = 'doors';
            if (!$canManageDoors) {
                $message = 'You do not have permission to control doors.';
                $messageType = 'error';
            } else {
                $doorId = trim(isset($_POST['door_id']) ? $_POST['door_id'] : '');
                $roomNumber = isset($_POST['room_number']) ? (int)$_POST['room_number'] : null;
                if ($doorId === '') {
                    $message = 'Invalid door selected.';
                    $messageType = 'error';
                } else {
                    $db->setDoorState($doorId, 0, 'unlocked', 'Remotely unlocked by admin', $_SESSION['valid_user'], $roomNumber);
                    $db->logAdminEvent($_SESSION['valid_user'], 'door_unlocked_remote', null, 'Door ' . $doorId . ' remotely unlocked');
                    $message = 'Door ' . $doorId . ' unlocked remotely.';
                    $messageType = 'success';
                }
            }
        }
    }

    $users = $db->getUsersWithPermissions();
    $doorStates = $db->getDoorStatesForClassRooms();
    $logs = $canViewLogs ? $db->getAdminLogs(250) : [];
} else {
    $users = [];
    $doorStates = [];
    $logs = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $message = 'DEV_MODE is ON: actions disabled.';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Admin Console</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="admin-body">
    <div class="dashboard-buttons">
        <?php if (!$DEV_MODE): ?>
            <a href="mainMenu.php" class="btn">Professor View</a>
            <a href="logout.php" class="btn logout">Logout</a>
        <?php else: ?>
            <a href="#" class="btn logout" onclick="return false;" aria-disabled="true" style="opacity:.6;pointer-events:none;">Logout (Disabled)</a>
        <?php endif; ?>
    </div>

    <div class="admin-wrap admin-shell">
        <h2 id="dashTitle">Admin Console: <?php echo e($displayName); ?></h2>

        <?php if ($message !== ''): ?>
            <div class="admin-alert <?php echo e($messageType); ?>" id="adminAlert" role="status" aria-live="polite">
                <span class="admin-alert-text"><?php echo e($message); ?></span>
                <button type="button" class="admin-alert-close" data-close-alert aria-label="Dismiss notification">&times;</button>
            </div>
        <?php endif; ?>

        <div class="admin-tabs" role="tablist" aria-label="Admin Tabs">
            <button type="button" class="admin-tab<?php echo $activeTab === 'users' ? ' active' : ''; ?>" data-tab="users">Users</button>
            <button type="button" class="admin-tab<?php echo $activeTab === 'faces' ? ' active' : ''; ?>" data-tab="faces">Faces</button>
            <button type="button" class="admin-tab<?php echo $activeTab === 'doors' ? ' active' : ''; ?>" data-tab="doors">Door Control</button>
            <button type="button" class="admin-tab<?php echo $activeTab === 'logs' ? ' active' : ''; ?>" data-tab="logs">Activity Logs</button>
        </div>

        <section class="tab-panel<?php echo $activeTab === 'users' ? ' active' : ''; ?>" id="tab-users">
            <div class="admin-grid">
                <article class="admin-card">
                    <h3>Create User</h3>
                    <?php if ($canManageUsers || $DEV_MODE): ?>
                    <form method="POST" class="stack-form">
                        <input type="hidden" name="action" value="create_user">

                        <label>Username</label>
                        <input type="text" name="username" pattern="[A-Za-z0-9_]+" required <?php echo $DEV_MODE ? 'disabled' : ''; ?>>

                        <label>Full Name</label>
                        <input type="text" name="full_name" required <?php echo $DEV_MODE ? 'disabled' : ''; ?>>

                        <label>Password (optional)</label>
                        <input type="password" name="password" <?php echo $DEV_MODE ? 'disabled' : ''; ?>>

                        <div class="label-row">Roles</div>
                        <div class="checkbox-grid">
                            <label><input type="checkbox" name="is_admin" <?php echo $DEV_MODE ? 'disabled' : ''; ?>> Admin</label>
                            <label><input type="checkbox" name="is_prof" <?php echo $DEV_MODE ? 'disabled' : ''; ?>> Professor</label>
                            <label><input type="checkbox" name="is_student" <?php echo $DEV_MODE ? 'disabled' : ''; ?>> Student</label>
                        </div>

                        <div class="label-row">Permissions</div>
                        <div class="checkbox-grid">
                            <label><input type="checkbox" name="can_manage_users" <?php echo $DEV_MODE ? 'disabled' : ''; ?>> Manage Users</label>
                            <label><input type="checkbox" name="can_manage_faces" <?php echo $DEV_MODE ? 'disabled' : ''; ?>> Manage Faces</label>
                            <label><input type="checkbox" name="can_manage_doors" <?php echo $DEV_MODE ? 'disabled' : ''; ?>> Manage Doors</label>
                            <label><input type="checkbox" name="can_view_logs" checked <?php echo $DEV_MODE ? 'disabled' : ''; ?>> View Logs</label>
                        </div>

                        <button type="submit" class="create-btn" <?php echo $DEV_MODE ? 'disabled' : ''; ?>>Create User</button>
                    </form>
                    <?php else: ?>
                        <p class="muted">You do not have permission to create users.</p>
                    <?php endif; ?>
                </article>

                <article class="admin-card wide">
                    <h3>User Roles and Permissions</h3>
                    <div class="table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Roles</th>
                                    <th>Permissions</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr><td colspan="4" class="muted">No users found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($users as $u): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo e($u['username']); ?></strong><br>
                                                <span class="subtle"><?php echo e($u['full_name']); ?></span>
                                            </td>
                                            <td>
                                                <form method="POST" class="inline-form">
                                                    <input type="hidden" name="action" value="update_user">
                                                    <input type="hidden" name="target_username" value="<?php echo e($u['username']); ?>">
                                                    <div class="checkbox-grid compact">
                                                        <label><input type="checkbox" name="is_admin" <?php echo ((int)$u['is_admin'] === 1 ? 'checked ' : '') . ($canManageUsers && !$DEV_MODE ? '' : 'disabled'); ?>> Admin</label>
                                                        <label><input type="checkbox" name="is_prof" <?php echo ((int)$u['is_prof'] === 1 ? 'checked ' : '') . ($canManageUsers && !$DEV_MODE ? '' : 'disabled'); ?>> Professor</label>
                                                        <label><input type="checkbox" name="is_student" <?php echo ((int)$u['is_student'] === 1 ? 'checked ' : '') . ($canManageUsers && !$DEV_MODE ? '' : 'disabled'); ?>> Student</label>
                                                    </div>
                                            </td>
                                            <td>
                                                    <div class="checkbox-grid compact">
                                                        <label><input type="checkbox" name="can_manage_users" <?php echo ((int)$u['can_manage_users'] === 1 ? 'checked ' : '') . ($canManageUsers && !$DEV_MODE ? '' : 'disabled'); ?>> Manage Users</label>
                                                        <label><input type="checkbox" name="can_manage_faces" <?php echo ((int)$u['can_manage_faces'] === 1 ? 'checked ' : '') . ($canManageUsers && !$DEV_MODE ? '' : 'disabled'); ?>> Manage Faces</label>
                                                        <label><input type="checkbox" name="can_manage_doors" <?php echo ((int)$u['can_manage_doors'] === 1 ? 'checked ' : '') . ($canManageUsers && !$DEV_MODE ? '' : 'disabled'); ?>> Manage Doors</label>
                                                        <label><input type="checkbox" name="can_view_logs" <?php echo ((int)$u['can_view_logs'] === 1 ? 'checked ' : '') . ($canManageUsers && !$DEV_MODE ? '' : 'disabled'); ?>> View Logs</label>
                                                    </div>
                                            </td>
                                            <td class="actions-cell">
                                                    <button type="submit" class="mini-btn" <?php echo $canManageUsers && !$DEV_MODE ? '' : 'disabled'; ?>>Save</button>
                                                </form>
                                                <form method="POST" class="inline-form" onsubmit="return confirm('Delete user <?php echo e($u['username']); ?>?');">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="target_username" value="<?php echo e($u['username']); ?>">
                                                    <button type="submit" class="mini-btn danger" <?php echo ($u['username'] === $currentUser || !$canManageUsers || $DEV_MODE) ? 'disabled' : ''; ?>>Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </article>
            </div>
        </section>

        <section class="tab-panel<?php echo $activeTab === 'faces' ? ' active' : ''; ?>" id="tab-faces">
            <div class="admin-grid">
                <article class="admin-card">
                    <h3>Face Capture</h3>
                    <?php if ($canManageFaces || $DEV_MODE): ?>
                        <label for="personIdInput">Person ID</label>
                        <input type="text" id="personIdInput" name="person_id" placeholder="Enter ID or username">
                        <button class="video-btn" id="btnRight" type="button">Start Capture</button>
                        <div class="video-box" id="videoRight">
                            <span id="videoPlaceholder">Face capture feed will appear here</span>
                        </div>
                    <?php else: ?>
                        <p class="muted">You do not have permission to manage face data.</p>
                    <?php endif; ?>
                </article>

                <article class="admin-card">
                    <h3>Delete Face Data</h3>
                    <?php if ($canManageFaces || $DEV_MODE): ?>
                        <label for="deleteUserId">User ID</label>
                        <input type="text" id="deleteUserId" placeholder="Enter user ID">
                        <button class="video-btn danger" id="deleteFaceBtn" type="button" <?php echo $DEV_MODE ? 'disabled' : ''; ?>>Delete Face Data</button>
         
                    <?php else: ?>
                        <p class="muted">You do not have permission to delete face data.</p>
                    <?php endif; ?>
                </article>
            </div>
        </section>

        <section class="tab-panel<?php echo $activeTab === 'doors' ? ' active' : ''; ?>" id="tab-doors">
            <article class="admin-card wide">
                <h3>Remote Door Control By Room</h3>
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Door</th>
                                <th>Classes</th>
                                <th>Status</th>
                                <th>Reason</th>
                                <th>Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($doorStates)): ?>
                                <tr><td colspan="6" class="muted">No class rooms found in Classes table.</td></tr>
                            <?php else: ?>
                                <?php foreach ($doorStates as $door): ?>
                                    <?php $doorLocked = (int)$door['is_locked'] === 1; ?>
                                    <tr>
                                        <td><strong><?php echo e($door['door_id']); ?></strong></td>
                                        <td><?php echo e($door['class_names']); ?></td>
                                        <td>
                                            <span class="door-badge <?php echo $doorLocked ? 'locked' : 'unlocked'; ?>">
                                                <?php echo $doorLocked ? 'Locked' : 'Unlocked'; ?>
                                            </span>
                                        </td>
                                        <td class="details"><?php echo e($door['lock_reason']); ?></td>
                                        <td>
                                            <div><?php echo e($door['last_changed_by']); ?></div>
                                            <div class="subtle"><?php echo e($door['last_changed_at']); ?></div>
                                        </td>
                                        <td class="actions-cell">
                                            <form method="POST" class="inline-form" onsubmit="return confirm('Lock room <?php echo e($door['door_id']); ?> until professor/admin face scan?');">
                                                <input type="hidden" name="action" value="door_lock_until_authorized">
                                                <input type="hidden" name="door_id" value="<?php echo e($door['door_id']); ?>">
                                                <input type="hidden" name="room_number" value="<?php echo e($door['room_number']); ?>">
                                                <button type="submit" class="mini-btn danger" <?php echo ($canManageDoors && !$DEV_MODE) ? '' : 'disabled'; ?>>
                                                    Lock
                                                </button>
                                            </form>
                                            <form method="POST" class="inline-form" onsubmit="return confirm('Unlock room <?php echo e($door['door_id']); ?> now?');">
                                                <input type="hidden" name="action" value="door_unlock_remote">
                                                <input type="hidden" name="door_id" value="<?php echo e($door['door_id']); ?>">
                                                <input type="hidden" name="room_number" value="<?php echo e($door['room_number']); ?>">
                                                <button type="submit" class="mini-btn" <?php echo ($canManageDoors && !$DEV_MODE) ? '' : 'disabled'; ?>>
                                                    Unlock
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (!$canManageDoors && !$DEV_MODE): ?>
                    <p class="muted">You do not have permission to control doors.</p>
                <?php endif; ?>
            </article>
        </section>

        <section class="tab-panel<?php echo $activeTab === 'logs' ? ' active' : ''; ?>" id="tab-logs">
            <article class="admin-card wide">
                <h3>Activity Logs</h3>
                <?php if (!$canViewLogs && !$DEV_MODE): ?>
                    <p class="muted">You do not have permission to view logs.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Action</th>
                                    <th>User</th>
                                    <th>Target</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($logs)): ?>
                                    <tr><td colspan="5" class="muted">No logs available.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td><?php echo e($log['created_at']); ?></td>
                                            <td><span class="log-tag"><?php echo e($log['action_type']); ?></span></td>
                                            <td><?php echo e($log['actor_username'] !== null ? $log['actor_username'] : '-'); ?></td>
                                            <td><?php echo e($log['target_username'] !== null ? $log['target_username'] : '-'); ?></td>
                                            <td class="details"><?php echo e($log['details']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </article>
        </section>
    </div>

    <script>
    (function() {
        const alertEl = document.getElementById('adminAlert');
        if (!alertEl) {
            return;
        }
        const closeBtn = alertEl.querySelector('[data-close-alert]');
        const hideAlert = () => {
            alertEl.classList.add('hidden');
        };
        if (closeBtn) {
            closeBtn.addEventListener('click', hideAlert);
        }
        window.setTimeout(hideAlert, 7000);
    })();
    </script>

    <script>
    (function() {
        const tabs = document.querySelectorAll('.admin-tab');
        const panels = {
            users: document.getElementById('tab-users'),
            faces: document.getElementById('tab-faces'),
            doors: document.getElementById('tab-doors'),
            logs: document.getElementById('tab-logs')
        };

        tabs.forEach((btn) => {
            btn.addEventListener('click', () => {
                const tabName = btn.getAttribute('data-tab');
                tabs.forEach((t) => t.classList.remove('active'));
                btn.classList.add('active');
                Object.keys(panels).forEach((name) => {
                    if (panels[name]) {
                        panels[name].classList.toggle('active', name === tabName);
                    }
                });
            });
        });
    })();
    </script>

    <script>
    (function() {
        const btn = document.getElementById('btnRight');
        const box = document.getElementById('videoRight');
        const idInput = document.getElementById('personIdInput');
        let isCapturing = false;
        let imgElement = null;

        if (!btn || !box || !idInput) {
            return;
        }

        btn.addEventListener('click', () => {
            const personId = idInput.value.trim();
            if (!isCapturing) {
                if (!personId) {
                    alert('Please enter a Person ID before starting capture.');
                    return;
                }
                imgElement = document.createElement('img');
                imgElement.src = 'http://debianRy.local:5000/video_feed?person_id=' + encodeURIComponent(personId);
                imgElement.style = 'width:100%; height:100%; object-fit:cover; display:block; border-radius:12px;';
                box.innerHTML = '';
                box.appendChild(imgElement);
                btn.textContent = 'Stop Capture';
                isCapturing = true;
            } else {
                if (imgElement) {
                    imgElement.src = '';
                    box.innerHTML = '<span id="videoPlaceholder">Face capture feed will appear here</span>';
                }
                btn.textContent = 'Start Capture';
                isCapturing = false;
            }
        });
    })();
    </script>

    <script>
    (function() {
        const deleteBtn = document.getElementById('deleteFaceBtn');
        const deleteInput = document.getElementById('deleteUserId');
        const status = document.getElementById('deleteFaceStatus');

        if (!deleteBtn || !deleteInput || !status) {
            return;
        }

        deleteBtn.addEventListener('click', function() {
            const userId = deleteInput.value.trim();
            if (!userId) {
                alert('Please enter a User ID.');
                return;
            }
            if (!confirm('Are you sure you want to delete face data for user: ' + userId + '?')) {
                return;
            }

            status.textContent = 'Deleting face data...';

            fetch('/htdocs/ryan/yilma/deleteFace.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'user_id=' + encodeURIComponent(userId)
            })
            .then((res) => res.text().then((body) => ({ok: res.ok, body: body})))
            .then((result) => {
                status.textContent = result.body;
                alert(result.body);
            })
            .catch((err) => {
                status.textContent = 'Error: ' + err;
                alert('Error: ' + err);
            });
        });
    })();
    </script>
</body>
</html>

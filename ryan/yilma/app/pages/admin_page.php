<?php
session_start();

$DEV_MODE = false;
$message = '';
$messageType = 'info';
$displayName = 'Administrator (Mock)';
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'users';
$allTabs = ['users', 'faces', 'doors', 'logs'];
$allowedTabs = $allTabs;
$isAdminUser = false;
$isProfessorUser = false;

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function adminUrl($params = [])
{
    $query = array_merge($_GET, $params);
    foreach ($query as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
        }
    }
    $query['tab'] = isset($query['tab']) ? $query['tab'] : 'users';
    $qs = http_build_query($query);
    return './admin.php' . ($qs !== '' ? '?' . $qs : '');
}

function logSortUrl($column, $currentSort, $currentDir)
{
    $nextDir = ($currentSort === $column && strtolower((string)$currentDir) === 'asc') ? 'desc' : 'asc';
    return adminUrl([
        'tab' => 'logs',
        'log_sort' => $column,
        'log_dir' => $nextDir,
        'log_page' => 1,
    ]);
}

function logSortIndicator($column, $currentSort, $currentDir)
{
    if ($column !== $currentSort) {
        return '';
    }
    return strtoupper((string)$currentDir) === 'ASC' ? '^' : 'v';
}

function isSecurityDeskPresetFromValues($isAdmin, $isProf, $canManageUsers, $canManageFaces, $canManageDoors, $canViewLogs)
{
    return (int)$isAdmin === 0
        && (int)$isProf === 0
        && (int)$canManageUsers === 0
        && (int)$canManageFaces === 0
        && (int)$canManageDoors === 1
        && (int)$canViewLogs === 1;
}

$allowedTabs = $allTabs;
if (!in_array($activeTab, $allTabs, true)) {
    $activeTab = 'users';
}

$db = null;
$currentUser = isset($_SESSION['valid_user']) ? $_SESSION['valid_user'] : '';
$canManageUsers = false;
$canManageFaces = false;
$canManageDoors = false;
$canViewLogs = false;
$logs = [];
$logActionOptions = [];
$logTotal = 0;
$logTotalPages = 1;
$logOffset = 0;
$logSearch = trim(isset($_GET['log_search']) ? (string)$_GET['log_search'] : '');
$logAction = trim(isset($_GET['log_action']) ? (string)$_GET['log_action'] : '');
$logActor = trim(isset($_GET['log_actor']) ? (string)$_GET['log_actor'] : '');
$logTarget = trim(isset($_GET['log_target']) ? (string)$_GET['log_target'] : '');
$logSort = isset($_GET['log_sort']) ? (string)$_GET['log_sort'] : 'created_at';
$logDir = isset($_GET['log_dir']) ? strtolower((string)$_GET['log_dir']) : 'desc';
$logPage = isset($_GET['log_page']) ? (int)$_GET['log_page'] : 1;
$logLimit = isset($_GET['log_limit']) ? (int)$_GET['log_limit'] : 100;
$allowedLogLimits = [50, 100, 250, 500];
if (!in_array($logLimit, $allowedLogLimits, true)) {
    $logLimit = 100;
}
if ($logDir !== 'asc' && $logDir !== 'desc') {
    $logDir = 'desc';
}
if ($logPage < 1) {
    $logPage = 1;
}

if (isset($_SESSION['admin_flash']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $message = isset($_SESSION['admin_flash']['message']) ? (string)$_SESSION['admin_flash']['message'] : '';
    $messageType = isset($_SESSION['admin_flash']['type']) ? (string)$_SESSION['admin_flash']['type'] : 'info';
    unset($_SESSION['admin_flash']);
}

if (!$DEV_MODE) {
    require_once __DIR__ . '/../database.php';
    $db = new database();
    $db->ensureAdminTables();

    if (!isset($_SESSION['valid_user'])) {
        header('Location: ./index.php');
        exit();
    }

    $isAdminUser = $db->isAdmin($_SESSION['valid_user']);
    $isProfessorUser = $db->isProf($_SESSION['valid_user']);
    $permissions = $db->getUserPermissions($_SESSION['valid_user']);

    $canManageUsers = $isAdminUser || ((int)$permissions['can_manage_users'] === 1);
    $canManageFaces = $isAdminUser || ((int)$permissions['can_manage_faces'] === 1);
    $canManageDoors = $isAdminUser || ((int)$permissions['can_manage_doors'] === 1);
    $canViewLogs = $isAdminUser || ((int)$permissions['can_view_logs'] === 1);

    $hasAdminAccess = $canManageUsers || $canManageFaces || $canManageDoors || $canViewLogs;
    if (!$hasAdminAccess) {
        if ($isProfessorUser) {
            header('Location: ./mainMenu.php');
        } else {
            header('Location: ./index.php');
        }
        exit();
    }

    $allowedTabs = [];
    if ($canManageUsers) { $allowedTabs[] = 'users'; }
    if ($canManageFaces) { $allowedTabs[] = 'faces'; }
    if ($canManageDoors) { $allowedTabs[] = 'doors'; }
    if ($canViewLogs) { $allowedTabs[] = 'logs'; }
    if (empty($allowedTabs)) {
        $allowedTabs = ['logs'];
    }
    if (!in_array($activeTab, $allowedTabs, true)) {
        $activeTab = $allowedTabs[0];
    }

    $displayName = $db->getUserInfo($_SESSION['valid_user'], 'full_name');

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
                $is_security_desk = isset($_POST['is_security_desk']) ? 1 : 0;

                if ($username === '' || !preg_match('/^[A-Za-z0-9_]{3,40}$/', $username)) {
                    $message = 'Username must be 3-40 chars and only letters, numbers, underscore.';
                    $messageType = 'error';
                } elseif ($full_name === '') {
                    $message = 'Full name is required.';
                    $messageType = 'error';
                } elseif (($is_admin === 1 || $is_prof === 1 || $is_security_desk === 1) && $password === '') {
                    $message = 'Password is required for Admin, Professor, or Security Desk users.';
                    $messageType = 'error';
                } elseif ($is_security_desk && ($is_admin === 1 || $is_prof === 1)) {
                    $message = 'Security desk users cannot also be Admin or Professor.';
                    $messageType = 'error';
                } elseif (($is_prof + $is_admin + $is_student) === 0 && !$is_security_desk) {
                    $message = 'Select at least one role or choose Security Desk.';
                    $messageType = 'error';
                } else {
                    $passwordHash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : '';
                    $result = $db->createUser($username, $full_name, $passwordHash, $is_prof, $is_admin, $is_student);

                    if ($result === 'Error') {
                        $message = 'Username already exists.';
                        $messageType = 'error';
                    } elseif ($result === true) {
                        if ($is_security_desk) {
                            $db->updateUserRolesAndPermissions($username, $is_prof, $is_admin, $is_student, 0, 0, 1, 1);
                        }
                        $db->logAdminEvent(
                            $_SESSION['valid_user'],
                            'user_created',
                            $username,
                            'roles(admin=' . $is_admin . ',prof=' . $is_prof . ',student=' . $is_student . ',security_desk=' . $is_security_desk . ')'
                        );
                        $message = $is_security_desk
                            ? 'Security desk user created successfully.'
                            : 'User created successfully.';
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
                $full_name = trim(isset($_POST['full_name']) ? $_POST['full_name'] : '');
                $is_prof = isset($_POST['is_prof']) ? 1 : 0;
                $is_admin = isset($_POST['is_admin']) ? 1 : 0;
                $is_student = isset($_POST['is_student']) ? 1 : 0;
                $is_security_desk = isset($_POST['is_security_desk']) ? 1 : 0;
                $was_security_desk = isset($_POST['was_security_desk']) ? (int)$_POST['was_security_desk'] : 0;

                if ($targetUser === '') {
                    $message = 'Invalid target user.';
                    $messageType = 'error';
                } elseif ($full_name === '' || strlen($full_name) > 100) {
                    $message = 'Full name is required and must be 100 characters or less.';
                    $messageType = 'error';
                } elseif ($is_security_desk && ($is_admin === 1 || $is_prof === 1)) {
                    $message = 'Security desk users cannot also be Admin or Professor.';
                    $messageType = 'error';
                } elseif (($is_prof + $is_admin + $is_student) === 0 && !$is_security_desk) {
                    $message = 'A user must keep at least one role or be assigned Security Desk.';
                    $messageType = 'error';
                } elseif ($targetUser === $_SESSION['valid_user'] && $is_admin === 0) {
                    $message = 'You cannot remove your own admin role.';
                    $messageType = 'error';
                } else {
                    if ($is_security_desk) {
                        $db->updateUserRoles(
                            $targetUser,
                            $is_prof,
                            $is_admin,
                            $is_student,
                            $full_name
                        );
                        $db->updateUserRolesAndPermissions(
                            $targetUser,
                            $is_prof,
                            $is_admin,
                            $is_student,
                            0,
                            0,
                            1,
                            1
                        );
                    } elseif ($was_security_desk === 1) {
                        $db->updateUserRoles(
                            $targetUser,
                            $is_prof,
                            $is_admin,
                            $is_student,
                            $full_name
                        );
                        $db->updateUserRolesAndPermissions(
                            $targetUser,
                            $is_prof,
                            $is_admin,
                            $is_student,
                            0,
                            0,
                            0,
                            0
                        );
                    } else {
                        $db->updateUserRoles(
                            $targetUser,
                            $is_prof,
                            $is_admin,
                            $is_student,
                            $full_name
                        );
                    }
                    $db->logAdminEvent(
                        $_SESSION['valid_user'],
                        'user_updated',
                        $targetUser,
                        'roles/full_name updated; security_desk=' . $is_security_desk
                    );
                    $message = 'User profile updated.';
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
                    $deleteInfo = $db->deleteUserByUsername($targetUser);
                    $faceFolderFound = is_array($deleteInfo) && !empty($deleteInfo['face_folder_found']);
                    $faceFolderDeleted = is_array($deleteInfo) && !empty($deleteInfo['face_folder_deleted']);
                    $retrainStarted = is_array($deleteInfo) && !empty($deleteInfo['retrain_started']);
                    $detail = 'Deleted from admin page';
                    if ($faceFolderFound) {
                        if ($faceFolderDeleted) {
                            $detail .= '; face images deleted';
                            $detail .= $retrainStarted ? '; retraining started' : '; retraining failed to start';
                        } else {
                            $detail .= '; face image folder found but delete failed';
                        }
                    }
                    $db->logAdminEvent($_SESSION['valid_user'], 'user_deleted', $targetUser, $detail);
                    if ($faceFolderDeleted && $retrainStarted) {
                        $message = 'User deleted. Face images removed and retraining started.';
                    } elseif ($faceFolderDeleted) {
                        $message = 'User deleted. Face images removed, but retraining failed to start.';
                    } elseif ($faceFolderFound) {
                        $message = 'User deleted, but face-image folder could not be removed.';
                    } else {
                        $message = 'User deleted successfully.';
                    }
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
                    $db->setDoorState($doorId, 1, 'locked_until_authorized', 'Remotely locked until authorized face scan', $_SESSION['valid_user'], $roomNumber);
                    $db->logAdminEvent($_SESSION['valid_user'], 'door_locked_remote', null, 'Door ' . $doorId . ' locked until authorized face scan (admin/professor/security desk)');
                    $message = 'Door ' . $doorId . ' locked. It will stay locked until an authorized face scan (admin/professor/security desk) is recognized at that door.';
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
                    $db->setDoorState(
                        $doorId,
                        0,
                        'temporary_unlocked',
                        'Remotely unlocked by admin for 5 seconds',
                        $_SESSION['valid_user'],
                        $roomNumber,
                        5
                    );
                    $db->logAdminEvent($_SESSION['valid_user'], 'door_unlocked_remote', null, 'Door ' . $doorId . ' remotely unlocked');
                    $message = 'Door ' . $doorId . ' unlocked for 5 seconds, then it will re-lock.';
                    $messageType = 'success';
                }
            }
        }

        if ($message === '') {
            $message = 'Action processed.';
            $messageType = 'info';
        }
        $_SESSION['admin_flash'] = [
            'message' => $message,
            'type' => $messageType,
        ];
        header('Location: ./admin.php?tab=' . rawurlencode($activeTab));
        exit();
    }

    $users = $db->getUsersWithPermissions();
    $doorStates = $db->getDoorStatesForClassRooms();
    if ($canViewLogs) {
        $logFilters = [
            'search' => $logSearch,
            'action_type' => $logAction,
            'actor_username' => $logActor,
            'target_username' => $logTarget,
        ];
        $logTotal = $db->countAdminLogs($logFilters);
        $logTotalPages = max(1, (int)ceil($logTotal / $logLimit));
        if ($logPage > $logTotalPages) {
            $logPage = $logTotalPages;
        }
        $logOffset = ($logPage - 1) * $logLimit;
        $logs = $db->getAdminLogs(array_merge($logFilters, [
            'limit' => $logLimit,
            'offset' => $logOffset,
            'sort' => $logSort,
            'direction' => $logDir,
        ]));
        $logActionOptions = $db->getAdminLogActionTypes();
    }
} else {
    $users = [];
    $doorStates = [];
    $allowedTabs = $allTabs;
    $canManageUsers = true;
    $canManageFaces = true;
    $canManageDoors = true;
    $canViewLogs = true;
    $isAdminUser = true;
    $isProfessorUser = true;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $message = 'DEV_MODE is ON: actions disabled.';
    }
}
$logShowingStart = $logTotal > 0 ? ($logOffset + 1) : 0;
$logShowingEnd = $logTotal > 0 ? ($logOffset + count($logs)) : 0;
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
    <div class="bg-animation">
        <div class="bg-shape shape-1"></div>
        <div class="bg-shape shape-2"></div>
        <div class="bg-shape shape-3"></div>
    </div>

    <div class="dashboard-buttons">
        <?php if (!$DEV_MODE): ?>
            <?php if ($isAdminUser || $isProfessorUser): ?>
                <a href="mainMenu.php" class="btn">Professor View</a>
            <?php endif; ?>
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
            <?php if (in_array('users', $allowedTabs, true)): ?>
                <button type="button" class="admin-tab<?php echo $activeTab === 'users' ? ' active' : ''; ?>" data-tab="users">Users</button>
            <?php endif; ?>
            <?php if (in_array('faces', $allowedTabs, true)): ?>
                <button type="button" class="admin-tab<?php echo $activeTab === 'faces' ? ' active' : ''; ?>" data-tab="faces">Faces</button>
            <?php endif; ?>
            <?php if (in_array('doors', $allowedTabs, true)): ?>
                <button type="button" class="admin-tab<?php echo $activeTab === 'doors' ? ' active' : ''; ?>" data-tab="doors">Door Control</button>
            <?php endif; ?>
            <?php if (in_array('logs', $allowedTabs, true)): ?>
                <button type="button" class="admin-tab<?php echo $activeTab === 'logs' ? ' active' : ''; ?>" data-tab="logs">Activity Logs</button>
            <?php endif; ?>
        </div>

        <?php if (in_array('users', $allowedTabs, true)): ?>
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

                        <label>Password</label>
                        <input type="password" name="password" autocomplete="new-password" <?php echo $DEV_MODE ? 'disabled' : ''; ?>>
                        <p class="step-help">Required for Admin, Professor, or Security Desk users.</p>

                        <div class="label-row">Roles</div>
                        <div class="checkbox-grid">
                            <label><input type="checkbox" name="is_admin" <?php echo $DEV_MODE ? 'disabled' : ''; ?>> Admin</label>
                            <label><input type="checkbox" name="is_prof" <?php echo $DEV_MODE ? 'disabled' : ''; ?>> Professor</label>
                            <label><input type="checkbox" name="is_student" <?php echo $DEV_MODE ? 'disabled' : ''; ?>> Student</label>
                            <label class="security-desk-role"><input type="checkbox" name="is_security_desk" <?php echo $DEV_MODE ? 'disabled' : ''; ?>> <span class="role-label-text">Security Desk (Doors + Logs)</span></label>
                        </div>

                        <button type="submit" class="create-btn" <?php echo $DEV_MODE ? 'disabled' : ''; ?>>Create User</button>
                    </form>
                    <?php else: ?>
                        <p class="muted">You do not have permission to create users.</p>
                    <?php endif; ?>
                </article>

                <article class="admin-card wide">
                    <h3>User Roles</h3>
                    <div class="table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Roles</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr><td colspan="3" class="muted">No users found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($users as $u): ?>
                                        <?php $updateFormId = 'update_user_' . preg_replace('/[^A-Za-z0-9_]/', '_', (string)$u['username']); ?>
                                        <?php
                                            $isSecurityDeskPreset = isSecurityDeskPresetFromValues(
                                                (int)$u['is_admin'],
                                                (int)$u['is_prof'],
                                                (int)$u['can_manage_users'],
                                                (int)$u['can_manage_faces'],
                                                (int)$u['can_manage_doors'],
                                                (int)$u['can_view_logs']
                                            );
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo e($u['username']); ?></strong>
                                                <label class="sr-only" for="<?php echo e($updateFormId . '_full_name'); ?>">Full Name</label>
                                                <input
                                                    id="<?php echo e($updateFormId . '_full_name'); ?>"
                                                    class="inline-fullname"
                                                    type="text"
                                                    name="full_name"
                                                    maxlength="100"
                                                    value="<?php echo e($u['full_name']); ?>"
                                                    form="<?php echo e($updateFormId); ?>"
                                                    <?php echo ($canManageUsers && !$DEV_MODE) ? '' : 'disabled'; ?>
                                                >
                                            </td>
                                            <td>
                                                <div class="checkbox-grid compact">
                                                    <label>
                                                        <input type="checkbox" name="is_admin" form="<?php echo e($updateFormId); ?>" <?php echo ((int)$u['is_admin'] === 1 ? 'checked ' : '') . ($canManageUsers && !$DEV_MODE ? '' : 'disabled'); ?>>
                                                        Admin
                                                    </label>
                                                    <label>
                                                        <input type="checkbox" name="is_prof" form="<?php echo e($updateFormId); ?>" <?php echo ((int)$u['is_prof'] === 1 ? 'checked ' : '') . ($canManageUsers && !$DEV_MODE ? '' : 'disabled'); ?>>
                                                        Professor
                                                    </label>
                                                    <label>
                                                        <input type="checkbox" name="is_student" form="<?php echo e($updateFormId); ?>" <?php echo ((int)$u['is_student'] === 1 ? 'checked ' : '') . ($canManageUsers && !$DEV_MODE ? '' : 'disabled'); ?>>
                                                        Student
                                                    </label>
                                                    <label>
                                                        <input type="checkbox" name="is_security_desk" form="<?php echo e($updateFormId); ?>" <?php echo ($isSecurityDeskPreset ? 'checked ' : '') . ($canManageUsers && !$DEV_MODE ? '' : 'disabled'); ?>>
                                                        Security Desk
                                                    </label>
                                                </div>
                                            </td>
                                            <td class="actions-cell">
                                                <div class="actions-row">
                                                    <form method="POST" class="inline-form" id="<?php echo e($updateFormId); ?>">
                                                        <input type="hidden" name="action" value="update_user">
                                                        <input type="hidden" name="target_username" value="<?php echo e($u['username']); ?>">
                                                        <input type="hidden" name="was_security_desk" value="<?php echo $isSecurityDeskPreset ? '1' : '0'; ?>">
                                                    <button type="submit" class="mini-btn" <?php echo $canManageUsers && !$DEV_MODE ? '' : 'disabled'; ?>>Save</button>
                                                    </form>
                                                    <form method="POST" class="inline-form" onsubmit="return confirm('Delete user <?php echo e($u['username']); ?>?');">
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="target_username" value="<?php echo e($u['username']); ?>">
                                                        <button type="submit" class="mini-btn danger" <?php echo ($u['username'] === $currentUser || !$canManageUsers || $DEV_MODE) ? 'disabled' : ''; ?>>Delete</button>
                                                    </form>
                                                </div>
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
        <?php endif; ?>

        <?php if (in_array('faces', $allowedTabs, true)): ?>
        <section class="tab-panel<?php echo $activeTab === 'faces' ? ' active' : ''; ?>" id="tab-faces">
            <div class="admin-grid">
                <article class="admin-card">
                    <h3>Face Enrollment Onboarding</h3>
                    <?php if ($canManageFaces || $DEV_MODE): ?>
                        <p class="muted onboarding-intro">
                            Choose whether you are creating a brand new account with face data or appending face data to an existing account.
                        </p>

                        <div class="face-flow-toggle" role="radiogroup" aria-label="Enrollment flow">
                            <label class="flow-pill">
                                <input type="radio" name="faceEnrollFlow" id="flowCreateUser" value="create" checked>
                                Create new user + face data
                            </label>
                            <label class="flow-pill">
                                <input type="radio" name="faceEnrollFlow" id="flowExistingUser" value="existing">
                                Existing user + append face data
                            </label>
                        </div>

                        <div class="onboarding-steps">
                            <section class="onboarding-step">
                                <h4>1. Identity</h4>
                                <label for="personIdInput">Username / Person ID</label>
                                <input type="text" id="personIdInput" name="person_id" placeholder="3-40 chars: letters, numbers, underscore">
                                <label for="personFullNameInput" id="personFullNameLabel">Full Name</label>
                                <input type="text" id="personFullNameInput" name="person_full_name" maxlength="100" placeholder="Required for new users">
                                <p class="step-help" id="personFullNameHelp">Required when creating a new user account.</p>
                                <div id="personPasswordGroup">
                                    <label for="personPasswordInput" id="personPasswordLabel">Portal Password</label>
                                    <input type="password" id="personPasswordInput" name="person_password" autocomplete="new-password" placeholder="Required for Admin, Professor, or Security Desk">
                                    <p class="step-help" id="personPasswordHelp">Required for Admin, Professor, or Security Desk users. Optional for student-only users.</p>
                                </div>
                            </section>

                            <section class="onboarding-step" id="newUserSetup">
                                <h4>2. Roles (new user only)</h4>
                                <div class="label-row">Roles</div>
                                <div class="checkbox-grid">
                                    <label><input type="checkbox" id="faceRoleAdmin"> Admin</label>
                                    <label><input type="checkbox" id="faceRoleProf"> Professor</label>
                                    <label><input type="checkbox" id="faceRoleStudent" checked> Student</label>
                                    <label class="security-desk-role"><input type="checkbox" id="faceRoleSecurityDesk"> <span class="role-label-text">Security Desk (Doors + Logs)</span></label>
                                </div>
                            </section>

                            <section class="onboarding-step">
                                <h4>3. Consent (required)</h4>
                                <label class="consent-check" for="faceConsentOptIn">
                                    <input type="checkbox" id="faceConsentOptIn">
                                    <span>I confirm the person has explicitly opted in to face enrollment for classroom access.</span>
                                </label>
                            </section>
                        </div>

                        <button class="video-btn" id="btnRight" type="button">Start Enrollment</button>
                        <div class="video-box" id="videoRight">
                            <span id="videoPlaceholder">Face capture feed will appear here</span>
                        </div>
                        <div class="message" id="captureFaceStatus" aria-live="polite"></div>
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
                        <div class="message" id="deleteFaceStatus" aria-live="polite"></div>
         
                    <?php else: ?>
                        <p class="muted">You do not have permission to delete face data.</p>
                    <?php endif; ?>
                </article>
            </div>
        </section>
        <?php endif; ?>

        <?php if (in_array('doors', $allowedTabs, true)): ?>
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
                                            <div class="actions-row">
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
                                            </div>
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
        <?php endif; ?>

        <?php if (in_array('logs', $allowedTabs, true)): ?>
        <section class="tab-panel<?php echo $activeTab === 'logs' ? ' active' : ''; ?>" id="tab-logs">
            <article class="admin-card wide">
                <h3>Activity Logs</h3>
                <?php if (!$canViewLogs && !$DEV_MODE): ?>
                    <p class="muted">You do not have permission to view logs.</p>
                <?php else: ?>
                    <form method="GET" class="logs-toolbar">
                        <input type="hidden" name="tab" value="logs">
                        <div class="logs-toolbar-row">
                            <div class="logs-field logs-field-search">
                                <label for="logSearchInput">Search</label>
                                <input
                                    type="text"
                                    id="logSearchInput"
                                    name="log_search"
                                    value="<?php echo e($logSearch); ?>"
                                    placeholder="Log ID, action, user, target, details"
                                >
                            </div>
                            <div class="logs-field">
                                <label for="logActionSelect">Action</label>
                                <select id="logActionSelect" name="log_action">
                                    <option value="">All actions</option>
                                    <?php foreach ($logActionOptions as $actionRow): ?>
                                        <?php $actionName = isset($actionRow['action_type']) ? (string)$actionRow['action_type'] : ''; ?>
                                        <option value="<?php echo e($actionName); ?>" <?php echo $logAction === $actionName ? 'selected' : ''; ?>>
                                            <?php echo e($actionName); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="logs-field">
                                <label for="logActorInput">Actor</label>
                                <input
                                    type="text"
                                    id="logActorInput"
                                    name="log_actor"
                                    value="<?php echo e($logActor); ?>"
                                    placeholder="Starts with username"
                                >
                            </div>
                            <div class="logs-field">
                                <label for="logTargetInput">Target</label>
                                <input
                                    type="text"
                                    id="logTargetInput"
                                    name="log_target"
                                    value="<?php echo e($logTarget); ?>"
                                    placeholder="Starts with username"
                                >
                            </div>
                            <div class="logs-field logs-field-small">
                                <label for="logLimitSelect">Rows</label>
                                <select id="logLimitSelect" name="log_limit">
                                    <?php foreach ($allowedLogLimits as $limitOption): ?>
                                        <option value="<?php echo e($limitOption); ?>" <?php echo $logLimit === $limitOption ? 'selected' : ''; ?>>
                                            <?php echo e($limitOption); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="logs-toolbar-footer">
                            <div class="logs-summary">
                                <span class="logs-summary-pill">
                                    Showing <?php echo e($logShowingStart); ?>-<?php echo e($logShowingEnd); ?> of <?php echo e($logTotal); ?>
                                </span>
                            </div>
                            <div class="logs-actions">
                                <button type="submit" class="mini-btn">Apply</button>
                                <a href="<?php echo e(adminUrl([
                                    'tab' => 'logs',
                                    'log_search' => null,
                                    'log_action' => null,
                                    'log_actor' => null,
                                    'log_target' => null,
                                    'log_sort' => null,
                                    'log_dir' => null,
                                    'log_page' => null,
                                    'log_limit' => 100,
                                ])); ?>" class="mini-btn ghost-btn">Clear</a>
                            </div>
                        </div>
                    </form>

                    <div class="table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>
                                        <a class="sort-link" href="<?php echo e(logSortUrl('log_id', $logSort, $logDir)); ?>">
                                            ID
                                            <span class="sort-indicator"><?php echo e(logSortIndicator('log_id', $logSort, $logDir)); ?></span>
                                        </a>
                                    </th>
                                    <th>
                                        <a class="sort-link" href="<?php echo e(logSortUrl('created_at', $logSort, $logDir)); ?>">
                                            Time
                                            <span class="sort-indicator"><?php echo e(logSortIndicator('created_at', $logSort, $logDir)); ?></span>
                                        </a>
                                    </th>
                                    <th>
                                        <a class="sort-link" href="<?php echo e(logSortUrl('action_type', $logSort, $logDir)); ?>">
                                            Action
                                            <span class="sort-indicator"><?php echo e(logSortIndicator('action_type', $logSort, $logDir)); ?></span>
                                        </a>
                                    </th>
                                    <th>
                                        <a class="sort-link" href="<?php echo e(logSortUrl('actor_username', $logSort, $logDir)); ?>">
                                            User
                                            <span class="sort-indicator"><?php echo e(logSortIndicator('actor_username', $logSort, $logDir)); ?></span>
                                        </a>
                                    </th>
                                    <th>
                                        <a class="sort-link" href="<?php echo e(logSortUrl('target_username', $logSort, $logDir)); ?>">
                                            Target
                                            <span class="sort-indicator"><?php echo e(logSortIndicator('target_username', $logSort, $logDir)); ?></span>
                                        </a>
                                    </th>
                                    <th>Picture</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($logs)): ?>
                                    <tr><td colspan="7" class="muted">No logs match the current filters.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td><?php echo e($log['log_id']); ?></td>
                                            <td><?php echo e($log['created_at']); ?></td>
                                            <td><span class="log-tag"><?php echo e($log['action_type']); ?></span></td>
                                            <td><?php echo e($log['actor_username'] !== null ? $log['actor_username'] : '-'); ?></td>
                                            <td><?php echo e($log['target_username'] !== null ? $log['target_username'] : '-'); ?></td>
                                            <td>
                                                <?php if (!empty($log['scan_image_path'])): ?>
                                                    <a href="<?php echo e($log['scan_image_path']); ?>" target="_blank" rel="noopener">
                                                        <img class="log-scan-image" src="<?php echo e($log['scan_image_path']); ?>" alt="Face scan snapshot">
                                                    </a>
                                                <?php else: ?>
                                                    <span class="muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="details"><?php echo e($log['details']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="table-meta table-meta-pagination">
                        <div>Page <?php echo e($logPage); ?> of <?php echo e($logTotalPages); ?></div>
                        <div class="pagination-actions">
                            <a
                                href="<?php echo e(adminUrl([
                                    'tab' => 'logs',
                                    'log_page' => $logPage > 1 ? ($logPage - 1) : 1,
                                ])); ?>"
                                class="mini-btn ghost-btn<?php echo $logPage <= 1 ? ' disabled-link' : ''; ?>"
                                <?php echo $logPage <= 1 ? 'aria-disabled="true" tabindex="-1"' : ''; ?>
                            >
                                Previous
                            </a>
                            <a
                                href="<?php echo e(adminUrl([
                                    'tab' => 'logs',
                                    'log_page' => $logPage < $logTotalPages ? ($logPage + 1) : $logTotalPages,
                                ])); ?>"
                                class="mini-btn ghost-btn<?php echo $logPage >= $logTotalPages ? ' disabled-link' : ''; ?>"
                                <?php echo $logPage >= $logTotalPages ? 'aria-disabled="true" tabindex="-1"' : ''; ?>
                            >
                                Next
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </article>
        </section>
        <?php endif; ?>
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
        const fullNameInput = document.getElementById('personFullNameInput');
        const fullNameLabel = document.getElementById('personFullNameLabel');
        const fullNameHelp = document.getElementById('personFullNameHelp');
        const passwordGroup = document.getElementById('personPasswordGroup');
        const passwordInput = document.getElementById('personPasswordInput');
        const passwordLabel = document.getElementById('personPasswordLabel');
        const passwordHelp = document.getElementById('personPasswordHelp');
        const flowCreate = document.getElementById('flowCreateUser');
        const flowExisting = document.getElementById('flowExistingUser');
        const newUserSetup = document.getElementById('newUserSetup');
        const roleAdmin = document.getElementById('faceRoleAdmin');
        const roleProf = document.getElementById('faceRoleProf');
        const roleStudent = document.getElementById('faceRoleStudent');
        const roleSecurityDesk = document.getElementById('faceRoleSecurityDesk');
        const consentOptIn = document.getElementById('faceConsentOptIn');
        const statusEl = document.getElementById('captureFaceStatus');
        let isCapturing = false;
        let imgElement = null;
        let statusPollTimer = null;
        let statusPollInFlight = false;
        let activeCapturePersonId = '';
        const personIdPattern = /^[A-Za-z0-9_]{3,40}$/;

        if (
            !btn || !box || !idInput || !fullNameInput || !statusEl ||
            !flowCreate || !flowExisting || !newUserSetup || !fullNameLabel || !fullNameHelp ||
            !passwordGroup || !passwordInput || !passwordLabel || !passwordHelp ||
            !roleAdmin || !roleProf || !roleStudent || !roleSecurityDesk ||
            !consentOptIn
        ) {
            return;
        }

        const getEnrollFlow = () => (flowCreate.checked ? 'create' : 'existing');

        const getSelectedRoles = () => ({
            is_admin: roleAdmin.checked ? 1 : 0,
            is_prof: roleProf.checked ? 1 : 0,
            is_student: roleStudent.checked ? 1 : 0,
            is_security_desk: roleSecurityDesk.checked ? 1 : 0
        });

        const hasAtLeastOneRole = (roles) => (roles.is_admin + roles.is_prof + roles.is_student) > 0 || roles.is_security_desk === 1;
        const requiresPortalPassword = (roles) => roles.is_admin === 1 || roles.is_prof === 1 || roles.is_security_desk === 1;

        const setSetupInputsDisabled = (disabled) => {
            flowCreate.disabled = disabled;
            flowExisting.disabled = disabled;
            idInput.disabled = disabled;
            fullNameInput.disabled = disabled;
            passwordInput.disabled = disabled;
            roleAdmin.disabled = disabled;
            roleProf.disabled = disabled;
            roleStudent.disabled = disabled;
            roleSecurityDesk.disabled = disabled;
            consentOptIn.disabled = disabled;
        };

        const syncPasswordRequirementUi = () => {
            const creating = getEnrollFlow() === 'create';
            const roles = getSelectedRoles();
            const required = creating && requiresPortalPassword(roles);
            passwordLabel.textContent = required ? 'Portal Password (required)' : 'Portal Password';
            passwordInput.required = required;
            passwordInput.placeholder = required
                ? 'Required for Admin, Professor, or Security Desk'
                : 'Optional for student-only users';
            passwordHelp.textContent = required
                ? 'Required because this user has Admin, Professor, or Security Desk access.'
                : 'Optional for student-only users.';
        };

        const syncEnrollFlowUi = () => {
            const creating = getEnrollFlow() === 'create';
            newUserSetup.hidden = !creating;
            passwordGroup.hidden = !creating;
            fullNameInput.required = creating;
            fullNameLabel.textContent = creating ? 'Full Name' : 'Full Name (optional)';
            fullNameInput.placeholder = creating ? 'Required for new users' : 'Optional';
            fullNameHelp.textContent = creating
                ? 'Required when creating a new user account.'
                : 'Optional for existing users. Leave blank to keep current profile name.';
            if (!creating) {
                passwordInput.value = '';
            }
            syncPasswordRequirementUi();
        };

        const stopRecognitionFeed = async () => {
            try {
                await fetch('http://debianRy.local:5001/stop_feed?t=' + Date.now(), { cache: 'no-store' });
            } catch (e) {}
        };

        const clearStatusPoll = () => {
            if (statusPollTimer) {
                window.clearTimeout(statusPollTimer);
                statusPollTimer = null;
            }
            statusPollInFlight = false;
        };

        const resetCaptureUi = (message) => {
            if (imgElement) {
                imgElement.src = '';
            }
            box.innerHTML = '<span id="videoPlaceholder">Face capture feed will appear here</span>';
            btn.textContent = 'Start Enrollment';
            isCapturing = false;
            imgElement = null;
            activeCapturePersonId = '';
            setSetupInputsDisabled(false);
            syncEnrollFlowUi();
            if (message) {
                statusEl.textContent = message;
            }
        };

        const stopCapture = async (options = {}) => {
            const notifyBackend = options.notifyBackend !== false;
            clearStatusPoll();
            if (notifyBackend) {
                try {
                    await fetch('http://debianRy.local:5000/stop_feed', { cache: 'no-store' });
                } catch (e) {}
            }
            resetCaptureUi(options.message || 'Capture stopped.');
        };

        const provisionNewUser = async (payload) => {
            const body = new URLSearchParams();
            body.set('username', payload.username);
            body.set('full_name', payload.full_name);
            body.set('is_admin', String(payload.is_admin));
            body.set('is_prof', String(payload.is_prof));
            body.set('is_student', String(payload.is_student));
            body.set('is_security_desk', String(payload.is_security_desk));
            body.set('password', payload.password);

            const res = await fetch('./faceProvisionUser.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: body.toString()
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || data.ok === false) {
                throw new Error(data.message || 'Could not create the user profile for enrollment.');
            }
            return data;
        };

        const lookupUser = async (personId) => {
            const res = await fetch(
                './faceUserLookup.php?username=' + encodeURIComponent(personId) + '&t=' + Date.now(),
                { cache: 'no-store' }
            );
            const body = await res.json().catch(() => ({}));
            if (!res.ok || body.ok === false) {
                throw new Error(body.message || 'Could not verify whether the user already exists.');
            }
            return body;
        };

        const scheduleStatusPoll = () => {
            clearStatusPoll();
            if (!isCapturing || !activeCapturePersonId) {
                return;
            }
            statusPollTimer = window.setTimeout(pollCaptureStatus, 350);
        };

        const pollCaptureStatus = async () => {
            if (!isCapturing || !activeCapturePersonId || statusPollInFlight) {
                return;
            }
            statusPollInFlight = true;
            try {
                const res = await fetch(
                    'http://debianRy.local:5000/capture_status?person_id=' +
                    encodeURIComponent(activeCapturePersonId) +
                    '&t=' + Date.now(),
                    { cache: 'no-store' }
                );
                const data = await res.json();
                const status = data.status || 'idle';
                if (data.message) {
                    statusEl.textContent = data.message;
                } else if (status === 'capturing') {
                    statusEl.textContent = 'Capturing face samples...';
                }
                if (status === 'completed') {
                    await stopCapture({ notifyBackend: false, message: data.message || 'Capture complete. Retraining started.' });
                    return;
                }
                if (status === 'stopped' || status === 'error') {
                    await stopCapture({ notifyBackend: false, message: data.message || 'Capture stopped.' });
                    return;
                }
            } catch (e) {
                statusEl.textContent = 'Checking capture progress...';
            } finally {
                statusPollInFlight = false;
            }
            scheduleStatusPoll();
        };

        flowCreate.addEventListener('change', syncEnrollFlowUi);
        flowExisting.addEventListener('change', syncEnrollFlowUi);
        roleAdmin.addEventListener('change', syncPasswordRequirementUi);
        roleProf.addEventListener('change', syncPasswordRequirementUi);
        roleStudent.addEventListener('change', syncPasswordRequirementUi);
        roleSecurityDesk.addEventListener('change', syncPasswordRequirementUi);
        syncEnrollFlowUi();

        btn.addEventListener('click', async () => {
            const personId = idInput.value.trim();
            if (!isCapturing) {
                if (!personId) {
                    alert('Please enter a username before starting enrollment.');
                    return;
                }
                if (!personIdPattern.test(personId)) {
                    alert('Username must be 3-40 chars and only letters, numbers, underscore.');
                    return;
                }
                if (!consentOptIn.checked) {
                    alert('Opt-in consent is required before enrollment.');
                    return;
                }

                const flow = getEnrollFlow();
                let fullName = fullNameInput.value.trim();
                const portalPassword = passwordInput.value;

                if (flow === 'create') {
                    if (!fullName) {
                        alert('Full name is required when creating a new user.');
                        return;
                    }
                    const roles = getSelectedRoles();
                    if (!hasAtLeastOneRole(roles)) {
                        alert('Select at least one role or choose Security Desk.');
                        return;
                    }
                    if (roles.is_security_desk === 1 && (roles.is_admin === 1 || roles.is_prof === 1)) {
                        alert('Security desk users cannot also be Admin or Professor.');
                        return;
                    }
                    if (requiresPortalPassword(roles) && portalPassword.trim() === '') {
                        alert('Password is required for Admin, Professor, or Security Desk users.');
                        return;
                    }
                }

                btn.textContent = 'Starting Enrollment...';
                try {
                    if (flow === 'create') {
                        const roles = getSelectedRoles();
                        statusEl.textContent = 'Creating user profile...';
                        const created = await provisionNewUser({
                            username: personId,
                            full_name: fullName,
                            password: portalPassword,
                            ...roles
                        });
                        fullName = created.full_name || fullName;
                        statusEl.textContent = created.message || 'User created. Starting facial capture...';
                    } else {
                        statusEl.textContent = 'Validating existing user...';
                        const lookup = await lookupUser(personId);
                        if (!lookup.exists) {
                            throw new Error('User was not found. Switch to "Create new user + face data" if needed.');
                        }
                        if (!fullName) {
                            fullName = lookup.full_name || '';
                        }
                        statusEl.textContent = lookup.message || 'Starting facial capture...';
                    }

                    activeCapturePersonId = personId;
                    await stopRecognitionFeed();
                    imgElement = document.createElement('img');
                    imgElement.src = 'http://debianRy.local:5000/video_feed?person_id=' + encodeURIComponent(personId) + '&full_name=' + encodeURIComponent(fullName);
                    imgElement.style = 'width:100%; height:100%; object-fit:cover; display:block; border-radius:12px;';
                    imgElement.onerror = () => {
                        if (isCapturing) {
                            scheduleStatusPoll();
                        }
                    };
                    box.innerHTML = '';
                    box.appendChild(imgElement);
                    btn.textContent = 'Stop Enrollment';
                    isCapturing = true;
                    setSetupInputsDisabled(true);
                    scheduleStatusPoll();
                } catch (err) {
                    btn.textContent = 'Start Enrollment';
                    statusEl.textContent = err && err.message ? err.message : 'Could not start capture.';
                    activeCapturePersonId = '';
                    alert(statusEl.textContent);
                }
            } else {
                await stopCapture();
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

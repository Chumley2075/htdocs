<?php
session_start();
header('Content-Type: application/json');

require_once '../database.php';

$db = new database();
$db->ensureAdminTables();

if (!isset($_SESSION['valid_user']) || !$db->isAdmin($_SESSION['valid_user'])) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'message' => 'Unauthorized',
    ]);
    exit;
}

$perm = $db->getUserPermissions($_SESSION['valid_user']);
$isAdmin = $db->isAdmin($_SESSION['valid_user']);
$canManageFaces = ((int)$perm['can_manage_faces'] === 1) || $isAdmin;
$canManageUsers = ((int)$perm['can_manage_users'] === 1) || $isAdmin;
if (!$canManageFaces || !$canManageUsers) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'message' => 'Permission denied',
    ]);
    exit;
}

$username = trim((string)(isset($_POST['username']) ? $_POST['username'] : ''));
$fullName = trim((string)(isset($_POST['full_name']) ? $_POST['full_name'] : ''));
$passwordRaw = isset($_POST['password']) ? (string)$_POST['password'] : '';
$passwordProvided = trim($passwordRaw) !== '';

$isAdminRole = isset($_POST['is_admin']) ? (int)$_POST['is_admin'] : 0;
$isProfRole = isset($_POST['is_prof']) ? (int)$_POST['is_prof'] : 0;
$isStudentRole = isset($_POST['is_student']) ? (int)$_POST['is_student'] : 0;
$isSecurityDeskRole = isset($_POST['is_security_desk']) ? (int)$_POST['is_security_desk'] : 0;

if ($username === '' || preg_match('/^[A-Za-z0-9_]{3,40}$/', $username) !== 1) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'message' => 'Username must be 3-40 chars and only letters, numbers, underscore.',
    ]);
    exit;
}

if ($fullName === '' || strlen($fullName) > 100) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'message' => 'Full name is required and must be 100 characters or less.',
    ]);
    exit;
}

if ($isSecurityDeskRole === 1 && ($isAdminRole === 1 || $isProfRole === 1)) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'message' => 'Security desk users cannot also be Admin or Professor.',
    ]);
    exit;
}

if (($isAdminRole === 1 || $isProfRole === 1 || $isSecurityDeskRole === 1) && !$passwordProvided) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'message' => 'Password is required for Admin, Professor, or Security Desk users.',
    ]);
    exit;
}

if (($isAdminRole + $isProfRole + $isStudentRole) === 0 && $isSecurityDeskRole !== 1) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'message' => 'Select at least one role or choose Security Desk.',
    ]);
    exit;
}

$existing = $db->findUserByUsername($username);
if (is_array($existing)) {
    http_response_code(409);
    echo json_encode([
        'ok' => false,
        'message' => 'Username already exists. Use existing-user enrollment flow.',
    ]);
    exit;
}

$passwordHash = $passwordProvided ? password_hash($passwordRaw, PASSWORD_DEFAULT) : '';
$created = $db->createUser($username, $fullName, $passwordHash, $isProfRole ? 1 : 0, $isAdminRole ? 1 : 0, $isStudentRole ? 1 : 0);
if ($created === 'Error') {
    http_response_code(409);
    echo json_encode([
        'ok' => false,
        'message' => 'Username already exists.',
    ]);
    exit;
}
if ($created !== true) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Could not create user profile.',
    ]);
    exit;
}

if ($isSecurityDeskRole === 1) {
    $db->updateUserRolesAndPermissions(
        $username,
        $isProfRole ? 1 : 0,
        $isAdminRole ? 1 : 0,
        $isStudentRole ? 1 : 0,
        0,
        0,
        1,
        1
    );
}

$db->logAdminEvent(
    $_SESSION['valid_user'],
    'face_onboard_user_created',
    $username,
    'Created via face onboarding; roles(admin=' . ($isAdminRole ? 1 : 0) .
    ',prof=' . ($isProfRole ? 1 : 0) .
    ',student=' . ($isStudentRole ? 1 : 0) .
    ',security_desk=' . ($isSecurityDeskRole ? 1 : 0) . ')'
);

echo json_encode([
    'ok' => true,
    'username' => $username,
    'full_name' => $fullName,
    'message' => 'Created user profile. Starting facial enrollment...',
]);

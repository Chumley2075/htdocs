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
$canManageFaces = ((int)$perm['can_manage_faces'] === 1) || $db->isAdmin($_SESSION['valid_user']);
if (!$canManageFaces) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'message' => 'Permission denied',
    ]);
    exit;
}

$username = trim((string)(
    isset($_REQUEST['username']) ? $_REQUEST['username'] : (
        isset($_REQUEST['person_id']) ? $_REQUEST['person_id'] : ''
    )
));

if ($username === '') {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'message' => 'Username is required.',
    ]);
    exit;
}

if (!preg_match('/^[A-Za-z0-9_]{3,40}$/', $username)) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'message' => 'Username must be 3-40 chars and only letters, numbers, underscore.',
    ]);
    exit;
}

$user = $db->findUserByUsername($username);
$exists = is_array($user);

echo json_encode([
    'ok' => true,
    'exists' => $exists,
    'username' => $username,
    'full_name' => $exists && isset($user['full_name']) ? (string)$user['full_name'] : '',
    'message' => $exists
        ? 'Found existing account. Facial data will be added to it.'
        : 'No existing account found. Capture will create the account and add facial data.',
]);

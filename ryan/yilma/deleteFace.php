<?php
session_start();
header('Content-Type: text/plain');

require_once '../database.php';
$db = new database();
$db->ensureAdminTables();

if (!isset($_SESSION['valid_user']) || !$db->isAdmin($_SESSION['valid_user'])) {
    http_response_code(403);
    echo 'Unauthorized';
    exit;
}

$perm = $db->getUserPermissions($_SESSION['valid_user']);
$canManageFaces = ((int)$perm['can_manage_faces'] === 1) || $db->isAdmin($_SESSION['valid_user']);
if (!$canManageFaces) {
    http_response_code(403);
    echo 'Permission denied';
    exit;
}

if (!isset($_POST['user_id'])) {
    http_response_code(400);
    echo 'Missing user_id';
    exit;
}

$user_id_raw = trim($_POST['user_id']);
if ($user_id_raw === '') {
    http_response_code(400);
    echo 'Empty user_id';
    exit;
}

$python = '/var/www//py311env/bin/python3.11';
$script = '/var/www/html/htdocs/ryan/yilma/deleteFace.py';
$cmd = 'sudo ' . $python . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($user_id_raw);

$output = [];
$return_code = 0;
exec($cmd . ' 2>&1', $output, $return_code);
$body = implode("\n", $output);

$actor = $_SESSION['valid_user'];
$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;

if ($return_code === 0) {
    $db->logAdminEvent($actor, 'face_deleted', $user_id_raw, 'Deleted face data and retraining started', $ip);
    echo "Deleted and retraining started.\n" . $body . "\n";
    exit;
}

if ($return_code === 1) {
    $db->logAdminEvent($actor, 'face_delete_failed', $user_id_raw, 'No exact face directory match', $ip);
    http_response_code(404);
    echo "No exact match for user directory. Case-sensitive.\n" . $body . "\n";
    exit;
}

$db->logAdminEvent($actor, 'face_delete_failed', $user_id_raw, 'Deletion script error', $ip);
http_response_code(500);
echo "Error during deletion.\n" . $body . "\n";

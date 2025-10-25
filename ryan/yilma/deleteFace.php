<?php
// Expects POST: user_id
// Runs deleteFace.py which handles deletion + starts retraining itself.

header('Content-Type: text/plain');


if (!isset($_POST['user_id'])) {
    http_response_code(400);
    echo "Missing user_id";
    exit;
}

$user_id_raw = trim($_POST['user_id']);
if ($user_id_raw === '') {
    http_response_code(400);
    echo "Empty user_id";
    exit;
}

// ====== PATHS: adjust if yours differ ======
$python = "/var/www//py311env/bin/python3.11";  // your venv's python
$script = "/var/www/html/htdocs/ryan/yilma/deleteFace.py";
// ===========================================

// Build a safe command (no shell globbing)
$cmd = $python . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($user_id_raw);

// Run and capture output
$output = [];
$return_code = 0;
exec($cmd . " 2>&1", $output, $return_code);

// Friendly response
$body = implode("\n", $output);

if ($return_code === 0) {
    // Deletion succeeded; trainer launched by Python
    echo "? Deleted and retraining started.\n" . $body . "\n";
    exit;
}
if ($return_code === 1) {
    // Nothing deleted (no exact match), do not retrain
    http_response_code(404);
    echo "No exact match for user directory. Case-sensitive.\n" . $body . "\n";
    exit;
}
// return_code 2 or others = error (permissions, path, etc.)
http_response_code(500);
echo "? Error during deletion.\n" . $body . "\n";

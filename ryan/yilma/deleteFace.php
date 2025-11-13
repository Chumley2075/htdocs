<?php

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


$python = "/var/www//py311env/bin/python3.11";  
$script = "/var/www/html/htdocs/ryan/yilma/deleteFace.py";



$cmd = 'sudo ' . $python . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($user_id_raw);


$output = [];
$return_code = 0;
exec($cmd . " 2>&1", $output, $return_code);


$body = implode("\n", $output);

if ($return_code === 0) {
    
    echo "Deleted and retraining started.\n" . $body . "\n";
    exit;
}
if ($return_code === 1) {
    
    http_response_code(404);
    echo "No exact match for user directory. Case-sensitive.\n" . $body . "\n";
    exit;
}

http_response_code(500);
echo "Error during deletion.\n" . $body . "\n";

<?php
require_once './database.php';
$DB = new database();

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$room = isset($_GET['room']) ? preg_replace('/[^A-Za-z0-9_-]/','', $_GET['room']) : '';

$ctx  = stream_context_create(['http' => ['timeout' => 1.5]]);
$name = trim(@file_get_contents('http://debianRy.local:5001/label', false, $ctx));
if ($name === '' || strcasecmp($name, 'Unknown') === 0) {
    $name = 'Unknown';
}

if ($name !== 'Unknown') {
    $classId = $DB->getCurrentClassID($room);
    $DB->insertAttendance($classId, $name);
}

echo $name;

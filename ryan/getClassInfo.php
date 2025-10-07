<?php
header('Content-Type: application/json');



require_once './database.php';
$fullInfoArray = array();
$DB = new database();
$roomNumber = isset($_GET['room']) ? intval($_GET['room']) : 115;

$currentClassId = $DB->getCurrentClassID($roomNumber);
if($currentClassId === null) {
    $fullInfoArray[] = "No class";  
    echo json_encode($fullInfoArray);
    exit;
}
$currentClassName = $DB->getClassName($currentClassId);

//$fullInfoArray[] = $currentClassId;
$fullInfoArray[] = $currentClassName;
echo json_encode($fullInfoArray);
?>
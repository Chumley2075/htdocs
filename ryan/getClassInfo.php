<?php
header('Content-Type: application/json');



require_once './database.php';
$fullInfoArray = array();
$DB = new database();
$roomNumber = isset($_GET['room']) ? intval($_GET['room']) : null;

$currentClassId = $DB->getCurrentClassID($roomNumber);
if($currentClassId === null) {
    $fullInfoArray["className"] = "No class";  
    $fullInfoArray["status"] = "Available";  
    $fullInfoArray["window"] = "Open";
    
    echo json_encode($fullInfoArray);
    exit;
}
$currentClassName = $DB->getClassName($currentClassId);
$startTime = date("g:i A", strtotime($startTime = $DB->getClassStartTime($currentClassId)));
$endTime = date("g:i A", strtotime($endTime = $DB->getClassEndTime($currentClassId)));
$endTime24 = intval($DB->getClassEndTime($currentClassId));


$window = $startTime."-".$endTime;
//$fullInfoArray[] = $currentClassId;
$fullInfoArray["className"] = $currentClassName;
$fullInfoArray["status"] = "In-Session";  

$fullInfoArray["window"] = $window;
$fullInfoArray["endsAt"] = $endTime;
$fullInfoArray["endsAt24"] = $endTime24;


echo json_encode($fullInfoArray);

?>
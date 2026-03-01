<?php
header('Content-Type: application/json');



require_once './database.php';
$fullInfoArray = array();
$DB = new database();
$roomNumber = isset($_GET['room']) ? intval($_GET['room']) : null;

$currentClassId = $DB->getCurrentClassID($roomNumber);
if($currentClassId === null) {
    $fullInfoArray["className"] = "No class";  
    $fullInfoArray["nextClass"] = "No class";
    $fullInfoArray["status"] = "Available";  
    $fullInfoArray["window"] = "Open";
    $fullInfoArray["hideEndsIn"] = true;
    echo json_encode($fullInfoArray);
    exit;
}
$currentClassName = $DB->getClassName($currentClassId);
$startTime = date("g:i A", strtotime($startTime = $DB->getClassStartTime($currentClassId)));
$endTime = date("g:i A", strtotime($endTime = $DB->getClassEndTime($currentClassId)));
$endTime24 = intval($DB->getClassEndTime($currentClassId));

$nextClassID = $DB->getNextClassID($roomNumber);

$nextClassName = $DB->getClassName($nextClassID);


$window = $startTime."-".$endTime;
//$fullInfoArray[] = $currentClassId;
$fullInfoArray["className"] = $currentClassName;
$fullInfoArray["nextClass"] = $nextClassName;
$fullInfoArray["status"] = "In-Session";  
$fullInfoArray["hideEndsIn"] = false;

$fullInfoArray["window"] = $window;
$fullInfoArray["endsAt"] = $endTime;
$fullInfoArray["endsAt24"] = $endTime24;


echo json_encode($fullInfoArray);

?>
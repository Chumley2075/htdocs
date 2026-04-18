<?php
require_once './database.php';
$DB = new database();

header('Cache-Control: no-store');

$room = isset($_GET['room']) ? preg_replace('/[^A-Za-z0-9_-]/','', $_GET['room']) : '';

function parseFaceLabels($providedName)
{
    $rawLabels = array_filter(array_map('trim', explode(',', (string)$providedName)), function ($label) {
        return $label !== '';
    });
    $hasUnknown = false;
    $knownLabels = [];
    foreach ($rawLabels as $rawLabel) {
        if (strcasecmp($rawLabel, 'Unknown') === 0) {
            $hasUnknown = true;
            continue;
        }
        $knownLabels[] = $rawLabel;
    }
    return [
        'known' => array_values(array_unique($knownLabels)),
        'has_unknown' => $hasUnknown,
    ];
}

function getLatestScanImageForLabel($username)
{
    $username = trim((string)$username);
    if ($username === '' || strcasecmp($username, 'Unknown') === 0) {
        return '';
    }
    $ctx = stream_context_create(['http' => ['timeout' => 1.2]]);
    $raw = @file_get_contents('http://debianRy.local:5001/scan_result?t=' . time(), false, $ctx);
    if ($raw === false || $raw === '') {
        return '';
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['status']) || (string)$data['status'] !== 'matched') {
        return '';
    }
    $scanLabel = isset($data['label']) ? (string)$data['label'] : '';
    $labels = array_filter(array_map('trim', explode(',', $scanLabel)), function ($label) {
        return $label !== '';
    });
    $matched = false;
    foreach ($labels as $label) {
        if (strcasecmp($label, $username) === 0) {
            $matched = true;
            break;
        }
    }
    if (!$matched) {
        return '';
    }
    $imageUrl = isset($data['image_url']) ? trim((string)$data['image_url']) : '';
    return $imageUrl;
}

if (isset($_GET['action']) && $_GET['action'] === 'can_enter') {
    header('Content-Type: application/json; charset=utf-8');
    $providedName = isset($_REQUEST['label']) ? (string)$_REQUEST['label'] : '';
    $parsed = parseFaceLabels($providedName);
    $labels = $parsed['known'];
    $hasUnknown = $parsed['has_unknown'];

    if ($hasUnknown || count($labels) !== 1) {
        echo json_encode([
            'ok' => true,
            'can_enter' => false,
            'message' => 'No face match found.'
        ]);
        exit;
    }

    $label = reset($labels);
    $access = $DB->canUserEnterRoom($room, $label);

    echo json_encode([
        'ok' => true,
        'can_enter' => !empty($access['can_enter']),
        'message' => isset($access['message']) ? (string)$access['message'] : '',
        'access_role' => isset($access['access_role']) ? (string)$access['access_role'] : ''
    ]);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

$providedName = isset($_REQUEST['label']) ? trim((string)$_REQUEST['label']) : '';
$name = $providedName;
if ($name === '') {
    $ctx  = stream_context_create(['http' => ['timeout' => 1.5]]);
    $name = trim(@file_get_contents('http://debianRy.local:5001/label', false, $ctx));
}
if ($name === '' || strcasecmp($name, 'Unknown') === 0) {
    $name = 'Unknown';
}

if ($name !== 'Unknown') {
    $parsed = parseFaceLabels($name);
    $labels = $parsed['known'];
    foreach ($labels as $label) {
        $access = $DB->canUserEnterRoom($room, $label);
        $doorRole = isset($access['access_role']) ? (string)$access['access_role'] : '';
        if ($doorRole === 'admin' || $doorRole === 'security_desk') {
            $scanImagePath = getLatestScanImageForLabel($label);
            if ($scanImagePath === '') {
                $scanImagePath = $DB->getLatestFaceScanImagePath($label, 300);
            }
            $roleText = $doorRole === 'admin' ? 'Admin' : 'Security Desk';
            $roomText = $room !== '' ? $room : 'unknown';
            $DB->logAdminEvent(
                $label,
                'door_opened_by_privileged_face',
                $label,
                'Door ' . $roomText . ' opened by ' . $roleText . ' via face scan',
                $scanImagePath
            );
            continue;
        }

        if (!empty($access['can_enter']) && !empty($access['class_id'])) {
            $DB->insertAttendance((int)$access['class_id'], $label);
        }
    }
}

echo $name;

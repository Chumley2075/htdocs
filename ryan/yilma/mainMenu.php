<?php
session_start();
require_once '../database.php';

$db = new database();
if (!isset($_SESSION['valid_user'])) {
    header("Location: ./index.php");
    exit();
}
if (method_exists($db->connection, 'set_charset')) {
    $db->connection->set_charset('utf8mb4');
}

$selectedClassId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$classes = $db->getClassList($_SESSION['valid_user']);
$profName = $db->getUserInfo($_SESSION['valid_user'], 'full_name');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Metro State University | Biometric System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="style.css">
<script>
window.onload = function() {
    document.getElementById('dashTitle').innerText =
        "Welcome Professor <?php echo htmlspecialchars($profName, ENT_QUOTES, 'UTF-8'); ?>";
}
</script>
</head>
<body>
<div class="bg-animation">
    <div class="bg-shape shape-1"></div>
    <div class="bg-shape shape-2"></div>
    <div class="bg-shape shape-3"></div>
</div>

<div class="dashboard-buttons">
    <a href="admin.php" class="btn">Admin Page</a>
    <a href="logout.php" class="btn logout">Logout</a>
</div>

<div id="dashboard">
    <h2 id="dashTitle"></h2>

    <form method="get" style="margin:0">
        <div class="class-selector">
            <select id="classSelect" name="class_id" onchange="this.form.submit()">
                <option value="">Select a Class</option>
                <?php
                foreach ($classes as $row) {
                    $cid = (int)$row['class_id'];
                    $cname = htmlspecialchars(isset($row['class_name']) ? $row['class_name'] : ("Class " . $cid), ENT_QUOTES, 'UTF-8');
                    $sel = ($cid === $selectedClassId) ? ' selected' : '';
                    echo '<option value="' . $cid . '"' . $sel . '>' . $cname . '</option>';
                }
                ?>
            </select>
        </div>
    </form>

    <?php
    if ($selectedClassId > 0) {
        $className = $db->getClassName($selectedClassId);
        if ($className === null) {
            $className = "Class " . $selectedClassId;
        }
        $start = $db->getClassStartTime($selectedClassId);
        $end   = $db->getClassEndTime($selectedClassId);
        if (!empty($start) && !empty($end)) {
            $timeStr = date('g:i A', strtotime($start)) . ' - ' . date('g:i A', strtotime($end));
        } else {
            $timeStr = 'No meeting scheduled today';
        }
        $rows = $db->getRosterWithAttendanceToday($selectedClassId);
        ?>
        <div class="class-info" id="classInfo">
            <p><strong id="className"><?php echo htmlspecialchars($className, ENT_QUOTES, 'UTF-8'); ?></strong></p>
            <p>Time: <span id="classTime"><?php echo $timeStr; ?></span> | Professor: <span id="classProfessor"><?php echo htmlspecialchars($profName, ENT_QUOTES, 'UTF-8'); ?></span></p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Scan Time</th>
                </tr>
            </thead>
            <tbody id="data">
                <?php
                if (!$rows || count($rows) === 0) {
                    echo '<tr><td colspan="4" style="text-align:center;opacity:.7">No enrolled students</td></tr>';
                } else {
                    foreach ($rows as $r) {
                        $sid = htmlspecialchars($r['student_username'], ENT_QUOTES, 'UTF-8');
                        $name = htmlspecialchars(isset($r['full_name']) ? $r['full_name'] : $sid, ENT_QUOTES, 'UTF-8');
                        if (!empty($r['scanned_at'])) {
                            $status = 'Present';
                            $scanned = date('Y-m-d g:i:s A', strtotime($r['scanned_at']));
                        } else {
                            $status = 'Absent';
                            $scanned = '-';
                        }
                        echo '<tr>';
                        echo '<td>' . $sid . '</td>';
                        echo '<td>' . $name . '</td>';
                        echo '<td>' . $status . '</td>';
                        echo '<td>' . $scanned . '</td>';
                        echo '</tr>';
                    }
                }
                ?>
            </tbody>
        </table>
        <?php
    } else {
        ?>
        <div class="class-info" id="classInfo">
            <p><strong id="className">-</strong></p>
            <p>Time: <span id="classTime">-</span> | Professor: <span id="classProfessor"><?php echo htmlspecialchars($profName, ENT_QUOTES, 'UTF-8'); ?></span></p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Scan Time</th>
                </tr>
            </thead>
            <tbody id="data">
                <tr><td colspan="4" style="text-align:center;opacity:.7">Select a class</td></tr>
            </tbody>
        </table>
        <?php
    }
    ?>
</div>
</body>
</html>

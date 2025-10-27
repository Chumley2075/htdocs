<?php
session_start();
require_once '../database.php';

$db = new database();
if (!isset($_SESSION['valid_user'])) {
    header("Location: ./index.php");
    exit();
}

$DB = new database();

?>
<script>
    window.onload = function() {
        document.getElementById('dashTitle').innerText = "Welcome Professor <?php echo $db->getUserInfo($_SESSION['valid_user'], "full_name"); ?>";

    }
</script>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Metro State University | Biometric System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    
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
        <!-- Dashboard -->
        <div id="dashboard">
            <h2 id="dashTitle"></h2>
            
            <div class="class-selector">
                <select id="classSelect">
                    <option value="">Select a Class</option>
                    <?php
                     $classes = $db->getClasses($_SESSION['valid_user']);
                      foreach ($classes as $row) {
                          $classID = (int)$row['class_id'];
                          $classTitle = htmlspecialchars($row['class_name'], ENT_QUOTES, 'UTF-8'); 
                          echo "<option value=\"$classID\">$classTitle</option>";
                      }
            ?>
                </select>
            </div>

            <div class="class-info" id="classInfo">
                <p><strong id="className">-</strong></p>
                <p>Time: <span id="classTime">-</span> | Professor: <span id="classProfessor">-</span></p>
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
                <tbody id="data"></tbody>
            </table>
        </div>
    </div>


</body>
</html>

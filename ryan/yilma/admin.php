<?php
session_start();
require_once '../database.php';

$db = new database();
if (!isset($_SESSION['valid_user'])) {
    header("Location: ./index.php");
    exit();
} else if (!($db->isAdmin($_SESSION['valid_user']))) {
    header("Location: ./mainMenu.php");
    exit();
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $username  = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $password  = trim($_POST['password']);
    if(!empty($password)){
      $passwordTemp = password_hash($password, PASSWORD_DEFAULT);
      $password = $passwordTemp;
    
    } else{
    
    $password =null;
    }

    $is_prof    = isset($_POST['is_prof']) ? 1 : 0;
    $is_admin   = isset($_POST['is_admin']) ? 1 : 0;
    $is_student = isset($_POST['is_student']) ? 1 : 0;
    $result = $db->createUser($username, $full_name, $password, $is_prof, $is_admin, $is_student);
    if($result === "Error"){
    echo"error has occured";
}else if($result){
   header("Location: ./admin.php");
    exit();
}
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Admin Page</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">


</head>

<body>
    <div class="dashboard-buttons">
        <a href="logout.php" class="btn logout">Logout</a>
    </div>

    <div class="admin-wrap">
        <h2 id="dashTitle">
            Welcome Administrator <?php echo htmlspecialchars($db->getUserInfo($_SESSION['valid_user'], "full_name")); ?>
        </h2>

        <div class="two-col">
            <!-- LEFT PANEL: Create User -->
            <div class="panel panel-left">
                <div class="form-panel">
                    <h3>Create User</h3>
                    <form method="POST">
                        <label>Username:</label>
                        <input type="text" name="username" pattern="[A-Za-z0-9]+" required>

                        <label>Full Name:</label>
                        <input type="text" name="full_name" required>

                        <label>Password (optional):</label>
                        <input type="password" name="password">

                        <label>Roles:</label>
                        <div class="checkbox-group">
                            <label><input type="checkbox" name="is_prof"> Professor</label>
                            <label><input type="checkbox" name="is_admin"> Admin</label>
                            <label><input type="checkbox" name="is_student"> Student</label>
                        </div>

                        <button type="submit" name="create_user" class="create-btn">Create User</button>
                    </form>
                    <div class="message"><?php echo $message; ?></div>
                </div>
            </div>

            
           <div class="panel panel-right">
    <button class="video-btn" id="btnRight" style="display:block;margin-left: 25%;">Start Capture</button>
    <div class="video-box" id="videoRight">
        <span id="videoPlaceholder">Face capture feed will appear here</span>
    </div>
</div>

<script>
document.getElementById('btnRight').addEventListener('click', () => {
  const box = document.getElementById('videoRight');
  box.innerHTML = `
    <img src="http://raspberrypi.local:5000/video_feed"
         style="width:100%; height:100%; object-fit:cover; display:block; border-radius:12px;" />
  `;
});
</script>
        </div>
    </div>
</body>
</html>

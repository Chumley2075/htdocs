<?php
session_start();
require_once '../database.php';

$db = new database();
if (!isset($_SESSION['valid_user'])) {
    header("Location: ./index.php");
    exit();
} else if ($db->isAdmin($_SESSION['valid_user'])) {
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

    <style>
        body { color: #0f172a; }
        #dashTitle { color: #fff; }
        .form-panel {
            background: #ffffff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            color: #1f2937;
        }
        .form-panel h3 { margin-bottom: 10px; color: #1f2937; }
        .form-panel label { font-weight: 600; color: #1f2937; }
        .form-panel input,
        .form-panel select {
            width: 100%;
            padding: 8px;
            margin: 8px 0 14px;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            color: #111827;
            background: #fff;
        }
        .form-panel input:focus,
        .form-panel select:focus {
            outline: none;
            border-color: #3a86ff;
            box-shadow: 0 0 0 3px rgba(58,134,255,.15);
        }
        .checkbox-group {
            display: flex;
            gap: 16px;
            margin-bottom: 14px;
        }
        .checkbox-group label {
            font-weight: normal;
            color: #1f2937;
        }
        .checkbox-group input {
            transform: scale(1.2);
            margin-right: 6px;
        }
        button.create-btn {
            width: 100%;
            padding: 10px;
            background: #007BFF;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
        button.create-btn:hover { background: #0056b3; }
        .video-btn {
            padding: 12px 18px;
            background: #3a86ff;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            cursor: pointer;
            transition: transform .06s ease, box-shadow .2s ease, background .2s ease;
            box-shadow: 0 6px 14px rgba(0,0,0,.12);
            width: fit-content; 
        }
        .video-btn:hover { background: #2f6fce; }
        .video-btn:active { transform: translateY(1px); }
        .video-box {
            background: #000;
            border: 3px solid #555;
            border-radius: 12px;
            overflow: hidden;
            aspect-ratio: 16 / 9;    
            display: grid;
            place-items: center;
            width: 100%;
        }
        .video-box span { color: #e5e7eb; }
        .message { margin-top: 10px; font-weight: 500; }
    </style>
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
                <button class="video-btn" id="btnRight">Start Capture</button>
                <div class="video-box" id="videoRight">
                    <span>Face capture feed will appear here</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

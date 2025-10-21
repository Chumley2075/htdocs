<?php
require_once '../database.php';
session_start();
session_unset();
session_destroy();
session_start();
$DB = new database();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'];
    $password = $_POST['password'];
    $passwordHash = $DB->getHashedPass($user);
    if ($DB->userExist($user)) {
        if (password_verify($password, $passwordHash)) {
            $_SESSION['valid_user'] = $user;
            header("Location: mainMenu.php");
        } else {
            echo "<p style = 'color: red;'>Could not log you in! Incorrect password.</p>";
        }
    } else {
        echo "<p>Could not log you in! Username does not exist.</p>";
    }
}
?>
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


    <!-- Main Content -->
    <div class="container">
        <!-- Login -->
        <div class="login-container" id="login">
            <h2>Welcome to Metro State University</h2>
            <h3>Professor / Administrator Portal</h3>
        <form method='post'>
        <label>
            Username:
        </label>
        <input type='text' name='username' placeholder='Enter your Username' required>
        <br>
        <label>
            Password:
        </label>
        <input type='password' name='password' placeholder='Enter your Password' required>
        <button type='submit' style='margin-bottom: 20px;'>
            Submit
        </button>
        <!-- <p style="color:black; font-weight:bold">For sign up: <a href="phpPages/createAccount.php"> click here </a> -->
</form>

        </div>

       
    </div>


</body>
</html>
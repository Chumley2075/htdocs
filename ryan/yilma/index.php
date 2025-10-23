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
    <title>Biometric System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
  /* Page background (simple gradient, no animation) */
  body {
    margin: 0;
    font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
    background: linear-gradient(135deg, #0f2b8c, #2446c0 60%, #3759d9);
    color: #0f172a;
  }


  .container {
    min-height: 100vh;
    display: grid;
    place-items: center;
    padding: 24px;
  }


  .login-container {
    width: 100%;
    max-width: 420px;         
    background: #ffffff;
    border-radius: 14px;
    padding: 22px 22px 18px;
    box-shadow: 0 12px 30px rgba(0,0,0,.18);
    color: #111827;
  }

  .login-container h2 {
    margin: 0 0 6px;
    font-size: 1.6rem;
    color: #0b1f63;
    font-weight: 800;
  }
  .login-container h3 {
    margin: 0 0 18px;
    font-size: 1rem;
    color: #223266;
    font-weight: 700;
  }


  label {
    display: block;
    margin: 10px 0 6px;
    font-size: 0.95rem;
    font-weight: 600;
    color: #1f2937;
  }

  input[type="text"],
  input[type="password"] {
    width: 100%;
    height: 44px;           
    padding: 0 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #fff;
    color: #111827;
    outline: none;
  }
  input::placeholder { color: #6b7280; }

  input:focus {
    border-color: #3a86ff;
    box-shadow: 0 0 0 3px rgba(58,134,255,.15);
  }

  button[type="submit"] {
    width: 100%;
    margin-top: 16px;
    height: 44px;            
    border: 0;
    border-radius: 10px;
    font-weight: 700;
    color: #fff;
    background: #0a84ff;
    box-shadow: 0 8px 20px rgba(10,132,255,.25);
    cursor: pointer;
  }
  button[type="submit"]:hover { background: #0a74e6; }


</style>
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
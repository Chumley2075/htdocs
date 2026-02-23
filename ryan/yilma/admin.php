<?php
session_start();


$DEV_MODE = true;

$message = "";
$displayName = "Administrator (Mock)";

$db = null;

if (!$DEV_MODE) {
    require_once '../database.php';
    $db = new database();

    if (!isset($_SESSION['valid_user'])) {
        header("Location: ./index.php");
        exit();
    } else if (!$db->isAdmin($_SESSION['valid_user'])) {
        header("Location: ./mainMenu.php");
        exit();
    }

    $displayName = $db->getUserInfo($_SESSION['valid_user'], "full_name");

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
        $username  = trim($_POST['username']);
        $full_name = trim($_POST['full_name']);
        $password  = trim($_POST['password']);

        $password = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : null;

        $is_prof    = isset($_POST['is_prof']) ? 1 : 0;
        $is_admin   = isset($_POST['is_admin']) ? 1 : 0;
        $is_student = isset($_POST['is_student']) ? 1 : 0;

        $result = $db->createUser($username, $full_name, $password, $is_prof, $is_admin, $is_student);

        if ($result === "Error") {
            $message = "An error has occurred.";
        } else if ($result) {
            header("Location: ./admin.php");
            exit();
        }
    }
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $message = "DEV_MODE is ON: actions disabled (CSS preview only).";
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
        <?php if (!$DEV_MODE): ?>
            <a href="logout.php" class="btn logout">Logout</a>
        <?php else: ?>
            <a href="#" class="btn logout" onclick="return false;" aria-disabled="true"
               style="opacity:.6;pointer-events:none;">
               Logout (Disabled)
            </a>
        <?php endif; ?>
    </div>

    <div class="admin-wrap">
        <h2 id="dashTitle">
            Welcome Administrator <?php echo htmlspecialchars($displayName); ?>
        </h2>

        <div class="two-col">

            <div class="panel panel-left">
                <div class="form-panel">
                    <h3>Create User</h3>

                    <form method="POST">
                        <label>Username:</label>
                        <input type="text" name="username" pattern="[A-Za-z0-9]+" required
                               <?php echo $DEV_MODE ? 'disabled' : ''; ?>>

                        <label>Full Name:</label>
                        <input type="text" name="full_name" required
                               <?php echo $DEV_MODE ? 'disabled' : ''; ?>>

                        <label>Password (optional):</label>
                        <input type="password" name="password"
                               <?php echo $DEV_MODE ? 'disabled' : ''; ?>>

                        <label>Roles:</label>
                        <div class="checkbox-group">
                            <label><input type="checkbox" name="is_prof" <?php echo $DEV_MODE ? 'disabled' : ''; ?>> Professor</label>
                            <label><input type="checkbox" name="is_admin" <?php echo $DEV_MODE ? 'disabled' : ''; ?>> Admin</label>
                            <label><input type="checkbox" name="is_student" <?php echo $DEV_MODE ? 'disabled' : ''; ?>> Student</label>
                        </div>

                        <button type="submit" name="create_user" class="create-btn"
                                <?php echo $DEV_MODE ? 'disabled title="DEV_MODE: disabled"' : ''; ?>>
                            Create User
                        </button>
                    </form>

                    <div class="message"><?php echo htmlspecialchars($message); ?></div>
                </div>
            </div>

            <div class="panel panel-right">
                <h3>Face Capture</h3>
                <label for="personIdInput">Person ID:</label>
                <input type="text" id="personIdInput" name="person_id" placeholder="Enter ID or Name"
                       style="width:80%; padding:8px; border-radius:8px; border:1px solid #ccc; margin-bottom:10px;">

                <button class="video-btn" id="btnRight" style="display:block;margin:10px auto;">
                    Start Capture
                </button>

                <div class="video-box" id="videoRight">
                    <span id="videoPlaceholder">Face capture feed will appear here</span>
                </div>
            </div>

            <div class="panel panel-right">
                <h3>Delete Face Data</h3>

                <label for="deleteUserId">User ID:</label>
                <input type="text" id="deleteUserId" placeholder="Enter User ID"
                       style="width:80%; padding:8px; border-radius:8px; border:1px solid #ccc; margin-bottom:10px;">

                <button class="video-btn" id="deleteFaceBtn"
                        style="display:block;margin:10px auto; background:#d9534f;"
                        <?php echo $DEV_MODE ? 'disabled title="DEV_MODE: disabled"' : ''; ?>>
                    Delete Face Data
                </button>

                <?php if ($DEV_MODE): ?>
                    <div class="message" style="margin-top:10px; opacity:.8;">
                        DEV_MODE is ON: delete action disabled.
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <script>
    const btn = document.getElementById('btnRight');
    const box = document.getElementById('videoRight');
    const idInput = document.getElementById('personIdInput');
    let isCapturing = false;
    let imgElement = null;

    btn.addEventListener('click', () => {
      const personId = idInput.value.trim();
      if (!isCapturing) {
        if (!personId) {
          alert("Please enter a Person ID before starting capture.");
          return;
        }
        imgElement = document.createElement('img');
        imgElement.src = "http://debianRy.local:5000/video_feed?person_id=" + encodeURIComponent(personId);
        imgElement.style = "width:100%; height:100%; object-fit:cover; display:block; border-radius:12px;";
        box.innerHTML = "";
        box.appendChild(imgElement);
        btn.textContent = "Stop Capture";
        isCapturing = true;
      } else {
        if (imgElement) {
          imgElement.src = "";
          box.innerHTML = '<span id="videoPlaceholder">Face capture feed will appear here</span>';
        }
        btn.textContent = "Start Capture";
        isCapturing = false;
      }
    });
    </script>

    <script>
    document.getElementById("deleteFaceBtn").addEventListener("click", function() {
        // In DEV_MODE this button is disabled, but keep the guard anyway
        const userId = document.getElementById("deleteUserId").value.trim();
        if (!userId) {
            alert("Please enter a User ID.");
            return;
        }

        if (!confirm("Are you sure you want to delete face data for user: " + userId + "?")) {
            return;
        }

        fetch("/htdocs/ryan/yilma/deleteFace.php", {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: "user_id=" + encodeURIComponent(userId)
        })
        .then(res => res.text())
        .then(data => alert(data))
        .catch(err => alert("Error: " + err));
    });
    </script>
</body>
</html>
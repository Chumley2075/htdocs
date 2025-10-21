<?php
// make_hash.php - dev-only page. REMOVE or protect this on any public server!

// Helper: escape for HTML output
function h($s){ return htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

$plaintext = '';
$hash = '';
$verify_result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic input check (do NOT rely on this for security)
    $plaintext = isset($_POST['password']) ? trim((string)$_POST['password']) : '';

    if ($plaintext === '') {
        $error = "Please enter a password.";
    } else {
        // Create the hash (uses bcrypt/argon2 depending on PHP version and PASSWORD_DEFAULT)
        $hash = password_hash($plaintext, PASSWORD_DEFAULT);

        // Example verify immediately (just to show usage)
        // In a real app you would store $hash and verify later with password_verify($entered, $hash)
        $verify_result = password_verify($plaintext, $hash) ? 'OK (password_verify returned true)' : 'FAILED';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Dev: Make Password Hash</title>
<style>
  body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; padding: 20px; }
  input[type="password"]{ width: 320px; padding: 8px; }
  textarea{ width: 100%; height: 100px; }
  .warn { color: #8a1; background:#fff3cd; padding:10px; border-radius:6px; margin-bottom:12px; }
  .danger { color:#721c24; background:#f8d7da; padding:10px; border-radius:6px; margin-bottom:12px; }
</style>
</head>
<body>
  <h1>Dev: Create a Password Hash</h1>

  <div class="warn">
    <strong>Dev only:</strong> This page displays hashed passwords and should <em>not</em> run on a public production server.
    Remove or protect it after use.
  </div>

  <form method="post" novalidate>
    <label for="pw">Plaintext password</label><br>
    <input id="pw" name="password" type="password" value="<?= h($plaintext) ?>" required autocomplete="new-password">
    <button type="submit">Make hash</button>
  </form>

  <?php if (!empty($error)): ?>
    <div class="danger"><?= h($error) ?></div>
  <?php endif; ?>

  <?php if ($hash !== ''): ?>
    <h2>Result</h2>
    <p><strong>Hash (store this in your DB):</strong></p>
    <textarea readonly><?= h($hash) ?></textarea>

    <p><strong>Immediate verify test:</strong> <?= h($verify_result) ?></p>

    <h3>How to verify later (example)</h3>
    <pre>
/* Example PHP verification code (use this in your login handler) */
$entered = $_POST['password_from_login_form'];
// $stored_hash = fetched from DB for this username
if (password_verify($entered, $stored_hash)) {
    // success: correct password
} else {
    // invalid password
}
    </pre>
  <?php endif; ?>

  <hr>
  <small>Notes: password_hash() handles salt internally. Use <code>password_verify()</code> to check. PASSWORD_DEFAULT picks a safe algorithm for your PHP version.</small>
</body>
</html>

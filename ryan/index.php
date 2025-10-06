 <?
require_once './database.php';
$DB = new database();

?>
 <!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Classroom Display</title>
  <link rel="stylesheet" href="styles.css">
  <script defer src="classroom.js"></script>
  
</head>
<body>

<main>
  
  <div class="container">
    <section id="display" aria-labelledby="displayHeading" class="panel left">
      <h2 id="displayHeading">Room <span id="roomNumber"></span></h2>

      <div class="date-time">
        <div><strong>Date:</strong> <time id="dateOnly"></time></div>
        <div><strong>Time:</strong> <time id="timeOnly"></time></div>
      </div>

      <div class="details">
        <p><strong>Current class:</strong> <span id="currentClass"></span></p>
        <p><strong>Window:</strong> <span id="window"></span></p>
        <p class="status-row">
          <strong>Status:</strong>
          <span id="status" class="available"></span>
          <span class="ends">Ends: <time id="endsAt"></time></span>
        </p>
      </div>
    </section>

    <aside id="scanner" class="panel right" aria-label="Scanner">
      <button id="scanFace" class="big-scan" type="button">Scan Face</button>
    </aside>
  </div>
</main>

<footer>
</footer>

</body>
</html>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Classroom Display</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>

<main>
  <div class="container">
    <section id="display" aria-labelledby="displayHeading" class="panel left">
      <h2 id="displayHeading">Room <span id="roomNumber">115</span></h2>

      <div class="date-time">
        <div><strong>Date:</strong> <time id="dateOnly"></time></div>
        <div><strong>Time:</strong> <time id="timeOnly"></time></div>
      </div>

      <div class="details">
        <p><strong>Current class:</strong> <span id="currentClass">Loading...</span></p>
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

<script>
function updateClassInfo() {
    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            var fullInfo = JSON.parse(this.responseText);
            document.getElementById("currentClass").textContent = fullInfo[0];
            var now = new Date();
            document.getElementById("dateOnly").textContent = now.toLocaleDateString();
            document.getElementById("timeOnly").textContent = now.toLocaleTimeString();
        }
    };
    xhttp.open("GET", "getClassInfo.php?room=115", true);
    xhttp.send();
}

// Update immediately and then every second
updateClassInfo();
setInterval(updateClassInfo, 1000);
</script>

</body>
</html>
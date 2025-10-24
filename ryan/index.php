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
          
        </p>
        <p> <strong> Ends in:</strong> <span id = "endsAt"></span></p>

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
  xhttp.onreadystatechange = function () {
    if (this.readyState == 4 && this.status == 200) {
      var fullInfo = JSON.parse(this.responseText);

      document.getElementById("currentClass").textContent = fullInfo["className"];
      document.getElementById("status").textContent = fullInfo["status"];
      document.getElementById("window").textContent = fullInfo["window"];

      var now = new Date();
      document.getElementById("dateOnly").textContent = now.toLocaleDateString();
      document.getElementById("timeOnly").textContent = now.toLocaleTimeString();

      if (fullInfo["status"] === "In-Session") {
        document.getElementById("status").className = "in-session";
      } else {
        document.getElementById("status").className = "available";
      }

      if (fullInfo["hideEndsIn"]) {
        document.getElementById("endsAt").textContent = "N/A";
      } else {
        var end = new Date(now);

        var endHour = Math.floor(fullInfo["endsAt24"]);
        var endMin = Math.round((fullInfo["endsAt24"] % 1) * 60);

        end.setHours(endHour, endMin, 0, 0);

        if (end < now) {
          end.setDate(end.getDate() + 1);
        }

        var diffMs = end - now;
        var diffMins = Math.floor(diffMs / 60000);
        var hoursLeft = Math.floor(diffMins / 60);
        var minutesLeft = diffMins % 60;

        document.getElementById("endsAt").textContent =
          hoursLeft + " hour(s) " + minutesLeft + " minute(s)";
      }
    }
  };
  xhttp.open("GET", "getClassInfo.php?room=115", true);
  xhttp.send();
}
updateClassInfo();
setInterval(updateClassInfo, 1000);
</script>


</body>
<div id="faceModal" class="modal">
  <div class="modal-box">
    <header class="modal-header">
      <h3>Face Scan</h3>
     <button id="closeFaceModal" class="modal-close" type="button" aria-label="Close">&times;</button>

    </header>
    <div class="modal-content">
      <img id="faceStream" src="" alt="Face recognition stream">
    </div>
  </div>
</div>

<script>
const scanBtn = document.getElementById('scanFace');
let isScanning = false;
let videoBox = null;

scanBtn.addEventListener('click', async () => {
  if (!isScanning) {
    scanBtn.textContent = "Loading Trainer...";
    await fetch("http://debianRy.local:5001/reload_trainer")
      .then(r => r.text())
      .catch(err => console.error("Reload trainer failed", err));

    
    videoBox = document.createElement('div');
    videoBox.style = `
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: rgba(0,0,0,0.9);
      padding: 10px;
      border-radius: 10px;
      z-index: 9999;
      text-align: center;
    `;

    const img = document.createElement('img');
    img.src = "http://debianRy.local:5001/video_feed";
    img.style = "width:640px; height:480px; border-radius:8px;";
    videoBox.appendChild(img);

    const closeBtn = document.createElement('button');
    closeBtn.innerHTML = "&times;";
    closeBtn.style = `
      position:absolute;
      top:5px;
      right:10px;
      background:none;
      border:none;
      color:white;
      font-size:30px;
      cursor:pointer;
    `;
    closeBtn.onclick = async () => {
      img.src = "";
      document.body.removeChild(videoBox);
      await fetch("http://debianRy.local:5001/stop_feed")
        .catch(err => console.error("Stop feed failed", err));
      scanBtn.textContent = "Scan Face";
      isScanning = false;
    };

    videoBox.appendChild(closeBtn);
    document.body.appendChild(videoBox);
    scanBtn.textContent = "Stop Scan";
    isScanning = true;
  } else {
    await fetch("http://debianRy.local:5001/stop_feed")
      .catch(err => console.error("Stop feed failed", err));
    if (videoBox) document.body.removeChild(videoBox);
    scanBtn.textContent = "Scan Face";
    isScanning = false;
  }
});
</script>
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
    xhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            var fullInfo = JSON.parse(this.responseText);
            document.getElementById("currentClass").textContent = fullInfo["className"];
            document.getElementById("status").textContent = fullInfo["status"];
            document.getElementById("window").textContent = fullInfo["window"];
            var now = new Date();
            document.getElementById("dateOnly").textContent = now.toLocaleDateString();
            document.getElementById("timeOnly").textContent = now.toLocaleTimeString();
            if(fullInfo["status"] === "In-Session"){
              document.getElementById("status").className = "in-session";
            }else{
              document.getElementById("status").className = "available";
            } 
            var hoursLeft = fullInfo["endsAt24"] - now.getHours() - (now.getMinutes()/60);
            if(hoursLeft < 1){
              hoursLeft = 0;  
            }
            var timeLeft = Math.round((fullInfo["endsAt24"] - now.getHours() - (now.getMinutes()/60))*60);
            document.getElementById("endsAt").textContent = hoursLeft + " hours " + timeLeft + " minutes" ; 
        }
    };
    xhttp.open("GET", "getClassInfo.php?room=115", true);
    xhttp.send();
}
var scanButton = document.getElementById('scanFace');
if(scanButton){
  scanButton.onclick = function(){
    alert('Scan Face(camera will be available soon)');
  };
}

updateClassInfo();
setInterval(updateClassInfo, 1000);
</script>

</body>
</html>
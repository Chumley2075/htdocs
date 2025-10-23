// ---- variables ----
var className = "ICS 370 - Software Design Model";
var room      = "Room 115";

var startAt   = "6:00 PM";
var endsAt    = "9:20 PM";          // fixed extra space
var startMins = 18 * 60;            // 6:00 PM
var endMins   = 21 * 60 + 20;       // 9:20 PM

// ---- static text ----
document.getElementById("currentClass").textContent = className;
document.getElementById("roomNumber").textContent   = room;
document.getElementById("window").textContent       = startAt + "–" + endsAt; // en dash
document.getElementById("endsAt").textContent       = endsAt;

// ---- clocks + status ----
function updateHeaderClock(now){
  document.getElementById("dateOnly").textContent = now.toLocaleDateString("en-US", {
    weekday:"short", month:"short", day:"2-digit", year:"numeric"
  });
  document.getElementById("timeOnly").textContent = now.toLocaleTimeString("en-US", {
    hour:"2-digit", minute:"2-digit", second:"2-digit"
  });
}

function updateCornerClock(now){
  var el = document.getElementById("cornerClock");
  if (!el) return;
  el.textContent = "Now " + now.toLocaleTimeString("en-US", { hour:"2-digit", minute:"2-digit" });
}

function updateStatus(now){
  var minutes   = now.getHours() * 60 + now.getMinutes();
  var inSession = minutes >= startMins && minutes <= endMins;

  // small status label
  var small = document.getElementById("status");
  if (small){
    small.textContent = inSession ? "In session" : "Available";
    small.classList.toggle("available", !inSession);
    small.classList.toggle("busy", inSession);
  }

  // hero pill + availability line (if present)
  var pill = document.getElementById("statusPill");
  var line = document.getElementById("availabilityLine");
  if (pill || line){
    var minsLeft = 0;
    if (minutes < startMins) minsLeft = startMins - minutes;       // until class starts
    else if (inSession)       minsLeft = endMins - minutes;         // until class ends

    if (pill){
      pill.textContent = inSession ? "In session" : "Available";
      pill.classList.toggle("busy", inSession);
      pill.classList.toggle("available", !inSession);
    }
    if (line){
      line.textContent = inSession
        ? "In session • ends in " + minsLeft + " min"
        : "Available for " + minsLeft + " minutes";
    }
  }
}

function tick(){
  var now = new Date();
  updateHeaderClock(now);
  updateCornerClock(now);
  updateStatus(now);
}

tick();
setInterval(tick, 1000); // header shows seconds; pill/line will update each second too

// ---- Scan Face button (demo) ----
var scanButton = document.getElementById("scanFace");
if (scanButton){
  scanButton.onclick = function(){
    alert("Scan Face (camera will be available soon)");
  };
}



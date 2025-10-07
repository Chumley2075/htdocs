// variables
var className = ''; 
var room  = 115;
document.getElementById('roomNumber').textContent = room;

var startAt = '';
var endsAt = '';
var startMins = 0;
var endMins  = 0;

var currentTime12;
var currentTime24;
var currentDate;

// displays live date/time
function tick() {
 currentTime12 = new Date().toLocaleTimeString([], { hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit' });
 currentTime24 = new Date().toLocaleTimeString([], { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
 currentDate = new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: '2-digit', year: 'numeric'});

  
  document.getElementById('dateOnly').textContent =  currentDate;

  document.getElementById('timeOnly').textContent = currentTime12;
  
  // session status- Classroom free/ class in session
  

 function splitTimeString(timeString) {
  const parts = timeString.split(':');
  const h = Number(parts[0]);
  const m = Number(parts[1]);
  const s = Number(parts[2]);
  return h * 3600 + m * 60 + s; 
}

function isTimeInRange(timeStr, startStr, endStr) {
  const t = splitTimeString(timeStr);
  const s = splitTimeString(startStr);
  const e = splitTimeString(endStr);

    if (s <= e) {
      if (t < s) {
        inRange = false;
      } else if (t > e) {
        inRange = false;
      } else {
        inRange = true; 
      }
  } else {
    if (t >= s) {
      inRange = true;
    } else if (t <= e) {
      inRange = true;
    } else {
      inRange = false;
    }
  }
  return inRange;
}
  
}
tick();
setInterval(tick, 1000);

// Button- will be replaced by a camera
var scanButton = document.getElementById('scanFace');
if(scanButton){
  scanButton.onclick = function(){
    alert('Scan Face(camera will be available soon)');
  };
}

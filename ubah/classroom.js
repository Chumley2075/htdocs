// variables
var className = 'ICS 370 - Software Design Model'; // can be edited
var room  = 'Room 115';

var startAt = '6:00 PM';
var endsAt = '9:20  PM';
var startMins = 18 * 60; // 6:00 pm
var endMins  = 21 * 60 + 20; // 9:20 converting time from 12clock to 24hrs

// static text

document.getElementById('currentClass').textContent = className;
document.getElementById('roomNumber').textContent = room;
document.getElementById('window').textContent  = startAt + "-" + endsAt;
document.getElementById('endsAt').textContent  = endsAt;

// displays livedate/time
function tick() {
  var now = new Date();

  // date

  document.getElementById('dateOnly').textContent = now.toLocaleDateString('en-US', {
    weekday: 'short',
     month: 'short',
    day: '2-digit',
     year: 'numeric'
  });

  //time
  document.getElementById('timeOnly').textContent = now.toLocaleTimeString('en-US',{
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'


  });

  // session status- Available/ class in session

  var minutes = now.getHours() * 60 + now.getMinutes();
  var inSession = minutes>= startMins && minutes <=endMins;
  document.getElementById('status').textContent = inSession ? 'Class In session' : 'Available';
  
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

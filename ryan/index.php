<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Classroom Display</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body class="display-body">

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
        <p><strong>Next Class:</strong> <span id="nextClass">Loading...</span></p>
        <p class="status-row">
          <strong>Status:</strong>
          <span id="status" class="status-pill available"></span>
        </p>
        <p><strong>Ends in:</strong> <span id="endsAt"></span></p>
      </div>
    </section>

    <aside id="scanner" class="panel right" aria-label="Scanner">
      <button id="scanFace" class="big-scan" type="button">Scan Face</button>
    </aside>
  </div>
</main>
<script>

const FORCE_MOCK = false;

function getMockClassInfo() {
  const inSession = true; 

  return {
    className: inSession ? "Algebra II (Mock)" : "No Class (Mock)",
    nextClass: inSession ? "ICS 370 (Mock)" : "Algebra II (Mock)",
    status: inSession ? "In-Session" : "Available",
    window: inSession ? "2nd Period 9:10-10:00" : "Open Window",
    hideEndsIn: false,
    endsAt24: 10.0 
  };
}

async function fetchClassInfo(room) {
  if (FORCE_MOCK) return getMockClassInfo();

  try {
    const res = await fetch(`getClassInfo.php?room=${encodeURIComponent(room)}`, {
      cache: "no-store"
    });
    if (!res.ok) throw new Error("HTTP " + res.status);

    
    const data = await res.json();
    return data;
  } catch (err) {
    console.warn("Using mock class info (backend unavailable):", err);
    return getMockClassInfo();
  }
}

function renderClassInfo(fullInfo) {
  document.getElementById("currentClass").textContent = fullInfo["className"] ?? "-";
  document.getElementById("nextClass").textContent = fullInfo["nextClass"] ?? fullInfo["className"] ?? "-";
  document.getElementById("status").textContent = fullInfo["status"] ?? "-";
  document.getElementById("window").textContent = fullInfo["window"] ?? "-";

  const now = new Date();
  document.getElementById("dateOnly").textContent = now.toLocaleDateString();
  document.getElementById("timeOnly").textContent = now.toLocaleTimeString();

  const statusEl = document.getElementById("status");
  if (fullInfo["status"] === "In-Session") {
    statusEl.className = "status-pill in-session";
  } else {
    statusEl.className = "status-pill available";
  }

  const canScan = fullInfo["status"] === "In-Session";
  const scanBtn = document.getElementById("scanFace");
  scanBtn.disabled = !canScan;
  scanBtn.title = canScan ? "" : "Face scan available only during class";

  if (fullInfo["hideEndsIn"]) {
    document.getElementById("endsAt").textContent = "N/A";
  } else {
    const end = new Date(now);
    const endsAt24 = Number(fullInfo["endsAt24"]);
    const endHour = Math.floor(endsAt24);
    const endMin = Math.round((endsAt24 % 1) * 60);

    end.setHours(endHour, endMin, 0, 0);
    if (end < now) end.setDate(end.getDate() + 1);

    const diffMs = end - now;
    const diffMins = Math.floor(diffMs / 60000);
    const hoursLeft = Math.floor(diffMins / 60);
    const minutesLeft = diffMins % 60;

    document.getElementById("endsAt").textContent =
      `${hoursLeft} hour(s) ${minutesLeft} minute(s)`;
  }
}

async function updateClassInfo() {
  const room = document.getElementById("roomNumber").textContent.trim() || "115";
  const info = await fetchClassInfo(room);
  renderClassInfo(info);
}

updateClassInfo();
setInterval(updateClassInfo, 1000);
</script>

<div id="faceModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="faceModalTitle">
  <div class="modal-box">
    <header class="modal-header">
      <h3 id="faceModalTitle">Face Scan</h3>
      <button id="closeFaceModal" class="modal-close" type="button" aria-label="Close">&times;</button>
    </header>
    <div class="modal-content">
      <img id="faceStream" src="" alt="Face recognition stream">
    </div>
  </div>
</div>

<script>
const scanBtn = document.getElementById('scanFace');
const roomNumberEl = document.getElementById('roomNumber');
const ROOM_ID = roomNumberEl ? roomNumberEl.textContent.trim() : '115';
const faceModal = document.getElementById('faceModal');
const closeFaceModal = document.getElementById('closeFaceModal');
const faceStream = document.getElementById('faceStream');

let isScanning = false;
let autoClose = null;

async function stopScan() {
  try { await fetch("http://debianRy.local:5001/stop_feed"); } catch(e) {}
  try { await fetch("labels.php?room=" + encodeURIComponent(ROOM_ID), { cache: "no-store" }); } catch(e) {}
  if (autoClose) { clearTimeout(autoClose); autoClose = null; }
  if (faceStream) faceStream.src = "about:blank";
  if (faceModal) faceModal.classList.remove('show');
  scanBtn.textContent = "Scan Face";
  isScanning = false;
}

scanBtn.addEventListener('click', async () => {
  if (scanBtn.disabled) return;
  if (!isScanning) {
    scanBtn.textContent = "Loading Trainer...";
    await fetch("http://debianRy.local:5001/reload_trainer").catch(()=>{});
    if (faceStream) {
      faceStream.src = "http://debianRy.local:5001/video_feed?door_id=" + encodeURIComponent(ROOM_ID) + "&t=" + Date.now();
    }
    if (faceModal) {
      faceModal.classList.add('show');
    }
    scanBtn.textContent = "Stop Scan";
    isScanning = true;

    autoClose = setTimeout(() => stopScan(), 10000);
  } else {
    await stopScan();
  }
});

if (closeFaceModal) {
  closeFaceModal.addEventListener('click', () => stopScan());
}
if (faceModal) {
  faceModal.addEventListener('click', (e) => {
    if (e.target === faceModal) {
      stopScan();
    }
  });
}
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && isScanning) {
    stopScan();
  }
});
</script>
</body>
</html>

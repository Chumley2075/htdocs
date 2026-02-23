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
  document.getElementById("currentClass").textContent = fullInfo["className"] ?? "—";
  document.getElementById("status").textContent = fullInfo["status"] ?? "—";
  document.getElementById("window").textContent = fullInfo["window"] ?? "—";

  const now = new Date();
  document.getElementById("dateOnly").textContent = now.toLocaleDateString();
  document.getElementById("timeOnly").textContent = now.toLocaleTimeString();

  const statusEl = document.getElementById("status");
  if (fullInfo["status"] === "In-Session") {
    statusEl.className = "in-session";
  } else {
    statusEl.className = "available";
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
const ROOM_ID = document.getElementById('roomNumber').textContent.trim();

let isScanning = false;
let videoBox = null;
let autoClose = null;

async function stopScan(imgEl) {
  try { await fetch("http://debianRy.local:5001/stop_feed"); } catch(e) {}
  try { await fetch("labels.php?room=" + encodeURIComponent(ROOM_ID), { cache: "no-store" }); } catch(e) {}
  if (autoClose) { clearTimeout(autoClose); autoClose = null; }
  if (imgEl) imgEl.src = "about:blank";
  if (videoBox && videoBox.parentNode) { document.body.removeChild(videoBox); videoBox = null; }
  scanBtn.textContent = "Scan Face";
  isScanning = false;
}

scanBtn.addEventListener('click', async () => {
  if (scanBtn.disabled) return;
  if (!isScanning) {
    scanBtn.textContent = "Loading Trainer...";
    await fetch("http://debianRy.local:5001/reload_trainer").catch(()=>{});

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
    img.src = "http://debianRy.local:5001/video_feed?t=" + Date.now();
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
    closeBtn.onclick = () => stopScan(img);

    videoBox.appendChild(closeBtn);
    document.body.appendChild(videoBox);
    scanBtn.textContent = "Stop Scan";
    isScanning = true;

    autoClose = setTimeout(() => stopScan(img), 10000);
  } else {
    await stopScan(videoBox ? videoBox.querySelector("img") : null);
  }
});
</script>

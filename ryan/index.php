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
      <img id="faceStream" src="" alt="">
      <div id="faceResult" class="face-result" aria-live="polite" hidden>
        <p id="faceResultMessage">Scanning...</p>
        <img id="faceSnapshot" src="" alt="Face scan snapshot" hidden>
        <div id="faceActions" class="face-actions" hidden>
          <button id="enterRoomBtn" class="face-action primary" type="button">Enter Room</button>
          <button id="optOutBtn" class="face-action danger" type="button">Opt Out</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const scanBtn = document.getElementById('scanFace');
const roomNumberEl = document.getElementById('roomNumber');
const ROOM_ID = roomNumberEl ? roomNumberEl.textContent.trim() : '115';
const RECOGNITION_BASE = "http://debianRy.local:5001";
const faceModal = document.getElementById('faceModal');
const closeFaceModal = document.getElementById('closeFaceModal');
const faceStream = document.getElementById('faceStream');
const faceResult = document.getElementById('faceResult');
const faceResultMessage = document.getElementById('faceResultMessage');
const faceSnapshot = document.getElementById('faceSnapshot');
const faceActions = document.getElementById('faceActions');
const enterRoomBtn = document.getElementById('enterRoomBtn');
const optOutBtn = document.getElementById('optOutBtn');

let isScanning = false;
let autoClose = null;
let preparePromise = null;
let labelPollTimer = null;
let labelPollInFlight = false;
let matchedLabel = "";
let optOutToken = "";
let scanStartedAt = 0;

async function stopCaptureFeed() {
  try {
    await fetch("http://debianRy.local:5000/stop_feed?t=" + Date.now(), { cache: "no-store" });
  } catch(e) {}
}

function clearLabelPoll() {
  if (labelPollTimer) {
    clearTimeout(labelPollTimer);
    labelPollTimer = null;
  }
  labelPollInFlight = false;
}

function queueAutoClose() {
  if (autoClose) {
    clearTimeout(autoClose);
  }
  autoClose = setTimeout(() => stopScan(false), 10000);
}

async function prepareScan(forceReload = false) {
  const url = RECOGNITION_BASE + "/prepare_scan?reload=" + (forceReload ? "1" : "0") + "&door_id=" + encodeURIComponent(ROOM_ID) + "&t=" + Date.now();
  if (!forceReload && preparePromise) {
    return preparePromise;
  }
  const request = (async () => {
    await stopCaptureFeed();
    return fetch(url, { cache: "no-store" }).catch(() => null);
  })();
  if (!forceReload) {
    preparePromise = request.finally(() => {
      preparePromise = null;
    });
    return preparePromise;
  }
  return request;
}

function clearScanResult() {
  matchedLabel = "";
  optOutToken = "";
  scanStartedAt = 0;
  if (faceResult) faceResult.hidden = true;
  if (faceResultMessage) faceResultMessage.textContent = "Scanning...";
  if (faceSnapshot) {
    faceSnapshot.hidden = true;
    faceSnapshot.src = "";
  }
  if (faceActions) faceActions.hidden = true;
  if (enterRoomBtn) enterRoomBtn.hidden = true;
  if (optOutBtn) optOutBtn.hidden = true;
  if (enterRoomBtn) enterRoomBtn.disabled = false;
  if (optOutBtn) optOutBtn.disabled = false;
}

function isActionableFaceLabel(label) {
  const parts = String(label || "")
    .split(",")
    .map((part) => part.trim())
    .filter(Boolean);
  return parts.length === 1 && parts[0].toLowerCase() !== "unknown";
}

async function checkCanEnter(label) {
  if (!isActionableFaceLabel(label)) {
    return {can_enter: false, message: "No face match found."};
  }
  try {
    const res = await fetch(
      "labels.php?action=can_enter&room=" + encodeURIComponent(ROOM_ID) + "&label=" + encodeURIComponent(label),
      { cache: "no-store" }
    );
    const data = await res.json();
    return {
      can_enter: Boolean(data.can_enter),
      message: data.message || ""
    };
  } catch (e) {
    return {can_enter: false, message: "Could not verify this student for the current room."};
  }
}

async function showScanResult(result) {
  const status = result && result.status ? result.status : "idle";
  const resultLabel = result && result.label ? result.label : "";
  const isMatchedKnownFace = status === "matched" && isActionableFaceLabel(resultLabel);
  matchedLabel = "";
  optOutToken = "";
  if (faceActions) faceActions.hidden = true;
  if (enterRoomBtn) enterRoomBtn.hidden = true;
  if (optOutBtn) optOutBtn.hidden = true;
  let canEnter = false;
  let roomMessage = "";
  if (isMatchedKnownFace) {
    const roomCheck = await checkCanEnter(resultLabel);
    canEnter = roomCheck.can_enter;
    roomMessage = roomCheck.message;
  }
  if (faceResult) faceResult.hidden = false;
  if (faceResultMessage) {
    if (isMatchedKnownFace && canEnter) {
      faceResultMessage.textContent = "Face matched: " + resultLabel;
    } else if (isMatchedKnownFace) {
      faceResultMessage.textContent = roomMessage || "You are not on the roster for this room right now.";
    } else {
      faceResultMessage.textContent = "No face match found.";
    }
  }
  if (faceSnapshot) {
    if (result && result.image_url) {
      faceSnapshot.src = result.image_url + "?t=" + Date.now();
      faceSnapshot.hidden = false;
    } else {
      faceSnapshot.hidden = true;
      faceSnapshot.src = "";
    }
  }
  if (isMatchedKnownFace && canEnter) {
    matchedLabel = resultLabel;
    optOutToken = result.token || "";
    if (faceActions) faceActions.hidden = false;
    if (enterRoomBtn) enterRoomBtn.hidden = false;
    if (optOutBtn) optOutBtn.hidden = false;
  }
  scanBtn.textContent = status === "no_match"
    ? "No Match"
    : (isMatchedKnownFace && canEnter ? "Scan Complete" : "No Entry");
}

async function stopVideoFeed() {
  if (!isScanning) {
    return;
  }
  isScanning = false;
  clearLabelPoll();
  try { await fetch(RECOGNITION_BASE + "/stop_feed?t=" + Date.now(), { cache: "no-store" }); } catch(e) {}
  if (autoClose) { clearTimeout(autoClose); autoClose = null; }
  if (faceStream) {
    faceStream.src = "";
    faceStream.hidden = true;
  }
}

async function stopScan(recordAttendance = false) {
  await stopVideoFeed();
  scanStartedAt = 0;
  if (recordAttendance && matchedLabel) {
    try {
      await fetch(
        "labels.php?room=" + encodeURIComponent(ROOM_ID) + "&label=" + encodeURIComponent(matchedLabel),
        { cache: "no-store" }
      );
    } catch(e) {}
  }
  if (faceModal) faceModal.classList.remove('show');
  scanBtn.textContent = "Scan Face";
  clearScanResult();
}

async function pollForLabel() {
  if (!isScanning || labelPollInFlight) {
    return;
  }
  labelPollInFlight = true;
  try {
    const res = await fetch(RECOGNITION_BASE + "/scan_result?t=" + Date.now(), { cache: "no-store" });
    const result = await res.json();
    const resultTimestamp = Number(result && result.timestamp ? result.timestamp : 0);
    if (
      scanStartedAt > 0 &&
      resultTimestamp > 0 &&
      resultTimestamp < (scanStartedAt - 0.2)
    ) {
      return;
    }
    if (!isScanning || !result || (result.status !== "matched" && result.status !== "no_match")) {
      return;
    }
    await stopVideoFeed();
    await showScanResult(result);
  } catch (e) {
  } finally {
    labelPollInFlight = false;
    if (isScanning) {
      labelPollTimer = setTimeout(() => {
        pollForLabel();
      }, 300);
    }
  }
}

if (faceStream) {
  faceStream.addEventListener('load', () => {
    if (!isScanning) return;
    scanBtn.textContent = "Stop Scan";
    if (!autoClose) {
      queueAutoClose();
    }
    if (!labelPollTimer) {
      labelPollTimer = setTimeout(() => {
        pollForLabel();
      }, 300);
    }
  });
}

scanBtn.addEventListener('click', async () => {
  if (scanBtn.disabled) return;
  if (!isScanning) {
    clearScanResult();
    scanStartedAt = Date.now() / 1000;
    scanBtn.textContent = "Starting Camera...";
    if (faceModal) {
      faceModal.classList.add('show');
    }
    await prepareScan(true);
    isScanning = true;
    if (faceStream) {
      faceStream.hidden = false;
      faceStream.src = RECOGNITION_BASE + "/video_feed?door_id=" + encodeURIComponent(ROOM_ID) + "&t=" + Date.now();
    }
    scanBtn.textContent = "Waiting for Camera...";
  } else {
    await stopScan(false);
  }
});

if (enterRoomBtn) {
  enterRoomBtn.addEventListener('click', async () => {
    if (!isActionableFaceLabel(matchedLabel)) return;
    enterRoomBtn.disabled = true;
    if (optOutBtn) optOutBtn.disabled = true;
    if (faceResultMessage) faceResultMessage.textContent = "Entering room...";
    await stopScan(true);
  });
}

if (optOutBtn) {
  optOutBtn.addEventListener('click', async () => {
    if (!isActionableFaceLabel(matchedLabel) || !optOutToken) return;
    const ok = confirm("Are you sure you want to opt out? This will delete your face data and regenerate the face model.");
    if (!ok) return;
    optOutBtn.disabled = true;
    if (enterRoomBtn) enterRoomBtn.disabled = true;
    if (faceResultMessage) faceResultMessage.textContent = "Deleting face data and regenerating the model...";
    try {
      const res = await fetch(RECOGNITION_BASE + "/opt_out_face", {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify({label: matchedLabel, token: optOutToken})
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.ok) {
        throw new Error(data.message || "Opt-out failed.");
      }
      if (faceResultMessage) faceResultMessage.textContent = data.message || "Face data deleted and model regenerated.";
      if (faceActions) faceActions.hidden = true;
      scanBtn.textContent = "Scan Face";
      window.setTimeout(() => stopScan(false), 1600);
    } catch (err) {
      if (faceResultMessage) faceResultMessage.textContent = err && err.message ? err.message : "Opt-out failed.";
      if (enterRoomBtn) enterRoomBtn.disabled = false;
      optOutBtn.disabled = false;
    }
  });
}

if (closeFaceModal) {
  closeFaceModal.addEventListener('click', () => stopScan(false));
}
if (faceModal) {
  faceModal.addEventListener('click', (e) => {
    if (e.target === faceModal) {
      stopScan(false);
    }
  });
}
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && faceModal && faceModal.classList.contains('show')) {
    stopScan(false);
  }
});
</script>
</body>
</html>

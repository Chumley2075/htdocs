# -*- coding: utf-8 -*-
import cv2, time, os, numpy as np
from pathlib import Path
from threading import Lock
import mysql.connector

from camera_device import open_camera, prewarm_camera

os.umask(0o002)  

CAM_INDEX = 0
BASE_DIR = Path("training_images")
MODELS_DIR = Path("models")
CONF_THRESH = 0.6
TARGET_SIZE = (200, 200)
TOTAL_SAMPLES = 200
DELAY_BETWEEN_SAVES = 0.05

prototxt = str(MODELS_DIR / "deploy.prototxt")
weights = str(MODELS_DIR / "res10_300x300_ssd_iter_140000.caffemodel")
net = cv2.dnn.readNetFromCaffe(prototxt, weights)
cam = None
_cam_lock = Lock()
_active_streams = 0
_state_lock = Lock()
_capture_state = {
    "status": "idle",
    "person_id": "",
    "full_name": "",
    "count": 0,
    "total": TOTAL_SAMPLES,
    "message": "",
    "updated_at": 0.0,
}


def set_capture_state(status: str, person_id: str = "", full_name: str = "", count: int = 0, message: str = ""):
    with _state_lock:
        _capture_state.update({
            "status": status,
            "person_id": person_id,
            "full_name": full_name,
            "count": count,
            "total": TOTAL_SAMPLES,
            "message": message,
            "updated_at": time.time(),
        })


def get_capture_status(person_id: str | None = None):
    with _state_lock:
        state = dict(_capture_state)
    if person_id and state.get("person_id") not in ("", person_id):
        return {
            "status": "idle",
            "person_id": person_id,
            "full_name": "",
            "count": 0,
            "total": TOTAL_SAMPLES,
            "message": "",
            "updated_at": state.get("updated_at", 0.0),
        }
    return state

def upsert_user_profile(person_id: str, full_name: str):
    display_name = (full_name or "").strip()
    conn = None
    try:
        conn = mysql.connector.connect(
            host="localhost",
            user="flaskuser",
            password="ics311",
            database="UniversityDB"
        )
        cur = conn.cursor()
        cur.execute(
            """
            SELECT username
            FROM users
            WHERE username = %s
            LIMIT 1
            """,
            (person_id,)
        )
        exists = cur.fetchone() is not None
        if exists:
            if display_name:
                cur.execute(
                    """
                    UPDATE users
                    SET full_name = %s
                    WHERE username = %s
                    LIMIT 1
                    """,
                    (display_name, person_id)
                )
        else:
            if not display_name:
                display_name = person_id
            cur.execute(
                """
                INSERT INTO users (username, full_name, password_hash, is_prof, is_admin, is_student)
                VALUES (%s, %s, %s, 0, 0, 1)
                """,
                (person_id, display_name, "")
            )
        conn.commit()
    except Exception as e:
        print(f"[WARN] Could not upsert user profile for face capture: {e}")
    finally:
        try:
            conn.close()
        except Exception:
            pass


def log_face_added(person_id: str):
    conn = None
    try:
        conn = mysql.connector.connect(
            host="localhost",
            user="flaskuser",
            password="ics311",
            database="UniversityDB"
        )
        cur = conn.cursor()
        cur.execute(
            """
            CREATE TABLE IF NOT EXISTS admin_logs (
                log_id BIGINT AUTO_INCREMENT PRIMARY KEY,
                actor_username VARCHAR(100) NULL,
                target_username VARCHAR(100) NULL,
                action_type VARCHAR(64) NOT NULL,
                details TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_action_time (action_type, created_at),
                INDEX idx_target_time (target_username, created_at)
            )
            """
        )
        cur.execute(
            """
            INSERT INTO admin_logs (actor_username, target_username, action_type, details)
            VALUES (%s, %s, %s, %s)
            """,
            ("system_capture", person_id, "face_added", "Face capture completed and retraining started")
        )
        conn.commit()
    except Exception as e:
        print(f"[WARN] Could not write face_added log: {e}")
    finally:
        try:
            conn.close()
        except Exception:
            pass

def detect_faces_dnn(frame_bgr, conf=CONF_THRESH):
    (h, w) = frame_bgr.shape[:2]
    blob = cv2.dnn.blobFromImage(cv2.resize(frame_bgr, (300, 300)), 1.0,
                                 (300, 300), (104.0, 177.0, 123.0))
    net.setInput(blob)
    det = net.forward()
    boxes = []
    for i in range(det.shape[2]):
        c = det[0, 0, i, 2]
        if c >= conf:
            (x1, y1, x2, y2) = (det[0, 0, i, 3:7] * np.array([w, h, w, h])).astype(int)
            x1, y1 = max(0, x1), max(0, y1)
            x2, y2 = min(w - 1, x2), min(h - 1, y2)
            if x2 > x1 and y2 > y1:
                boxes.append((x1, y1, x2 - x1, y2 - y1))
    return boxes


def warmup_camera():
    try:
        if cam is not None and cam.isOpened():
            return True
        return prewarm_camera(
            index=CAM_INDEX,
            width=1920,
            height=1080,
            fps=30,
            warmup_frames=12,
            warmup_timeout_s=1.5,
        )
    except Exception as e:
        print(f"[WARN] Camera warmup failed: {e}")
        return False


def stop_camera():
    global cam
    with _cam_lock:
        if cam is not None and cam.isOpened():
            cam.release()
        cam = None


def stop_capture():
    state = get_capture_status()
    if state.get("status") == "capturing":
        set_capture_state(
            "stopped",
            person_id=state.get("person_id", ""),
            full_name=state.get("full_name", ""),
            count=state.get("count", 0),
            message="Capture stopped.",
        )
    stop_camera()


def acquire_camera():
    global cam
    with _cam_lock:
        if cam is None or not cam.isOpened():
            cam = open_camera(
                index=CAM_INDEX,
                width=1920,
                height=1080,
                fps=30,
                warmup_frames=12,
                warmup_timeout_s=1.5,
            )
        return cam


def begin_stream():
    global _active_streams
    with _cam_lock:
        _active_streams += 1


def end_stream():
    global cam, _active_streams
    with _cam_lock:
        _active_streams = max(0, _active_streams - 1)
        if _active_streams == 0 and cam is not None and cam.isOpened():
            cam.release()
            cam = None

def generate_frames(person_id: str, full_name: str = ""):
    save_dir = BASE_DIR / person_id
    save_dir.mkdir(parents=True, exist_ok=True)
    upsert_user_profile(person_id, full_name)
    set_capture_state(
        "capturing",
        person_id=person_id,
        full_name=full_name,
        count=0,
        message="Capturing face samples...",
    )
    cam = acquire_camera()
    count = 0
    last_save_ts = 0.0
    begin_stream()
    try:
        while True:
            ok, frame = cam.read()
            if not ok:
                break
            faces = detect_faces_dnn(frame, CONF_THRESH)
            if not faces:
                faces = detect_faces_dnn(frame, 0.5)
            if faces:
                x, y, w, h = max(faces, key=lambda b: b[2] * b[3])
                cv2.rectangle(frame, (x, y), (x + w, y + h), (0, 230, 0), 2)
                now = time.time()
                if now - last_save_ts >= DELAY_BETWEEN_SAVES and count < TOTAL_SAMPLES:
                    face = frame[y:y + h, x:x + w]
                    gray = cv2.cvtColor(face, cv2.COLOR_BGR2GRAY)
                    gray = cv2.resize(gray, TARGET_SIZE, interpolation=cv2.INTER_AREA)
                    gray = cv2.equalizeHist(gray)
                    count += 1
                    filename = save_dir / f"{count}.jpg"
                    cv2.imwrite(str(filename), gray)
                    last_save_ts = now
                    set_capture_state(
                        "capturing",
                        person_id=person_id,
                        full_name=full_name,
                        count=count,
                        message=f"Capturing face samples... {count}/{TOTAL_SAMPLES}",
                    )
                    cv2.putText(frame, f"Saved {count}/{TOTAL_SAMPLES}", (10, 30),
                                cv2.FONT_HERSHEY_SIMPLEX, 0.9, (255, 255, 255), 2)
            ret, buffer = cv2.imencode('.jpg', frame)
            if not ret:
                continue
            frame_bytes = buffer.tobytes()
            yield (b'--frame\r\n'
                   b'Content-Type: image/jpeg\r\n\r\n' + frame_bytes + b'\r\n')
            if count >= TOTAL_SAMPLES:
                log_face_added(person_id)
                set_capture_state(
                    "completed",
                    person_id=person_id,
                    full_name=full_name,
                    count=count,
                    message="Capture complete. Retraining started.",
                )

                import subprocess, sys
                subprocess.Popen(
                    [sys.executable, "/var/www/html/htdocs/ryan/yilma/trainer.py"],
                    cwd="/var/www/html/htdocs/ryan/yilma",
                    stdout=subprocess.DEVNULL,
                    stderr=subprocess.STDOUT
                )

                return
    except Exception as e:
        set_capture_state(
            "error",
            person_id=person_id,
            full_name=full_name,
            count=count,
            message=f"Capture failed: {e}",
        )
        raise
    finally:
        state = get_capture_status(person_id)
        if state.get("status") == "capturing":
            set_capture_state(
                "stopped",
                person_id=person_id,
                full_name=full_name,
                count=count,
                message="Capture ended before completion.",
            )
        end_stream()

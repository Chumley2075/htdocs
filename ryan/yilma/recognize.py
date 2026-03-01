import os
import time
from collections import deque
from pathlib import Path
from threading import Lock

import cv2
import numpy as np
import mysql.connector

from depth_helper import DepthHelper

MODELS_DIR = Path("models")
TRAINER_DIR = Path("trainer")
TRAINER_YML = TRAINER_DIR / "trainer.yml"
LABELS_NPY = TRAINER_DIR / "labels.npy"
LAST_LABEL_PATH = "/tmp/last_label.txt"
DEFAULT_DOOR_ID = os.getenv("DOOR_ID", "").strip() or None

prototxt_path = str(MODELS_DIR / "deploy.prototxt")
model_path = str(MODELS_DIR / "res10_300x300_ssd_iter_140000.caffemodel")

net = cv2.dnn.readNetFromCaffe(prototxt_path, model_path)

cam = None
recognizer = None
label_map = {}

_latest_labels = "Unknown"
_labels_lock = Lock()
_label_history = deque(maxlen=20)
depth_helper = DepthHelper(
    min_depth_mm=450,
    max_depth_mm=2000,
    min_variation_mm=40,
    required=False,
)
_last_logged_label = None
_last_logged_at = 0.0
_last_door_eval_at = 0.0


def detect_faces_dnn(frame, conf_threshold=0.6):
    (h, w) = frame.shape[:2]
    blob = cv2.dnn.blobFromImage(
        cv2.resize(frame, (300, 300)),
        1.0,
        (300, 300),
        (104.0, 177.0, 123.0),
    )
    net.setInput(blob)
    det = net.forward()
    boxes = []
    for i in range(det.shape[2]):
        conf = det[0, 0, i, 2]
        if conf > conf_threshold:
            box = det[0, 0, i, 3:7] * np.array([w, h, w, h])
            (x1, y1, x2, y2) = box.astype(int)
            x1 = max(0, x1)
            y1 = max(0, y1)
            boxes.append((x1, y1, x2 - x1, y2 - y1))
    return boxes


def load_trainer_from_db():
    global recognizer, label_map
    if recognizer is not None and label_map:
        return
    if not TRAINER_YML.is_file() or not LABELS_NPY.is_file():
        print("[WARN] Trainer files not found; recognition will show 'Unknown'.")
        recognizer = None
        label_map = {}
        return
    try:
        rec = cv2.face.LBPHFaceRecognizer_create()
        rec.read(str(TRAINER_YML))
        lm = np.load(LABELS_NPY, allow_pickle=True).item()
    except Exception as e:
        print(f"[ERROR] Failed to load recognizer/labels: {e}")
        recognizer = None
        label_map = {}
        return
    recognizer = rec
    label_map = lm
    print(f"[INFO] Loaded recognizer with {len(label_map)} label(s).")


def stop_camera():
    global cam
    if cam is not None and cam.isOpened():
        cam.release()
    cam = None


def get_latest_labels():
    with _labels_lock:
        return _latest_labels or "Unknown"


def get_db_connection():
    return mysql.connector.connect(
        host="localhost",
        user="flaskuser",
        password="ics311",
        database="UniversityDB",
    )


def ensure_door_control(cur):
    cur.execute(
        """
        CREATE TABLE IF NOT EXISTS door_control_rooms (
            door_id VARCHAR(50) PRIMARY KEY,
            room_number INT NULL,
            is_locked TINYINT(1) NOT NULL DEFAULT 0,
            lock_mode VARCHAR(40) NOT NULL DEFAULT 'unlocked',
            lock_reason VARCHAR(255) NULL,
            last_changed_by VARCHAR(100) NULL,
            last_changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_room_number (room_number)
        )
        """
    )
    cur.execute(
        """
        INSERT INTO door_control_rooms (door_id, room_number, is_locked, lock_mode, lock_reason, last_changed_by)
        SELECT CAST(x.room_number AS CHAR),
               x.room_number,
               0,
               'unlocked',
               'Initial state',
               'system'
        FROM (
            SELECT DISTINCT roomNumber AS room_number
            FROM Classes
            WHERE roomNumber IS NOT NULL
        ) AS x
        ON DUPLICATE KEY UPDATE room_number = VALUES(room_number)
        """
    )


def normalize_door_id(door_id):
    if door_id is None:
        return None
    door_id = str(door_id).strip()
    if not door_id:
        return None
    return door_id[:50]


def get_door_state(door_id=None):
    door_id = normalize_door_id(door_id)
    conn = None
    try:
        conn = get_db_connection()
        cur = conn.cursor(dictionary=True)
        ensure_door_control(cur)
        conn.commit()
        if door_id:
            cur.execute(
                """
                SELECT door_id, room_number, is_locked, lock_mode, lock_reason, last_changed_by, last_changed_at
                FROM door_control_rooms
                WHERE door_id = %s
                LIMIT 1
                """,
                (door_id,),
            )
        else:
            cur.execute(
                """
                SELECT door_id, room_number, is_locked, lock_mode, lock_reason, last_changed_by, last_changed_at
                FROM door_control_rooms
                ORDER BY room_number, door_id
                LIMIT 1
                """
            )
        row = cur.fetchone()
        if row:
            row["is_locked"] = int(row.get("is_locked", 0))
            return row
    except Exception as e:
        print(f"[WARN] Could not load door state: {e}")
    finally:
        try:
            conn.close()
        except Exception:
            pass
    return {
        "door_id": door_id,
        "room_number": None,
        "is_locked": 0,
        "lock_mode": "unlocked",
        "lock_reason": "",
        "last_changed_by": "",
        "last_changed_at": None,
    }


def log_face_scan(label: str):
    conn = None
    try:
        conn = get_db_connection()
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
            (label, label, "face_scanned", "Recognized by camera stream"),
        )
        conn.commit()
    except Exception as e:
        print(f"[WARN] Could not write face_scanned log: {e}")
    finally:
        try:
            conn.close()
        except Exception:
            pass


def is_prof_or_admin(username: str):
    conn = None
    try:
        conn = get_db_connection()
        cur = conn.cursor(dictionary=True)
        cur.execute(
            """
            SELECT is_admin, is_prof
            FROM users
            WHERE username = %s
            LIMIT 1
            """,
            (username,),
        )
        row = cur.fetchone()
        if not row:
            return False
        return int(row.get("is_admin", 0)) == 1 or int(row.get("is_prof", 0)) == 1
    except Exception as e:
        print(f"[WARN] Could not verify role for {username}: {e}")
        return False
    finally:
        try:
            conn.close()
        except Exception:
            pass


def unlock_door_from_face_scan(username: str, door_id=None):
    door_id = normalize_door_id(door_id)
    conn = None
    try:
        conn = get_db_connection()
        cur = conn.cursor()
        ensure_door_control(cur)
        if door_id:
            cur.execute(
                """
                UPDATE door_control_rooms
                SET is_locked = 0,
                    lock_mode = 'unlocked',
                    lock_reason = %s,
                    last_changed_by = %s,
                    last_changed_at = CURRENT_TIMESTAMP
                WHERE door_id = %s
                  AND is_locked = 1
                  AND lock_mode = 'locked_until_authorized'
                """,
                ("Unlocked by authorized face scan", username, door_id),
            )
        else:
            cur.execute(
                """
                UPDATE door_control_rooms
                SET is_locked = 0,
                    lock_mode = 'unlocked',
                    lock_reason = %s,
                    last_changed_by = %s,
                    last_changed_at = CURRENT_TIMESTAMP
                WHERE is_locked = 1
                  AND lock_mode = 'locked_until_authorized'
                """,
                ("Unlocked by authorized face scan", username),
            )
        did_unlock = cur.rowcount > 0
        if did_unlock:
            detail = (
                "Door auto-unlocked after authorized face scan"
                if not door_id
                else f"Door {door_id} auto-unlocked after authorized face scan"
            )
            cur.execute(
                """
                INSERT INTO admin_logs (actor_username, target_username, action_type, details)
                VALUES (%s, %s, %s, %s)
                """,
                (username, None, "door_unlocked_by_face", detail),
            )
        conn.commit()
        return did_unlock
    except Exception as e:
        print(f"[WARN] Could not unlock door from face scan: {e}")
        return False
    finally:
        try:
            conn.close()
        except Exception:
            pass


def evaluate_door_auto_unlock(stable_label: str, door_id=None):
    global _last_door_eval_at
    now = time.time()
    if (now - _last_door_eval_at) < 1.5:
        return
    _last_door_eval_at = now

    if stable_label == "Unknown":
        return
    if not door_id:
        return

    door_state = get_door_state(door_id)
    if int(door_state.get("is_locked", 0)) != 1:
        return
    if door_state.get("lock_mode") != "locked_until_authorized":
        return

    for raw in stable_label.split(","):
        username = raw.strip()
        if not username or username == "Unknown":
            continue
        if is_prof_or_admin(username):
            unlocked = unlock_door_from_face_scan(username, door_id)
            if unlocked:
                if door_id:
                    print(f"[INFO] Door {door_id} auto-unlocked by face scan: {username}")
                else:
                    print(f"[INFO] Door auto-unlocked by face scan: {username}")
            return


def generate_frames(door_id=None):
    global cam, recognizer, label_map, _latest_labels, _last_logged_label, _last_logged_at
    resolved_door_id = normalize_door_id(door_id) or DEFAULT_DOOR_ID
    if recognizer is None:
        load_trainer_from_db()
    if cam is None or not cam.isOpened():
        cam = cv2.VideoCapture(0)
        cam.set(cv2.CAP_PROP_FRAME_WIDTH, 1920)
        cam.set(cv2.CAP_PROP_FRAME_HEIGHT, 1080)
        cam.set(cv2.CAP_PROP_FPS, 60)
    while True:
        ok, frame = cam.read()
        if not ok:
            break
        gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
        h, w = frame.shape[:2]
        faces = detect_faces_dnn(frame)
        labels_this_frame = []
        min_face_height = h // 4
        for (x, y, fw, fh) in faces:
            if fh < min_face_height:
                labels_this_frame.append("Unknown")
                color = (0, 0, 255)
                display = "Unknown (Small)"
                cv2.rectangle(frame, (x, y), (x+fw, y+fh), color, 2)
                cv2.putText(
                    frame,
                    display,
                    (x, y - 10),
                    cv2.FONT_HERSHEY_SIMPLEX,
                    0.8,
                    (255, 255, 255),
                    2,
                )
                continue
            roi = gray[y:y+fh, x:x+fw]
            if roi.size == 0 or recognizer is None:
                continue
            face_ok, depth_mm, var_mm = depth_helper.evaluate_for_box(
                x, y, fw, fh, w, h
            )
            if not face_ok:
                labels_this_frame.append("Unknown")
                color = (0, 0, 255)
                display = "Unknown (Depth)"
                cv2.rectangle(frame, (x, y), (x+fw, y+fh), color, 2)
                cv2.putText(
                    frame,
                    display,
                    (x, y - 10),
                    cv2.FONT_HERSHEY_SIMPLEX,
                    0.8,
                    (255, 255, 255),
                    2,
                )
                continue
            id_num, conf = recognizer.predict(roi)
            label = label_map.get(id_num, f"ID {id_num}")
            is_known = (conf < 100)
            labels_this_frame.append(label if is_known else "Unknown")
            color = (0, 255, 0) if is_known else (0, 0, 255)
            display = f"{label} ({conf:.1f})" if is_known else "Unknown"
            cv2.rectangle(frame, (x, y), (x+fw, y+fh), color, 2)
            cv2.putText(
                frame,
                display,
                (x, y - 10),
                cv2.FONT_HERSHEY_SIMPLEX,
                0.8,
                (255, 255, 255),
                2,
            )
        labels_this_frame = labels_this_frame or ["Unknown"]
        frame_has_known = any(l != "Unknown" for l in labels_this_frame)
        if frame_has_known:
            known_labels = sorted(set(l for l in labels_this_frame if l != "Unknown"))
            frame_label = ",".join(known_labels)
        else:
            frame_label = "Unknown"
        _label_history.append(1 if frame_has_known else 0)
        if len(_label_history) >= 10:
            ratio = sum(_label_history) / len(_label_history)
            if ratio >= 0.7 and frame_has_known:
                stable_label = frame_label
            else:
                stable_label = "Unknown"
        else:
            stable_label = frame_label if frame_has_known else "Unknown"
        with _labels_lock:
            if stable_label != _latest_labels:
                _latest_labels = stable_label
                try:
                    with open(LAST_LABEL_PATH, "w") as f:
                        f.write(_latest_labels)
                except Exception:
                    pass
        now = time.time()
        if stable_label != "Unknown":
            if stable_label != _last_logged_label or (now - _last_logged_at) >= 30:
                log_face_scan(stable_label)
                _last_logged_label = stable_label
                _last_logged_at = now
        evaluate_door_auto_unlock(stable_label, resolved_door_id)
        ret, buffer = cv2.imencode('.jpg', frame)
        if not ret:
            continue
        yield (
            b'--frame\r\n'
            b'Content-Type: image/jpeg\r\n\r\n' + buffer.tobytes() + b'\r\n'
        )

import os
import time
from collections import deque
from pathlib import Path
from threading import Lock

import cv2
import numpy as np
import mysql.connector

from camera_device import open_camera, prewarm_camera, release_camera
from depth_helper import DepthHelper

MODELS_DIR = Path("models")
TRAINER_DIR = Path("trainer")
TRAINER_YML = TRAINER_DIR / "trainer.yml"
LABELS_NPY = TRAINER_DIR / "labels.npy"
LAST_LABEL_PATH = "/tmp/last_label.txt"
DEFAULT_DOOR_ID = os.getenv("DOOR_ID", "").strip() or None
CAMERA_WIDTH = 1280
CAMERA_HEIGHT = 720
CAMERA_FPS = 30
JPEG_QUALITY = 80
RECOGNIZED_HOLD_SECONDS = 1.2

prototxt_path = str(MODELS_DIR / "deploy.prototxt")
model_path = str(MODELS_DIR / "res10_300x300_ssd_iter_140000.caffemodel")

net = cv2.dnn.readNetFromCaffe(prototxt_path, model_path)

cam = None
recognizer = None
label_map = {}
_trainer_signature = None
_cam_lock = Lock()
_active_streams = 0

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
_last_depth_log_signature = None
_last_depth_log_at = 0.0
_candidate_label = "Unknown"
_candidate_label_since = 0.0


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


def get_trainer_signature():
    if not TRAINER_YML.is_file() or not LABELS_NPY.is_file():
        return None
    return (
        TRAINER_YML.stat().st_mtime_ns,
        LABELS_NPY.stat().st_mtime_ns,
    )


def load_trainer_from_db(force=False):
    global recognizer, label_map, _trainer_signature
    current_signature = get_trainer_signature()
    if not force and recognizer is not None and label_map and current_signature == _trainer_signature:
        return
    if not TRAINER_YML.is_file() or not LABELS_NPY.is_file():
        print("[WARN] Trainer files not found; recognition will show 'Unknown'.")
        recognizer = None
        label_map = {}
        _trainer_signature = None
        return
    try:
        rec = cv2.face.LBPHFaceRecognizer_create()
        rec.read(str(TRAINER_YML))
        lm = np.load(LABELS_NPY, allow_pickle=True).item()
    except Exception as e:
        print(f"[ERROR] Failed to load recognizer/labels: {e}")
        recognizer = None
        label_map = {}
        _trainer_signature = None
        return
    recognizer = rec
    label_map = lm
    _trainer_signature = current_signature
    print(f"[INFO] Loaded recognizer with {len(label_map)} label(s).")


def stop_camera():
    global cam
    with _cam_lock:
        release_camera(cam)
        cam = None


def warmup_camera():
    try:
        if cam is not None and cam.isOpened():
            return True
        return prewarm_camera(
            index=0,
            width=CAMERA_WIDTH,
            height=CAMERA_HEIGHT,
            fps=CAMERA_FPS,
            warmup_frames=12,
            warmup_timeout_s=1.5,
        )
    except Exception as e:
        print(f"[WARN] Camera warmup failed: {e}", flush=True)
        return False


def acquire_camera():
    global cam
    with _cam_lock:
        if cam is None or not cam.isOpened():
            cam = open_camera(
                index=0,
                width=CAMERA_WIDTH,
                height=CAMERA_HEIGHT,
                fps=CAMERA_FPS,
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
            release_camera(cam)
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
            unlock_until DATETIME NULL,
            last_changed_by VARCHAR(100) NULL,
            last_changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_room_number (room_number)
        )
        """
    )
    try:
        cur.execute(
            "ALTER TABLE door_control_rooms ADD COLUMN IF NOT EXISTS unlock_until DATETIME NULL"
        )
    except Exception:
        # Older MySQL variants may not support IF NOT EXISTS in ALTER TABLE.
        pass
    cur.execute(
        """
        INSERT INTO door_control_rooms (door_id, room_number, is_locked, lock_mode, lock_reason, unlock_until, last_changed_by)
        SELECT CAST(x.room_number AS CHAR),
               x.room_number,
               1,
               'locked_until_authorized',
               'Initial state',
               NULL,
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


def apply_door_timeouts(cur):
    cur.execute(
        """
        UPDATE door_control_rooms
        SET is_locked = 1,
            lock_mode = 'locked_until_authorized',
            lock_reason = 'Auto re-locked after temporary unlock',
            unlock_until = NULL,
            last_changed_by = 'system_timeout',
            last_changed_at = CURRENT_TIMESTAMP
        WHERE is_locked = 0
          AND (
                (unlock_until IS NOT NULL AND unlock_until <= NOW())
                OR (lock_mode = 'temporary_unlocked' AND TIMESTAMPDIFF(SECOND, last_changed_at, NOW()) >= 5)
              )
        """
    )


def get_door_state(door_id=None):
    door_id = normalize_door_id(door_id)
    conn = None
    try:
        conn = get_db_connection()
        cur = conn.cursor(dictionary=True)
        ensure_door_control(cur)
        apply_door_timeouts(cur)
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


def log_depth_rejection_once():
    global _last_depth_log_signature, _last_depth_log_at
    details = depth_helper.get_last_eval_details()
    if not details:
        return
    signature = (
        details.get("mode"),
        details.get("source"),
        details.get("reason"),
        details.get("fresh"),
        details.get("valid_samples"),
        details.get("face_cells"),
    )
    now = time.time()
    if signature == _last_depth_log_signature and (now - _last_depth_log_at) < 5:
        return
    _last_depth_log_signature = signature
    _last_depth_log_at = now
    avg = details.get("avg_depth_mm")
    var = details.get("variation_mm")
    avg_txt = "None" if avg is None else f"{avg:.1f}"
    var_txt = "None" if var is None else f"{var:.1f}"
    age = details.get("last_data_age_s")
    age_txt = "None" if age is None else f"{age:.2f}"
    print(
        "[DEPTH] reject "
        f"mode={details.get('mode')} "
        f"source={details.get('source')} "
        f"reason={details.get('reason')} "
        f"fresh={details.get('fresh')} "
        f"valid_samples={details.get('valid_samples')} "
        f"face_cells={details.get('face_cells')} "
        f"avg_mm={avg_txt} "
        f"var_mm={var_txt} "
        f"age_s={age_txt}",
        flush=True,
    )


def initialize_recognition_service():
    load_trainer_from_db()
    try:
        depth_helper.evaluate()
    except Exception as e:
        print(f"[WARN] Depth startup initialization failed: {e}", flush=True)


def prepare_scan(force_reload=False):
    load_trainer_from_db(force=force_reload)
    return warmup_camera()


def generate_frames(door_id=None):
    global _latest_labels, _last_logged_label, _last_logged_at, _candidate_label, _candidate_label_since
    resolved_door_id = normalize_door_id(door_id) or DEFAULT_DOOR_ID
    _label_history.clear()
    _candidate_label = "Unknown"
    _candidate_label_since = 0.0
    with _labels_lock:
        _latest_labels = "Unknown"
        try:
            with open(LAST_LABEL_PATH, "w") as f:
                f.write(_latest_labels)
        except Exception:
            pass
    load_trainer_from_db()
    cam = acquire_camera()
    begin_stream()
    try:
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
                roi = cv2.resize(roi, (200, 200), interpolation=cv2.INTER_AREA)
                face_ok, depth_mm, var_mm = depth_helper.evaluate_for_box(
                    x, y, fw, fh, w, h
                )
                if not face_ok:
                    log_depth_rejection_once()
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
            now = time.time()
            if len(_label_history) >= 10:
                ratio = sum(_label_history) / len(_label_history)
                if ratio >= 0.7 and frame_has_known:
                    stable_label_candidate = frame_label
                else:
                    stable_label_candidate = "Unknown"
            else:
                stable_label_candidate = frame_label if frame_has_known else "Unknown"
            if stable_label_candidate == "Unknown":
                _candidate_label = "Unknown"
                _candidate_label_since = 0.0
                stable_label = "Unknown"
            else:
                if stable_label_candidate != _candidate_label:
                    _candidate_label = stable_label_candidate
                    _candidate_label_since = now
                    stable_label = "Unknown"
                elif (now - _candidate_label_since) >= RECOGNIZED_HOLD_SECONDS:
                    stable_label = stable_label_candidate
                else:
                    stable_label = "Unknown"
            with _labels_lock:
                if stable_label != _latest_labels:
                    _latest_labels = stable_label
                    try:
                        with open(LAST_LABEL_PATH, "w") as f:
                            f.write(_latest_labels)
                    except Exception:
                        pass
            if stable_label != "Unknown":
                if stable_label != _last_logged_label or (now - _last_logged_at) >= 30:
                    log_face_scan(stable_label)
                    _last_logged_label = stable_label
                    _last_logged_at = now
            evaluate_door_auto_unlock(stable_label, resolved_door_id)
            ret, buffer = cv2.imencode('.jpg', frame, [int(cv2.IMWRITE_JPEG_QUALITY), JPEG_QUALITY])
            if not ret:
                continue
            yield (
                b'--frame\r\n'
                b'Content-Type: image/jpeg\r\n\r\n' + buffer.tobytes() + b'\r\n'
            )
    finally:
        end_stream()

initialize_recognition_service()

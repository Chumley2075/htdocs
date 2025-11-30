# recognize.py
import cv2
import numpy as np
import mysql.connector
from pathlib import Path
from threading import Lock
from depth_helper import DepthHelper
MODELS_DIR = Path("models")
TRAINER_DIR = Path("trainer")

prototxt_path = str(MODELS_DIR / "deploy.prototxt")
model_path    = str(MODELS_DIR / "res10_300x300_ssd_iter_140000.caffemodel")
depth_helper = DepthHelper(
    min_depth_mm=300,
    max_depth_mm=2000,
    min_variation_mm=40,
    required=False,
)
net = cv2.dnn.readNetFromCaffe(prototxt_path, model_path)
cam = None
recognizer = None
label_map = {}

_latest_labels = "Unknown"
_labels_lock = Lock()
LAST_LABEL_PATH = "/tmp/last_label.txt"

def load_trainer_from_db():
    global recognizer, label_map
    conn = mysql.connector.connect(
        host="localhost",
        user="flaskuser",
        password="ics311",
        database="UniversityDB"
    )
    cursor = conn.cursor()
    cursor.execute("SELECT trainer, labels FROM face_models ORDER BY id DESC LIMIT 1")
    row = cursor.fetchone()
    conn.close()
    if not row:
        return
    trainer_blob, labels_blob = row
    trainer_path = TRAINER_DIR / "trainer_temp.yml"
    labels_path  = TRAINER_DIR / "labels_temp.npy"
    TRAINER_DIR.mkdir(exist_ok=True)
    with open(trainer_path, 'wb') as f:
        f.write(trainer_blob)
    with open(labels_path, 'wb') as f:
        f.write(labels_blob)
    recognizer = cv2.face.LBPHFaceRecognizer_create()
    recognizer.read(str(trainer_path))
    label_map = np.load(labels_path, allow_pickle=True).item()

def detect_faces_dnn(frame, conf_threshold=0.4):
    (h, w) = frame.shape[:2]
    blob = cv2.dnn.blobFromImage(frame, 1.0, (300, 300),
                                 (104.0, 177.0, 123.0))
    net.setInput(blob)
    det = net.forward()
    boxes = []
    for i in range(det.shape[2]):
        conf = det[0, 0, i, 2]
        if conf > conf_threshold:
            box = det[0, 0, i, 3:7] * np.array([w, h, w, h])
            (x1, y1, x2, y2) = box.astype(int)
            boxes.append((x1, y1, x2 - x1, y2 - y1))
    return boxes
def generate_frames():
    global cam, recognizer, label_map, _latest_labels

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

        # --- NEW: depth gate via ToF sensor ---
        gate_ok, depth_mm, var_mm = depth_helper.evaluate()
        # Optional debug:
        # print("ToF gate:", gate_ok, "avg:", depth_mm, "var:", var_mm)

        if gate_ok:
            faces = detect_faces_dnn(frame)
        else:
            faces = []
            # Optional visual indicator when depth rejects the frame:
            cv2.putText(
                frame,
                "Depth rejected",
                (10, 30),
                cv2.FONT_HERSHEY_SIMPLEX,
                0.8,
                (0, 0, 255),
                2,
                cv2.LINE_AA,
            )
        # --- END NEW ---

        labels_this_frame = []

        for (x, y, w, h) in faces:
            roi = gray[y:y+h, x:x+w]
            if roi.size == 0 or recognizer is None:
                continue

            id_num, conf = recognizer.predict(roi)
            label = label_map.get(id_num, f"ID {id_num}")
            is_known = (conf < 100)
            labels_this_frame.append(label if is_known else "Unknown")

            color = (0, 255, 0) if is_known else (0, 0, 255)
            display = f"{label} ({conf:.1f})" if is_known else "Unknown"

            cv2.rectangle(frame, (x, y), (x+w, y+h), color, 2)
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

        with _labels_lock:
            new_val = ",".join(labels_this_frame)
            if new_val != _latest_labels:
                _latest_labels = new_val
                try:
                    with open(LAST_LABEL_PATH, "w") as f:
                        f.write(_latest_labels)
                except Exception:
                    pass

        ret, buffer = cv2.imencode('.jpg', frame)
        if not ret:
            continue

        yield (
            b'--frame\r\n'
            b'Content-Type: image/jpeg\r\n\r\n' + buffer.tobytes() + b'\r\n'
        )
def stop_camera():
    global cam
    if cam and cam.isOpened():
        cam.release()
        cam = None

import os
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
        ret, buffer = cv2.imencode('.jpg', frame)
        if not ret:
            continue
        yield (
            b'--frame\r\n'
            b'Content-Type: image/jpeg\r\n\r\n' + buffer.tobytes() + b'\r\n'
        )

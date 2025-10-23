# -*- coding: utf-8 -*-
import cv2
import numpy as np
from pathlib import Path

# === Paths ===
MODELS_DIR = Path("models")
TRAINER_DIR = Path("trainer")

prototxt_path = str(MODELS_DIR / "deploy.prototxt")
model_path    = str(MODELS_DIR / "res10_300x300_ssd_iter_140000.caffemodel")
trainer_yml   = str(TRAINER_DIR / "trainer.yml")
labels_npy    = str(TRAINER_DIR / "labels.npy")

# === Load models ===
print("[INFO] Loading models...")
net = cv2.dnn.readNetFromCaffe(prototxt_path, model_path)

recognizer = cv2.face.LBPHFaceRecognizer_create()
recognizer.read(trainer_yml)

# Load label map (numeric ID → string ID)
try:
    label_map = np.load(labels_npy, allow_pickle=True).item()
except FileNotFoundError:
    print("[WARN] Label map not found. IDs will show as numbers.")
    label_map = {}

# === Setup camera ===
cam = cv2.VideoCapture(0)
cam.set(cv2.CAP_PROP_FRAME_WIDTH, 640)
cam.set(cv2.CAP_PROP_FRAME_HEIGHT, 480)
font = cv2.FONT_HERSHEY_SIMPLEX

# === Face detection using DNN ===
def detect_faces_dnn(frame, conf_threshold=0.6):
    (h, w) = frame.shape[:2]
    blob = cv2.dnn.blobFromImage(cv2.resize(frame, (300, 300)), 1.0,
                                 (300, 300), (104.0, 177.0, 123.0))
    net.setInput(blob)
    detections = net.forward()
    boxes = []
    for i in range(0, detections.shape[2]):
        confidence = detections[0, 0, i, 2]
        if confidence > conf_threshold:
            box = detections[0, 0, i, 3:7] * np.array([w, h, w, h])
            (x1, y1, x2, y2) = box.astype("int")
            x1, y1 = max(0, x1), max(0, y1)
            x2, y2 = min(w - 1, x2), min(h - 1, y2)
            boxes.append((x1, y1, x2 - x1, y2 - y1))
    return boxes

print("[INFO] Starting DNN + LBPH Face Recognition.")
print("[INFO] Press 'q' to quit.")

while True:
    ret, frame = cam.read()
    if not ret:
        break

    gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
    faces = detect_faces_dnn(frame)

    for (x, y, w, h) in faces:
        roi_gray = gray[y:y+h, x:x+w]
        if roi_gray.size == 0:
            continue

        id_num, conf = recognizer.predict(roi_gray)
        # Lower confidence = better match
        label = label_map.get(id_num, f"ID {id_num}")

        # You can adjust this threshold based on your dataset quality
        if conf < 80:
            display_name = f"{label}"
            color = (0, 255, 0)
        else:
            display_name = "Unknown"
            color = (0, 0, 255)

        cv2.rectangle(frame, (x, y), (x+w, y+h), color, 2)
        cv2.putText(frame, f"{display_name} ({conf:.1f})", (x, y - 10),
                    font, 0.7, (255, 255, 255), 2)

    cv2.imshow("DNN + LBPH Face Recognition", frame)
    if cv2.waitKey(1) & 0xFF == ord('q'):
        break

cam.release()
cv2.destroyAllWindows()

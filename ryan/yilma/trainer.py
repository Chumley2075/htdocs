# -*- coding: utf-8 -*-
import cv2
import numpy as np
from pathlib import Path
import mysql.connector

# === Paths ===
data_path = Path('training_images')
trainer_path = Path('trainer')
trainer_path.mkdir(parents=True, exist_ok=True)

# === Create LBPH recognizer ===
recognizer = cv2.face.LBPHFaceRecognizer_create()

def get_images_and_labels(base_path: Path):
    """Read grayscale face images per user folder."""
    face_samples = []
    ids = []
    label_map = {}  # numeric label ? folder name
    next_id = 0

    for person_dir in sorted(base_path.iterdir()):
        if not person_dir.is_dir():
            continue
        person_id = person_dir.name
        print(f"[INFO] Processing {person_id} ...")

        image_paths = list(person_dir.glob("*.jpg"))
        if not image_paths:
            print(f"  [WARN] No images found for {person_id}")
            continue

        label_map[next_id] = person_id

        for img_path in image_paths:
            gray = cv2.imread(str(img_path), cv2.IMREAD_GRAYSCALE)
            if gray is None:
                print(f"  [WARN] Could not read {img_path}")
                continue

            if gray.shape != (200, 200):
                gray = cv2.resize(gray, (200, 200), interpolation=cv2.INTER_AREA)

            gray = cv2.equalizeHist(gray)
            face_samples.append(gray)
            ids.append(next_id)

        next_id += 1

    return face_samples, ids, label_map


def upload_trainer_to_db(trainer_file: Path, labels_file: Path):
    """Upload trainer and label map as BLOBs into face_models table."""
    conn = mysql.connector.connect(
    host="localhost",
    user="flaskuser",
    password="ics311",
    database="UniversityDB"
    )
    cursor = conn.cursor()

    with open(trainer_file, "rb") as f1, open(labels_file, "rb") as f2:
        trainer_blob = f1.read()
        labels_blob = f2.read()

    cursor.execute("""
        INSERT INTO face_models (trainer, labels)
        VALUES (%s, %s)
        ON DUPLICATE KEY UPDATE
        trainer=VALUES(trainer), labels=VALUES(labels)
    """, (trainer_blob, labels_blob))

    conn.commit()
    conn.close()
    print("[INFO] Trainer and labels uploaded to DB.")


# === Train recognizer ===
print("[INFO] Scanning training_images for faces ...")
faces, ids, label_map = get_images_and_labels(data_path)
if not faces:
    raise RuntimeError("No valid training images found!")

print(f"[INFO] Training on {len(faces)} samples across {len(label_map)} person(s)...")
recognizer.train(faces, np.array(ids))

# === Save locally ===
trainer_yml = trainer_path / "trainer.yml"
labels_npy = trainer_path / "labels.npy"
recognizer.write(str(trainer_yml))
np.save(labels_npy, label_map)
print(f"[INFO] Saved {trainer_yml} and {labels_npy}")

# === Upload to DB ===
upload_trainer_to_db(trainer_yml, labels_npy)
print("[INFO] Training complete.")

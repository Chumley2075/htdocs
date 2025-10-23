# -*- coding: utf-8 -*-
import cv2
import numpy as np
from pathlib import Path

# ==== Paths ====
data_path = Path('training_images')
trainer_path = Path('trainer')
trainer_path.mkdir(parents=True, exist_ok=True)

# ==== LBPH Recognizer ====
recognizer = cv2.face.LBPHFaceRecognizer_create()

def get_images_and_labels(base_path: Path):
    """Traverse all subfolders, read pre-cropped grayscale faces, and return samples, numeric labels, and label map."""
    face_samples = []
    ids = []
    label_map = {}  # {numeric_label: folder_name}
    next_id = 0

    for person_dir in sorted(base_path.iterdir()):
        if not person_dir.is_dir():
            continue
        person_id = person_dir.name
        print(f"[INFO] Processing folder: {person_id}")

        image_paths = list(person_dir.glob("*.jpg"))
        if not image_paths:
            print(f"  [WARN] No images found in {person_dir}")
            continue

        # Assign an integer label for this folder
        label_map[next_id] = person_id

        for img_path in image_paths:
            # Load already pre-cropped, grayscale image
            gray = cv2.imread(str(img_path), cv2.IMREAD_GRAYSCALE)
            if gray is None:
                print(f"  [WARN] Failed to read {img_path}")
                continue

            # Verify size and normalize if needed
            if gray.shape != (200, 200):
                gray = cv2.resize(gray, (200, 200), interpolation=cv2.INTER_AREA)

            gray = cv2.equalizeHist(gray)

            face_samples.append(gray)
            ids.append(next_id)

        next_id += 1

    return face_samples, ids, label_map


# ==== Training ====
print("\n[INFO] Scanning training_images/ for subfolders and faces...")
faces, ids, label_map = get_images_and_labels(data_path)

if len(faces) == 0:
    raise RuntimeError("No valid training images found. Make sure captureFaces.py saved images correctly.")

print(f"[INFO] Training LBPH recognizer on {len(faces)} samples across {len(label_map)} person(s)...")
recognizer.train(faces, np.array(ids))

# ==== Save Model & Labels ====
trainer_yml = trainer_path / 'trainer.yml'
recognizer.write(str(trainer_yml))

labels_npy = trainer_path / 'labels.npy'
np.save(labels_npy, label_map)

print(f"\n[INFO] Training complete.")
print(f"[INFO] Saved model: {trainer_yml.resolve()}")
print(f"[INFO] Saved label map: {labels_npy.resolve()}")
print(f"[INFO] Trained IDs: {list(label_map.values())}")

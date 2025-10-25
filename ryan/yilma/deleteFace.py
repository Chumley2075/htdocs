#!/usr/bin/env python3
import sys, os, shutil, traceback

if len(sys.argv) < 2:
    print("Usage: delete_face.py <user_id>")
    sys.exit(2)

user_id = sys.argv[1]

# ---- adjust only this base dir if needed ----
BASE = "/var/www/html/htdocs/ryan/yilma"
# ---------------------------------------------

face_dir = os.path.join(BASE, "training_images", user_id)
EXTRA_FILES = [
    os.path.join(BASE, "trainer", "labels.npy"),
    os.path.join(BASE, "trainer", "trainer.yml"),
    # add more here if needed
]

deleted_anything = False

try:
    # Try deleting the face directory (if present)
    if os.path.isdir(face_dir):
        shutil.rmtree(face_dir)
        print(f"Deleted directory: {face_dir}")
        deleted_anything = True
    else:
        print(f"Dataset directory not found: {face_dir}")

    # Try deleting each extra file independently
    for path in EXTRA_FILES:
        if os.path.isfile(path):
            try:
                os.remove(path)
                print(f"Deleted file: {path}")
                deleted_anything = True
            except OSError as e:
                print(f"Warning: could not delete {path}: {e}")
        else:
            print(f"Not found (skipping): {path}")

    if deleted_anything:
        sys.exit(0)
    else:
        print(f"No face data or trainer/labels found for user: {user_id}")
        sys.exit(1)

except Exception:
    print("Unexpected error during deletion:")
    traceback.print_exc()
    sys.exit(2)

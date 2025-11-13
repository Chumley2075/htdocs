#!/usr/bin/env python3.11
# -*- coding: utf-8 -*-
import sys, os, shutil, subprocess, traceback

BASE_DIR        = "/var/www/html/htdocs/ryan/yilma"
TRAINING_ROOT   = f"{BASE_DIR}/training_images"
TRAIN_DIR       = f"{BASE_DIR}/trainer"
TRAINER_SCRIPT  = f"{BASE_DIR}/trainer.py"
EXTRA_FILES     = [
    f"{TRAIN_DIR}/trainer.yml",
    f"{TRAIN_DIR}/labels.npy",
]

def main() -> int:
    if len(sys.argv) < 2:
        print("Usage: deleteFace.py <user_id>")
        return 2

    user_id = sys.argv[1]

   
    try:
        entries = os.listdir(TRAINING_ROOT)  
    except FileNotFoundError:
        print(f"Training root not found: {TRAINING_ROOT}")
        return 2

    if user_id not in entries:
        print(f"No face data found for user (case-sensitive): '{user_id}'")
        return 1   

    face_dir = os.path.join(TRAINING_ROOT, user_id)

    
    try:
        shutil.rmtree(face_dir)
        print(f"Deleted user directory: {face_dir}")
    except PermissionError as e:
        print(f"Permission denied removing {face_dir}: {e}")
        return 2
    except FileNotFoundError:
        print(f"Directory vanished before delete: {face_dir}")
        return 1
    except OSError as e:
        print(f"OS error removing {face_dir}: {e}")
        return 2

   
    for path in EXTRA_FILES:
        if os.path.isfile(path):
            try:
                os.remove(path)
                print(f"Deleted: {path}")
            except PermissionError as e:
                print(f"Permission denied deleting {path}: {e}")
            except OSError as e:
                print(f"Could not delete {path}: {e}")
        else:
            print(f"(skip) Not found: {path}")

    
    try:
        subprocess.Popen(
            [sys.executable, TRAINER_SCRIPT],
            cwd=BASE_DIR,                 
            stdout=subprocess.DEVNULL,
            stderr=subprocess.STDOUT,
        )
        print("Retraining started in background.")
    except Exception:
        print("Failed to start trainer:")
        traceback.print_exc()
        
    return 0

if __name__ == "__main__":
    sys.exit(main())

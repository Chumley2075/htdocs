#!/usr/bin/env python3.11
# -*- coding: utf-8 -*-
import sys, os, shutil, subprocess, traceback

BASE_DIR        = "/var/www/html/htdocs/ryan/yilma"
TRAINING_ROOT   = f"{BASE_DIR}/training_images"
TRAINER_SCRIPT  = f"{BASE_DIR}/trainer.py"

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

    try:
        result = subprocess.run(
            [sys.executable, TRAINER_SCRIPT],
            cwd=BASE_DIR,
            capture_output=True,
            text=True,
            check=False,
        )
        if result.stdout:
            print(result.stdout.strip())
        if result.stderr:
            print(result.stderr.strip())
        if result.returncode != 0:
            print(f"Retraining failed with exit code {result.returncode}")
            return 3
        print("Retraining completed.")
    except Exception:
        print("Failed to start trainer:")
        traceback.print_exc()
        return 3

    return 0

if __name__ == "__main__":
    sys.exit(main())

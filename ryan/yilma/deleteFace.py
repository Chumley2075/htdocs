#!/usr/bin/env python3.11
# -*- coding: utf-8 -*-
import os
import shutil
import subprocess
import sys
import traceback
import unicodedata

BASE_DIR = "/var/www/html/htdocs/ryan/yilma"
TRAINING_ROOT = f"{BASE_DIR}/training_images"
TRAINER_SCRIPT = f"{BASE_DIR}/trainer.py"
MAX_SAMPLE_DIRS = 20


def normalize_user_id(user_id: str) -> str:
    return unicodedata.normalize("NFKC", (user_id or "").strip())


def list_training_dirs():
    try:
        entries = os.listdir(TRAINING_ROOT)
    except FileNotFoundError:
        return None, f"Training root not found: {TRAINING_ROOT}"
    except OSError as e:
        return None, f"Could not read training root '{TRAINING_ROOT}': {e}"

    dirs = []
    for entry in entries:
        full = os.path.join(TRAINING_ROOT, entry)
        if os.path.isdir(full):
            dirs.append(entry)
    dirs.sort()
    return dirs, None


def resolve_face_dir(user_id: str, dirs):
    if user_id in dirs:
        return user_id, "exact"
    case_matches = [entry for entry in dirs if entry.lower() == user_id.lower()]
    if len(case_matches) == 1:
        return case_matches[0], "case-insensitive"
    if len(case_matches) > 1:
        return None, "ambiguous"
    return None, "missing"


def run_retraining() -> int:
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
        return 0
    except Exception:
        print("Failed to start trainer:")
        traceback.print_exc()
        return 3


def main() -> int:
    if len(sys.argv) < 2:
        print("Usage: deleteFace.py <user_id>")
        return 2

    user_id = normalize_user_id(sys.argv[1])
    if not user_id:
        print("Empty user_id after normalization.")
        return 2

    dirs, list_error = list_training_dirs()
    if list_error:
        print(list_error)
        return 2

    matched_dir, match_type = resolve_face_dir(user_id, dirs)
    if matched_dir is None:
        print(f"No face directory match found for '{user_id}'.")
        if match_type == "ambiguous":
            print("Multiple case-insensitive matches exist; deletion is unsafe.")
        if dirs:
            sample = ", ".join(dirs[:MAX_SAMPLE_DIRS])
            more = len(dirs) - min(len(dirs), MAX_SAMPLE_DIRS)
            print(f"Available face directories ({len(dirs)} total): {sample}")
            if more > 0:
                print(f"...and {more} more")
        else:
            print("No face directories currently exist in training_images.")

        retrain_code = run_retraining()
        if retrain_code == 0:
            print("No matching folder was deleted, but model retraining completed to clear stale labels.")
            return 4
        return 1

    face_dir = os.path.join(TRAINING_ROOT, matched_dir)
    if match_type != "exact":
        print(f"Resolved '{user_id}' to folder '{matched_dir}' via {match_type} match.")

    try:
        shutil.rmtree(face_dir)
        print(f"Deleted user directory: {face_dir}")
    except PermissionError as e:
        print(f"Permission denied removing {face_dir}: {e}")
        return 2
    except FileNotFoundError:
        print(f"Directory vanished before delete: {face_dir}")
        retrain_code = run_retraining()
        return 4 if retrain_code == 0 else 1
    except OSError as e:
        print(f"OS error removing {face_dir}: {e}")
        return 2

    retrain_code = run_retraining()
    if retrain_code != 0:
        return 3
    return 0


if __name__ == "__main__":
    sys.exit(main())

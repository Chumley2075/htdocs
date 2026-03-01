#!/usr/bin/env python3
"""Launch both local Flask services used by the project.

- cameraTest.py (face capture) on port 5000
- flaskRecognize.py (recognition/labels/door state) on port 5001
"""

import signal
import subprocess
import sys
import time
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent
SERVICES = [
    ("capture", BASE_DIR / "cameraTest.py"),
    ("recognize", BASE_DIR / "flaskRecognize.py"),
]


class ServiceRunner:
    def __init__(self):
        self.processes = []
        self.stopping = False

    def start_all(self):
        for name, script in SERVICES:
            if not script.is_file():
                raise FileNotFoundError(f"Missing service script: {script}")

            proc = subprocess.Popen(
                [sys.executable, str(script)],
                cwd=str(BASE_DIR),
            )
            self.processes.append((name, proc))
            print(f"[STARTED] {name}: pid={proc.pid}, script={script.name}")

    def stop_all(self):
        if self.stopping:
            return
        self.stopping = True

        for name, proc in self.processes:
            if proc.poll() is None:
                print(f"[STOP] Sending SIGTERM to {name} (pid={proc.pid})")
                proc.terminate()

        deadline = time.time() + 5
        while time.time() < deadline:
            if all(proc.poll() is not None for _, proc in self.processes):
                return
            time.sleep(0.1)

        for name, proc in self.processes:
            if proc.poll() is None:
                print(f"[KILL] Force killing {name} (pid={proc.pid})")
                proc.kill()

    def run(self):
        def _handle_signal(signum, _frame):
            print(f"\\n[INFO] Received signal {signum}. Shutting down services...")
            self.stop_all()

        signal.signal(signal.SIGINT, _handle_signal)
        signal.signal(signal.SIGTERM, _handle_signal)

        self.start_all()
        print("[INFO] Both Flask services are running. Press Ctrl+C to stop.")

        try:
            while True:
                for name, proc in self.processes:
                    code = proc.poll()
                    if code is not None:
                        print(f"[ERROR] Service '{name}' exited with code {code}. Stopping remaining services...")
                        self.stop_all()
                        return code
                time.sleep(0.5)
        finally:
            self.stop_all()


def main():
    runner = ServiceRunner()
    try:
        return runner.run()
    except FileNotFoundError as exc:
        print(f"[ERROR] {exc}")
        return 1


if __name__ == "__main__":
    sys.exit(main())

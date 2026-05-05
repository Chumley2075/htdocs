# Ryan System Guide

This repository section has two code areas:

- `ryan/app/` for the classroom web app.
- `ryan/yilma/` for the portal app and face-recognition services.

## Canonical Structure

### Classroom (`ryan/app`)
- `core/database.php`: shared business logic and DB access.
- `controllers/`: classroom endpoints.
- `pages/`: classroom UI.

### Yilma Portal + Recognition (`ryan/yilma`)
- `app/pages/`: portal page implementations (`login_page.php`, `admin_page.php`, `main_menu_page.php`).
- `app/controllers/`: portal API/controller implementations.
- `app/database.php`: local shim to shared DB class.
- root `*.php`: URL compatibility shims only.
- root `*.py`: recognition/capture/training runtime scripts.
- `data/`: all recognition assets and runtime data.

### Yilma Data (`ryan/yilma/data`)
- `models/`: model files.
- `training_images/`: face enrollment images.
- `trainer/`: generated trainer artifacts.
- `scan_images/`: recognition snapshots.
- `db_snapshots/`: SQL snapshot files.

## What To Edit

- Access decisions and DB behavior: `ryan/app/core/database.php`
- Classroom API behavior: `ryan/app/controllers/`
- Classroom UI: `ryan/app/pages/`
- Portal UI: `ryan/yilma/app/pages/`
- Portal endpoints: `ryan/yilma/app/controllers/`
- Recognition behavior: `ryan/yilma/recognize.py`
- Capture/enrollment flow: `ryan/yilma/captureFaces.py` and `ryan/yilma/cameraTest.py`
- Trainer pipeline: `ryan/yilma/trainer.py` and `ryan/yilma/deleteFace.py`

## Operational Notes

- Root PHP files are intentionally thin compatibility entrypoints.
- Yilma runtime data is now expected under `ryan/yilma/data/*` directly.
- Keep `__pycache__` and generated image artifacts out of version control.

# Biometric Access System Crash Course

Use this file when you need to make changes quickly without re-learning the whole project.

## 1) 60-Second Orientation

- `ryan/app/` = classroom web app (display, class info, access decision endpoints).
- `ryan/yilma/` = portal app (admin/prof/security desk) + Python face services.
- `ryan/yilma/data/` = all face runtime assets and generated artifacts.

If you are changing behavior, start in canonical files under `app/` or Python runtime files, not route shim files.

## 2) Canonical Layout

### Classroom Side (`ryan/app`)
- `core/database.php`: DB + business rules (users, roles, access, logs, attendance).
- `controllers/`: classroom endpoints.
- `pages/`: classroom UI page implementation.

### Portal Side (`ryan/yilma/app`)
- `pages/login_page.php`: portal login flow.
- `pages/admin_page.php`: user/face/door/log admin UI.
- `pages/main_menu_page.php`: reporting UI.
- `controllers/`: face provision, lookup, delete, logout handlers.
- `database.php`: shim to shared DB class.

### Face Runtime (`ryan/yilma/*.py`)
- `recognize.py`: recognition loop + scan result + door unlock logic.
- `flaskRecognize.py`: recognition HTTP service (port 5001).
- `captureFaces.py`: enrollment capture logic.
- `cameraTest.py`: capture HTTP service (port 5000).
- `trainer.py`: rebuilds model artifacts from enrollment images.
- `deleteFace.py`: deletes a user face folder then retrains.

### Runtime Data (`ryan/yilma/data`)
- `models/`: DNN model files.
- `training_images/`: per-user enrollment images.
- `trainer/`: generated trainer outputs.
- `scan_images/`: snapshot images produced by scans.
- `db_snapshots/`: SQL snapshot/reference file(s).

## 3) Edit-By-Task Map

- Change access/authorization rules:
  - `ryan/app/core/database.php`
- Change classroom API payloads:
  - `ryan/app/controllers/`
- Change classroom UI:
  - `ryan/app/pages/classroom_page.php`
- Change portal page behavior/UI:
  - `ryan/yilma/app/pages/`
- Change portal face/user actions:
  - `ryan/yilma/app/controllers/`
- Change recognition decisions, labels, snapshots, unlock flow:
  - `ryan/yilma/recognize.py`
- Change enrollment capture behavior:
  - `ryan/yilma/captureFaces.py`
- Change train/retrain behavior:
  - `ryan/yilma/trainer.py`, `ryan/yilma/deleteFace.py`

## 4) Route Shim Rule

These are compatibility entrypoints and should stay thin:
- `ryan/index.php`, `ryan/labels.php`, `ryan/getClassInfo.php`, `ryan/database.php`
- `ryan/yilma/index.php`, `admin.php`, `mainMenu.php`, `faceProvisionUser.php`, `faceUserLookup.php`, `deleteFace.php`, `logout.php`

Do feature changes in the canonical files they include.

## 5) Current Path Convention (Important)

Yilma runtime now uses explicit `data/*` paths.

Do not reintroduce top-level alias directories such as:
- `ryan/yilma/models`
- `ryan/yilma/trainer`
- `ryan/yilma/training_images`
- `ryan/yilma/scan_images`

Use only:
- `ryan/yilma/data/models`
- `ryan/yilma/data/trainer`
- `ryan/yilma/data/training_images`
- `ryan/yilma/data/scan_images`

## 6) Safe Change Workflow

1. Edit the canonical file(s).
2. Syntax check changed files:
   - `php -l <file.php>`
   - `python3 -m py_compile <file.py>`
3. Test the user flow end-to-end.
4. Verify changed paths and file set with `git status --short`.

## 7) Docs Scope

Only two project docs should exist:
- `ryan/README.md`
- `ryan/docs/CRASH_COURSE_ONE_PAGER.md`

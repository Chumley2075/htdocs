# Biometric Access System: Beginner Crash Course

This one-pager is the fastest way to understand where code lives and what to edit.

## 1) If You Are New, Do This First
1. Read this file once.
2. Open `ryan/README.md` for quick links.
3. Find your task in the "What to edit for X" table.
4. Edit canonical files in `ryan/app/*` or `ryan/yilma/app/*` first.
5. Keep route shim files thin unless you are changing URLs.

## 2) High-Level Mental Model
- `ryan/` = classroom web app + shared PHP business logic.
- `ryan/yilma/` = admin/professor portal + Python face-recognition services.
- PHP decides policy/business rules.
- Python handles camera, face capture, recognition, and snapshot generation.

## 3) Folder Map (Organized)
### `ryan/`
- `app/core/database.php` = shared database and core access logic.
- `app/controllers/` = API/controller endpoints used by classroom pages.
- `app/pages/` = main classroom UI pages.
- root `*.php` files = URL compatibility shims (`index.php`, `labels.php`, etc.).

### `ryan/yilma/`
- `app/pages/` = portal pages (login, admin, reports).
- `app/controllers/` = portal APIs (provision user, lookup, delete face, logout).
- root `*.py` files = recognition/capture/training services.
- `data/` = organized runtime/model/storage area:
  - `data/models/` = DNN model files.
  - `data/training_images/` = captured face images for training.
  - `data/trainer/` = generated trainer artifacts.
  - `data/scan_images/` = scan snapshots used in logs.
  - `data/db_snapshots/universityDB(2).sql` = DB backup reference file.

### Compatibility links you can keep using
The old paths still work and map to `data/*`:
- `ryan/yilma/models -> data/models`
- `ryan/yilma/training_images -> data/training_images`
- `ryan/yilma/trainer -> data/trainer`
- `ryan/yilma/scan_images -> data/scan_images`
- `ryan/yilma/universityDB(2).sql -> data/db_snapshots/universityDB(2).sql`

## 4) Canonical Files You Usually Edit
### Classroom side
- `ryan/app/core/database.php`
- `ryan/app/controllers/labels_controller.php`
- `ryan/app/controllers/class_info_controller.php`
- `ryan/app/pages/classroom_page.php`

### Portal side
- `ryan/yilma/app/pages/login_page.php`
- `ryan/yilma/app/pages/admin_page.php`
- `ryan/yilma/app/pages/main_menu_page.php`
- `ryan/yilma/app/controllers/face_provision_controller.php`
- `ryan/yilma/app/controllers/face_user_lookup_controller.php`
- `ryan/yilma/app/controllers/delete_face_controller.php`

### Recognition side
- `ryan/yilma/recognize.py`
- `ryan/yilma/flaskRecognize.py`
- `ryan/yilma/cameraTest.py`
- `ryan/yilma/captureFaces.py`
- `ryan/yilma/deleteFace.py`
- `ryan/yilma/trainer.py`

## 5) What To Edit For X
| You need to change... | Start here | Usually also touch |
|---|---|---|
| Who can enter a room | `ryan/app/core/database.php` (`canUserEnterRoom`) | `ryan/app/controllers/labels_controller.php` |
| Classroom allow/deny message | `ryan/app/controllers/labels_controller.php` | `ryan/app/pages/classroom_page.php` |
| Class/session info payload | `ryan/app/controllers/class_info_controller.php` | `ryan/app/pages/classroom_page.php` |
| Admin portal UI behavior | `ryan/yilma/app/pages/admin_page.php` | `ryan/app/core/database.php` |
| Face onboarding flow | `ryan/yilma/app/controllers/face_provision_controller.php` | `ryan/yilma/app/pages/admin_page.php`, `ryan/yilma/captureFaces.py` |
| Face delete behavior | `ryan/yilma/app/controllers/delete_face_controller.php` | `ryan/yilma/deleteFace.py`, `ryan/yilma/trainer.py` |
| Auto-unlock behavior | `ryan/yilma/recognize.py` | `ryan/app/core/database.php` |
| Snapshot save/log behavior | `ryan/yilma/recognize.py` | `ryan/app/controllers/labels_controller.php`, `ryan/app/core/database.php` |
| Professor report filters | `ryan/yilma/app/pages/main_menu_page.php` | `ryan/app/core/database.php` |

## 6) UI Edit Map (All Pages)
Use this when you need to change layout, text, buttons, spacing, colors, or client-side behavior.

| Page | URL/entry | Edit HTML/PHP markup here | Edit CSS here | Edit JS behavior here |
|---|---|---|---|---|
| Classroom display | `ryan/index.php` | `ryan/app/pages/classroom_page.php` | `ryan/styles.css` | Inline `<script>` blocks inside `ryan/app/pages/classroom_page.php` |
| Portal login | `ryan/yilma/index.php` | `ryan/yilma/app/pages/login_page.php` | Inline `<style>` in `login_page.php` and shared `ryan/yilma/style.css` | Mostly server-side in `login_page.php` (minimal client JS) |
| Admin console (Users/Faces/Doors/Logs tabs) | `ryan/yilma/admin.php` | `ryan/yilma/app/pages/admin_page.php` | `ryan/yilma/style.css` (especially `.admin-body`, `.tab-panel`, form/table classes) | Inline `<script>` blocks near bottom of `admin_page.php` |
| Professor/Admin reports | `ryan/yilma/mainMenu.php` | `ryan/yilma/app/pages/main_menu_page.php` | `ryan/yilma/style.css` (especially `body.report-page` and report classes) | Inline `<script>` in `main_menu_page.php` head (date-range/report controls) |
| Face capture service page (debug/service UI) | `http://<host>:5000/` | `ryan/yilma/cameraTest.py` `index()` template string | Inline style in that template string | `cameraTest.py` route handlers (`/video_feed`, `/capture_status`, `/stop_feed`) |
| Recognition service page (debug/service UI) | `http://<host>:5001/` | `ryan/yilma/flaskRecognize.py` `index()` template string | Inline style in that template string | `flaskRecognize.py` routes and `recognize.py` runtime logic |

Important UI notes:
- `ryan/classroom.js` is currently not loaded by `classroom_page.php`. Classroom behavior is driven by inline scripts in `classroom_page.php`.
- `ryan/yilma/style.css` is shared by login, admin, and report pages. Use page-scoped selectors like `.admin-body ...` and `body.report-page ...` to avoid cross-page regressions.
- Root PHP files (`index.php`, `admin.php`, `mainMenu.php`) are route shims. UI edits should happen in `app/pages/*`.

## 7) Runtime Flow (Simple Version)
1. Classroom page loads via `ryan/index.php` -> `ryan/app/pages/classroom_page.php`.
2. It calls `ryan/getClassInfo.php` and `ryan/labels.php`.
3. `labels_controller.php` asks `database.php` if entry is allowed.
4. Python recognition service (`flaskRecognize.py` + `recognize.py`) provides scan status and snapshots.
5. Portal pages under `ryan/yilma/app/pages` manage users/faces/doors/reports.

## 8) Route Shim Files (Normally Leave Alone)
- `ryan/index.php`, `ryan/labels.php`, `ryan/getClassInfo.php`, `ryan/database.php`
- `ryan/yilma/index.php`, `ryan/yilma/admin.php`, `ryan/yilma/mainMenu.php`
- `ryan/yilma/faceProvisionUser.php`, `ryan/yilma/faceUserLookup.php`
- `ryan/yilma/deleteFace.php`, `ryan/yilma/logout.php`

## 9) Quick Safety Checklist
1. Edit canonical files first.
2. Run syntax checks:
   - `php -l <file.php>`
   - `python3 -m py_compile <file.py>`
3. Verify the affected flow end-to-end.
4. Check diff with `git status --short`.

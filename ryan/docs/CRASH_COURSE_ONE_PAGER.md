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

## 10) File One-Liners (What Each File Does)
### `ryan/` classroom app
| File | One-liner |
|---|---|
| `ryan/index.php` | URL shim that loads the classroom page. |
| `ryan/labels.php` | URL shim for classroom label/access API routes. |
| `ryan/getClassInfo.php` | URL shim for class/session info API. |
| `ryan/database.php` | URL shim exposing the shared `database` class. |
| `ryan/styles.css` | Main stylesheet for classroom display + face-scan modal UI. |
| `ryan/classroom.js` | Legacy classroom JS placeholder (currently not wired into `classroom_page.php`). |
| `ryan/app/core/database.php` | Core shared DB/business logic (roles, access rules, attendance, logs, doors). |
| `ryan/app/controllers/class_info_controller.php` | Returns room/class status data used by classroom display. |
| `ryan/app/controllers/labels_controller.php` | Handles scan label decisions and room-entry authorization flow. |
| `ryan/app/pages/classroom_page.php` | Classroom UI markup and inline JS for live status + scan modal behavior. |

### `ryan/yilma/` portal app
| File | One-liner |
|---|---|
| `ryan/yilma/index.php` | URL shim that loads the portal login page. |
| `ryan/yilma/admin.php` | URL shim that loads admin console page. |
| `ryan/yilma/mainMenu.php` | URL shim that loads professor/admin report page. |
| `ryan/yilma/faceProvisionUser.php` | URL shim for user+face provisioning endpoint. |
| `ryan/yilma/faceUserLookup.php` | URL shim for existing-user lookup during enrollment. |
| `ryan/yilma/deleteFace.php` | URL shim for deleting a user’s face data. |
| `ryan/yilma/logout.php` | URL shim for logout endpoint. |
| `ryan/yilma/style.css` | Shared stylesheet for login, admin, and reporting pages. |
| `ryan/yilma/app/database.php` | Portal-local shim that imports shared DB class from `ryan/database.php`. |
| `ryan/yilma/app/pages/login_page.php` | Login UI and portal auth redirect handling. |
| `ryan/yilma/app/pages/admin_page.php` | Admin console UI for users, faces, door control, and logs. |
| `ryan/yilma/app/pages/main_menu_page.php` | Professor/admin attendance reporting dashboard UI. |
| `ryan/yilma/app/controllers/face_provision_controller.php` | API controller to create/update user profile before face enrollment. |
| `ryan/yilma/app/controllers/face_user_lookup_controller.php` | API controller to fetch existing user details for enrollment flow. |
| `ryan/yilma/app/controllers/delete_face_controller.php` | API controller that coordinates face deletion and retraining actions. |
| `ryan/yilma/app/controllers/logout_controller.php` | API/controller endpoint that ends portal session. |

### `ryan/yilma/` recognition and capture runtime
| File | One-liner |
|---|---|
| `ryan/yilma/cameraTest.py` | Flask service on port 5000 for live face enrollment capture stream/status. |
| `ryan/yilma/camera_device.py` | Camera open/warmup/release helper utilities for capture/recognition services. |
| `ryan/yilma/captureFaces.py` | Captures and stores enrollment face samples, then triggers model retraining. |
| `ryan/yilma/deleteFace.py` | Removes a user’s face training data and retriggers trainer pipeline. |
| `ryan/yilma/depth_helper.py` | Depth-processing helper used to improve recognition/scan reliability. |
| `ryan/yilma/flaskRecognize.py` | Flask service on port 5001 exposing recognition, scan result, and door-state APIs. |
| `ryan/yilma/recognize.py` | Core recognition loop: face match, scan snapshots, and auto-unlock decisions. |
| `ryan/yilma/run_flask_services.py` | Convenience launcher that starts both Flask services together. |
| `ryan/yilma/sensorTEST.py` | Hardware/sensor test script for local diagnostics. |
| `ryan/yilma/trainer.py` | Builds LBPH trainer artifacts from training images and uploads trainer data to DB. |

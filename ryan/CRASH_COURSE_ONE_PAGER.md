# Crash Course: Where Everything Lives

## 1) Big Picture
This repo has two app surfaces that share the same database:
- `ryan/` = classroom-facing scan UI and attendance/entry API.
- `ryan/yilma/` = admin + professor experience and the face-recognition services.

Most business logic is in:
- `ryan/database.php` (shared DB and access/role helpers)
- `ryan/labels.php` (classroom entry + attendance decision endpoint)
- `ryan/yilma/recognize.py` (camera recognition + scan snapshot + auto-door unlock rules)

---

## 2) Feature-to-File Map

### Classroom Scan + Enter Room Flow
- UI screen: `ryan/index.php`
- UI behavior/helpers: `ryan/classroom.js` (if used by page) + inline JS in `ryan/index.php`
- Entry decision endpoint: `ryan/labels.php`
- Current class by room + schedule: `ryan/getClassInfo.php`
- Scan CSS: `ryan/styles.css`

### Attendance + Roster Checks
- Decision logic and attendance write: `ryan/labels.php`
- DB methods:
  - `getCurrentClassID()`
  - `isUserEnrolledInClass()`
  - `insertAttendance()`
  in `ryan/database.php`

### Admin/Professor/Security Desk Portal
- Login + role-based routing: `ryan/yilma/index.php`
- Admin console (users/faces/doors/logs): `ryan/yilma/admin.php`
- Professor reporting/dashboard: `ryan/yilma/mainMenu.php`
- Portal CSS: `ryan/yilma/style.css`

### Face Enrollment + Deletion
- Face onboarding API: `ryan/yilma/faceProvisionUser.php`
- Existing-user lookup for onboarding: `ryan/yilma/faceUserLookup.php`
- Delete face endpoint/script: `ryan/yilma/deleteFace.php`, `ryan/yilma/deleteFace.py`
- Capture utility: `ryan/yilma/captureFaces.py`
- Model retraining: `ryan/yilma/trainer.py`

### Recognition Service + Door Auto Unlock
- Flask wrapper API (ports/endpoints): `ryan/yilma/flaskRecognize.py`
- Core recognition engine + snapshots + door logic: `ryan/yilma/recognize.py`
- Camera/depth helpers: `ryan/yilma/camera_device.py`, `ryan/yilma/depth_helper.py`
- Service launcher: `ryan/yilma/run_flask_services.py`

### Shared Data/Permissions
- Shared DB layer + schema-ensure helpers: `ryan/database.php`
- Legacy schema snapshot (reference only): `ryan/yilma/universityDB(2).sql`

---

## 3) Current Access Rules (Quick Mental Model)
- Student: must be in current room's active class roster.
- Professor: normal class behavior; can unlock locked doors via authorized face scan flow.
- Admin: can enter any room and can unlock locked doors via face scan.
- Security Desk: can enter any room and can unlock locked doors via face scan.

Where this is enforced:
- Room-entry decision: `ryan/labels.php` via `database.php::canUserEnterRoom()`
- Door auto-unlock recognition path: `ryan/yilma/recognize.py`

---

## 4) Logging + Images
- All operational events are stored in `admin_logs` (via `database.php::logAdminEvent()` or Python inserts).
- `scan_image_path` is used for scan snapshots.
- Privileged room entry logs (Admin/Security Desk) are written by `ryan/labels.php`.
- Auto-unlock by face scan logs are written by `ryan/yilma/recognize.py`.

---

## 5) If You Need To Change X, Start Here
- "Who can enter this room?": `ryan/database.php::canUserEnterRoom()` then `ryan/labels.php`
- "Who can unlock doors from face?": `ryan/yilma/recognize.py` (`get_door_unlock_role`, `evaluate_door_auto_unlock`)
- "Admin tabs/permissions": `ryan/yilma/admin.php` + `database.php::getUserPermissions()`
- "Face onboarding fields/validation": `ryan/yilma/admin.php` + `ryan/yilma/faceProvisionUser.php`
- "Professor reports": `ryan/yilma/mainMenu.php` + `database.php` attendance query methods

---

## 6) Safe Edit Order For New Contributors
1. Update shared logic in `ryan/database.php` first.
2. Update calling endpoint (`labels.php` or `admin.php` or `recognize.py`).
3. Update UI messaging/text in the related PHP page.
4. Run quick syntax checks:
   - `php -l <file>.php`
   - `python3 -m py_compile <file>.py`

This order keeps behavior consistent and avoids split-brain rules across files.


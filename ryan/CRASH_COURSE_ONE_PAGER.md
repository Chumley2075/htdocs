# Biometric Access System: Single Project Documentation

This is the single source of truth for project documentation.

## 1) New Contributor Quickstart
If someone asks for a small feature change and you are new to this repo:
1. Read this document once.
2. Find the feature in the "Small Feature Change Index" table below.
3. Edit canonical files under `ryan/app/*`, `ryan/yilma/app/*`, and `ryan/yilma/*.py`.
4. Keep route shim files thin and unchanged unless routing itself changes.
5. Run syntax checks before handoff.

## 2) Project Layout

### `ryan/`
- Classroom scan web app (room display, scan modal, room entry checks).
- Shared DB/business logic used by both classroom and portal flows.

### `ryan/yilma/`
- Admin/professor/security desk portal.
- Face recognition and camera services.

## 3) Canonical Files (Edit These First)

### Classroom side
- `ryan/app/core/database.php` (shared `database` class)
- `ryan/app/controllers/labels_controller.php` (entry checks + attendance + privileged door logging)
- `ryan/app/controllers/class_info_controller.php` (class/session info API)
- `ryan/app/pages/classroom_page.php` (main classroom UI)

### Portal side
- `ryan/yilma/app/pages/login_page.php`
- `ryan/yilma/app/pages/admin_page.php`
- `ryan/yilma/app/pages/main_menu_page.php`
- `ryan/yilma/app/controllers/face_provision_controller.php`
- `ryan/yilma/app/controllers/face_user_lookup_controller.php`
- `ryan/yilma/app/controllers/delete_face_controller.php`
- `ryan/yilma/app/controllers/logout_controller.php`

### Recognition/runtime side
- `ryan/yilma/recognize.py` (core recognition + door auto-unlock + scan snapshots)
- `ryan/yilma/flaskRecognize.py` (Flask API wrapper)
- `ryan/yilma/run_flask_services.py` (service launcher)
- `ryan/yilma/camera_device.py`, `ryan/yilma/depth_helper.py` (hardware/depth helpers)
- `ryan/yilma/captureFaces.py`, `ryan/yilma/deleteFace.py`, `ryan/yilma/trainer.py` (enrollment/training utilities)

## 4) Compatibility Route Shims (Keep URLs Stable)
These files are entrypoints that include canonical files. They should remain thin.

- `ryan/index.php`
- `ryan/labels.php`
- `ryan/getClassInfo.php`
- `ryan/database.php`
- `ryan/yilma/index.php`
- `ryan/yilma/admin.php`
- `ryan/yilma/mainMenu.php`
- `ryan/yilma/faceProvisionUser.php`
- `ryan/yilma/faceUserLookup.php`
- `ryan/yilma/deleteFace.php`
- `ryan/yilma/logout.php`

## 5) Request/Flow Map (Where Logic Runs)

### Classroom flow
1. Browser loads `ryan/index.php` (shim) -> `ryan/app/pages/classroom_page.php`.
2. Page requests class card data from `ryan/getClassInfo.php` -> `handle_class_info_request()` in `class_info_controller.php`.
3. Face scan state comes from recognition endpoint `/scan_result`.
4. Entry decision call goes to `ryan/labels.php?action=can_enter` -> `handle_labels_request()` in `labels_controller.php`.
5. Final allow/deny is determined by `database::canUserEnterRoom()` in `ryan/app/core/database.php`.
6. If student access is valid, attendance is inserted.
7. If admin/security desk face opens the door, privileged admin log entry is written.

### Recognition flow
1. Flask app in `flaskRecognize.py` serves `/video_feed`, `/scan_result`, `/label`, `/door_state`, `/opt_out_face`.
2. `recognize.py` controls recognition state, snapshots, and auto-unlock decisions.
3. Door state checks rely on DB methods (`getDoorState`, `setDoorState`).

### Portal flow
1. Login loads `ryan/yilma/index.php` (shim) -> `app/pages/login_page.php`.
2. Admin console is `app/pages/admin_page.php`.
3. Face APIs:
- New user + face onboarding: `app/controllers/face_provision_controller.php`
- Existing user lookup: `app/controllers/face_user_lookup_controller.php`
- Face deletion: `app/controllers/delete_face_controller.php` (invokes `deleteFace.py`)
4. Professor reporting UI is `app/pages/main_menu_page.php` (supports `day`, `last7`, `custom`).

## 6) Role and Access Mental Model

### Stored roles
- `users.is_admin`
- `users.is_prof`
- `users.is_student`

### Security desk role
- Not a hardcoded credential/user.
- Determined by permission profile via `database::isSecurityDeskUser()`:
- `can_manage_users = 0`
- `can_manage_faces = 0`
- `can_manage_doors = 1`
- `can_view_logs = 1`
- and not admin/professor

### Entry rules
- Unknown/no single actionable face match: deny.
- Student: must be enrolled in active class for that room.
- Admin: may enter any room.
- Security desk: may enter any room.
- Central decision method: `database::canUserEnterRoom($roomNumber, $username)`.

## 7) Folder Ownership Notes

### `ryan/app/`
- `core/database.php`: shared DB class and core business rules.
- `controllers/labels_controller.php`: classroom scan decision path and attendance/logging side effects.
- `controllers/class_info_controller.php`: class/session status JSON.

### `ryan/yilma/app/`
- `pages/login_page.php`: portal login and routing.
- `pages/admin_page.php`: admin UI (users, faces, doors, logs).
- `pages/main_menu_page.php`: professor/admin reports.
- `controllers/face_provision_controller.php`: create user + face onboarding API.
- `controllers/face_user_lookup_controller.php`: existing user lookup API.
- `controllers/delete_face_controller.php`: face deletion API.
- `controllers/logout_controller.php`: logout endpoint.
- `database.php`: local shim to shared DB class (`../../database.php`).

## 8) Small Feature Change Index
Use this map to quickly route typical requests.

| Request Type | First File(s) To Edit | Typical Secondary File(s) |
|---|---|---|
| Change room entry rule (who can enter) | `ryan/app/core/database.php` (`canUserEnterRoom`) | `ryan/app/controllers/labels_controller.php` |
| Change no-match / denied messaging | `ryan/app/controllers/labels_controller.php` | `ryan/app/pages/classroom_page.php` |
| Change attendance write behavior | `ryan/app/controllers/labels_controller.php` | `ryan/app/core/database.php` |
| Change class/session room info payload | `ryan/app/controllers/class_info_controller.php` | `ryan/app/pages/classroom_page.php` |
| Change login behavior/redirects (portal) | `ryan/yilma/app/pages/login_page.php` | `ryan/app/core/database.php` |
| Change admin tab visibility/permissions | `ryan/yilma/app/pages/admin_page.php` | `ryan/app/core/database.php` |
| Change user creation roles/password requirements | `ryan/yilma/app/pages/admin_page.php` | `ryan/yilma/app/controllers/face_provision_controller.php` |
| Change face onboarding UI copy/fields | `ryan/yilma/app/pages/admin_page.php` | `ryan/yilma/app/controllers/face_user_lookup_controller.php` |
| Change face deletion behavior | `ryan/yilma/app/controllers/delete_face_controller.php` | `ryan/yilma/deleteFace.py`, `ryan/app/core/database.php` |
| Change admin activity log entries/fields | `ryan/app/core/database.php` (`logAdminEvent`) | `ryan/yilma/app/pages/admin_page.php`, `ryan/yilma/recognize.py` |
| Change door lock/unlock UI actions | `ryan/yilma/app/pages/admin_page.php` | `ryan/app/core/database.php`, `ryan/yilma/recognize.py` |
| Change auto-unlock role rules | `ryan/yilma/recognize.py` | `ryan/app/core/database.php`, `ryan/app/controllers/labels_controller.php` |
| Change scan snapshot behavior/log image linking | `ryan/yilma/recognize.py` | `ryan/app/controllers/labels_controller.php`, `ryan/app/core/database.php` |
| Change professor report filters/ranges | `ryan/yilma/app/pages/main_menu_page.php` | `ryan/app/core/database.php` |
| Change report photo display source | `ryan/yilma/app/pages/main_menu_page.php` | `ryan/app/core/database.php` |

## 9) Data Model Quick Reference
- `users`: account identity and role flags.
- `user_permissions`: granular portal capabilities (users/faces/doors/logs).
- `admin_logs`: admin/security actions with optional scan image path.
- `Attendance`: scan-based attendance.
- `Classes`, `ClassSchedule`, `Enrollments`: scheduling and roster checks.
- `door_control_rooms`: lock state per room/door.

## 10) Safe Change Workflow
1. Update policy/query logic first (`app/core/database.php` or `recognize.py`), if needed.
2. Update controller/API layer.
3. Update page/UI copy and behavior.
4. Verify syntax/lint basics.
5. Re-test the user flow end-to-end.

## 11) Verification Commands
- PHP syntax: `php -l <changed_php_file>`
- Python syntax: `python3 -m py_compile <changed_py_file>`
- Locate function quickly: `grep -n "function_name" <file>`
- Review local changes: `git status --short`

## 12) Known Gotchas
- Root route files are compatibility shims; edit canonical files first.
- Recognition and PHP both influence final scan behavior; UI-only changes may be incomplete.
- Unknown/no-box/no-match handling is intentionally guarded in multiple layers (client + controller + policy).
- `ryan/yilma/universityDB(2).sql` is a backup schema reference, not live DB state.

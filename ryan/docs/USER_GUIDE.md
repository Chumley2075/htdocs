# Biometric Classroom Access System User Guide

## Overview

This system helps schools manage classroom access and attendance using face recognition.

It includes:
- A classroom display screen for live scan results.
- A portal for professors, administrators, and security desk users.
- Automatic attendance and access logging.

## Required Equipment

- Intel NUC PC with 16 GB RAM.
- Included system ISO image (use this ISO for installation).
- USB webcam.
- Adafruit ToF sensor.
- GPIO-to-USB-C converter board (for the ToF sensor connection).

## Installation Note

The included ISO already has the database setup. No separate database installation is required.

## Accessing the System

Open these pages in your browser:
- Classroom display: `http://<server>/ryan/`
- Portal login: `http://<server>/ryan/yilma/`

Replace `<server>` with your server address.

## User Roles

- Professor: View class attendance reports.
- Administrator: Manage users, faces, doors, and logs.
- Security Desk: Manage door state and view logs.

## Logging In

1. Go to the portal login page.
2. Enter your username and password.
3. Click **Submit**.

After login:
- Professors go to attendance reports.
- Admins/Security Desk users go to admin tools.

## Classroom Display Use

The classroom display shows:
- Class/session details.
- Face scan status.
- Access allowed or denied state.

If a user is recognized and authorized, the system records attendance and access events automatically.

## Admin Console (Administrators)

Open **Admin Page** from the portal.

Main sections:
- Users: Create and update accounts/roles.
- Faces: Enroll or delete facial data.
- Doors: Lock/unlock room access.
- Logs: Review system events.

## Creating Admin, Professor, and Security Desk Users

1. Log in with an account that has user-management access.
2. Open **Admin Page** and go to the **Users** section.
3. Enter the new user details:
- Username
- Full name
- Password (required for Admin, Professor, and Security Desk users)
4. Choose the role:
- **Administrator**: Enable Admin role for full admin-level access.
- **Professor**: Enable Professor role for reporting/dashboard access.
- **Security Desk**: Enable Security Desk option for door/log operations.
5. Submit the form to create the account.

Important role note:
- Security Desk users should not also be set as Admin or Professor.

## Enrolling a User Face

1. Go to **Admin Page**.
2. Open the **Faces** section.
3. Choose existing user lookup or create user details.
4. Start enrollment and let capture finish.
5. Wait for training/retraining to complete.

The user can then be recognized by the system.

## Deleting a User Face

1. Go to **Admin Page**.
2. Open the **Faces** section.
3. Enter/select the target user.
4. Run delete face action.

The system removes saved face data and retrains automatically.

## Attendance Reports (Professor/Admin)

1. Open the reports dashboard.
2. Select a class.
3. Select report type:
- Day
- Last 7 days
- Custom range
4. Click **Run Report**.

You can review:
- Meeting summary.
- Student attendance status/rates.
- Exceptions and scan photos.

Use **Print / Save PDF** to export.

## Door Control

Authorized users can manage room door lock state from the admin console.

Typical modes:
- Locked until authorized scan.
- Unlocked (temporary/manual based on controls).

All changes are logged.

## Logs and Audit Trail

The logs section records key actions such as:
- Face scans.
- User changes.
- Face enrollment/deletion.
- Door state changes.

Use filters/search to find specific events.

## Accessing phpMyAdmin

Use phpMyAdmin to inspect the database directly.

1. Open: `http://<server>/phpmyadmin/`
2. Log in with:
- Username: `root`
- Password: `password`
3. Open database: `UniversityDB`

Common tables:
- `users` (accounts and role flags)
- `Classes`, `ClassSchedule`, `Enrollments`
- `Attendance`
- `admin_logs`

## Logout

Click **Logout** when finished.

## Troubleshooting

- Cannot log in:
  - Verify username/password.
  - Confirm your account has portal access.
- No report data:
  - Confirm the class is selected and has a schedule/roster.
  - Try a different report date/range.
- Face not recognized:
  - Re-enroll face data.
  - Check camera position and lighting.

# Development History

This history is a narrative summary reconstructed from the current Doc Sathi codebase on 2026-04-23.

It is intentionally not presented as Git-derived history. It describes how the project appears to have evolved based on the PHP application structure, SQL schema files, migration scripts, and demo assets currently in the repository.

## 1. Initial product slice

The project appears to have started as a role-based medical appointment system with a simple landing page, account routing, and a MySQL-backed data model. The base schema centers on patients, doctors, schedules, appointments, specialties, and a shared `webuser` table for login routing.

## 2. Account and validation pass

The next visible layer is stronger account handling. Registration pages now apply password hashing, email and phone validation, date-of-birth checks, and duplicate-account protection. Session handling was also separated by role so admin, doctor, and patient sessions do not collide as easily in a local multi-app environment.

## 3. Patient-facing booking journey

The patient area grew into a fuller workflow: browse doctors, filter by specialty, inspect schedules, create bookings, and review appointment status. The appointment logic now distinguishes upcoming, in-progress, and completed records instead of treating every booking as a static row.

## 4. Doctor operations and schedule control

Doctor-facing functionality expanded beyond profile access into an operational workspace. The current codebase includes dashboard metrics, patient summaries, schedule creation and editing, overlap checks, duration-aware session windows, and completion tracking for appointments.

## 5. Verification and admin governance

Later work clearly focused on making the doctor workflow safer and more realistic. Doctor onboarding now supports verification status, document uploads, admin review remarks, rejection and resubmission flows, and patient booking restrictions for unapproved doctors. The admin area also now surfaces verification queues and operational summary cards.

## 6. Optimization and demo support

The repository includes explicit performance indexes, standalone workflow migrations, runtime schema-upgrade code, seeded demo records, and demo credential documentation. That suggests a final phase aimed at stabilizing local demos, classroom review, or project presentation.

## Current repository presentation gaps

- No Git repository is initialized in this project folder yet.
- `README.md` and `.gitignore` are missing.
- Multiple pages reference `css/animations.css`, but that file is not present.
- Demo credentials and uploaded verification PDFs are tracked inside the application tree, which should be reviewed before any public release.
- `admin/delete-appointment.php.php` looks like an accidental duplicate wrapper file and should be reviewed before publishing.

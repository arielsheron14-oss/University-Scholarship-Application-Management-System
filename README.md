# University Scholarship Application Management System

A PHP 8+ / MySQL scholarship portal with student profiles, staff scholarship management, configurable application forms, required/optional document rules, application snapshots and staff document review.

## Requirements
- XAMPP (Apache, PHP 8.0+ and MySQL/MariaDB)
- PHP extensions: PDO MySQL, Fileinfo
- Modern browser

## Fresh installation
1. Extract `University_Scholarship_System` into `C:\xampp\htdocs\`.
2. Start Apache and MySQL.
3. Open phpMyAdmin and import `database/schema.sql`.
4. Check database credentials in `config/database.php`.
5. Ensure the `private_uploads` folder exists and is writable by Apache. It contains a deny-all `.htaccess`; do not remove it.
6. Visit `http://localhost/University_Scholarship_System/`.

## Upgrade an existing installation
1. Back up your database and project folder.
2. Import `database/dynamic_application_migration.sql` into the existing `scholarship_system` database.
3. Keep your existing user/profile data and existing `uploads/profile_pictures` directory.
4. Create the `private_uploads` folder if it is missing and ensure Apache can write to it. Keep `.htaccess` in place.
5. Replace the old project files with this version, preserving `config/database.php` credentials.

## First administrator
1. Register through the website.
2. In phpMyAdmin, open `scholarship_system.users` and change your account's `role` to `admin`.
3. Log out and back in. Admin can manage user roles and scholarship settings.

## New dynamic application workflow
1. Staff creates and publishes a scholarship in Staff Dashboard → Scholarship manager.
2. Open **Configure form** next to that scholarship.
3. Add supporting document requirements. Set each to Required or Optional, select accepted types (`pdf,jpg,jpeg,png`) and maximum size (0.1–20 MB).
4. Add scholarship-specific questions (short text, long answer, number, date or dropdown). Dropdown options are entered one per line.
5. Student opens the scholarship application. Personal, academic and financial information is prefilled from the profile and displayed as a read-only snapshot.
6. Student completes the statement, answers configured questions, uploads required/optional documents and confirms the declaration.
7. Staff opens Review Applications → Open review to see the submitted profile snapshot, answers, documents and application status. Staff can securely view documents, mark each Pending / Verified / Rejected and add a review note.

## Data and privacy behaviour
- The application stores a snapshot of the profile details at submission time. Later profile edits do not rewrite old applications.
- Required documents and required questions are validated server-side.
- Application documents are stored in `private_uploads`, not the public `uploads` folder. Staff document viewing is routed through an authenticated endpoint.
- Students should correct profile details in My Profile before submitting; application profile fields are intentionally read-only to keep the profile as the source of truth.
- Avoid collecting personal or financial information that is not needed for scholarship assessment. Restrict staff accounts to trusted reviewers.

## Existing team workflow
Continue using the seven-member task allocation agreed by your group. Commit the updated schema/migration and the application builder pages together so every team member uses the same database structure.

## Important deployment notes
This is a university project implementation, not a production-certified system. Before real deployment, configure HTTPS, secure session cookie settings, login rate limiting, backups, malware scanning for uploads, audit logging and server-level private storage. Apache `.htaccess` protection requires `AllowOverride` to be enabled; for Nginx, configure an equivalent deny rule for `/private_uploads/`.

# CRM deployment handover

## New installation

1. Use Apache 2.4, PHP 8.1+ with PDO MySQL, mbstring and OpenSSL, MySQL/MariaDB, and HTTPS. Enable rewrite/headers modules and .htaccess overrides. PHP-FPM reads .user.ini; configure equivalent settings on the host if disabled.
2. Extract the release and create an empty database with a dedicated database user. Copy .env.example.php to .env.php. Set database credentials and the exact HTTPS APP_URL, including its subdirectory if applicable.
3. Set CRM_ADMIN_USERNAME, CRM_ADMIN_EMAIL, and a unique CRM_ADMIN_PASSWORD (12+ characters) in the installer process environment. Run `php scripts/install.php`. Unset those variables afterward. No default password is included.
4. Configure SMTP in .env.php; test password-reset delivery. Configure hosting HTTPS redirects, database backups and error-log rotation.

## Existing installation

Back up files/database, preserve .env.php, deploy the code and run `php scripts/migrate_bookings.php`. Never import the old development all.sql: it contains destructive statements and is excluded from the release. The supplied database/schema.sql contains only additive schema creation.

## Before client go-live

- Test both logins, employee/agent creation, hotel/room/rate management, booking assignment, payment, cancellation, restored inventory and history on the target domain.
- Verify employee isolation, search, notifications, mobile navigation, logout and actual password-reset email delivery.
- Verify web access to .env.php, .user.ini, database/, scripts/ and docs/ is denied. Confirm external CSS/JS assets load.
- Verify a backup can be restored in a separate database.

## Verification status

74 HTTP checks and four-process reservation/cancellation race tests passed locally using an isolated QA database. The original configured database was unavailable and its configuration was preserved. Target-host database access, SMTP and final client acceptance remain deployment gates; this package is not a completed production certification.

Manual CRM bookings without a room category do not reserve inventory. Use New room booking for availability-backed reservations. Historical bookings are not automatically matched across the two legacy/canonical schemas. See booking-upgrade.md for remaining full-project QA coverage.

The package excludes real credentials, Git metadata, QA data, test sessions, development seed scripts and destructive SQL.

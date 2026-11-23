# Application security review — 20 November 2026

This review covers the PHP application, customer and staff routes, JavaScript templates, SQL use, provider integrations, uploads, and repository web-server rules. It is a code review with local regression and HTTP testing, not a penetration test of the live host or a certification that every vulnerability has been eliminated.

## Confirmed issues repaired

| Issue | Repair |
| --- | --- |
| Setup, seed, test, source, Git and backup files could be served from the repository document root | Root and directory Apache deny rules, CLI-only setup/test entry points, matching preview-router restrictions, and directory listing disabled |
| Staff sessions had no application expiry or password-reset revocation | 30-minute idle timeout, eight-hour absolute lifetime, credential/role fingerprint checked against the database on every authenticated request, session and CSRF rotation at login, complete session clearing at logout, and a session version so disabling/re-enabling an account cannot resurrect its old sessions |
| Permissive session IDs and weak default PHP request handling | Strict cookie-only sessions, HttpOnly and SameSite cookies, Secure cookies on HTTPS, startup PHP configuration and fail-closed session initialization |
| Booking with a known phone number overwrote shared customer details | Name, phone and email snapshots belong to each booking; new bookings cannot change an existing customer profile or the receipt address on another appointment |
| Login throttling could be bypassed by rotating usernames; counters lost concurrent increments | Separate account, IP and pair limits; atomic database increments; request limits on booking creation, lookup and availability |
| Malformed array parameters could cause type errors | Scalar request validation, explicitly permitted structured admin forms, nesting and size limits, NUL and invalid UTF-8 rejection |
| Runtime error output could disclose internals | Startup and application error display disabled; unexpected exceptions produce generic responses with restricted diagnostic logging |
| Browser execution lacked a restrictive policy | CSP restricts scripts to local assets, blocks objects and framing, and restricts forms; inline print/cancellation handlers moved to JavaScript; HTTPS responses send HSTS |
| Application settings wrote executable PHP files | New SMS/payment settings are atomically saved as private JSON, with restrictive file permissions and an optional location outside the document root; old PHP settings remain readable for compatibility |
| Upload directories could execute unexpected files under a permissive server configuration | Only generated image filenames are allowed in the public upload directories; receipts and private data remain denied; temporary restore/config files are not public |
| Public image reads ran schema DDL | Photo backup schema is now created by an explicit migration, allowing the runtime database account to operate without schema privileges and avoiding implicit MySQL transaction commits |
| Staff booking mutations relied solely on controller authorization | Service methods verify an active staff account; booking changes lock their booking row before reading state |

Existing prepared SQL, escaped HTML/JSON, CSRF checks, image MIME/dimension checks, private receipt access, provider TLS verification, Paystack HMAC verification, server-side amount verification and idempotent settlement were reviewed and retained. The webhook body is bounded before parsing and its declared size is checked before reading.

## Deployment order — required

1. Back up the **live** database, `assets/images/gallery/`, `assets/images/team/`, `storage/`, and private server configuration. Keep backups outside the public document root.
2. Stage `src/SecurityMigration.php` and `database/migrate_security.php` on the live installation. They work with the existing class autoloader. Run `php database/migrate_security.php` **before publishing the changed booking and payment code**. It adds contact snapshot columns, backfills existing bookings, and creates the upload backup table without replacing records. It is safe to rerun. Use a migration account with schema privileges; the ordinary runtime account should have only the required SELECT, INSERT, UPDATE and DELETE permissions.
3. Publish the application changes, including dotfiles. Preserve the live upload/storage folders and configuration; do not import the local database over the live one. Run `php database/migrate_uploaded_photos.php` while existing live image files are still present to protect all current uploads. Missing original images cannot be reconstructed without their files or a prior backup.
4. Set `SPA_ENV=production`, a dedicated database user/password, and a private session directory accessible only to the application account. Configure HTTPS redirects on the host. Production requests over HTTP are refused. TLS-terminating proxies must make the server's HTTPS state accurate; arbitrary `X-Forwarded-Proto` headers are deliberately not trusted.
5. Set `SPA_PRIVATE_STORAGE` to a private absolute directory outside the document root where practical. Otherwise `storage/private/` is protected by the supplied Apache rules. Give the application write access only to the necessary uploads, receipts, private settings and session directories. Application source should be read-only to the web worker. Preserve private settings in future deployments.
6. Existing `config/sms.local.php` and `config/paystack.local.php` continue to work. Saving each provider's settings through admin writes the new private JSON format. After verifying the new files, remove the legacy secret copies through the hosting account and make the code/config directory read-only. Never put credentials into Git or public backups.
7. Check that `.htaccess` is honored and `mod_rewrite` is enabled on Apache. PHP-FPM/CGI uses `.user.ini`; Apache mod_php has matching startup settings in `.htaccess`. Hosts may cache `.user.ini` changes. If using Nginx, configure equivalent private-directory, dotfile, backup and upload-execution restrictions in Nginx; it does not read these files. Never publish the PHP development server.
8. Verify private URLs return 403/404, all public pages load, admin/receptionist permissions work, uploads work, and an ordinary customer can book, manage and pay using provider test mode. Existing staff sessions will need to sign in again. Verify HTTPS cookies and headers on the actual host.

## Hosting work still required

If the hosting account has no terminal, select the live spa database in phpMyAdmin, export a backup, and import `database/migrate_security_hostinger.sql` instead of running the PHP migration. This upgrades existing records without replacing the live database. It adds the admin session field and photo backup table used by staff pages as well as booking contact snapshots. An error on a staff page can also have other causes: the hosting error log's `Application failure:` line identifies the exception class, source file and line without exposing database credentials. Do not reset passwords or import a local database merely because a page shows the generic server-error message.

- **Upgrade PHP:** the inspected local runtime is PHP **8.1.25** from October 2023. PHP 8.1 is outside upstream security support. Upgrade local and live environments to a current, security-patched supported PHP branch, preferably 8.4 or newer. Check the live version independently; it was not inspected here. Patch Apache, the database server, OpenSSL and the hosting platform too. Application changes do not patch these components.
- Confirm HTTPS, trusted proxy handling, private session storage, filesystem ownership, database network isolation, encrypted/restorable backups, error-log access and log retention in hosting. None of those server settings can be inferred from this checkout.
- The application still uses password-only staff login. Enforce MFA or an access gateway for staff through hosting/identity infrastructure where available. Adding native MFA and verified customer phone/email ownership requires an additional authentication flow.
- Guest booking intentionally does not prove ownership of a phone number. Per-booking snapshots prevent cross-booking data alteration, and IP limits reduce abuse, but they cannot stop a distributed attacker from reserving slots or sending unwanted confirmations. Use hosting rate limits/bot protection and consider OTP verification if abuse occurs.
- Public availability checks are database-intensive. Apply edge request limits and monitor usage. Periodically remove old `rate_limits` rows using a private scheduled task, retaining at least the longest active window (currently one hour); never delete active windows.
- Receipts are served under an isolated sandbox policy; MIME validation is not malware scanning. If business requirements demand scanning, integrate a private scanner before accepting receipts. Do not send customer receipts to public scanning services.
- If any private directories, database dumps or credentials were previously public, inspect access logs and rotate affected credentials. This review found no obvious committed live provider/private keys in the tracked files scanned; that is not a complete historical secret scan.

## Validation

- `C:/xampp/php/php.exe tests/run.php` — application and security regression suite, using an in-memory SQLite database.
- `python tests/security-http.py` — isolated HTTP checks with a temporary SQLite database, fake staff accounts, disabled SMS/payments, real multipart upload/restore/deletion, role checks, CSRF, session rotation and revocation, private routes, malformed requests and webhook limits. It refuses to run with `config/config.php` present to avoid accidentally using a real database.
- `C:/xampp/php/php.exe tests/mysql-security-regression.php` — native MySQL/MariaDB limiter and expiry behavior; creates and removes only its uniquely named test rows.
- The migration was applied and rerun locally. Apache configuration syntax and local HTTP restrictions were checked independently of the PHP preview router.

Checks performed with the installed PHP version do not establish compatibility or patch status for a different production runtime. Test on the upgraded deployment before releasing it.

References: [OWASP session management](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html), [OWASP authorization](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html), [OWASP PHP configuration](https://cheatsheetseries.owasp.org/cheatsheets/PHP_Configuration_Cheat_Sheet.html), and [PHP supported versions](https://www.php.net/supported-versions.php).

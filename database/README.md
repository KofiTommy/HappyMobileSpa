# Happy Mobile Spa database and local setup

## Requirements

- XAMPP with PHP 8.1 or newer, MariaDB, and Apache
- PHP extensions: PDO MySQL, PDO SQLite (for tests), and mbstring
- Node.js only when using the PHP preview wrapper instead of Apache

The application uses the `happy_mobile_spa` database and `Africa/Accra` as its fixed business timezone. Appointment times are stored in UTC.

## First-time setup

1. Start MariaDB in the XAMPP Control Panel.
2. From the project directory, create the tables:

   ```bash
   C:/xampp/mysql/bin/mysql.exe -u root < database/schema.sql
   ```

3. Insert the demonstration treatments, therapists, and schedules:

   ```bash
   C:/xampp/php/php.exe database/seed.php
   ```

4. Create a private administrator account. The normal Windows flow prompts for a hidden password and requires at least 12 characters:

   ```bash
   C:/xampp/php/php.exe database/create_admin.php
   ```

   For automated local provisioning only, `SPA_ADMIN_PASSWORD` can supply the password through the environment. Do not put passwords in scripts, source control, or shared shell history.

5. Update **Admin → Business settings** with the real phone number, email address, service address/area, opening hours, holidays, and cancellation policy before publishing the site.

## Configuration

The defaults in `config/config.example.php` use MariaDB at `127.0.0.1`, database `happy_mobile_spa`, user `root`, and an empty password for local XAMPP development.

For another local or hosted database, set these environment variables in the web-server environment:

- `SPA_DB_DSN`
- `SPA_DB_USER`
- `SPA_DB_PASS`
- `SPA_ENV=production` on the public server

Do not commit credentials. Restrict database privileges to the application database in production.

## Launch

### Apache

Copy or serve the project as `C:\xampp\htdocs\www.happymobilespa.com`, start Apache and MariaDB, then open the configured local virtual host. Ensure PHP can reach MariaDB.

### PHP preview wrapper

```bash
npm run dev -- --port 7100
```

The wrapper starts PHP's development server and forwards `--host` and `--port`. Set `SPA_PHP` if PHP is installed somewhere other than `C:/xampp/php/php.exe`. Stop the preview with Ctrl+C.

## Tests

```bash
C:/xampp/php/php.exe tests/run.php
C:/xampp/php/php.exe tests/mysql-settings-regression.php
```

The main suite uses an isolated in-memory SQLite database. The MySQL regression check uses the configured development database and saves the existing cutoff value twice without changing it.

## Backup and restore

Back up before upgrades and on a regular schedule:

```bash
C:/xampp/mysql/bin/mysqldump.exe -u root --single-transaction --routines --triggers happy_mobile_spa > backup-happy-mobile-spa.sql
```

Store backups securely because they contain customer contact details and appointment notes. Test restoration on a separate database before relying on a backup:

```bash
C:/xampp/mysql/bin/mysql.exe -u root restored_happy_mobile_spa < backup-happy-mobile-spa.sql
```

## Before production

- Remove demonstration bookings, customers, services, and staff that are not real business records. Preserve genuine booking history; archive treatments and deactivate staff instead of deleting referenced records.
- Create a strong administrator password and remove unused admin accounts.
- Use HTTPS, a restricted database account, scheduled backups, and a production PHP/web-server configuration.
- Keep payment at the spa unless a separate payment integration is implemented and reviewed.
- **Do not expose a default XAMPP installation directly to the public internet.** Use a properly hardened production host and keep MariaDB inaccessible from the public network.

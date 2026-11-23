# Happy Mobile Spa database and local setup

## Requirements

- A current, security-patched supported PHP release (preferably 8.4 or newer), MariaDB, and Apache
- PHP extensions: PDO MySQL, PDO SQLite (for tests), and mbstring
- Node.js only when using the PHP preview wrapper instead of Apache

The application uses the `happy_mobile_spa` database and `Africa/Accra` as its fixed business timezone. Appointment times are stored in UTC.

Before deploying the security update to an existing installation, follow [the security review and deployment order](../docs/SECURITY.md). In particular, run `php database/migrate_security.php` before publishing the updated booking code. New installations include the required columns and photo backup table in `schema.sql`.

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

## Receptionist access for team members

For an existing installation, run `C:/xampp/php/php.exe database/migrate_receptionists.php` once before using the updated staff area. This preserves existing administrators and bookings. New installations include roles in `schema.sql`.

Sign in as an administrator, open **Team & schedules**, and edit an existing team member. In **Receptionist access**, choose a username and initial password, then select **Assign as receptionist**. The login is linked to that team profile; each member can have one login. You can reset the password or remove/restore receptionist access from the same profile. Receptionists use the same `/admin/login.php` page. Disabling an account also blocks its existing session on the next request.

Receptionists can create bookings, search today's appointments by client name, phone, or reference, check confirmed clients in on their appointment day, confirm/cancel/complete appointments, mark no-shows, and record payments. Arrival and booking changes record the signed-in account in booking events. Treatments, team schedules, business settings, account management, and booking conflict overrides are restricted to administrators.

## Gallery and homepage carousel

For existing installations run `C:/xampp/php/php.exe database/migrate_gallery.php` once. New installations include the gallery table in `schema.sql`.

Open **Admin → Gallery & homepage** to upload photos, edit captions and image descriptions, choose display order, publish/unpublish, feature photos in the homepage carousel, or delete them. Only administrators can manage the gallery. Published, featured photos rotate every six seconds; visitors can use previous/next and pause/play controls. Rotation pauses on hover, focus, and hidden tabs, and starts paused for reduced-motion preferences. If no published photos are selected, the original spa photos appear.

### Homepage hero videos

The same admin form accepts **MP4 videos up to 5 MB** alongside the existing photos. Choose a short landscape clip exported with H.264 video for browser compatibility, add its caption and scene description, and enable **Show in homepage carousel**. A clip can replace a photo, and photos and videos can be mixed in any display order. Team profile pictures are unaffected.

Hero videos start muted when autoplay is allowed and include native playback/audio controls. A video stays on screen until it finishes; a paused video is not skipped by the normal six-second photo timer. The slideshow pause button pauses the active video. Inactive slides and hidden tabs pause their players. Reduced motion and browser data-saving preferences suppress automatic video playback, while native controls remain available. A single hero video loops. Gallery and homepage gallery-preview players wait for the visitor to press Play.

Videos are stored under `assets/videos/gallery/`, excluded from Git, and backed up in the existing `uploaded_photos` table so missing files can be restored after deployments. Track and deploy that folder's `.htaccess`, preserve the uploaded videos, and include them and the live database in backups. The ordinary application user needs write access to the video directory and SELECT/INSERT/DELETE permissions on the backup table. The database must accept a 5 MB binary parameter (configure `max_allowed_packet` above 5 MB, for example 8 MB or higher). The included PHP limits already allow one 5 MB upload and an 8 MB form; a host-level lower limit must be raised in hosting. Large uploads that cannot be backed up are rejected rather than published without protection.

This feature uses existing gallery paths and the existing upload backup table: **no additional database migration is required** if the earlier security/upload migration is installed. Deploy the changed code and `assets/videos/gallery/.htaccess` through Git. Videos are not transcoded, so test playback on a phone after uploading. For spoken content, use a captioned clip and provide a descriptive text alternative in the form.

Uploads use the same image size/type limits as team photos. Back up `assets/images/gallery/` along with the database and allow the web server to write to it. Gallery uploads are excluded from source control.

## Team profile photos

### Therapist introduction videos

For an existing installation, run `php database/migrate_therapist_videos.php` before deploying the updated team editor. If the host has no terminal, select the live database in phpMyAdmin, export a backup, then import `database/migrate_therapist_videos_hostinger.sql`. Both upgrades are safe to rerun and preserve profile pictures and other staff data. New installations include `staff.video_url` in `schema.sql`.

Open **Admin → Team & schedules**, edit a therapist, and paste an optional **Introduction video** link. Supported destinations are YouTube, Vimeo, TikTok, Instagram and direct public HTTPS MP4/WebM files. Videos remain hosted at their source; this field does not upload video files to the spa server. Choose a publicly accessible video and include captions where possible. Clear the field to remove the video without changing the profile picture.

Clients see **Watch introduction** on the homepage therapist profile and next to the therapist selection card when booking. It opens a separate tab so their booking selections stay in place. No video autoplays or loads a third-party player before the customer opens the link. Inactive therapists remain hidden from customer pages.

Under **Team & schedules**, upload a JPG, PNG, or WebP when adding or editing a team member. Photos must be at most 2 MB and 4096 × 4096 pixels. Save the profile to publish the photo in the customer therapist selector. Use **Remove current photo** to return to an initials placeholder.

Uploaded photos are stored in `assets/images/team/`; include this directory in backups and ensure the web server can write to it. Photos are excluded from source control. The existing `staff.photo` column is used, so no database migration is needed.

## Contact messages

Existing installations: run `C:/xampp/php/php.exe database/migrate_contact_messages.php` once. New installations include this table in `schema.sql`.

Customers can submit enquiries from the contact page. Messages are saved in **Admin → Customer messages**, with new/read/replied/archived statuses and paginated filters. Only administrators can access the inbox. **Reply by email** opens the administrator's email app; no automatic email forwarding or delivery is configured. Mark messages replied after sending the response.

The form validates contact details and message length, verifies CSRF tokens, uses a spam honeypot, and limits submissions to five per hour per IP address. Contact messages contain personal information and are included in database backups.

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

## Booking SMS and 24-hour reminders

Existing installations: run `C:/xampp/php/php.exe database/migrate_sms.php` once. Fresh installations include the notification queue in `schema.sql`.

Sign in as admin and open **SMS notifications**. Enter your **BulkSMS Ghana API key**, an approved sender ID (1?11 letters/numbers), and enable sending. The API key is saved in ignored `config/sms.local.php`, never displayed in forms or logs, and shared by web requests and the reminder worker. Protect this file in server backups. No messages are sent while disabled. Keep sufficient credit in your BulkSMS Ghana account. This integration uses the provider's HTTPS `/smsapi` endpoint and response codes documented at https://bulksmsghana.com/developer/.

Confirmed customer and reception bookings send a confirmation immediately after the booking transaction commits. Pending bookings send it once confirmed. Future reminders are stored in the database for exactly 24 hours before the appointment in Ghana time. Bookings made within 24 hours receive only the confirmation. Rescheduling queues a new confirmation and adjusts the reminder; cancellation/completion invalidates queued messages. Existing future confirmed appointments also acquire reminders when the worker runs, without sending old confirmations.

Install the Windows task from the project directory:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\install-sms-task.ps1
```

It runs every minute, using windowless PHP where available. By default it attempts logged-off operation; if Windows denies that permission, it falls back to running while the current user is logged on. Run the installer from an **Administrator PowerShell** to enable logged-off operation. The PC and MariaDB must remain running; a sleeping or powered-off PC cannot send reminders. Check the worker heartbeat under **Admin ? SMS notifications**. Re-run the installer after moving the project. The task name is `HappyMobileSpa-SmsNotifications`.

On Linux hosting configure a cron job every minute (adjust paths):

```cron
* * * * * /usr/bin/php /var/www/happymobilespa/scripts/sms-worker.php
```

Run `C:/xampp/php/php.exe scripts/sms-worker.php` for a manual worker check. `SPA_SMS_DISABLED=1` overrides sending, including in the test suite. Tests inject a fake sender; they never send paid SMS. PHP cURL requires a valid CA certificate configuration; do not disable TLS verification.

The queue prevents concurrent workers from submitting the same job. Definite temporary rejections (1002/HTTP 429) retry after five minutes, up to three attempts. Network timeouts, crashes after claiming, unknown responses, and ambiguous server failures are marked **unknown**, not automatically resent: check the provider's history to avoid duplicate charges. Other definite failures appear in the admin log for review. More-than-one-hour-late notifications are skipped so turning SMS on after an outage does not flood clients with stale confirmations or late reminders. **Submitted** means accepted by the provider, not confirmed handset delivery; consult the provider's delivery reports. Long SMS text may consume multiple SMS credits.

## Paystack mobile money payments

Existing installations: run `C:/xampp/php/php.exe database/migrate_paystack.php` once. Fresh installs include the `paystack_payments` table in `schema.sql`.

Open **Admin ? Mobile money payments**. Enter your full website URL (including any subfolder), Paystack **secret** key, and enable checkout. Start with `sk_test_...`; use `sk_live_...` only after testing with your activated Ghana Paystack account. The key is saved in ignored `config/paystack.local.php`, protected by the config folder rules, and never displayed to customers or logged. The hosted checkout integration does not need your public key. Protect the private configuration file in backups.

The integration initializes `POST https://api.paystack.co/transaction/initialize` server-side with `currency=GHS`, the saved booking total in pesewas, and `channels=["mobile_money"]`. Customers choose their network and authorize through Paystack. The app does not collect wallet PINs. Enable the mobile money channel on your Paystack dashboard. See https://paystack.com/docs/payments/payment-channels/ and https://paystack.com/docs/api/transaction/.

Configure the webhook URL shown on the admin page in the matching test/live Paystack dashboard, for example:

```text
https://www.happymobilespa.com/api/paystack-webhook.php
```

Callbacks use `/payment-return.php`. Live settings require public HTTPS. HTTP localhost is accepted only for a test key; localhost cannot receive provider webhooks. A publicly reachable test environment is needed to test asynchronous mobile money confirmations. PHP cURL needs working CA certificates; never disable TLS checks.

Appointments remain confirmed and reserve their slots before payment; unpaid appointments are not automatically cancelled. Customers can choose pay-at-spa or mobile money in booking review, then continue to hosted checkout after the appointment is saved. Customer email is optional, including for mobile money. When blank, checkout supplies a unique `guest-<payment-token>@happymobilespa.online` address solely for Paystack's required email parameter. It is stored in the payment record for verification and never written into the booking's contact email or shared customer profile. Refreshing checkout reuses the same payment and address. A valid customer-provided email is retained; invalid nonblank emails must be corrected or cleared. Customers without an email can still see payment status on the website; an emailed receipt requires a real address. Existing unpaid bookings can be paid after authenticating through **Manage booking**. A payment failure does not create another booking. No database migration is required for the email fallback change.

The callback verifies payment directly with Paystack. Webhooks validate the raw-body HMAC SHA512 signature, then independently verify the reference with Paystack. The reference, full amount, currency, customer email, mobile money channel, and test/live domain must match the stored payment. Successful processing is transactional and idempotent. Repeated callbacks/webhooks cannot record payment twice. The payment log distinguishes test and live transactions. Tests set `SPA_PAYSTACK_DISABLED=1` and use simulated API responses; they never charge wallets.

A booking reuses one payment reference and hosted checkout URL to prevent duplicate initialization. An interrupted initialization is flagged rather than automatically generating another charge. Use **Check payment status** or **Admin ? Mobile money payments ? Verify with Paystack** to reconcile it; if unresolved, check the reference in Paystack and contact the spa. Do not submit a separate payment if money was deducted. An abandoned checkout can be revisited through the same booking while still valid.

Money received after cancellation, completion, a price change, or another recorded payment is flagged **paid review** for the administrator and does not reopen the booking. Refunds and disputes are handled directly in Paystack; this integration does not initiate refunds or synchronize refund/chargeback events. Cancelling a booking does not refund a payment. Paid online payments cannot be reversed with the ordinary paid/unpaid control. Test mode simulates paid bookings without collecting money: use dedicated test appointments, and clear them before going live.

## Monthly finance, salaries, expenses, and budgets

Existing installations: back up the database, then run `C:/xampp/php/php.exe database/migrate_finance.php` once. The migration is safe to rerun and preserves existing bookings and staff. Fresh installations include the Finance tables in `schema.sql`.

For Hostinger hosting without command-line access, back up the database and upload the application changes first. Open the spa database in **hPanel → phpMyAdmin**, select **Import**, and import `database/migrate_finance_hostinger.sql`. On Hostinger/MariaDB the file uses `ADD COLUMN IF NOT EXISTS` and `CREATE TABLE IF NOT EXISTS`, so it is safe to rerun after a partial setup; existing columns, tables, bookings, staff, and Finance records are preserved.

Open **Admin → Finance & budgets**. Set each worker’s usual monthly salary under **Team & schedules**, then generate pending monthly salary records without overwriting existing ones. Edit records to add bonuses, deductions, paid dates, payment methods, and references. Record other expenses with optional JPG, PNG, WebP, or PDF receipts up to 5 MB. Receipts are stored under `storage/receipts`, blocked from direct web access, and streamed only to signed-in administrators; include them in private backups and allow PHP to write to that folder.

Monthly budgets cover expected income, payroll, and expense categories. The overview separates actual income received from expected appointment value, and paid amounts from pending commitments. Cancelling preserves the record and audit history while excluding it from totals; editing a cancelled record can restore it. Overview, salary, and expense CSV exports are available for the selected month. All Finance mutations and receipt/CSV routes are administrator-only; receptionist permissions are unchanged.

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
- Verify Paystack test checkout, signed webhooks, and payment reconciliation on the public HTTPS host before enabling live mobile money payments.
- **Do not expose a default XAMPP installation directly to the public internet.** Use a properly hardened production host and keep MariaDB inaccessible from the public network.

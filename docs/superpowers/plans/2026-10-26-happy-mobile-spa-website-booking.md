# Happy Mobile Spa Website and Booking System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a branded Happy Mobile Spa website with real guest bookings, customer self-service, and a secure admin dashboard using XAMPP.

**Architecture:** Server-rendered PHP pages call focused catalog, availability, booking, authentication, and settings services. Public and admin interfaces share one MySQL schema; all writes use PDO prepared statements and booking mutations use transactions. A Node wrapper launches PHP's built-in server for workspace previews while Apache remains supported.

**Tech Stack:** PHP 8.1.25, PDO, MariaDB 10.4.32, MySQL/InnoDB, vanilla JavaScript, custom CSS, Node.js 24 development wrapper, PHPUnit-free PHP test runner.

## Global Constraints

- Keep the supplied `Happy Mobile Massage Logo.pdf` unchanged.
- Use PHP 8.1 syntax and XAMPP binaries at `C:\xampp\php\php.exe` and `C:\xampp\mysql\bin\mysql.exe`.
- Use schema `happy_mobile_spa`, InnoDB, UTC timestamp storage, and display timezone `Africa/Accra`.
- Use ivory `#FAF7F0`, forest `#173F35`, leaf `#35B558`, burgundy `#BE1025`, and charcoal `#24312C`.
- First-release payment method is `pay_at_spa`; do not add online payments, SMS, WhatsApp, or automated email.
- Do not publish invented ratings, testimonials, awards, addresses, or business claims.
- Use prepared statements, CSRF tokens, output escaping, secure sessions, throttling, and no default admin password.
- Tests must use a database name ending in `_test`; never truncate the development or production schema.
- Preserve `npm run dev -- --host ... --port ...` preview support.
- Commit each completed task after its tests pass.

---

## File Structure

- `index.php`, `services.php`, `about.php`, `gallery.php`, `contact.php`: public pages.
- `book.php`, `manage.php`, `api/availability.php`: booking and customer-management entry points.
- `admin/index.php`, `admin/login.php`, `admin/bookings.php`, `admin/services.php`, `admin/staff.php`, `admin/settings.php`: authenticated entry points.
- `src/bootstrap.php`: autoloading, configuration, sessions, timezone, and security headers.
- `src/Database.php`: PDO connection and transaction helper.
- `src/Csrf.php`, `src/Auth.php`, `src/RateLimit.php`: security services.
- `src/Catalog.php`: service/staff/settings queries and mutations.
- `src/Availability.php`: slot generation and final availability checks.
- `src/Booking.php`: transactional booking and status operations.
- `templates/`: shared public/admin layouts and focused page partials.
- `assets/css/site.css`, `assets/css/admin.css`, `assets/js/booking.js`, `assets/images/`: local presentation assets.
- `database/schema.sql`, `database/seed.php`, `database/create_admin.php`: schema and CLI setup.
- `tests/`: isolated test runner and domain/integration tests.
- `router.php`, `server.mjs`, `package.json`: preview-server lifecycle.

### Task 1: Foundation, Configuration, and Preview Runtime

**Files:**
- Create: `config/config.example.php`, `src/Database.php`, `src/bootstrap.php`, `router.php`, `tests/bootstrap.php`, `tests/run.php`, `tests/DatabaseTest.php`, `.gitignore`
- Modify: `server.mjs`, `package.json`

**Interfaces:**
- Produces: `Database::connection(): PDO`, `Database::transaction(callable $callback): mixed`, `config(string $key, mixed $default = null): mixed`, `escape(?string $value): string`.
- Consumes: XAMPP PHP 8.1 and PDO MySQL/SQLite extensions.

- [ ] **Step 1: Write the failing database and bootstrap test**

Create `tests/DatabaseTest.php`:

```php
<?php
function test_database_and_escape(): void {
    $pdo = Database::connection();
    assert_same('1', $pdo->query('SELECT 1')->fetchColumn(), 'PDO query');
    assert_same('&lt;Book&gt;', escape('<Book>'), 'HTML escaping');
    $value = Database::transaction(fn() => 'committed');
    assert_same('committed', $value, 'transaction return value');
}
```

Create `tests/bootstrap.php`:

```php
<?php
declare(strict_types=1);
putenv('SPA_DB_DSN=sqlite::memory:');
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/tests/DatabaseTest.php';
```

Create `tests/run.php`:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
$tests = array_filter(get_defined_functions()['user'], fn($name) => str_starts_with($name, 'test_'));
$failed = 0;
foreach ($tests as $test) {
    try { $test(); echo "PASS $test\n"; }
    catch (Throwable $error) { $failed++; echo "FAIL $test: {$error->getMessage()}\n"; }
}
exit($failed ? 1 : 0);
```

- [ ] **Step 2: Run the test and verify the failure**

Run: `C:\xampp\php\php.exe tests\run.php`
Expected: FAIL because `src/bootstrap.php` does not exist.

- [ ] **Step 3: Implement the foundation**

Create `config/config.example.php`:

```php
<?php
return [
    'db_dsn' => getenv('SPA_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=happy_mobile_spa;charset=utf8mb4',
    'db_user' => getenv('SPA_DB_USER') ?: 'root',
    'db_pass' => getenv('SPA_DB_PASS') ?: '',
    'timezone' => 'Africa/Accra',
    'environment' => getenv('SPA_ENV') ?: 'development',
];
```

Create `src/Database.php`:

```php
<?php
declare(strict_types=1);
final class Database {
    private static ?PDO $pdo = null;
    public static function connection(): PDO {
        if (self::$pdo === null) {
            self::$pdo = new PDO(config('db_dsn'), config('db_user'), config('db_pass'), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
        return self::$pdo;
    }
    public static function transaction(callable $callback): mixed {
        $pdo = self::connection();
        $pdo->beginTransaction();
        try { $result = $callback($pdo); $pdo->commit(); return $result; }
        catch (Throwable $error) { $pdo->rollBack(); throw $error; }
    }
}
```

Create `src/bootstrap.php`:

```php
<?php
declare(strict_types=1);
const APP_ROOT = __DIR__ . '/..';
$config = is_file(APP_ROOT . '/config/config.php')
    ? require APP_ROOT . '/config/config.php'
    : require APP_ROOT . '/config/config.example.php';
function config(string $key, mixed $default = null): mixed {
    global $config; return $config[$key] ?? $default;
}
function escape(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
function assert_same(mixed $expected, mixed $actual, string $label): void {
    if ($expected !== $actual) throw new RuntimeException($label . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
}
spl_autoload_register(function(string $class): void {
    $file = __DIR__ . '/' . basename(str_replace('\\', '/', $class)) . '.php';
    if (is_file($file)) require $file;
});
date_default_timezone_set('UTC');
if (PHP_SAPI !== 'cli') {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
    session_start();
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}
```

Create `router.php`:

```php
<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) return false;
if ($path === '/') { require __DIR__ . '/index.php'; return true; }
http_response_code(404);
require __DIR__ . '/templates/404.php';
```

Replace `server.mjs` with:

```js
import { spawn } from 'node:child_process';
const args = process.argv.slice(2);
const value = (long, short) => {
  const index = args.findIndex(arg => arg === long || arg === short);
  return index >= 0 ? args[index + 1] : undefined;
};
const host = value('--host', '-H') || '127.0.0.1';
const port = Number(value('--port', '-p') || process.env.PORT || 7100);
if (!Number.isInteger(port) || port < 1 || port > 65535) throw new Error('Invalid port');
const php = process.env.SPA_PHP || 'C:/xampp/php/php.exe';
const child = spawn(php, ['-S', `${host}:${port}`, 'router.php'], { stdio: 'inherit', shell: false });
for (const signal of ['SIGINT', 'SIGTERM']) process.on(signal, () => child.kill(signal));
child.on('error', error => { console.error(error.message); process.exitCode = 1; });
child.on('exit', code => { process.exitCode = code ?? 1; });
```

Update `package.json`:

```json
{
  "name": "happy-mobile-spa",
  "private": true,
  "version": "1.0.0",
  "description": "Happy Mobile Spa website and booking system",
  "scripts": {
    "dev": "node server.mjs",
    "test": "C:/xampp/php/php.exe tests/run.php"
  }
}
```

Add `.gitignore`:

```gitignore
/config/config.php
/logo-preview.png
/.idea/
/.vscode/
```

- [ ] **Step 4: Run foundation tests and preview checks**

Run: `C:\xampp\php\php.exe tests\run.php`
Expected: `PASS test_database_and_escape`.

Run: `npm run dev -- --port 7100`
Expected: PHP development server listens on `127.0.0.1:7100`; stop it before continuing.

- [ ] **Step 5: Commit**

```bash
git add .gitignore config src router.php tests server.mjs package.json
git commit -m "build: add PHP application foundation"
```

### Task 2: Brand Assets and Public Website

**Files:**
- Create: `scripts/prepare_assets.py`, `templates/layout.php`, `templates/home.php`, `templates/services.php`, `templates/about.php`, `templates/gallery.php`, `templates/contact.php`, `templates/404.php`, `assets/css/site.css`, `assets/js/site.js`
- Create: `index.php`, `services.php`, `about.php`, `gallery.php`, `contact.php`
- Test: `tests/PublicPagesTest.php`

**Interfaces:**
- Consumes: `escape()` and `config()` from Task 1.
- Produces: `render_public(string $title, string $template, array $data = []): void`; public routes use database-backed catalog data after Task 3.

- [ ] **Step 1: Add failing page-rendering tests**

```php
<?php
function test_public_layout_branding(): void {
    $html = render_template_for_test('home', ['services' => []]);
    assert_contains('Happy Mobile Spa', $html, 'brand name');
    assert_contains('assets/images/logo.jpg', $html, 'logo asset');
    assert_contains('Book Now', $html, 'booking call to action');
    assert_not_contains('Serenity Spa', $html, 'old branding removed');
}
```

Add `assert_contains()` and `assert_not_contains()` to `tests/bootstrap.php`; make `render_template_for_test()` capture `render_public()` output without starting a second session.

- [ ] **Step 2: Run and verify failure**

Run: `C:\xampp\php\php.exe tests\run.php`
Expected: FAIL with `render_public()` undefined.

- [ ] **Step 3: Create optimized logo assets and public templates**

Use Pillow in `scripts/prepare_assets.py` to open the extracted `logo-assets/page-1-image-1-Im0.jpg`, crop pixels differing from white by more than 18 levels, add a 48-pixel margin, and save `assets/images/logo.jpg` at quality 88. Create a 64×64 favicon. Never overwrite the PDF.

Implement `render_public()` to extract data with `EXTR_SKIP`, capture the requested template, and include `templates/layout.php`. Build semantic header/nav/main/footer landmarks, a mobile menu, visible focus states, and Book Now links to `book.php`.

Define the exact design tokens in `assets/css/site.css`:

```css
:root {
  --ivory: #FAF7F0;
  --forest: #173F35;
  --leaf: #35B558;
  --burgundy: #BE1025;
  --charcoal: #24312C;
  --white: #FFFFFF;
  --display: Georgia, 'Times New Roman', serif;
  --body: Inter, 'Segoe UI', system-ui, sans-serif;
  --radius: 20px;
  --shadow: 0 18px 50px rgba(23, 63, 53, .12);
}
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { scroll-behavior: auto !important; animation: none !important; transition: none !important; }
}
```

Build complete responsive sections for hero, service cards, visit preparation, gallery, contact details, and footer. Show configured contact values only when non-empty. Use locally stored optimized imagery and empty `alt` text for decorative images. Include no ratings or testimonials.

- [ ] **Step 4: Verify rendering and accessibility basics**

Run: `C:\xampp\php\php.exe tests\run.php`
Expected: all tests PASS.

Run: `C:\xampp\php\php.exe -l index.php` and lint every new PHP file.
Expected: `No syntax errors detected` for each file.

- [ ] **Step 5: Commit**

```bash
git add index.php services.php about.php gallery.php contact.php templates assets scripts tests
git commit -m "feat: build branded public spa website"
```

### Task 3: Schema, Seed Data, and Catalog

**Files:**
- Create: `database/schema.sql`, `database/seed.php`, `src/Catalog.php`
- Modify: `services.php`, `templates/home.php`, `templates/services.php`
- Test: `tests/CatalogTest.php`, `tests/schema.mysql.sql`
- Create: `tests/Fixtures.php` with `seed_test_catalog(): void`; load it from `tests/bootstrap.php`.

**Interfaces:**
- Produces: `Catalog::activeServices(): array`, `Catalog::activeStaff(?int $serviceId = null): array`, `Catalog::settings(): array`, `Catalog::findService(string $slug): ?array`.
- Consumes: `Database::connection()` from Task 1.

- [ ] **Step 1: Write failing catalog tests**

```php
<?php
function test_active_catalog_and_price_types(): void {
    seed_test_catalog();
    $services = Catalog::activeServices();
    assert_same(6, count($services), 'six active seed services');
    assert_same('Swedish Massage', $services[0]['name'], 'first service');
    assert_same('350.00', $services[0]['price'], 'decimal price remains exact');
    $staff = Catalog::activeStaff((int) $services[0]['id']);
    assert_same(true, count($staff) >= 2, 'eligible therapists returned');
}
```

`seed_test_catalog()` must create equivalent SQLite test tables and insert only test rows. Keep MySQL integration schema in `tests/schema.mysql.sql` and skip MySQL integration unless `SPA_TEST_MYSQL=1` and the DSN database ends in `_test`.

- [ ] **Step 2: Run and verify failure**

Run: `C:\xampp\php\php.exe tests\run.php`
Expected: FAIL because `Catalog` is undefined.

- [ ] **Step 3: Implement schema and catalog**

Create all spec tables with foreign keys and indexes on booking start/end/status, customer phone, staff/service eligibility, and schedule dates. Use `DECIMAL(10,2)` for prices, `DATETIME` for UTC times, and unique normalized booking references and customer phones. `settings` stores unique keys with text values; `booking_events.details` stores JSON.

Seed Swedish Massage 60 min ₵350, Deep Tissue Ritual 90 min ₵520, Hot Stone Massage 75 min ₵480, Facial Glow 45 min ₵280, Mani + Pedi 90 min ₵220, and Prenatal Massage 60 min ₵400. Include the mockup buffers, four sample therapists, eligibility, Tuesday–Saturday hours, and a default 24-hour cutoff. Label therapists as editable demonstration records in admin, not public biographical claims.

Implement catalog methods with active/archive filters and stable display ordering. `findService()` returns `null` for missing/inactive slugs. Update public templates to use these methods and escape every value.

- [ ] **Step 4: Run tests and schema setup**

Run: `C:\xampp\php\php.exe tests\run.php`
Expected: all tests PASS.

Run: `C:\xampp\mysql\bin\mysql.exe -u root < database\schema.sql`
Expected: schema `happy_mobile_spa` created without errors.

Run: `C:\xampp\php\php.exe database\seed.php`
Expected: `Seeded Happy Mobile Spa demonstration catalog.`

- [ ] **Step 5: Commit**

```bash
git add database src/Catalog.php services.php templates tests
git commit -m "feat: add persistent service and staff catalog"
```

### Task 4: Availability Engine

**Files:**
- Create: `src/Availability.php`
- Test: `tests/AvailabilityTest.php`

**Interfaces:**
- Consumes: `Catalog::findService()`, `Database::connection()`.
- Produces: `Availability::slots(int $serviceId, ?int $staffId, string $date): array`, `Availability::isAvailable(int $serviceId, int $staffId, DateTimeImmutable $start, ?int $ignoreBookingId = null, ?PDO $pdo = null): bool`.
- Slot shape: `['staff_id' => int, 'start' => 'Y-m-d H:i:s', 'end' => 'Y-m-d H:i:s', 'label' => 'g:i A']`; all shape times are UTC.

- [ ] **Step 1: Write boundary and exclusion tests**

```php
<?php
function test_availability_excludes_breaks_buffers_and_time_off(): void {
    seed_availability_fixture();
    $slots = Availability::slots(1, 1, '2026-10-27');
    $labels = array_column($slots, 'label');
    assert_same('10:30', $labels[0], 'first slot follows booking buffer and break');
    assert_same(false, in_array('10:15', $labels, true), 'buffer and break excluded');
    assert_same(true, in_array('13:00', $labels, true), 'first lunch-end slot included');
    $start = new DateTimeImmutable('2026-10-27 09:00:00', new DateTimeZone('UTC'));
    assert_same(false, Availability::isAvailable(1, 3, $start), 'unassigned staff rejected');
    assert_same(false, Availability::isAvailable(1, 1, $start->modify('+1 day')), 'time off rejected');
}
function test_any_available_assigns_only_eligible_free_staff(): void {
    seed_availability_fixture();
    $slots = Availability::slots(1, null, '2026-10-27');
    assert_same([1, 2], array_values(array_unique(array_column($slots, 'staff_id'))), 'eligible staff');
}
```

Extend `tests/Fixtures.php` with `seed_availability_fixture(): void`. It resets only the SQLite in-memory tables, then creates service 1 (60 minutes plus 15-minute buffer), eligible staff 1 and 2, ineligible staff 3, a 09:00–10:00 booking for staff 1, a 10:00–10:30 break, 12:00–13:00 lunch, 09:00–18:00 opening hours, and next-day time off. Staff 2 has no booking and therefore appears in the any-therapist result.

- [ ] **Step 2: Run and verify failure**

Run: `C:\xampp\php\php.exe tests\run.php`
Expected: FAIL because `Availability` is undefined.

- [ ] **Step 3: Implement the single availability service**

Generate candidates in 15-minute increments in business-local time, convert to UTC for storage, and reject candidates unless the entire service plus buffer fits opening hours and the therapist's shift. Exclude breaks, holidays, time off, inactive/unassigned staff, inactive services, and overlaps with `pending`, `confirmed`, or `completed` bookings. Treat intervals as half-open: `[start, end)` so adjacent non-buffered appointments do not overlap.

For `staffId === null`, return slots grouped by start time with the lowest-ID eligible free therapist selected. `isAvailable()` repeats every rule for final writes and accepts `ignoreBookingId` for rescheduling.

- [ ] **Step 4: Run all availability tests**

Run: `C:\xampp\php\php.exe tests\run.php`
Expected: all tests PASS, including both availability tests.

- [ ] **Step 5: Commit**

```bash
git add src/Availability.php tests/AvailabilityTest.php
git commit -m "feat: calculate conflict-free appointment availability"
```

### Task 5: Guest Booking and Confirmation

**Files:**
- Create: `src/Booking.php`, `src/Csrf.php`, `book.php`, `api/availability.php`, `templates/book.php`, `templates/confirmation.php`, `assets/js/booking.js`
- Modify: `assets/css/site.css`
- Test: `tests/BookingTest.php`

**Interfaces:**
- Consumes: `Availability` and `Catalog`.
- Produces: `Booking::create(array $input): array`, `Booking::reference(int $length = 8): string`, `Csrf::token(): string`, `Csrf::verify(?string $token): bool`.
- `create()` input: `service_id`, optional `staff_id`, `start` UTC, `name`, `phone`, optional `email`, optional `notes`, `policy_accepted`; returns the inserted booking including `reference`.

- [ ] **Step 1: Write failing creation and concurrency tests**

```php
<?php
function test_guest_booking_is_confirmed_with_reference(): void {
    seed_availability_fixture();
    $booking = Booking::create([
        'service_id' => 1, 'staff_id' => null, 'start' => '2026-10-27 10:30:00',
        'name' => 'Efua Mensah', 'phone' => '0244127788', 'email' => '',
        'notes' => '', 'policy_accepted' => '1',
    ]);
    assert_same('confirmed', $booking['status'], 'booking status');
    assert_same('pay_at_spa', $booking['payment_method'], 'payment method');
    assert_same(12, strlen($booking['reference']), 'reference length');
}
function test_overlapping_booking_is_rejected(): void {
    test_guest_booking_is_confirmed_with_reference();
    assert_throws(fn() => Booking::create([
        'service_id' => 1, 'staff_id' => 1, 'start' => '2026-10-27 10:30:00',
        'name' => 'Another Client', 'phone' => '0200000000', 'email' => '',
        'notes' => '', 'policy_accepted' => '1',
    ]), 'Slot is no longer available');
}
```

Normalize Ghana numbers to local ten-digit form for `024...` and `23324...`; reject other formats. Generate references as `SPA-` plus eight cryptographically random uppercase alphanumeric characters.

- [ ] **Step 2: Run and verify failure**

Run: `C:\xampp\php\php.exe tests\run.php`
Expected: FAIL because `Booking` is undefined.

- [ ] **Step 3: Implement transactional booking and the multi-step UI**

Use a transaction, lock the selected staff row with `SELECT ... FOR UPDATE`, atomically choose an available therapist for **Any available**, re-run `Availability::isAvailable(..., $pdo)`, upsert the normalized customer, and insert booking plus `booking_events` audit data. Throw `DomainException('Slot is no longer available')` on conflict.

Build a four-step accessible form with service/therapist, date/time, details, and review. `api/availability.php` returns only JSON slots for valid service/date input. `booking.js` uses fetch with AbortController, disables Continue while loading, preserves selections, and shows server errors in an `aria-live` region. The final POST requires CSRF and policy acceptance. PRG redirects to `book.php?confirmation=REFERENCE` after creation; confirmation displays only a booking whose reference matches the session's recently created booking.

- [ ] **Step 4: Run tests and inspect the complete flow**

Run: `C:\xampp\php\php.exe tests\run.php`
Expected: all tests PASS.

Run the preview and book one demonstration appointment.
Expected: confirmation displays reference, service, therapist, date/time, price, and **Payment due at the spa**.

- [ ] **Step 5: Commit**

```bash
git add src book.php api templates assets tests
git commit -m "feat: add transactional guest appointment booking"
```

### Task 6: Customer Lookup, Rescheduling, and Cancellation

**Files:**
- Create: `manage.php`, `templates/manage-login.php`, `templates/manage-booking.php`, `src/RateLimit.php`
- Modify: `src/Booking.php`, `assets/css/site.css`
- Test: `tests/ManageBookingTest.php`

**Interfaces:**
- Produces: `Booking::findForCustomer(string $reference, string $phone): ?array`, `Booking::rescheduleForCustomer(int $id, string $start): array`, `Booking::cancelForCustomer(int $id): void`, `RateLimit::check(string $action, string $key, int $max = 8, int $seconds = 900): void`.
- Uses a 30-minute server-side management session after reference/phone verification.

- [ ] **Step 1: Write failing authorization and cutoff tests**

```php
<?php
function test_customer_lookup_requires_matching_phone(): void {
    $booking = seed_confirmed_booking();
    assert_same(null, Booking::findForCustomer($booking['reference'], '0200000000'), 'wrong phone denied');
    assert_same($booking['id'], Booking::findForCustomer($booking['reference'], '0244127788')['id'], 'matching phone allowed');
}
function test_customer_cannot_cancel_inside_cutoff(): void {
    $booking = seed_confirmed_booking('+12 hours');
    assert_throws(fn() => Booking::cancelForCustomer($booking['id']), 'Contact the spa to change this appointment');
}
```

- [ ] **Step 2: Run and verify failure**

Run: `C:\xampp\php\php.exe tests\run.php`
Expected: FAIL because management methods are undefined.

- [ ] **Step 3: Implement self-service and throttling**

Compare normalized phone numbers with `hash_equals`. Return generic lookup failure on the page. Rate-limit by action plus a hash of IP and normalized reference. Store a signed management session containing booking ID and expiry; regenerate the session ID after successful lookup.

Use the same transactional availability checks for rescheduling. Allow customer changes only when now is at least 24 hours before the existing appointment. Allow only `pending`/`confirmed` bookings to be rescheduled or cancelled. Insert audit events and use PRG with flash messages.

- [ ] **Step 4: Run tests**

Run: `C:\xampp\php\php.exe tests\run.php`
Expected: all tests PASS.

- [ ] **Step 5: Commit**

```bash
git add manage.php templates src assets tests
git commit -m "feat: add secure customer booking self-service"
```

### Task 7: Admin Authentication, Dashboard, and Calendar

**Files:**
- Create: `src/Auth.php`, `database/create_admin.php`, `admin/login.php`, `admin/logout.php`, `admin/index.php`, `admin/bookings.php`, `templates/admin/layout.php`, `templates/admin/login.php`, `templates/admin/dashboard.php`, `templates/admin/bookings.php`, `assets/css/admin.css`
- Modify: `src/Booking.php`
- Test: `tests/AuthTest.php`

**Interfaces:**
- Produces: `Auth::attempt(string $username, string $password): bool`, `Auth::requireUser(): array`, `Auth::logout(): void`, `Booking::adminList(array $filters): array`, `Booking::setStatus(int $id, string $status, int $adminId): void`, `Booking::setPaymentStatus(int $id, string $status, int $adminId): void`.

- [ ] **Step 1: Write failing authentication and transition tests**

```php
<?php
function test_admin_password_and_status_rules(): void {
    $hash = password_hash('correct horse battery staple', PASSWORD_DEFAULT);
    seed_admin('owner', $hash);
    assert_same(false, Auth::attempt('owner', 'wrong'), 'wrong password denied');
    assert_same(true, Auth::attempt('owner', 'correct horse battery staple'), 'correct password accepted');
    $booking = seed_confirmed_booking();
    Booking::setPaymentStatus($booking['id'], 'paid', 1);
    assert_same('paid', Booking::adminList(['id' => $booking['id']])[0]['payment_status'], 'payment recorded');
    Booking::setStatus($booking['id'], 'completed', 1);
    assert_throws(fn() => Booking::setStatus($booking['id'], 'confirmed', 1), 'Invalid status transition');
}
```

Add `seed_admin(string $username, string $hash): int` and `seed_confirmed_booking(string $startModifier = '+48 hours'): array` to `tests/Fixtures.php`. The booking fixture resets the relevant test rows, uses service 1/staff 1, sets customer phone `0244127788`, and applies the modifier to the current UTC time. `seed_manual_input()` returns the same valid input shape as `Booking::create()` with start `2026-10-27 10:30:00`.

- [ ] **Step 2: Run and verify failure**

Run: `C:\xampp\php\php.exe tests\run.php`
Expected: FAIL because `Auth` is undefined.

- [ ] **Step 3: Implement secure admin and schedule views**

`create_admin.php` interactively reads username and hidden password, validates at least 12 characters, and upserts no records silently. `Auth::attempt()` verifies active users, applies `RateLimit`, regenerates the session ID, and stores only admin ID/username. `requireUser()` redirects to login for unauthenticated web requests and throws in CLI tests.

Allow transitions: pending→confirmed/cancelled; confirmed→completed/cancelled/no_show; completed/cancelled/no_show are terminal. Build today KPIs, upcoming list, day/week calendar, therapist/status filters, booking details, and status forms with CSRF. Escape all data and never display internal notes publicly.

- [ ] **Step 4: Run tests and admin smoke check**

Run: `C:\xampp\php\php.exe tests\run.php`
Expected: all tests PASS.

Run: `C:\xampp\php\php.exe database\create_admin.php`
Expected: prompts for username/password and prints `Admin user created.`

- [ ] **Step 5: Commit**

```bash
git add src/Auth.php src/RateLimit.php src/Booking.php database admin templates assets tests
git commit -m "feat: add secure admin dashboard and calendar"
```

### Task 8: Admin Catalog, Staff, Settings, and Manual Booking

**Files:**
- Create: `admin/services.php`, `admin/staff.php`, `admin/settings.php`, `admin/manual-booking.php`, `templates/admin/services.php`, `templates/admin/staff.php`, `templates/admin/settings.php`, `templates/admin/manual-booking.php`
- Modify: `src/Catalog.php`, `src/Booking.php`, `templates/admin/layout.php`
- Test: `tests/AdminManagementTest.php`

**Interfaces:**
- Produces: `Catalog::saveService(array $input, ?int $id = null): int`, `Catalog::archiveService(int $id): void`, `Catalog::saveStaff(array $input, ?int $id = null): int`, `Catalog::saveSchedule(int $staffId, array $hours, array $breaks): void`, `Catalog::saveSettings(array $input): void`, `Booking::createManual(array $input, int $adminId, bool $override = false): array`.

- [ ] **Step 1: Write failing history and settings tests**

```php
<?php
function test_archive_preserves_history_and_settings_are_validated(): void {
    $booking = seed_confirmed_booking();
    Catalog::archiveService((int) $booking['service_id']);
    assert_same($booking['id'], Booking::adminList(['id' => $booking['id']])[0]['id'], 'history retained');
    assert_throws(fn() => Catalog::saveSettings(['cutoff_hours' => '-1']), 'Cutoff must be between 0 and 168 hours');
}
function test_manual_booking_records_admin_actor(): void {
    $booking = Booking::createManual(seed_manual_input(), 1);
    assert_same('admin', $booking['last_event_actor'], 'manual audit actor');
}
```

- [ ] **Step 2: Run and verify failure**

Run: `C:\xampp\php\php.exe tests\run.php`
Expected: FAIL because management methods are undefined.

- [ ] **Step 3: Implement management forms**

Validate names, prices ≥0, durations 15–480 minutes, buffers 0–120 minutes, valid weekdays/times, no overlapping breaks, and cutoff 0–168 hours. Archive/deactivate instead of deleting records referenced by bookings. Save schedule replacements in transactions. Manual bookings reuse `Booking::create()` internals but record admin actor, allow pending status, and mark `pay_at_spa` paid/unpaid. An explicit override may bypass conflicts only after a confirmation checkbox and records override details in `booking_events`.

Business settings manage opening hours, holidays, phone, email, address, timezone fixed to `Africa/Accra`, cutoff, and policy text. Public pages immediately consume saved values.

- [ ] **Step 4: Run tests and full PHP lint**

Run: `C:\xampp\php\php.exe tests\run.php`
Expected: all tests PASS.

Run: `for /r %f in (*.php) do @C:\xampp\php\php.exe -l "%f"`
Expected: no syntax errors.

- [ ] **Step 5: Commit**

```bash
git add admin templates src tests
git commit -m "feat: add admin catalog schedule and manual booking tools"
```

### Task 9: End-to-End Verification, Polish, and Delivery

**Files:**
- Create: `tests/e2e-checklist.md`, `database/README.md`
- Modify: `assets/css/site.css`, `assets/css/admin.css`, `assets/js/booking.js`, affected templates found during verification

**Interfaces:**
- Consumes every public and admin flow.
- Produces a verified release and reproducible setup instructions.

- [ ] **Step 1: Add final regression checks for observed failures**

For each defect found, add a named test to the relevant existing test file with concrete fixture and expected result before editing implementation. Example:

```php
function test_cancelled_booking_releases_slot(): void {
    $booking = seed_confirmed_booking();
    Booking::setStatus($booking['id'], 'cancelled', 1);
    $start = new DateTimeImmutable($booking['slot_start'], new DateTimeZone('UTC'));
    assert_same(true, Availability::isAvailable(1, 1, $start), 'cancelled slot is available');
}
```

- [ ] **Step 2: Run the regression before fixes**

Run: `C:\xampp\php\php.exe tests\run.php`
Expected: newly added regression tests fail for the observed defects; existing passing behavior remains green.

- [ ] **Step 3: Fix defects and complete browser verification**

Verify mobile widths 360/390/768 and desktop 1280/1440; keyboard-only booking; visible focus; 200% zoom; reduced motion; invalid phone/email; CSRF failure; stale slot; wrong booking phone; cutoff enforcement; admin logout protection; service archive history; schedule changes; manual booking; cancellation releasing a slot; and print-friendly confirmation.

Document exact setup in `database/README.md`: create schema, seed demonstration catalog, create admin, configure environment, launch Apache or `npm run dev`, and remove demonstration records before production. Include backup instructions and a warning not to expose XAMPP directly to the public internet.

- [ ] **Step 4: Run final automated and preview checks**

Run: `C:\xampp\php\php.exe tests\run.php`
Expected: all tests PASS with zero failures.

Run: `npm run dev -- --port 7100`
Expected: public website and admin flows work through the PHP preview. Stop the server after verification.

- [ ] **Step 5: Commit**

```bash
git add assets templates tests database
git commit -m "test: verify complete spa website and booking release"
```

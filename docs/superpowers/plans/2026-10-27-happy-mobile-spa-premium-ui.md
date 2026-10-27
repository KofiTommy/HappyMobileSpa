# Happy Mobile Spa Premium UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transform the existing public website, customer booking flow, and reception dashboards into a polished Elevated Quiet Luxury experience while preserving every working business rule and route.

**Architecture:** Keep the current PHP entry points, template renderer, SQLite/MySQL data layer, vanilla JavaScript, and existing CSS files. Add one whitelisted SVG helper and one read-only overview presenter; build all other improvements through targeted template, CSS, and enhancement-script changes. Public and admin styling share brand values but retain separate layouts and information density.

**Tech Stack:** PHP 8.1, existing PDO SQLite/MySQL layer, HTML5, CSS3, vanilla JavaScript, existing Node preview server, existing PHP regression suite.

## Global Constraints

- Ivory background: `#FAF7F0`; forest primary: `#173F35`; leaf accent: `#35B558`; burgundy accent: `#BE1025`; charcoal text: `#24312C`.
- Real information only: no fabricated reviews, awards, statistics, testimonials, popularity labels, or business claims.
- Preserve the supplied logo and existing gallery/team assets.
- Preserve the four booking steps and all server-side CSRF, availability, payment, and booking validation.
- JavaScript enhances but is not required for core booking and management actions.
- Do not change Paystack/webhook behavior, SMS workers, authentication rules, booking conflict rules, or customer permissions.
- Do not add a frontend framework, build step, external analytics, or new production dependency.
- Preserve all existing uncommitted user work; use targeted edits and review diffs file by file.
- Do not stage or commit pre-existing user changes. Because most target files are already dirty, each task ends with a diff checkpoint rather than a code commit; only newly created, previously clean files may be committed independently.
- Run `C:/xampp/php/php.exe tests/run.php` and `C:/xampp/php/php.exe tests/mysql-settings-regression.php` after behavioral changes.
- Keep all temporary verification data inside the test database and remove temporary records/servers before delivery.

---

### Task 1: Establish and Repair the Regression Baseline

**Files:**
- Modify: `tests/Fixtures.php`
- Modify: `tests/AvailabilityTest.php`
- Modify: `tests/BookingTest.php`
- Modify: `tests/ManageBookingTest.php`
- Modify: `tests/AdminManagementTest.php`
- Modify: `tests/ReceptionistTest.php`
- Modify: `tests/SmsTest.php`
- Modify: `tests/PaystackTest.php`

**Interfaces:**
- Consumes: existing `seed_availability_fixture(): void`, `booking_input(): array`, and `Availability` business rules.
- Produces: `fixture_date(): string`, returning a future Tuesday in `Y-m-d`; `fixture_datetime(string $time): string`, returning that date and time in `Y-m-d H:i:s`. Production availability behavior remains unchanged.

- [ ] **Step 1: Record the current failures and write a fixture-date regression test**

Current baseline: MySQL regression passes; `tests/run.php` has eight failures caused by the hard-coded 27 October 2026 fixture now being in the past. Add to `tests/PublicPagesTest.php`:

```php
function test_availability_fixture_is_always_in_the_future(): void
{
    $date = new DateTimeImmutable(fixture_date(), new DateTimeZone('UTC'));
    assert_same('Tuesday', $date->format('l'), 'fixture remains on staffed Tuesday');
    assert_same(true, $date > new DateTimeImmutable('today', new DateTimeZone('UTC')), 'fixture is not in the past');
    assert_same(fixture_date() . ' 10:30:00', fixture_datetime('10:30'), 'fixture datetime format');
}
```

- [ ] **Step 2: Run the new test and confirm the missing-helper failure**

Run: `C:/xampp/php/php.exe tests/run.php`

Expected: `FAIL test_availability_fixture_is_always_in_the_future` with an undefined `fixture_date()` error; the eight existing availability failures remain visible.

- [ ] **Step 3: Replace calendar-specific fixture dates with one future-Tuesday helper**

Add to `tests/Fixtures.php`:

```php
function fixture_date(): string
{
    $today = new DateTimeImmutable('today', new DateTimeZone('UTC'));
    $tuesday = $today->modify('next tuesday');
    return $tuesday->format('Y-m-d');
}

function fixture_datetime(string $time): string
{
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $time)) {
        throw new InvalidArgumentException('Fixture time must be HH:MM');
    }
    return fixture_date() . ' ' . $time . ':00';
}
```

In `seed_availability_fixture()`, build the existing booking and leave record from the helper:

```php
$date = fixture_date();
$leaveDate = (new DateTimeImmutable($date, new DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d');
$leave = $pdo->prepare('INSERT INTO staff_time_off (staff_id,off_date,note) VALUES (1,?,\'Leave\')');
$leave->execute([$leaveDate]);
$existing = $pdo->prepare("INSERT INTO bookings (reference,customer_id,service_id,staff_id,slot_start,slot_end,price,status,payment_method,payment_status,notes,accepted_policy,created_at,updated_at) VALUES ('SPA-EXISTING',1,1,1,?,?,350,'confirmed','pay_at_spa','unpaid','','Policy',datetime(),datetime())");
$existing->execute([fixture_datetime('09:00'), fixture_datetime('10:00')]);
```

Replace only test expectations and test inputs tied to `2026-10-27`: use `fixture_date()` for dates, `fixture_datetime('HH:MM')` for UTC datetimes, and `fixture_date() . 'T10:30'` for browser-local input. Replace the separate `2026-10-28` leave expectation with `(new DateTimeImmutable(fixture_date(), new DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d')`. Keep the Tuesday weekday value `2`, relative `+48 hours` fixtures, and all production files unchanged.

- [ ] **Step 4: Verify the entire baseline is green**

Run:

```bash
C:/xampp/php/php.exe tests/run.php
C:/xampp/php/php.exe tests/mysql-settings-regression.php
```

Expected: every test prints `PASS`; MySQL prints `PASS MySQL unchanged settings can be saved repeatedly.`

- [ ] **Step 5: Review the test-only checkpoint**

```bash
git diff -- tests/Fixtures.php tests/PublicPagesTest.php tests/AvailabilityTest.php tests/BookingTest.php tests/ManageBookingTest.php tests/AdminManagementTest.php tests/ReceptionistTest.php tests/SmsTest.php tests/PaystackTest.php
```

Expected: only future-proof fixture/test changes are shown; no production diff is introduced by this task.

---

### Task 2: Add the Shared Visual Language and Accessible Public Shell

**Files:**
- Create: `src/Ui.php`
- Modify: `src/view.php`
- Modify: `templates/layout.php`
- Modify: `assets/css/site.css`
- Modify: `assets/js/site.js`
- Test: `tests/PublicPagesTest.php`

**Interfaces:**
- Consumes: existing `escape()`, `$activePage`, `$settings`, and `$content` template values.
- Produces: `ui_icon(string $name, string $class = ''): string`; supported names are `arrow`, `calendar`, `check`, `chevron`, `clock`, `close`, `mail`, `menu`, `phone`, `pin`, `plus`, and `user`. Unknown names throw `InvalidArgumentException`; all output is `aria-hidden="true"` and `focusable="false"`.

- [ ] **Step 1: Write failing icon and shell accessibility tests**

```php
function test_shared_icons_and_public_shell_are_accessible(): void
{
    $icon = ui_icon('calendar', 'metric-icon');
    assert_contains('viewBox="0 0 24 24"', $icon, 'SVG viewbox');
    assert_contains('aria-hidden="true"', $icon, 'decorative SVG');
    assert_contains('class="metric-icon"', $icon, 'icon class escaped');
    assert_throws(fn () => ui_icon('not-an-icon'), 'Unknown icon');
    $html = render_template_for_test('home', ['services' => [], 'settings' => [], 'heroImages' => Gallery::defaults(), 'staff' => [], 'galleryImages' => []]);
    assert_contains('aria-label="Open navigation"', $html, 'menu accessible name');
    assert_contains('class="mobile-book-bar"', $html, 'mobile booking access');
    assert_contains('href="#main"', $html, 'skip link retained');
}
```

Run: `C:/xampp/php/php.exe tests/run.php`
Expected: failure on undefined `ui_icon()`.

- [ ] **Step 2: Implement the whitelisted SVG helper**

Create `src/Ui.php` with a `$paths` map containing simple 24×24 stroke icons and this exact public API:

```php
<?php
declare(strict_types=1);

final class Ui
{
    public static function icon(string $name, string $class = ''): string
    {
        $paths = [
            'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
            'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
            'check' => '<path d="m5 12 4 4L19 6"/>',
            'chevron' => '<path d="m6 9 6 6 6-6"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
            'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
            'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
            'phone' => '<path d="M7 3H4a1 1 0 0 0-1 1c0 9.4 7.6 17 17 17a1 1 0 0 0 1-1v-3l-4-2-2 2c-3.5-1.5-6.5-4.5-8-8l2-2-2-4Z"/>',
            'pin' => '<path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2"/>',
            'plus' => '<path d="M12 5v14M5 12h14"/>',
            'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        ];
        if (!isset($paths[$name])) { throw new InvalidArgumentException('Unknown icon: ' . $name); }
        return '<svg class="' . escape($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
    }
}
```

Add to `src/view.php`:

```php
function ui_icon(string $name, string $class = ''): string
{
    return Ui::icon($name, $class);
}
```

- [ ] **Step 3: Refine the header/footer and mobile booking access**

In `templates/layout.php`, keep every current route and replace text arrows/menu bars with `ui_icon()`. Give the toggle an explicit accessible name and add the compact mobile action before the footer:

```php
<button class="menu-toggle" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="site-nav" data-menu-toggle>
    <?= ui_icon('menu', 'menu-icon-open') ?><?= ui_icon('close', 'menu-icon-close') ?>
</button>
```

```php
<nav class="mobile-book-bar" aria-label="Quick booking">
    <a href="book.php"><?= ui_icon('calendar') ?><span>Book your treatment</span><?= ui_icon('arrow') ?></a>
</nav>
```

- [ ] **Step 4: Add resilient tokens, focus, controls, and reduced-motion rules**

At the top of `assets/css/site.css`, retain the established fonts and replace the root/component foundation with:

```css
:root{--ivory:#FAF7F0;--forest:#173F35;--leaf:#35B558;--burgundy:#BE1025;--charcoal:#24312C;--white:#FFF;--sand:#EEE9DE;--sage:#DDE7DE;--muted:#5C6D64;--line:rgba(23,63,53,.16);--display:'EB Garamond',Georgia,serif;--body:'Jost','Segoe UI',system-ui,sans-serif;--radius:18px;--radius-sm:10px;--shadow:0 24px 70px rgba(23,63,53,.12);--page:clamp(1.25rem,5vw,6.5rem);--content:1440px}
:focus-visible{outline:3px solid var(--burgundy);outline-offset:4px}
.button,.text-link,.menu-toggle{min-height:44px}
.button{border-radius:999px;transition:transform .25s,background .25s,color .25s,box-shadow .25s}
.button:hover{transform:translateY(-2px);box-shadow:0 10px 24px rgba(23,63,53,.14)}
.menu-icon-close{display:none}.menu-toggle[aria-expanded=true] .menu-icon-open{display:none}.menu-toggle[aria-expanded=true] .menu-icon-close{display:block}
.mobile-book-bar{display:none}
@media(max-width:760px){body{padding-bottom:72px}.mobile-book-bar{position:fixed;display:block;inset:auto 12px 12px;z-index:90}.mobile-book-bar a{display:flex;align-items:center;justify-content:space-between;gap:12px;min-height:52px;padding:12px 18px;border-radius:999px;background:var(--forest);color:var(--ivory);box-shadow:var(--shadow)}.booking-shell~.mobile-book-bar,.manage-shell~.mobile-book-bar{display:none}}
@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}*,*::before,*::after{animation:none!important;transition:none!important;scroll-behavior:auto!important}}
```

- [ ] **Step 5: Make navigation dismissible and verify**

Extend `assets/js/site.js` with one close function, Escape handling, outside-click handling, and resize cleanup; update `aria-label` alongside `aria-expanded`:

```javascript
const closeMenu = () => {
  menuButton.setAttribute('aria-expanded', 'false');
  menuButton.setAttribute('aria-label', 'Open navigation');
  menu.classList.remove('is-open');
  document.body.classList.remove('menu-open');
};
document.addEventListener('keydown', event => {
  if (event.key === 'Escape' && menuButton.getAttribute('aria-expanded') === 'true') {
    closeMenu(); menuButton.focus();
  }
});
window.addEventListener('resize', () => { if (window.innerWidth > 760) closeMenu(); }, { passive: true });
```

Run: `C:/xampp/php/php.exe tests/run.php && node --check assets/js/site.js`
Expected: all PHP tests pass and JavaScript exits `0`.
Checkpoint: `git diff -- src/Ui.php src/view.php templates/layout.php assets/css/site.css assets/js/site.js tests/PublicPagesTest.php`

---

### Task 3: Build the Editorial Homepage with Real Content

**Files:**
- Modify: `index.php`
- Modify: `templates/home.php`
- Modify: `assets/css/site.css`
- Modify: `assets/js/hero-carousel.js` only if existing carousel controls conflict with the new markup
- Test: `tests/PublicPagesTest.php`

**Interfaces:**
- Consumes: `Catalog::activeServices()`, `Catalog::activeStaff()`, `Gallery::images(bool $heroOnly = false)`, current settings and payment readiness.
- Produces: `$staff` and `$galleryImages` for `templates/home.php`; first active service by existing display order becomes `$featuredService`. No popularity field is introduced.

- [ ] **Step 1: Write failing homepage content tests**

```php
function test_homepage_uses_real_catalog_team_gallery_and_practical_faq(): void
{
    seed_test_catalog();
    $html = render_template_for_test('home', [
        'services' => Catalog::activeServices(), 'settings' => [],
        'heroImages' => Gallery::defaults(), 'staff' => Catalog::activeStaff(),
        'galleryImages' => array_slice(Gallery::defaults(), 0, 3),
    ]);
    assert_contains('Featured treatment', $html, 'display-order feature label');
    assert_contains('Swedish Massage', $html, 'real service');
    assert_contains('Ama S.', $html, 'real therapist');
    assert_contains('How do I book?', $html, 'practical FAQ');
    assert_contains('Real availability', $html, 'factual trust signal');
    assert_not_contains('Most popular', $html, 'no invented popularity');
}
```

Run: `C:/xampp/php/php.exe tests/run.php`
Expected: missing homepage structure/content assertions fail.

- [ ] **Step 2: Supply only existing records to the template**

Use this in `index.php`:

```php
$services = Catalog::activeServices();
$settings = Catalog::settings();
$heroImages = Gallery::images(true);
$staff = Catalog::activeStaff();
$galleryImages = array_slice(Gallery::images(), 0, 3);
$featuredService = $services[0] ?? null;
render_public('Massage & wellness', 'home', compact('services', 'settings', 'heroImages', 'staff', 'galleryImages', 'featuredService'));
```

- [ ] **Step 3: Compose the homepage sections without changing booking routes**

Retain the existing hero carousel data attributes and controls. After the hero, add this factual trust strip and semantic section order:

```php
<section class="trust-strip" aria-label="Booking benefits">
    <span><?= ui_icon('calendar') ?> Real availability</span>
    <span><?= ui_icon('check') ?> Secure booking</span>
    <span><?= ui_icon('user') ?> No account needed</span>
    <span><?= ui_icon('clock') ?> <?= Paystack::ready() ? 'Mobile money or pay at spa' : 'Pay at the spa' ?></span>
</section>
```

Build treatment cards from `array_slice($services, 0, 6)` with name, description, duration, price, and `book.php?service=<slug>`. Render `$featuredService` only when non-null. Build team cards from `$staff`, using `../`-free public photo paths exactly as stored and initials when `photo` is null. Build gallery figures from `$galleryImages`; do not add captions or biographies from outside database/default asset records.

Use native accessible FAQs:

```php
<section class="home-faq section-grid" id="faqs">
    <p class="section-index">05 / PRACTICAL DETAILS</p>
    <div><h2>Before you book.</h2>
        <details><summary>How do I book? <?= ui_icon('plus') ?></summary><p>Choose a treatment, select an available time, enter your details, and review your appointment. No account is required.</p></details>
        <details><summary>Can I change or cancel? <?= ui_icon('plus') ?></summary><p>Use Manage booking with your reference and phone number. Online changes follow the cancellation policy shown during booking.</p></details>
        <details><summary>How do I pay? <?= ui_icon('plus') ?></summary><p><?= Paystack::ready() ? 'Choose mobile money through Paystack or pay at the spa. Your review page shows the available choices.' : 'Payment is made at the spa.' ?></p></details>
        <details><summary>What should I tell my therapist? <?= ui_icon('plus') ?></summary><p>Add pressure preferences, sensitive areas, pregnancy, injuries, or comfort notes to your booking, or discuss them privately when you arrive.</p></details>
    </div>
</section>
```

- [ ] **Step 4: Add the editorial layouts and resilient card treatment**

```css
.trust-strip{display:grid;grid-template-columns:repeat(4,1fr);margin:0 var(--page);border:1px solid var(--line);border-radius:var(--radius);background:#ffffff80}.trust-strip span{display:flex;align-items:center;justify-content:center;gap:10px;min-height:82px;padding:18px;border-right:1px solid var(--line);font-size:.82rem}.trust-strip span:last-child{border:0}.trust-strip svg{width:20px;height:20px;color:var(--leaf)}
.treatment-cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.treatment-card{display:flex;flex-direction:column;min-width:0;padding:28px;border:1px solid var(--line);border-radius:var(--radius);background:#ffffff8c;overflow-wrap:anywhere}.treatment-card h3{font-size:2rem}.treatment-card .service-facts{margin-top:auto;padding-top:24px}.team-grid,.home-gallery-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:24px}.team-card img,.team-initials{width:100%;aspect-ratio:4/5;border-radius:var(--radius);object-fit:cover}.team-initials{display:grid;place-items:center;background:var(--sage);font-family:var(--display);font-size:4rem}.home-faq details{border-top:1px solid var(--line);padding:20px 0}.home-faq summary{display:flex;justify-content:space-between;gap:20px;cursor:pointer;font-family:var(--display);font-size:1.5rem;color:var(--forest)}
@media(max-width:900px){.treatment-cards,.team-grid,.home-gallery-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.trust-strip{grid-template-columns:repeat(2,1fr)}}
@media(max-width:560px){.treatment-cards,.team-grid,.home-gallery-grid,.trust-strip{grid-template-columns:1fr}.trust-strip span{justify-content:flex-start;border-right:0;border-bottom:1px solid var(--line)}}
```

Run: `C:/xampp/php/php.exe tests/run.php`
Expected: all tests pass, including homepage content and existing gallery/carousel escaping tests.
Checkpoint: `git diff -- index.php templates/home.php assets/css/site.css assets/js/hero-carousel.js tests/PublicPagesTest.php`

---

### Task 4: Align Secondary Public Pages and Empty States

**Files:**
- Modify: `templates/services.php`
- Modify: `templates/about.php`
- Modify: `templates/gallery.php`
- Modify: `templates/contact.php`
- Modify: `assets/css/site.css`
- Test: `tests/PublicPagesTest.php`

**Interfaces:**
- Consumes: existing `$services`, `$images`, `$settings`, `$old`, `$sent`, and `$error`; all form field names and actions remain unchanged.
- Produces: shared `.editorial-hero`, `.catalog-card`, `.content-panel`, `.form-panel`, `.empty-state`, and `.contact-methods` structures.

- [ ] **Step 1: Add failing secondary-page and empty-gallery coverage**

```php
function test_secondary_pages_have_actions_and_intentional_empty_states(): void
{
    $html = render_template_for_test('services', ['services' => [], 'settings' => []]);
    assert_contains('Contact us', $html, 'empty catalog contact action');
    $html = render_template_for_test('gallery', ['images' => [], 'settings' => []]);
    assert_contains('gallery is being prepared', $html, 'empty gallery message');
    assert_contains('Explore treatments', $html, 'empty gallery next action');
    $html = render_template_for_test('contact', ['settings' => [], 'old' => []]);
    assert_contains('contact-methods', $html, 'contact layout');
    assert_contains('name="message"', $html, 'message form retained');
}
```

Run: `C:/xampp/php/php.exe tests/run.php`
Expected: new secondary-page assertions fail.

- [ ] **Step 2: Convert the catalog and gallery to semantic cards with real fallbacks**

For each service, use this structure and preserve the exact booking URL:

```php
<article class="catalog-card">
    <span class="treatment-number"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
    <div class="catalog-card-copy"><h2><?= escape($service['name']) ?></h2><p><?= escape($service['description']) ?></p></div>
    <dl class="catalog-card-facts"><div><dt>Duration</dt><dd><?= (int) $service['duration_min'] ?> min</dd></div><div><dt>Price</dt><dd><?= money($service['price']) ?></dd></div></dl>
    <a class="button button-small" href="book.php?service=<?= escape($service['slug']) ?>">Book treatment <?= ui_icon('arrow') ?></a>
</article>
```

Use these intentional empty states:

```php
<div class="empty-state"><h2>Our treatment menu is being prepared.</h2><p>Please check back soon or contact us for help choosing a treatment.</p><a class="button" href="contact.php">Contact us</a></div>
```

```php
<div class="empty-state"><h2>Our gallery is being prepared.</h2><p>Explore our treatments or contact us to plan your visit.</p><a class="button" href="services.php">Explore treatments</a></div>
```

- [ ] **Step 3: Group about/contact content and retain every supported detail**

Keep the current about copy and image, wrapping before-visit items in `.content-panel`. On contact, group address, phone, email, and opening hours inside `.contact-methods` with matching `pin`, `phone`, `mail`, and `clock` icons. Put the existing message form in `.form-panel`; retain CSRF, honeypot, validation attributes, status/error roles, field names, and `contact.php#send-message` action.

- [ ] **Step 4: Style the shared editorial components and verify**

```css
.catalog-card{display:grid;grid-template-columns:48px minmax(0,1fr) minmax(180px,.35fr) auto;gap:24px;align-items:center;padding:30px;margin-bottom:16px;border:1px solid var(--line);border-radius:var(--radius);background:#ffffff80}.catalog-card h2{font-size:2.2rem}.catalog-card-facts{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin:0}.catalog-card-facts dt{font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;color:var(--muted)}.catalog-card-facts dd{margin:4px 0 0;color:var(--forest);font-weight:500}.content-panel,.form-panel,.contact-methods{padding:clamp(24px,4vw,52px);border:1px solid var(--line);border-radius:var(--radius);background:#ffffff80}.contact-methods{display:grid;gap:22px}.contact-method{display:grid;grid-template-columns:28px 1fr;gap:14px}.contact-method svg{width:24px;height:24px;color:var(--leaf)}.empty-state{padding:clamp(30px,5vw,60px);border:1px solid var(--line);border-radius:var(--radius);background:var(--sand)}
@media(max-width:1000px){.catalog-card{grid-template-columns:40px 1fr auto}.catalog-card-facts{grid-column:2/3}.catalog-card .button{grid-column:3;grid-row:1/3}}
@media(max-width:640px){.catalog-card{grid-template-columns:1fr;padding:24px}.catalog-card-facts,.catalog-card .button{grid-column:1;grid-row:auto}.catalog-card .treatment-number{display:none}}
```

Run: `C:/xampp/php/php.exe tests/run.php`
Expected: all tests pass; existing contact escaping and gallery tests remain green.
Checkpoint: `git diff -- templates/services.php templates/about.php templates/gallery.php templates/contact.php assets/css/site.css tests/PublicPagesTest.php`

---

### Task 5: Add the Two-Column Booking Flow and Live Summary

**Files:**
- Modify: `templates/book.php`
- Modify: `assets/css/booking.css`
- Modify: `assets/js/booking.js`
- Test: `tests/PublicPagesTest.php`

**Interfaces:**
- Consumes: existing `$services`, `$staff`, `$selectedService`, `$old`, `$error`, `$settings`, therapist profile JSON, and `api/availability.php` slot objects.
- Produces: `.booking-layout`, `.booking-main`, `.appointment-summary`, `[data-summary]`, `[data-slot-period]`, and a retry button with `[data-retry-slots]`; existing form field names and submission contract do not change.

- [ ] **Step 1: Add failing booking enhancement contract tests**

```php
function test_booking_has_live_summary_grouped_slots_and_retry_enhancements(): void
{
    $template = file_get_contents(APP_ROOT . '/templates/book.php');
    assert_contains('data-summary', $template, 'live appointment summary');
    assert_contains('booking-layout', $template, 'two-column booking structure');
    $js = file_get_contents(APP_ROOT . '/assets/js/booking.js');
    assert_contains('data-slot-period', $js, 'morning and afternoon groups');
    assert_contains('data-retry-slots', $js, 'availability retry');
    assert_contains('updateSummary', $js, 'selections update summary');
    assert_contains('updateSummary();', $js, 'summary initialized');
}
```

Run: `C:/xampp/php/php.exe tests/run.php`
Expected: missing summary/slot-group assertions fail.

- [ ] **Step 2: Wrap the existing form in current-step and summary columns**

Keep CSRF, progress, fieldsets, field names, therapist JSON, and payment inputs. Place progress and fieldsets inside `.booking-main`; add the summary beside them inside `.booking-layout`:

```php
<div class="booking-layout">
    <div class="booking-main">
        <!-- existing progress and four fieldsets remain here -->
    </div>
    <aside class="appointment-summary" aria-label="Appointment summary">
        <p class="eyebrow">Your selection</p>
        <h2>Appointment summary</h2>
        <dl data-summary aria-live="polite"></dl>
        <p class="summary-policy"><?= escape($settings['cancellation_policy'] ?? 'Contact the spa before changing your appointment.') ?></p>
    </aside>
</div>
```

Do not put PHP comments into the delivered template; the comment above identifies the insertion region only. Give each service radio input `data-service-label`, `data-service-duration`, and `data-service-price` so JavaScript never parses formatted display text.

- [ ] **Step 3: Implement live summary, period grouping, and retry without altering availability**

Add these exact functions to `assets/js/booking.js` and call `updateSummary()` after every service, staff, date, or slot change:

```javascript
const updateSummary = () => {
  const service = selectedService();
  const selectedSlot = slots.querySelector('input:checked');
  const rows = [
    ['Treatment', service?.dataset.serviceLabel || 'Choose a treatment'],
    ['Duration', service?.dataset.serviceDuration ? `${service.dataset.serviceDuration} min` : '—'],
    ['Price', service?.dataset.servicePrice || '—'],
    ['Therapist', staffSelect.options[staffSelect.selectedIndex]?.text || 'Any available therapist'],
    ['Date', dateInput.value || 'Choose a date'],
    ['Time', selectedSlot?.dataset.timeLabel || 'Choose a time'],
  ];
  const summary = form.querySelector('[data-summary]');
  summary.replaceChildren();
  rows.forEach(([term, value]) => {
    const row = document.createElement('div');
    const dt = document.createElement('dt'); dt.textContent = term;
    const dd = document.createElement('dd'); dd.textContent = value;
    row.append(dt, dd); summary.append(row);
  });
};

const appendSlot = (container, slot) => {
  const label = document.createElement('label');
  label.className = 'time-slot';
  const input = document.createElement('input');
  input.type = 'radio'; input.name = 'slot_choice'; input.value = slot.start;
  input.dataset.timeLabel = slot.label;
  const text = document.createElement('span');
  text.textContent = slot.label;
  const staff = document.createElement('small'); staff.textContent = slot.staff_name;
  text.append(staff); label.append(input, text); container.append(label);
  input.addEventListener('change', event => { startInput.value = event.target.value; updateSummary(); });
};
```

In `loadSlots()`, replace flat rendering with two groups based on the hour in `slot.label` parsed from `slot.start` in UTC:

```javascript
const periods = [
  { name: 'Morning', slots: data.slots.filter(slot => Number(slot.start.slice(11, 13)) < 12) },
  { name: 'Afternoon', slots: data.slots.filter(slot => Number(slot.start.slice(11, 13)) >= 12) },
];
periods.filter(period => period.slots.length).forEach(period => {
  const group = document.createElement('section');
  group.dataset.slotPeriod = period.name.toLowerCase();
  const heading = document.createElement('h3'); heading.textContent = period.name;
  const grid = document.createElement('div'); grid.className = 'slot-grid';
  period.slots.forEach(slot => appendSlot(grid, slot));
  group.append(heading, grid); slots.append(group);
});
```

On non-abort failures, set the status message and append a typed button:

```javascript
const retry = document.createElement('button');
retry.type = 'button'; retry.className = 'button-back'; retry.dataset.retrySlots = '';
retry.textContent = 'Try loading times again'; retry.addEventListener('click', loadSlots);
slotStatus.append(document.createTextNode(' '), retry);
```

- [ ] **Step 4: Style the responsive two-column flow and submission states**

```css
.booking-layout{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:32px;align-items:start}.booking-main{min-width:0}.appointment-summary{position:sticky;top:110px;padding:28px;border-radius:var(--radius);background:var(--forest);color:#E6EEE8}.appointment-summary h2{color:var(--ivory);font-size:2rem}.appointment-summary dl>div{display:grid;grid-template-columns:90px 1fr;gap:12px;padding:12px 0;border-bottom:1px solid #ffffff26}.appointment-summary dt{font-size:.72rem;color:#B7D5BF}.appointment-summary dd{margin:0;overflow-wrap:anywhere}.summary-policy{margin:24px 0 0;font-size:.8rem}.slot-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.time-slot input:focus-visible+span{outline:3px solid var(--burgundy);outline-offset:3px}.time-slot input:checked+span{border-color:var(--forest);background:var(--sage)}.button:disabled{cursor:wait;opacity:.7;transform:none}.form-error,.form-alert:not(:empty){border-radius:var(--radius-sm)}
@media(max-width:900px){.booking-layout{grid-template-columns:1fr}.appointment-summary{position:static;order:-1}.appointment-summary dl{display:grid;grid-template-columns:1fr 1fr;gap:0 18px}}
@media(max-width:560px){.slot-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.appointment-summary dl{display:block}}
```

Run: `C:/xampp/php/php.exe tests/run.php && node --check assets/js/booking.js`
Expected: all tests pass; JavaScript syntax is valid. In a later browser task, disable JavaScript and verify all four fieldsets and the submit action remain present.
Checkpoint: `git diff -- templates/book.php assets/css/booking.css assets/js/booking.js tests/PublicPagesTest.php`

---

### Task 6: Polish Confirmation, Payment, and Customer Management

**Files:**
- Modify: `templates/confirmation.php`
- Modify: `templates/manage-login.php`
- Modify: `templates/manage-booking.php`
- Modify: `templates/payment.php`
- Modify: `templates/payment-return.php`
- Modify: `assets/css/booking.css`
- Modify: `assets/js/site.js`
- Test: `tests/PublicPagesTest.php`

**Interfaces:**
- Consumes: existing booking records, `$canChange`, `$slots`, payment states, current routes and CSRF forms.
- Produces: `[data-copy-reference]` and `[data-copy-status]` progressive copy controls; shared `.appointment-card`, `.appointment-actions`, `.danger-zone`, and `.status-banner` components.

- [ ] **Step 1: Add failing customer-surface tests**

```php
function test_customer_surfaces_use_accessible_appointment_components(): void
{
    $confirmation = file_get_contents(APP_ROOT . '/templates/confirmation.php');
    assert_contains('data-copy-reference', $confirmation, 'progressive copy button');
    assert_contains('data-copy-status', $confirmation, 'copy feedback live region');
    assert_contains('appointment-card', $confirmation, 'appointment card');
    $manage = file_get_contents(APP_ROOT . '/templates/manage-booking.php');
    assert_contains('danger-zone', $manage, 'explicit cancellation area');
    assert_contains('aria-describedby="cancel-policy"', $manage, 'cancellation explanation');
}
```

Run: `C:/xampp/php/php.exe tests/run.php`
Expected: missing customer-component assertions fail.

- [ ] **Step 2: Build the confirmation appointment card and accessible copy behavior**

Retain the privacy fallback and payment conditions. Replace the reference box with:

```php
<div class="reference-box">
    <span>BOOKING REFERENCE</span>
    <strong data-reference><?= escape($booking['reference']) ?></strong>
    <button type="button" data-copy-reference data-reference-value="<?= escape($booking['reference']) ?>">Copy reference</button>
    <span class="sr-only" role="status" aria-live="polite" data-copy-status></span>
</div>
```

Wrap details in `.appointment-card`; keep Paystack, manage, print, and policy actions. Add this non-inline handler to `assets/js/site.js`:

```javascript
document.querySelectorAll('[data-copy-reference]').forEach(button => {
  button.addEventListener('click', async () => {
    const status = button.parentElement.querySelector('[data-copy-status]');
    try {
      if (!navigator.clipboard) throw new Error('Clipboard unavailable');
      await navigator.clipboard.writeText(button.dataset.referenceValue);
      button.textContent = 'Copied'; status.textContent = 'Booking reference copied.';
    } catch {
      status.textContent = `Copy this reference: ${button.dataset.referenceValue}`;
      button.textContent = 'Select reference above';
    }
  });
});
```

- [ ] **Step 3: Clarify management and payment states without changing permissions**

In `manage-booking.php`, put status/date/treatment/therapist/payment in `.appointment-card`, available changes in `.content-panel`, and cancellation in its own explicit region:

```php
<section class="danger-zone" aria-labelledby="cancel-heading">
    <h2 id="cancel-heading">Cancel this appointment</h2>
    <p id="cancel-policy">This action cannot be undone. <?= $booking['payment_status'] === 'paid' ? 'Cancellation does not automatically refund a payment; contact the spa about a refund.' : escape($booking['accepted_policy']) ?></p>
    <form class="manage-form" method="post" action="manage.php" onsubmit="return confirm('Cancel this appointment? This cannot be undone.')">
        <input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>">
        <input type="hidden" name="action" value="cancel"><input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
        <button class="button button-danger" type="submit" aria-describedby="cancel-policy">Cancel appointment</button>
    </form>
</section>
```

Keep lookup, rescheduling, payment, and session-clearing forms unchanged in names/actions. Style payment and return states with `.status-banner status-success|status-pending|status-error` based only on actual state; retain the explicit test-checkout warning and “check before paying again” copy.

- [ ] **Step 4: Add shared customer components and verify**

```css
.appointment-card,.manage-form,.danger-zone,.status-banner{padding:clamp(22px,4vw,36px);margin:24px 0;border:1px solid var(--line);border-radius:var(--radius);background:#ffffffa6}.appointment-actions{display:flex;gap:12px;flex-wrap:wrap}.danger-zone{border-color:#be102566;background:#fff7f5}.button-danger{border-color:var(--burgundy);background:var(--burgundy);color:#fff}.status-success{border-left:4px solid var(--leaf)}.status-pending{border-left:4px solid #9a6a19}.status-error{border-left:4px solid var(--burgundy)}
@media(max-width:560px){.appointment-actions{flex-direction:column}.appointment-actions>*{width:100%}}
```

Run: `C:/xampp/php/php.exe tests/run.php && node --check assets/js/site.js`
Expected: all tests pass; existing manage/payment tests retain their original behavior.
Checkpoint: `git diff -- templates/confirmation.php templates/manage-login.php templates/manage-booking.php templates/payment.php templates/payment-return.php assets/css/booking.css assets/js/site.js tests/PublicPagesTest.php`

---

### Task 7: Create the Reception Workspace and Useful Overview

**Files:**
- Create: `src/ReceptionOverview.php`
- Modify: `admin/index.php`
- Modify: `templates/admin/layout.php`
- Modify: `templates/admin/dashboard.php`
- Modify: `assets/css/admin.css`
- Create: `assets/js/admin.js`
- Create: `tests/ReceptionOverviewTest.php`
- Modify: `tests/bootstrap.php`

**Interfaces:**
- Consumes: `Booking::adminList(array $filters = [])`, current admin role, and `contact_messages` when the admin-only messages module is present.
- Produces: `ReceptionOverview::summarize(array $todayBookings, array $upcoming, ?int $unreadMessages): array` with keys `today`, `pending`, `confirmed`, `paid_revenue`, `expected_revenue`, `unread_messages`, and `workload`; `workload` is a list of `['staff_name' => string, 'appointments' => int, 'minutes' => int]` sorted by name.

- [ ] **Step 1: Write the failing overview presenter test**

Create `tests/ReceptionOverviewTest.php` and require it from `tests/bootstrap.php`:

```php
<?php
declare(strict_types=1);

function test_reception_overview_summarizes_real_appointments(): void
{
    $today = [
        ['staff_name' => 'Ama S.', 'duration_min' => 60, 'status' => 'confirmed', 'payment_status' => 'paid', 'price' => '350.00'],
        ['staff_name' => 'Ama S.', 'duration_min' => 90, 'status' => 'pending', 'payment_status' => 'unpaid', 'price' => '520.00'],
        ['staff_name' => 'Kofi M.', 'duration_min' => 45, 'status' => 'cancelled', 'payment_status' => 'unpaid', 'price' => '280.00'],
    ];
    $summary = ReceptionOverview::summarize($today, [], 3);
    assert_same(3, $summary['today'], 'today count');
    assert_same(1, $summary['pending'], 'pending count');
    assert_same(1, $summary['confirmed'], 'confirmed count');
    assert_same(350.0, $summary['paid_revenue'], 'recorded payments');
    assert_same(870.0, $summary['expected_revenue'], 'active appointment value');
    assert_same(3, $summary['unread_messages'], 'unread messages');
    assert_same(2, $summary['workload'][0]['appointments'], 'therapist workload');
    assert_same(150, $summary['workload'][0]['minutes'], 'therapist minutes');
}
```

Run: `C:/xampp/php/php.exe tests/run.php`
Expected: undefined `ReceptionOverview` failure.

- [ ] **Step 2: Implement the pure read-only presenter**

```php
<?php
declare(strict_types=1);

final class ReceptionOverview
{
    public static function summarize(array $todayBookings, array $upcoming, ?int $unreadMessages): array
    {
        $workload = [];
        $paid = 0.0;
        $expected = 0.0;
        foreach ($todayBookings as $booking) {
            $name = (string) $booking['staff_name'];
            $workload[$name] ??= ['staff_name' => $name, 'appointments' => 0, 'minutes' => 0];
            $workload[$name]['appointments']++;
            $workload[$name]['minutes'] += (int) $booking['duration_min'];
            if ($booking['payment_status'] === 'paid') { $paid += (float) $booking['price']; }
            if (in_array($booking['status'], ['pending', 'confirmed'], true)) { $expected += (float) $booking['price']; }
        }
        $workload = array_values($workload);
        usort($workload, static fn (array $a, array $b): int => strcmp($a['staff_name'], $b['staff_name']));
        return [
            'today' => count($todayBookings),
            'pending' => count(array_filter($todayBookings, static fn (array $b): bool => $b['status'] === 'pending')),
            'confirmed' => count(array_filter($todayBookings, static fn (array $b): bool => $b['status'] === 'confirmed')),
            'paid_revenue' => $paid,
            'expected_revenue' => $expected,
            'unread_messages' => $unreadMessages,
            'workload' => $workload,
        ];
    }
}
```

In `admin/index.php`, keep current booking queries and add the role-restricted count:

```php
$unreadMessages = null;
if ($admin['role'] === 'admin') {
    $unreadMessages = (int) Database::connection()->query("SELECT COUNT(*) FROM contact_messages WHERE status='new'")->fetchColumn();
}
$summary = ReceptionOverview::summarize($bookings, $upcoming, $unreadMessages);
render_admin('Dashboard', 'dashboard', compact('admin', 'today', 'bookings', 'upcoming', 'paidRevenue', 'summary'));
```

- [ ] **Step 3: Build accessible admin navigation and dashboard panels**

In `templates/admin/layout.php`, preserve every route and role condition. Add a mobile toggle with `aria-expanded="false"` and `aria-controls="admin-nav"`, SVG icons through `ui_icon()`, `aria-current="page"` on the active link, and a visible New booking action. Add `<script src="../assets/js/admin.js" defer></script>`.

Create `assets/js/admin.js` with the same Escape/open-state pattern as the public menu, scoped to `[data-admin-menu-toggle]` and `[data-admin-nav]`. Do not trap focus or lock body scrolling.

In `templates/admin/dashboard.php`, render metric cards from `$summary`: appointments today, pending confirmations, recorded payments, expected active appointment value, and unread messages only when `$summary['unread_messages'] !== null`. Keep the existing client search, today schedule, and upcoming appointments. Add a therapist workload list from `$summary['workload']`; show a designed “No therapist workload today” empty state when empty. Do not add recent activity because no activity feed query has been defined.

- [ ] **Step 4: Add dense responsive workspace styling and verify**

```css
.admin-sidebar{width:248px}.admin-sidebar nav a{display:flex;align-items:center;gap:11px;border-radius:10px}.admin-sidebar nav svg{width:19px;height:19px;flex:none}.admin-menu-toggle{display:none;min-height:44px}.metric-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}.metric{border:1px solid var(--line);border-radius:16px;background:#fff}.metric>strong{font-variant-numeric:tabular-nums}.dashboard-grid{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(280px,.7fr);gap:20px}.workload-row{display:grid;grid-template-columns:1fr auto;gap:12px;padding:14px 0;border-bottom:1px solid var(--line)}
@media(max-width:1100px){.metric-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.dashboard-grid{grid-template-columns:1fr}}
@media(max-width:760px){.admin-menu-toggle{display:inline-flex}.admin-sidebar nav{display:none}.admin-sidebar nav.is-open{display:flex;overflow:visible}.admin-sidebar nav a{min-height:44px}.admin-sidebar{position:static;height:auto}}
```

Run: `C:/xampp/php/php.exe tests/run.php && C:/xampp/php/php.exe tests/mysql-settings-regression.php && node --check assets/js/admin.js`
Expected: all tests pass, MySQL regression passes, JavaScript syntax is valid.
Checkpoint: `git diff -- src/ReceptionOverview.php admin/index.php templates/admin/layout.php templates/admin/dashboard.php assets/css/admin.css assets/js/admin.js tests/ReceptionOverviewTest.php tests/bootstrap.php`

---

### Task 8: Add Day/Week Agendas and Responsive Appointment Cards

**Files:**
- Modify: `src/Booking.php`
- Modify: `admin/bookings.php`
- Modify: `templates/admin/bookings.php`
- Modify: `assets/css/admin.css`
- Test: `tests/AdminManagementTest.php`

**Interfaces:**
- Consumes: current booking filters, status/payment/check-in actions, staff list, and role permissions.
- Produces: `Booking::adminList()` optional `start_date` and `end_date` filters (inclusive `Y-m-d`); `$view` is `all|day|week`, `$agendaDate` is the anchor date, and `$weekStart`/`$weekEnd` are supplied for week navigation.

- [ ] **Step 1: Write failing date-range and agenda tests**

```php
function test_booking_agenda_uses_inclusive_read_only_date_ranges(): void
{
    $booking = seed_confirmed_booking();
    $date = gmdate('Y-m-d', strtotime($booking['slot_start']));
    $rows = Booking::adminList(['start_date' => $date, 'end_date' => $date]);
    assert_same([$booking['id']], array_map('intval', array_column($rows, 'id')), 'inclusive agenda range');
    assert_same([], Booking::adminList(['start_date' => '1999-01-01', 'end_date' => '1999-01-01']), 'outside agenda range');
    $bookingsTemplate = file_get_contents(APP_ROOT . '/templates/admin/bookings.php');
    assert_contains('bookings.php?view=day', $bookingsTemplate, 'day agenda');
    assert_contains('bookings.php?view=week', $bookingsTemplate, 'week agenda');
    assert_contains('appointment-card-admin', $bookingsTemplate, 'responsive appointment card');
}
```

Run: `C:/xampp/php/php.exe tests/run.php`
Expected: range test returns all bookings and template assertions fail.

- [ ] **Step 2: Add only the two read-only filters**

In `Booking::adminList()` after the existing `date` filter:

```php
if (!empty($filters['start_date'])) {
    $sql .= ' AND DATE(b.slot_start) >= ?'; $params[] = $filters['start_date'];
}
if (!empty($filters['end_date'])) {
    $sql .= ' AND DATE(b.slot_start) <= ?'; $params[] = $filters['end_date'];
}
```

In `admin/bookings.php`, retain POST handling verbatim and add strict GET normalization:

```php
$view = (string) ($_GET['view'] ?? 'all');
if (!in_array($view, ['all', 'day', 'week'], true)) { $view = 'all'; }
$agendaDate = (string) ($_GET['date'] ?? (new DateTimeImmutable('today', business_timezone()))->format('Y-m-d'));
$parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $agendaDate, business_timezone());
if (!$parsedDate || $parsedDate->format('Y-m-d') !== $agendaDate) {
    $parsedDate = new DateTimeImmutable('today', business_timezone());
    $agendaDate = $parsedDate->format('Y-m-d');
}
$weekStart = $parsedDate->modify('monday this week');
$weekEnd = $weekStart->modify('+6 days');
$filters = array_intersect_key($_GET, array_flip(['id','date','status','staff_id','search']));
if ($view === 'day') { $filters['start_date'] = $filters['end_date'] = $agendaDate; unset($filters['date']); }
if ($view === 'week') { $filters['start_date'] = $weekStart->format('Y-m-d'); $filters['end_date'] = $weekEnd->format('Y-m-d'); unset($filters['date']); }
$bookings = Booking::adminList($filters);
$staff = Catalog::activeStaff();
render_admin('Bookings & calendar', 'bookings', compact('admin', 'bookings', 'staff', 'filters', 'error', 'view', 'agendaDate', 'weekStart', 'weekEnd'));
```

- [ ] **Step 3: Add agenda controls and semantic appointment cards**

Preserve the existing filter names and all POST forms. Add day/week/all links with selected state via `aria-current="page"`, previous/next week links using `$weekStart->modify('-7 days')` and `+7 days`, and a date input for day navigation. Group week cards under their local formatted date. Use this card structure for each booking:

```php
<article class="appointment-card-admin">
    <time datetime="<?= escape($booking['slot_start']) ?>"><?= escape($time->format('D, j M · g:i A')) ?></time>
    <div class="booking-person"><h3><?= escape($booking['customer_name']) ?></h3><a href="tel:<?= escape($booking['customer_phone']) ?>"><?= escape($booking['customer_phone']) ?></a><small><?= escape($booking['reference']) ?></small></div>
    <div class="booking-service"><strong><?= escape($booking['service_name']) ?></strong><span><?= escape($booking['staff_name']) ?> · <?= (int) $booking['duration_min'] ?> min</span><b><?= money($booking['price']) ?> · <?= $booking['payment_status'] === 'paid' ? 'Paid' : 'Unpaid' ?></b></div>
    <div class="booking-controls"><!-- existing status, payment, and check-in forms --></div>
</article>
```

The comment above is a placement instruction only, not delivered markup; move the existing forms without changing actions, transitions, or role conditions.

- [ ] **Step 4: Style agenda/card views and verify**

```css
.agenda-tabs{display:flex;gap:8px;flex-wrap:wrap}.agenda-tabs a{min-height:44px;padding:10px 16px;border:1px solid var(--line);border-radius:999px}.agenda-tabs a[aria-current=page]{background:var(--forest);color:#fff}.agenda-day{margin-top:24px}.agenda-day>h2{margin-bottom:14px}.appointment-card-admin{display:grid;grid-template-columns:145px minmax(160px,.8fr) minmax(220px,1.2fr) minmax(180px,auto);gap:18px;align-items:start;padding:22px;margin-bottom:12px;border:1px solid var(--line);border-radius:16px;background:#fff;overflow-wrap:anywhere}.appointment-card-admin time{font-variant-numeric:tabular-nums;color:var(--forest);font-weight:600}
@media(max-width:1100px){.appointment-card-admin{grid-template-columns:120px 1fr 1fr}.booking-controls{grid-column:2/4}}
@media(max-width:640px){.appointment-card-admin{grid-template-columns:1fr}.booking-controls{grid-column:1}.booking-controls form{display:grid;grid-template-columns:1fr auto;width:100%}}
```

Run: `C:/xampp/php/php.exe tests/run.php`
Expected: all tests pass; existing booking status/payment/check-in tests remain green.
Checkpoint: `git diff -- src/Booking.php admin/bookings.php templates/admin/bookings.php assets/css/admin.css tests/AdminManagementTest.php`

---

### Task 9: Unify Every Admin Management Screen

**Files:**
- Modify: `templates/admin/manual-booking.php`
- Modify: `templates/admin/services.php`
- Modify: `templates/admin/staff.php`
- Modify: `templates/admin/receptionist-access.php`
- Modify: `templates/admin/gallery.php`
- Modify: `templates/admin/gallery-form.php`
- Modify: `templates/admin/payments.php`
- Modify: `templates/admin/messages.php`
- Modify: `templates/admin/sms.php`
- Modify: `templates/admin/settings.php`
- Modify: `assets/css/admin.css`
- Modify: `assets/js/admin.js`
- Test: `tests/AdminManagementTest.php`
- Test: `tests/GalleryTest.php`
- Test: `tests/ContactMessageTest.php`
- Test: `tests/SmsTest.php`
- Test: `tests/PaystackTest.php`

**Interfaces:**
- Consumes: every existing template variable, route, field name, status value, credential behavior, CSRF token, and role condition.
- Produces: shared `.page-lead`, `.panel-heading`, `.form-section`, `.record-list`, `.record-card`, `.admin-empty`, `.status`, `.inline-actions`, and `.help-text` classes. No controller or API changes.

- [ ] **Step 1: Add a failing cross-module presentation contract test**

```php
function test_admin_modules_share_status_empty_and_destructive_patterns(): void
{
    foreach (['payments', 'messages', 'sms', 'gallery', 'settings', 'services', 'staff', 'manual-booking'] as $template) {
        $css = file_get_contents(APP_ROOT . '/assets/css/admin.css');
        assert_contains('.admin-empty', $css, 'shared empty state');
        $html = file_get_contents(APP_ROOT . '/templates/admin/' . $template . '.php');
        assert_contains('admin-section', $html, $template . ' retains panels');
        assert_not_contains('<script>document.', $html, $template . ' avoids inline document handlers');
    }
    assert_contains('data-confirm', file_get_contents(APP_ROOT . '/templates/admin/services.php'), 'archive confirmation');
    assert_contains('data-confirm', file_get_contents(APP_ROOT . '/templates/admin/gallery.php'), 'gallery delete confirmation');
}
```

Run: `C:/xampp/php/php.exe tests/run.php`
Expected: manual booking still has an inline document handler and destructive forms lack the shared attribute.

- [ ] **Step 2: Apply shared headings, panels, and explicit empty states**

For each management template, retain existing forms and records while applying this heading pattern with its actual existing title/description:

```php
<header class="page-lead">
    <div><p class="admin-eyebrow">RECEPTION WORKSPACE</p><h2>Section title</h2><p>Existing operational description.</p></div>
</header>
```

“Section title” and “Existing operational description” above are structural labels: use the current template’s real headings and copy, not these literal strings. Put form groups in `.form-section`, repeated rows in `.record-list` and `.record-card`, and replace plain no-record paragraphs with:

```php
<div class="admin-empty"><h3>No records in this view.</h3><p>Change the filters or check again after new activity.</p></div>
```

Use module-appropriate empty headings where current copy already exists: “No online payments started yet”, “No messages in this view”, “No notifications queued yet”, and “No gallery photos uploaded yet”. Preserve all configuration/delivery state wording and never display secrets.

- [ ] **Step 3: Move inline behavior into the enhancement script**

Move the manual datetime conversion into `assets/js/admin.js` using the same logic and field names:

```javascript
const manualForm = document.querySelector('.manual-form');
manualForm?.addEventListener('submit', event => {
  const local = manualForm.elements.local_start.value;
  if (local) manualForm.elements.start.value = local.length === 16 ? `${local.replace('T', ' ')}:00` : local.replace('T', ' ');
  if (!manualForm.elements.start.value) event.preventDefault();
});
```

Replace inline archive/delete `onsubmit` handlers with `data-confirm="Archive this treatment? Existing bookings will be preserved."` and `data-confirm="Delete this photo? This cannot be undone."`; add one delegated handler:

```javascript
document.addEventListener('submit', event => {
  const message = event.target.dataset.confirm;
  if (message && !window.confirm(message)) event.preventDefault();
});
```

Retain customer cancellation inline confirmation from Task 6; this admin handler applies only to admin pages.

- [ ] **Step 4: Add the shared management CSS and verify every module**

```css
.page-lead{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:24px}.panel-heading{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:20px}.form-section{padding:22px 0;border-top:1px solid var(--line)}.record-list{display:grid;gap:12px}.record-card{min-width:0;padding:20px;border:1px solid var(--line);border-radius:14px;background:#fff;overflow-wrap:anywhere}.help-text{display:block;margin-top:6px;color:var(--muted);font-size:.78rem}.inline-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.admin-empty{padding:36px 24px;border:1px dashed var(--line);border-radius:14px;text-align:center;background:#ffffff70}.status{display:inline-flex;align-items:center;min-height:28px;padding:4px 10px;border-radius:999px;text-transform:capitalize}.admin-form input:focus-visible,.admin-form select:focus-visible,.admin-form textarea:focus-visible{outline:3px solid var(--burgundy);outline-offset:2px}
@media(max-width:640px){.page-lead,.panel-heading{align-items:flex-start;flex-direction:column}.management-row,.record-card{overflow-wrap:anywhere}.row-actions,.inline-actions{width:100%}.row-actions .small-button,.inline-actions .small-button{min-height:44px}}
```

Run:

```bash
C:/xampp/php/php.exe tests/run.php
C:/xampp/php/php.exe tests/mysql-settings-regression.php
node --check assets/js/admin.js
```

Expected: all PHP and module-specific tests pass; MySQL regression passes; JavaScript syntax is valid.
Checkpoint: `git diff -- templates/admin assets/css/admin.css assets/js/admin.js tests/AdminManagementTest.php tests/GalleryTest.php tests/ContactMessageTest.php tests/SmsTest.php tests/PaystackTest.php`

---

### Task 10: Browser Accessibility, Responsive, and Full-Flow Verification

**Files:**
- Modify only files with defects observed during this task
- Test: existing full PHP suite and browser checks

**Interfaces:**
- Consumes: all previous task outputs and current admin/customer routes.
- Produces: verified public, booking, customer management, and every current admin module at required viewport sizes; no new production interface.

- [ ] **Step 1: Run complete static and regression verification**

```bash
C:/xampp/php/php.exe tests/run.php
C:/xampp/php/php.exe tests/mysql-settings-regression.php
node --check assets/js/site.js
node --check assets/js/booking.js
node --check assets/js/admin.js
node --check assets/js/hero-carousel.js
node --check assets/js/admin-gallery.js
git ls-files --cached --others --exclude-standard '*.php' | while IFS= read -r file; do C:/xampp/php/php.exe -l "$file" || exit 1; done
```

Expected: all tests pass, all JavaScript checks exit `0`, and every PHP file reports `No syntax errors detected`.

- [ ] **Step 2: Start a temporary preview and inspect all public surfaces**

Run `npm run dev -- --port 7102` for validation. Check `/`, `/services.php`, `/about.php`, `/gallery.php`, `/contact.php`, `/book.php`, `/manage.php`, and `/admin/login.php` at 360, 390, 640, 768, 1280, and 1440 pixels.

Expected at every size: no horizontal overflow, supplied logo remains crisp, navigation and 44 px targets work, cards wrap long content, empty states are intentional, footer/contact links remain reachable, and mobile booking access does not obscure fields or footer actions.

- [ ] **Step 3: Exercise booking and customer flows with and without JavaScript**

With JavaScript: choose each treatment type, change therapist, load real availability, select morning/afternoon slots, verify live summary and review values, test loading failure retry, submit once, copy/print confirmation, and open manage booking. With JavaScript disabled: verify all fieldsets, required controls, payment choices, policy acceptance, and server submission remain usable.

Expected: selected values remain consistent, double submission is prevented only after valid submission, failures do not claim success, and existing payment choices/confirmation states match actual configuration.

- [ ] **Step 4: Exercise admin and reception flows**

Create a temporary administrator and receptionist only through existing test/setup mechanisms. Verify login/logout, overview counts and workload, client search, day/week agenda, filters, manual booking, status/check-in/payment controls, treatments, team/schedules, receptionist access, gallery, payments, messages, SMS, and settings. Verify admin-only links remain hidden for receptionists.

Expected: counts match temporary records, unread messages are admin-only, actual payment/SMS configuration states remain honest, destructive confirmations work, and all mobile record cards fit without page-wide scrolling.

- [ ] **Step 5: Check accessibility and reduced motion**

Use keyboard-only navigation for public menu, booking steps, FAQ, forms, copy/print controls, admin menu, filters, and destructive actions. Test 200% zoom/reflow and `prefers-reduced-motion: reduce`.

Expected: visible focus, logical focus after Escape/step changes, named controls, labeled fields, announced loading/copy/error states, no keyboard trap, no motion-dependent content, readable contrast, and no loss of functionality.

- [ ] **Step 6: Repair only observed defects and rerun the affected tests**

For each defect, add the smallest failing regression assertion first, reproduce it, make a targeted fix, then rerun the relevant test plus the full commands in Step 1. Do not change business rules to resolve a visual/test issue.

- [ ] **Step 7: Clean up and review the final diff**

Remove all temporary admin/customer/booking/message/gallery/payment/SMS verification records using the existing test setup or isolated test database; do not delete real business records. Stop the temporary preview server. Review:

```bash
git status --short
git diff --check
git diff --stat
```

Expected: no whitespace errors, no temporary records or server, no deleted supplied assets, no credentials, no generated dependencies, and no unrelated rewrites. Deliver the logical preview root `C:\xampp\htdocs\www.happymobilespa.com` and one `http://localhost:7100/` link; Kimi Work owns subsequent preview startup.

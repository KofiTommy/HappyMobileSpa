<?php
declare(strict_types=1);

function render_template_for_test(string $template, array $data = []): string
{
    ob_start();
    render_public('Happy Mobile Spa', $template, $data);
    return (string) ob_get_clean();
}

function test_public_layout_branding(): void
{
    assert_same(true, is_file(APP_ROOT . '/assets/images/logo.jpg'), 'optimized logo exists');
    $html = render_template_for_test('home', ['services' => [], 'settings' => []]);
    assert_contains('Happy Mobile Spa', $html, 'brand name');
    assert_contains('assets/images/logo.jpg', $html, 'logo asset');
    assert_contains('Book Now', $html, 'booking call to action');
    assert_same(true, is_file(APP_ROOT . '/assets/images/tokaa-tech-consult.png'), 'Tokaa footer logo exists');
    assert_contains('assets/images/tokaa-tech-consult.png', $html, 'Tokaa footer logo rendered');
    assert_contains('href="https://tokaatechconsult.com"', $html, 'developer credit links to Tokaa website');
    assert_contains('Developed by', $html, 'developer credit label');
    assert_contains('rel="noopener noreferrer"', $html, 'external developer link is protected');
    assert_not_contains('Serenity Spa', $html, 'old branding removed');
}

function test_booking_service_changes_refresh_therapists_and_slots(): void
{
    $js = file_get_contents(APP_ROOT . '/assets/js/booking.js');
    assert_contains('refreshStaffOptions', $js, 'therapist options refresh from eligible availability');
    assert_contains("staffSelect.value = ''", $js, 'service changes clear stale therapist preference');
}

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
    assert_contains("dateInput.addEventListener('change', () => { loadSlots(); updateSummary(); });", $js, 'date changes clear stale slots before summary update');
    assert_contains("staffSelect.addEventListener('change', () => { syncTherapistCards(); loadSlots(); updateSummary(); });", $js, 'therapist changes clear stale slots before summary update');
    assert_contains("if (!dateInput.value || !selectedService()) { slotStatus.textContent = 'Select a date to see available times.'; return; }", $js, 'cleared date resets slot status');
}

function test_no_javascript_booking_renders_and_submits_real_availability(): void
{
    seed_availability_fixture();
    $services = Catalog::activeServices();
    $selectedService = $services[0];
    $availableSlots = Availability::slots(1, null, fixture_date());
    $slot = $availableSlots[0];
    $old = ['service_id' => 1, 'staff_id' => '', 'appointment_date' => fixture_date(), 'slot_choice' => $slot['start']];
    $html = render_template_for_test('book', [
        'services' => $services, 'staff' => Catalog::activeStaff(1), 'selectedService' => $selectedService,
        'settings' => Catalog::settings(), 'error' => '', 'old' => $old,
        'availableSlots' => $availableSlots, 'availabilityError' => '',
    ]);
    assert_contains('name="appointment_date"', $html, 'no-JS date is submitted');
    assert_contains('value="' . fixture_date() . '"', $html, 'selected date is retained');
    assert_contains('name="refresh_availability"', $html, 'no-JS availability refresh control');
    assert_contains('name="slot_choice" value="' . $slot['start'] . '"', $html, 'server-rendered slot submits its real start');
    assert_contains($slot['staff_name'], $html, 'server-rendered slot identifies therapist');
    $controller = file_get_contents(APP_ROOT . '/book.php');
    assert_contains('isset($_POST[\'refresh_availability\'])', $controller, 'availability refresh does not create a booking');
    assert_contains('$_POST[\'start\'] = (string) $_POST[\'slot_choice\'];', $controller, 'fallback choice maps to existing booking start');
    $js = file_get_contents(APP_ROOT . '/assets/js/booking.js');
    assert_contains('form.querySelector(\'[data-step="3"] [type="submit"]\')', $js, 'availability refresh is not mistaken for final submit');
    $booking = Booking::create(booking_input($slot['start']));
    assert_same($slot['start'], $booking['slot_start'], 'server-rendered start passes existing booking validation');
}

function test_booking_step_changes_focus_legend_only_after_navigation(): void
{
    $template = file_get_contents(APP_ROOT . '/templates/book.php');
    assert_same(4, substr_count($template, '<legend tabindex="-1">'), 'each step legend can receive programmatic focus');
    $js = file_get_contents(APP_ROOT . '/assets/js/booking.js');
    assert_contains('const showStep = (index, moveFocus = false) => {', $js, 'focus movement is opt-in');
    assert_contains("steps[index].querySelector('legend')?.focus({ preventScroll: true });", $js, 'new step legend receives focus');
    assert_contains('showStep(Math.min(3, current + 1), true)', $js, 'next moves focus');
    assert_contains('showStep(Math.max(0, current - 1), true)', $js, 'back moves focus');
    assert_contains('showStep(0);', $js, 'initialization does not steal focus');
}

function test_short_viewport_navigation_is_scrollable(): void
{
    $siteCss = file_get_contents(APP_ROOT . '/assets/css/site.css');
    assert_contains('max-height:calc(100vh - 70px);max-height:calc(100dvh - 70px);overflow-y:auto', $siteCss, 'mobile public menu scrolls within short viewports');
    $adminCss = file_get_contents(APP_ROOT . '/assets/css/admin.css');
    assert_contains('.admin-sidebar{position:sticky;top:0;display:flex;flex-direction:column;height:100vh;overflow-y:auto;', $adminCss, 'desktop admin sidebar scrolls within short viewports');
}

function test_selected_slot_staff_text_has_dark_contrast(): void
{
    $css = file_get_contents(APP_ROOT . '/assets/css/booking.css');
    assert_contains('.time-slot input:checked+span small{color:var(--forest)}', $css, 'selected slot staff text uses dark foreground');
    assert_not_contains('.time-slot input:checked+span small{color:#D2E1D7}', $css, 'low-contrast selected staff color removed');
}

function test_mobile_review_includes_duration_and_price(): void
{
    $js = file_get_contents(APP_ROOT . '/assets/js/booking.js');
    $start = strpos($js, 'const updateReview = () => {');
    $end = strpos($js, "form.addEventListener('submit'", $start);
    assert_same(true, $start !== false && $end !== false, 'review renderer found');
    $review = substr($js, $start, $end - $start);
    assert_contains("['Duration', service?.dataset.serviceDuration", $review, 'review includes selected treatment duration');
    assert_contains("['Price', service?.dataset.servicePrice", $review, 'review includes selected treatment price');
}

function test_booking_and_staff_copy_use_typographic_symbols(): void
{
    $booking = file_get_contents(APP_ROOT . '/templates/book.php');
    assert_contains('Paystack — MTN, ATMoney, or Telecel.', $booking, 'payment copy uses an em dash');
    assert_not_contains('Paystack ? MTN', $booking, 'payment copy encoding artifact removed');
    $staff = file_get_contents(APP_ROOT . '/templates/admin/staff.php');
    assert_contains('4096 × 4096 pixels', $staff, 'image dimensions use a multiplication sign');
    assert_not_contains('4096 ? 4096 pixels', $staff, 'image dimensions encoding artifact removed');
}

function test_availability_fixture_is_always_in_the_future(): void
{
    $date = new DateTimeImmutable(fixture_date(), new DateTimeZone('UTC'));
    assert_same('Tuesday', $date->format('l'), 'fixture remains on staffed Tuesday');
    assert_same(true, $date > new DateTimeImmutable('today', new DateTimeZone('UTC')), 'fixture is not in the past');
    assert_same(fixture_date() . ' 10:30:00', fixture_datetime('10:30'), 'fixture datetime format');
}

function test_shared_icons_and_public_shell_are_accessible(): void
{
    $icon = ui_icon('calendar', 'metric-icon');
    assert_contains('viewBox="0 0 24 24"', $icon, 'SVG viewbox');
    assert_contains('aria-hidden="true"', $icon, 'decorative SVG');
    assert_contains('focusable="false"', $icon, 'non-focusable SVG');
    assert_contains('class="metric-icon"', $icon, 'icon class');
    $escapedIcon = ui_icon('calendar', 'metric-icon" data-test="escaped');
    assert_contains('class="metric-icon&quot; data-test=&quot;escaped"', $escapedIcon, 'icon class escaped');
    assert_not_contains('class="metric-icon" data-test="escaped"', $escapedIcon, 'raw class attribute rejected');
    assert_throws(fn () => ui_icon('not-an-icon'), 'Unknown icon');
    $html = render_template_for_test('home', ['services' => [], 'settings' => [], 'heroImages' => Gallery::defaults(), 'staff' => [], 'galleryImages' => []]);
    assert_contains('aria-label="Open navigation"', $html, 'menu accessible name');
    assert_contains('class="mobile-book-bar"', $html, 'mobile booking access');
    assert_contains('href="#main"', $html, 'skip link retained');
}

function test_public_shell_icons_and_mobile_targets_have_stable_sizes(): void
{
    $css = file_get_contents(APP_ROOT . '/assets/css/site.css');
    assert_contains('.button svg,.menu-toggle svg,.mobile-book-bar svg{width:20px;height:20px;flex-shrink:0}', $css, 'shared icon dimensions and flex protection');
    assert_contains('width:44px;height:44px;min-width:44px;min-height:44px', $css, 'mobile toggle minimum target');
    assert_contains('.site-nav a{display:flex;align-items:center;min-height:44px;font-size:.82rem}', $css, 'mobile navigation link targets');
}

function test_mobile_booking_bar_is_suppressed_on_customer_form_pages(): void
{
    $css = file_get_contents(APP_ROOT . '/assets/css/site.css');
    $originalScript = $_SERVER['SCRIPT_NAME'] ?? null;
    try {
        foreach (['book', 'manage', 'payment', 'payment-return'] as $page) {
            $_SERVER['SCRIPT_NAME'] = $page . '.php';
            $html = render_template_for_test('home', ['services' => [], 'settings' => [], 'heroImages' => Gallery::defaults(), 'staff' => [], 'galleryImages' => []]);
            assert_contains('<body class="page-' . $page . '">', $html, $page . ' body page class');
            assert_contains('.page-' . $page . ' .mobile-book-bar', $css, $page . ' mobile bar suppression');
        }
    } finally {
        if ($originalScript === null) {
            unset($_SERVER['SCRIPT_NAME']);
        } else {
            $_SERVER['SCRIPT_NAME'] = $originalScript;
        }
    }
}

function test_about_layout_uses_shrink_safe_responsive_tracks(): void
{
    $css = file_get_contents(APP_ROOT . '/assets/css/site.css');
    assert_contains('.about-split{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,1fr);', $css, 'desktop about tracks can shrink');
    assert_contains('.about-split>img,.about-split>div{min-width:0}', $css, 'about children do not force intrinsic overflow');
    assert_contains('.about-split{grid-template-columns:minmax(0,1fr);padding:0 20px 90px}', $css, 'mobile about track can shrink');
}

function test_small_mobile_header_keeps_booking_and_menu_clear(): void
{
    $css = file_get_contents(APP_ROOT . '/assets/css/site.css');
    assert_contains('@media(max-width:390px){.brand span{display:none}}', $css, 'smallest header hides crowding brand text');
}

function test_single_column_booking_places_summary_after_form(): void
{
    $css = file_get_contents(APP_ROOT . '/assets/css/booking.css');
    assert_contains('@media(max-width:900px){.booking-layout{grid-template-columns:1fr}.appointment-summary{position:static;order:1}', $css, 'single-column summary follows the booking form');
}

function test_gallery_grid_uses_shrink_safe_tracks(): void
{
    $css = file_get_contents(APP_ROOT . '/assets/css/site.css');
    assert_contains('.editorial-gallery{display:grid;grid-template-columns:repeat(12,minmax(0,1fr))', $css, 'editorial gallery base tracks can shrink');
    assert_contains('.editorial-gallery figure{min-width:0}', $css, 'editorial gallery children do not force intrinsic overflow');
    assert_contains('.managed-gallery{grid-template-columns:repeat(2,minmax(0,1fr))', $css, 'managed gallery base tracks can shrink');
    assert_contains('@media(max-width:760px){.managed-gallery{display:grid;grid-template-columns:minmax(0,1fr)}', $css, 'managed gallery mobile track can shrink');
}

function test_homepage_uses_real_catalog_team_gallery_and_practical_faq(): void
{
    seed_test_catalog();
    Database::connection()->exec('UPDATE services SET display_order = CASE WHEN id = 2 THEN 0 ELSE id + 1 END');
    $services = Catalog::activeServices();
    assert_same('Deep Tissue Ritual', $services[0]['name'], 'first display-order service');
    $galleryImages = array_slice(Gallery::defaults(), 0, 3);
    $html = render_template_for_test('home', [
        'services' => $services, 'settings' => [],
        'heroImages' => Gallery::defaults(), 'staff' => Catalog::activeStaff(),
        'galleryImages' => $galleryImages, 'featuredService' => $services[0],
    ]);
    assert_contains('Featured treatment', $html, 'display-order feature label');
    assert_contains('aria-labelledby="experience-title"', $html, 'experience section has an accessible heading');
    assert_same(3, substr_count($html, '<li><span>0'), 'three booking experience steps');
    $featuredStart = strpos($html, '<article class="featured-treatment">');
    $featuredEnd = strpos($html, '</article>', $featuredStart);
    assert_same(true, $featuredStart !== false && $featuredEnd !== false, 'featured treatment renders');
    $featuredHtml = substr($html, $featuredStart, $featuredEnd - $featuredStart);
    assert_contains('<h3>Deep Tissue Ritual</h3>', $featuredHtml, 'first display-order service is featured');
    assert_contains('href="book.php?service=deep-tissue-ritual"', $featuredHtml, 'featured service booking URL');
    assert_contains('class="featured-treatment-media"', $featuredHtml, 'featured treatment has an image-led booking action');
    assert_contains('src="' . $galleryImages[0]['path'] . '"', $featuredHtml, 'featured treatment uses a real gallery image');
    assert_not_contains('<h3>Swedish Massage</h3>', $featuredHtml, 'later service is not featured');
    assert_same(count($services), substr_count($html, 'class="treatment-card-number"'), 'treatment cards have editorial numbering');
    assert_contains('Ama S.', $html, 'real therapist');
    $galleryStart = strpos($html, '<div class="home-gallery-grid">');
    $galleryEnd = strpos($html, '</div>', $galleryStart);
    assert_same(true, $galleryStart !== false && $galleryEnd !== false, 'homepage gallery renders');
    $galleryHtml = substr($html, $galleryStart, $galleryEnd - $galleryStart);
    assert_same(3, substr_count($galleryHtml, '<figure><img '), 'three homepage gallery figures');
    foreach ($galleryImages as $index => $image) {
        assert_contains('src="' . $image['path'] . '" alt="' . $image['alt_text'] . '"', $galleryHtml, 'real gallery image and alternative text');
        assert_contains('<figcaption><span>' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) . '</span>' . $image['caption'] . '</figcaption>', $galleryHtml, 'real gallery caption and editorial number');
    }
    assert_contains('How do I book?', $html, 'practical FAQ');
    assert_contains('Real availability', $html, 'factual trust signal');
    assert_not_contains('Most popular', $html, 'no invented popularity');
    $css = file_get_contents(APP_ROOT . '/assets/css/site.css');
    assert_contains('.featured-treatment .featured-label{margin-bottom:22px;', $css, 'featured label spacing outranks paragraph reset');
    assert_contains('.page-index .featured-booking .service-facts{margin:0 0 28px;color:#C5D3CA}', $css, 'featured duration remains readable on the dark panel');
    assert_contains('.page-index .featured-booking .button{padding:16px 18px;gap:10px;font-size:.68rem;letter-spacing:.1em;white-space:nowrap}', $css, 'featured booking action stays readable');
    assert_contains('@media(max-width:760px){', $css, 'homepage mobile layout rules exist');
    assert_contains('.experience-section{grid-template-columns:1fr;gap:34px;margin:30px 12px 10px;padding:54px 26px;border-radius:26px}', $css, 'experience section collapses cleanly on mobile');
    assert_contains('@media(prefers-reduced-motion:reduce){.page-index .hero-slide.is-active img{transform:none}}', $css, 'homepage respects reduced motion');
}

function test_secondary_pages_have_actions_and_intentional_empty_states(): void
{
    $html = render_template_for_test('services', ['services' => [], 'settings' => []]);
    assert_contains('Contact us', $html, 'empty catalog contact action');
    $html = render_template_for_test('gallery', ['images' => [], 'settings' => []]);
    assert_contains('gallery is being prepared', $html, 'empty gallery message');
    assert_contains('Explore treatments', $html, 'empty gallery next action');

    $html = render_template_for_test('services', ['services' => [[
        'name' => 'Swedish Massage', 'description' => 'A gentle full-body treatment.',
        'duration_min' => 60, 'price' => 125, 'slug' => 'swedish-massage',
    ]], 'settings' => []]);
    assert_contains('<article class="catalog-card">', $html, 'populated treatment card structure');
    assert_contains('<h2>Swedish Massage</h2>', $html, 'treatment name');
    assert_contains('<dt>Duration</dt><dd>60 min</dd>', $html, 'treatment duration');
    assert_contains('<dt>Price</dt><dd>₵125.00</dd>', $html, 'treatment price');
    assert_contains('href="book.php?service=swedish-massage"', $html, 'treatment booking URL');

    $html = render_template_for_test('contact', ['settings' => [], 'old' => []]);
    assert_contains('contact-methods', $html, 'contact layout');
    assert_contains('method="post" action="contact.php#send-message"', $html, 'contact form action retained');
    assert_contains('<input type="hidden" name="csrf" value="', $html, 'contact CSRF retained');
    assert_contains('name="name" autocomplete="name" minlength="2" maxlength="160" required', $html, 'contact name validation retained');
    assert_contains('type="email" name="email" autocomplete="email" maxlength="190" required', $html, 'contact email validation retained');
    assert_contains('name="message" rows="6" minlength="10" maxlength="5000" required', $html, 'contact message validation retained');
    $css = file_get_contents(APP_ROOT . '/assets/css/site.css');
    assert_not_contains('.contact-grid>div:first-child{padding:', $css, 'legacy contact padding removed');
}

function test_customer_surfaces_use_accessible_appointment_components(): void
{
    $confirmation = file_get_contents(APP_ROOT . '/templates/confirmation.php');
    assert_contains('data-copy-reference', $confirmation, 'progressive copy button');
    assert_contains('data-copy-status', $confirmation, 'copy feedback live region');
    assert_contains('appointment-card', $confirmation, 'appointment card');
    assert_contains('Unpaid — payable at the spa or by mobile money', $confirmation, 'readable unpaid payment wording');
    $actionsStart = strpos($confirmation, '<div class="confirmation-actions appointment-actions">');
    $paymentAction = strpos($confirmation, 'Pay by mobile money');
    $manageAction = strpos($confirmation, '>Manage booking</a>');
    assert_same(true, $actionsStart !== false && $paymentAction > $actionsStart && $paymentAction < $manageAction, 'payment action shares responsive appointment actions');
    $manage = file_get_contents(APP_ROOT . '/templates/manage-booking.php');
    assert_contains('danger-zone', $manage, 'explicit cancellation area');
    assert_contains('aria-describedby="cancel-policy"', $manage, 'cancellation explanation');
}

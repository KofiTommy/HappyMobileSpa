<?php
declare(strict_types=1);

function test_service_management_preserves_history_and_validates(): void
{
    $booking = seed_confirmed_booking();
    Catalog::archiveService((int) $booking['service_id']);
    assert_same($booking['id'], (int) Booking::adminList(['id' => $booking['id']])[0]['id'], 'history retained');
    assert_same(false, in_array(1, array_column(Catalog::activeServices(), 'id'), true), 'archived service hidden');
    assert_throws(fn () => Catalog::saveService([
        'name' => 'Invalid Treatment', 'description' => 'A treatment description.',
        'duration_min' => 60, 'buffer_min' => 15, 'price' => '-1', 'active' => 1,
    ]), 'Price must be zero or greater');
    assert_throws(fn () => Catalog::saveService([
        'name' => 'Invalid Treatment', 'description' => 'A treatment description.',
        'duration_min' => 10, 'buffer_min' => 15, 'price' => '100', 'active' => 1,
    ]), 'Duration must be between 15 and 480 minutes');

    $id = Catalog::saveService([
        'name' => 'Restorative Scalp Ritual', 'description' => 'A focused scalp and neck treatment.',
        'duration_min' => 45, 'buffer_min' => 10, 'price' => '240.50', 'active' => 1,
    ]);
    $service = Catalog::findService('restorative-scalp-ritual');
    assert_same($id, $service['id'], 'new service can be found');
    assert_same('240.50', $service['price'], 'service price saved');
    assert_same(true, in_array($id, array_column(Catalog::adminServices(), 'id'), true), 'admin service list includes new service');
}

function test_staff_management_and_schedule_validation(): void
{
    seed_test_catalog();
    $staffId = Catalog::saveStaff([
        'name' => 'Efua T.', 'biography' => 'A thoughtful massage therapist.',
        'service_ids' => [1, 2], 'active' => 1,
    ]);
    assert_same(true, in_array($staffId, array_column(Catalog::activeStaff(1), 'id'), true), 'staff service assignment saved');
    Catalog::saveSchedule($staffId, [
        ['weekday' => 2, 'start_time' => '09:00', 'end_time' => '17:00'],
        ['weekday' => 1, 'start_time' => '', 'end_time' => ''],
    ], [
        ['weekday' => 2, 'start_time' => '12:00', 'end_time' => '13:00'],
        ['weekday' => 1, 'start_time' => '', 'end_time' => ''],
    ]);
    assert_same(true, Availability::isAvailable(1, $staffId, new DateTimeImmutable(fixture_datetime('09:00'), new DateTimeZone('UTC'))), 'saved schedule is bookable');
    assert_same(true, in_array($staffId, array_map('intval', array_column(Catalog::adminStaff(), 'id')), true), 'admin staff list includes new team member');
    assert_same('12:00', Catalog::schedule($staffId)['breaks'][0]['start_time'], 'saved breaks can be edited');
    assert_throws(fn () => Catalog::saveSchedule($staffId, [], [
        ['weekday' => 2, 'start_time' => '12:00', 'end_time' => '13:00'],
        ['weekday' => 2, 'start_time' => '12:30', 'end_time' => '13:30'],
    ]), 'Breaks cannot overlap');
    Catalog::saveStaff([
        'name' => 'Efua T.', 'biography' => 'A thoughtful massage therapist.',
        'service_ids' => [1], 'active' => 0,
    ], $staffId);
    assert_same(false, in_array($staffId, array_column(Catalog::activeStaff(), 'id'), true), 'inactive staff hidden');
}

function test_settings_update_public_catalog_inputs_and_are_validated(): void
{
    seed_availability_fixture();
    assert_throws(fn () => Catalog::saveSettings(['cutoff_hours' => '-1']), 'Cutoff must be between 0 and 168 hours');
    $hours = [];
    for ($day = 0; $day <= 6; $day++) {
        $hours[$day] = ['closed' => $day === 0 ? 1 : 0, 'open_time' => '08:00', 'close_time' => '19:00'];
    }
    Catalog::saveSettings([
        'cutoff_hours' => '36', 'phone' => '024 412 7788', 'email' => 'hello@happymobilespa.com',
        'address' => 'Accra, Ghana', 'timezone' => 'Africa/Accra',
        'cancellation_policy' => 'Changes are free up to 36 hours before your visit.',
        'business_hours' => $hours,
        'holidays' => [['holiday_date' => fixture_date(), 'name' => 'Team training']],
    ]);
    $settings = Catalog::settings();
    assert_same('36', $settings['cutoff_hours'], 'cutoff saved');
    assert_same('Accra, Ghana', $settings['address'], 'address saved');
    assert_same('08:00', Catalog::businessHours()[1]['open_time'], 'business hours can be edited');
    assert_same('Team training', Catalog::holidays()[0]['name'], 'holidays can be edited');
    assert_same(false, Availability::isAvailable(1, 1, new DateTimeImmutable(fixture_datetime('10:30'), new DateTimeZone('UTC'))), 'holiday immediately affects availability');
}

function test_manual_booking_records_admin_status_and_payment(): void
{
    seed_availability_fixture();
    $adminId = seed_admin('owner', password_hash('correct horse battery staple', PASSWORD_DEFAULT));
    $input = seed_manual_input();
    $input['status'] = 'pending';
    $input['payment_status'] = 'paid';
    $booking = Booking::createManual($input, $adminId);
    assert_same('admin', $booking['last_event_actor'], 'manual audit actor');
    assert_same($adminId, (int) $booking['last_event_admin_id'], 'manual admin id');
    assert_same('pending', $booking['status'], 'manual pending status');
    assert_same('paid', $booking['payment_status'], 'manual payment status');
}

function test_manual_booking_accepts_browser_local_datetime_without_javascript(): void
{
    seed_availability_fixture();
    $adminId = seed_admin('owner', password_hash('correct horse battery staple', PASSWORD_DEFAULT));
    $input = seed_manual_input();
    unset($input['start']);
    $input['local_start'] = fixture_date() . 'T10:30';
    $booking = Booking::createManual($input, $adminId);
    assert_same(fixture_datetime('10:30'), $booking['slot_start'], 'browser local time converted');
}

function test_manual_override_only_bypasses_booking_conflicts_and_is_audited(): void
{
    seed_availability_fixture();
    $adminId = seed_admin('owner', password_hash('correct horse battery staple', PASSWORD_DEFAULT));
    $input = seed_manual_input();
    Booking::createManual($input, $adminId);
    assert_throws(fn () => Booking::createManual($input, $adminId), 'Slot is no longer available');
    $booking = Booking::createManual($input, $adminId, true);
    assert_same('admin', $booking['last_event_actor'], 'override actor');
    $query = Database::connection()->prepare('SELECT details FROM booking_events WHERE booking_id = ? ORDER BY id DESC LIMIT 1');
    $query->execute([$booking['id']]);
    $details = json_decode((string) $query->fetchColumn(), true, 512, JSON_THROW_ON_ERROR);
    assert_same(true, $details['conflict_override'], 'override details recorded');
    $outsideHours = $input;
    $outsideHours['start'] = fixture_datetime('07:00');
    assert_throws(fn () => Booking::createManual($outsideHours, $adminId, true), 'Slot is no longer available');
}

function test_admin_management_views_render_accessible_forms(): void
{
    seed_test_catalog();
    $admin = ['id' => 1, 'username' => 'owner', 'role' => 'admin'];
    $render = function (string $template, array $data) use ($admin): string {
        ob_start();
        render_admin('Management', $template, $data + ['admin' => $admin, 'error' => '', 'success' => '']);
        return (string) ob_get_clean();
    };
    $services = Catalog::adminServices();
    $html = $render('services', ['services' => $services, 'editing' => null]);
    assert_contains('Add treatment', $html, 'treatment form');
    assert_contains('Archive', $html, 'archive action');
    $staff = Catalog::adminStaff();
    $html = $render('staff', ['staff' => $staff, 'services' => $services, 'editing' => $staff[0], 'schedule' => Catalog::schedule((int) $staff[0]['id'])]);
    assert_contains('Add team member', $html, 'staff form');
    assert_contains('Weekly schedule', $html, 'schedule form');
    $html = $render('settings', ['settings' => Catalog::settings(), 'hours' => Catalog::businessHours(), 'holidays' => []]);
    assert_contains('Business hours', $html, 'business hours settings');
    assert_contains('Cancellation policy', $html, 'policy settings');
    $html = $render('manual-booking', ['services' => Catalog::activeServices(), 'staff' => Catalog::activeStaff(), 'old' => []]);
    assert_contains('Create booking', $html, 'manual booking submit');
    assert_contains('Override a booking conflict', $html, 'explicit override checkbox');
}

function test_csrf_token_and_verification_work_without_web_session(): void
{
    $token = Csrf::token();
    assert_same($token, Csrf::token(), 'CLI rendering reuses token');
    assert_same(true, Csrf::verify($token), 'valid token accepted');
    assert_same(false, Csrf::verify('wrong'), 'invalid token rejected');
}

function test_admin_management_grid_collapses_on_mobile(): void
{
    $css = file_get_contents(APP_ROOT . '/assets/css/admin.css');
    assert_contains('@media(max-width:760px){.management-grid{grid-template-columns:1fr}', $css, 'mobile management layout');
}

function test_management_rows_stack_on_mobile_and_muted_text_is_defined(): void
{
    $css = file_get_contents(APP_ROOT . '/assets/css/admin.css');
    assert_contains('--muted:#52655A', $css, 'accessible muted text token');
    assert_contains('.management-row{flex-direction:column;align-items:stretch}', $css, 'mobile details and actions stack');
    assert_contains('.management-row>div{min-width:0}', $css, 'mobile row children can shrink');
}

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

function test_booking_agenda_uses_inclusive_read_only_date_ranges(): void
{
    $booking = seed_confirmed_booking();
    $date = gmdate('Y-m-d', strtotime($booking['slot_start']));
    $rows = Booking::adminList(['start_date' => $date, 'end_date' => $date]);
    assert_same([$booking['id']], array_map('intval', array_column($rows, 'id')), 'inclusive agenda range');
    assert_same([], Booking::adminList(['start_date' => '1999-01-01', 'end_date' => '1999-01-01']), 'outside agenda range');
    $bookingsTemplate = file_get_contents(APP_ROOT . '/templates/admin/bookings.php');
    assert_contains('appointment-card-admin', $bookingsTemplate, 'responsive appointment card');
}

function test_booking_agenda_navigation_preserves_active_filters(): void
{
    $filters = ['id' => '17', 'status' => 'confirmed', 'staff_id' => '2', 'search' => 'Ama & Kofi'];
    $render = static function (string $view) use ($filters): string {
        $bookings = [];
        $staff = [];
        $error = '';
        $agendaDate = '2026-11-11';
        $parsedDate = new DateTimeImmutable($agendaDate, business_timezone());
        $weekStart = $parsedDate->modify('monday this week');
        $weekEnd = $weekStart->modify('+6 days');
        ob_start();
        include APP_ROOT . '/templates/admin/bookings.php';
        return (string) ob_get_clean();
    };

    $html = $render('week');
    $retained = 'id=17&amp;status=confirmed&amp;staff_id=2&amp;search=Ama+%26+Kofi';
    assert_contains('href="bookings.php?' . $retained . '&amp;view=all"', $html, 'all agenda retains filters');
    assert_contains('href="bookings.php?' . $retained . '&amp;view=day&amp;date=2026-11-11"', $html, 'day agenda retains filters');
    assert_contains('href="bookings.php?' . $retained . '&amp;view=week&amp;date=2026-11-11"', $html, 'week agenda retains filters');
    assert_contains('href="bookings.php?' . $retained . '&amp;view=week&amp;date=2026-11-02"', $html, 'previous week retains filters');
    assert_contains('href="bookings.php?' . $retained . '&amp;view=week&amp;date=2026-11-16"', $html, 'next week retains filters');
    assert_not_contains('start_date', $html, 'navigation does not expose internal range filters');
    assert_not_contains('end_date', $html, 'navigation does not expose internal end range filters');

    $dayHtml = $render('day');
    foreach (['id' => '17', 'status' => 'confirmed', 'staff_id' => '2', 'search' => 'Ama &amp; Kofi'] as $name => $value) {
        assert_contains('<input type="hidden" name="' . $name . '" value="' . $value . '">', $dayHtml, 'day date form retains ' . $name);
    }
}

function test_paystack_admin_labels_respect_payment_status(): void
{
    $booking = seed_confirmed_booking();
    $booking += [
        'customer_name' => 'Efua Mensah', 'customer_phone' => '0244127788', 'customer_email' => '',
        'service_name' => 'Swedish Massage', 'staff_name' => 'Ama S.', 'duration_min' => 60,
        'checked_in_at' => null,
    ];
    $booking['payment_method'] = 'paystack_momo';
    $render = static function (string $paymentStatus) use ($booking): string {
        $booking['payment_status'] = $paymentStatus;
        $bookings = [$booking];
        $filters = [];
        $view = 'all';
        $staff = [];
        $error = '';
        $agendaDate = '2026-11-11';
        ob_start();
        include APP_ROOT . '/templates/admin/bookings.php';
        return (string) ob_get_clean();
    };
    $unpaid = $render('unpaid');
    assert_contains('Paystack · unpaid', $unpaid, 'unpaid Paystack booking is not labelled paid');
    assert_not_contains('Paid via Paystack', $unpaid, 'unpaid Paystack booking rejects paid label');
    $paid = $render('paid');
    assert_contains('Paid via Paystack', $paid, 'paid Paystack booking retains paid label');
}

function test_appointment_card_grid_uses_shrink_safe_responsive_tracks(): void
{
    $css = file_get_contents(APP_ROOT . '/assets/css/admin.css');
    assert_contains('grid-template-columns:minmax(0,145px) minmax(0,.8fr) minmax(0,1.2fr) minmax(0,180px)', $css, 'shrink-safe desktop card tracks');
    assert_contains('@media(max-width:1250px){.appointment-card-admin{grid-template-columns:minmax(0,120px) minmax(0,1fr) minmax(0,1fr)}.booking-controls{grid-column:2/4}}', $css, 'compact card layout before overflow');
    assert_contains('@media(max-width:640px){.appointment-card-admin{grid-template-columns:minmax(0,1fr)}', $css, 'shrink-safe mobile card track');
}

function test_settings_business_hours_shrink_on_mobile(): void
{
    $css = file_get_contents(APP_ROOT . '/assets/css/admin.css');
    assert_contains('.business-hours-editor>div{display:grid;grid-template-columns:110px 100px minmax(0,1fr) minmax(0,1fr);gap:14px;', $css, 'desktop business hours use shrink-safe time tracks');
    assert_contains('@media(max-width:640px){.business-hours-editor>div{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}', $css, 'mobile business hours use shrink-safe tracks');
    assert_contains('.business-hours-editor>div>strong,.business-hours-editor>div>label{grid-column:1/-1}', $css, 'day and closed controls stack above time fields');
    assert_contains('.business-hours-editor input[type=time]{width:100%;min-width:0}', $css, 'time inputs can shrink within their tracks');
}

function test_mobile_agenda_navigation_uses_clean_grid(): void
{
    $css = file_get_contents(APP_ROOT . '/assets/css/admin.css');
    assert_contains('.agenda-tabs{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));width:100%}', $css, 'agenda views use equal shrink-safe mobile tracks');
    assert_contains('.agenda-week-navigation{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:16px}', $css, 'week buttons use a clean two-column grid');
    assert_contains('.agenda-week-navigation>span{grid-column:1/-1;grid-row:1;align-self:center;text-align:center}', $css, 'week date range occupies its own full row');
    assert_contains('.agenda-week-navigation>a{display:flex;align-items:center;justify-content:center;min-width:0;padding:10px 8px;text-align:center}', $css, 'week navigation links shrink without crowding');
}

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
    assert_same(true, Availability::isAvailable(1, $staffId, new DateTimeImmutable('2026-10-27 09:00:00', new DateTimeZone('UTC'))), 'saved schedule is bookable');
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
        'holidays' => [['holiday_date' => '2026-10-27', 'name' => 'Team training']],
    ]);
    $settings = Catalog::settings();
    assert_same('36', $settings['cutoff_hours'], 'cutoff saved');
    assert_same('Accra, Ghana', $settings['address'], 'address saved');
    assert_same('08:00', Catalog::businessHours()[1]['open_time'], 'business hours can be edited');
    assert_same('Team training', Catalog::holidays()[0]['name'], 'holidays can be edited');
    assert_same(false, Availability::isAvailable(1, 1, new DateTimeImmutable('2026-10-27 10:30:00', new DateTimeZone('UTC'))), 'holiday immediately affects availability');
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
    $input['local_start'] = '2026-10-27T10:30';
    $booking = Booking::createManual($input, $adminId);
    assert_same('2026-10-27 10:30:00', $booking['slot_start'], 'browser local time converted');
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
    $outsideHours['start'] = '2026-10-27 07:00:00';
    assert_throws(fn () => Booking::createManual($outsideHours, $adminId, true), 'Slot is no longer available');
}

function test_admin_management_views_render_accessible_forms(): void
{
    seed_test_catalog();
    $admin = ['id' => 1, 'username' => 'owner'];
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

<?php
declare(strict_types=1);

function booking_input(?string $start = null): array
{
    return [
        'service_id' => 1,
        'staff_id' => null,
        'start' => $start ?? fixture_datetime('10:30'),
        'name' => 'Efua Mensah',
        'phone' => '0244127788',
        'email' => '',
        'notes' => '',
        'policy_accepted' => '1',
    ];
}

function test_guest_booking_is_confirmed_with_reference(): void
{
    seed_availability_fixture();
    $booking = Booking::create(booking_input());
    assert_same('confirmed', $booking['status'], 'booking status');
    assert_same('pay_at_spa', $booking['payment_method'], 'payment method');
    assert_same(12, strlen($booking['reference']), 'reference length');
}

function test_overlapping_booking_is_rejected(): void
{
    seed_availability_fixture();
    Booking::create(booking_input());
    $input = booking_input();
    $input['staff_id'] = 1;
    $input['phone'] = '0200000000';
    assert_throws(fn () => Booking::create($input), 'Slot is no longer available');
}

function test_cancelled_booking_releases_slot(): void
{
    seed_availability_fixture();
    $adminId = seed_admin('owner', password_hash('correct horse battery staple', PASSWORD_DEFAULT));
    Database::connection()->exec("DELETE FROM staff_breaks WHERE staff_id=1 AND weekday=2 AND start_time='10:00'");
    $bookingId = (int) Database::connection()->query("SELECT id FROM bookings WHERE reference='SPA-EXISTING'")->fetchColumn();
    $start = new DateTimeImmutable(fixture_datetime('09:00'), new DateTimeZone('UTC'));
    assert_same(false, Availability::isAvailable(1, 1, $start), 'confirmed booking blocks slot');
    Booking::setStatus($bookingId, 'cancelled', $adminId);
    assert_same(true, Availability::isAvailable(1, 1, $start), 'cancelled slot is available');
}

function test_customer_email_update_avoids_collation_sensitive_nullif(): void
{
    $source = file_get_contents(APP_ROOT . '/src/Booking.php');
    assert_not_contains('NULLIF(', $source, 'customer update avoids mixed-collation NULLIF expression');

    seed_availability_fixture();
    Booking::create(booking_input());
    $email = Database::connection()->query("SELECT email FROM customers WHERE phone='0244127788'")->fetchColumn();
    assert_same(null, $email, 'blank email is still stored as SQL NULL');
}

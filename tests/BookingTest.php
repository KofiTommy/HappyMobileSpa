<?php
declare(strict_types=1);

function booking_input(string $start = '2026-10-27 10:30:00'): array
{
    return [
        'service_id' => 1,
        'staff_id' => null,
        'start' => $start,
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

<?php
declare(strict_types=1);

function test_customer_lookup_requires_matching_phone(): void
{
    $booking = seed_confirmed_booking();
    assert_same(null, Booking::findForCustomer($booking['reference'], '0200000000'), 'wrong phone denied');
    assert_same($booking['id'], Booking::findForCustomer($booking['reference'], '0244127788')['id'], 'matching phone allowed');
}

function test_customer_cannot_cancel_inside_cutoff(): void
{
    $booking = seed_confirmed_booking('+12 hours');
    assert_throws(fn () => Booking::cancelForCustomer($booking['id']), 'Contact the spa to change this appointment');
}

function test_customer_reschedule_uses_availability(): void
{
    seed_availability_fixture();
    Database::connection()->exec("UPDATE settings SET setting_value='0' WHERE setting_key='cutoff_hours'");
    $booking = Booking::create(booking_input());
    $updated = Booking::rescheduleForCustomer($booking['id'], fixture_datetime('10:45'));
    assert_same(fixture_datetime('10:45'), $updated['slot_start'], 'new slot saved');
}

function test_rate_limit_blocks_repeated_attempts(): void
{
    seed_test_catalog();
    RateLimit::check('lookup', 'test-key', 2, 900);
    RateLimit::check('lookup', 'test-key', 2, 900);
    assert_throws(fn () => RateLimit::check('lookup', 'test-key', 2, 900), 'Too many attempts');
}

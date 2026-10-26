<?php
declare(strict_types=1);

function test_admin_password_and_status_rules(): void
{
    $hash = password_hash('correct horse battery staple', PASSWORD_DEFAULT);
    seed_admin('owner', $hash);
    assert_same(false, Auth::attempt('owner', 'wrong'), 'wrong password denied');
    assert_same(true, Auth::attempt('owner', 'correct horse battery staple'), 'correct password accepted');
    $booking = seed_confirmed_booking();
    Booking::setPaymentStatus($booking['id'], 'paid', 1);
    assert_same('paid', Booking::adminList(['id' => $booking['id']])[0]['payment_status'], 'payment recorded');
    Booking::setStatus($booking['id'], 'completed', 1);
    assert_throws(fn () => Booking::setStatus($booking['id'], 'confirmed', 1), 'Invalid status transition');
}

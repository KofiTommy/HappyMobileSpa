<?php
declare(strict_types=1);

function test_admin_password_and_status_rules(): void
{
    $hash = password_hash('correct horse battery staple', PASSWORD_DEFAULT);
    $booking = seed_confirmed_booking();
    $adminId = seed_admin('owner', $hash);
    assert_same(false, Auth::attempt('owner', 'wrong'), 'wrong password denied');
    assert_same(true, Auth::attempt('owner', 'correct horse battery staple'), 'correct password accepted');
    Booking::setPaymentStatus($booking['id'], 'paid', $adminId);
    assert_same('paid', Booking::adminList(['id' => $booking['id']])[0]['payment_status'], 'payment recorded');
    Booking::setStatus($booking['id'], 'completed', $adminId);
    assert_throws(fn () => Booking::setStatus($booking['id'], 'confirmed', $adminId), 'Invalid status transition');
}

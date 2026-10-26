<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
$admin = Auth::requireUser();
$today = (new DateTimeImmutable('today', business_timezone()))->format('Y-m-d');
$bookings = Booking::adminList(['date' => $today]);
$upcoming = array_values(array_filter(Booking::adminList(['status' => 'confirmed']), fn (array $booking): bool => $booking['slot_start'] >= gmdate('Y-m-d H:i:s')));
$upcoming = array_slice($upcoming, 0, 8);
$paidRevenue = array_sum(array_map(fn (array $booking): float => $booking['payment_status'] === 'paid' ? (float) $booking['price'] : 0, $bookings));
render_admin('Dashboard', 'dashboard', compact('admin', 'today', 'bookings', 'upcoming', 'paidRevenue'));

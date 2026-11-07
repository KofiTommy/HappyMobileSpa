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
            if ($booking['payment_status'] === 'paid') { $paid += (float) $booking['price']; }
            if (in_array($booking['status'], ['pending', 'confirmed'], true)) { $expected += (float) $booking['price']; }
            if ($booking['status'] === 'cancelled') { continue; }
            $name = (string) $booking['staff_name'];
            $workload[$name] ??= ['staff_name' => $name, 'appointments' => 0, 'minutes' => 0];
            $workload[$name]['appointments']++;
            $workload[$name]['minutes'] += (int) $booking['duration_min'];
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

<?php
declare(strict_types=1);

function test_reception_overview_summarizes_real_appointments(): void
{
    $today = [
        ['staff_name' => 'Ama S.', 'duration_min' => 60, 'status' => 'confirmed', 'payment_status' => 'paid', 'price' => '350.00'],
        ['staff_name' => 'Ama S.', 'duration_min' => 90, 'status' => 'pending', 'payment_status' => 'unpaid', 'price' => '520.00'],
        ['staff_name' => 'Kofi M.', 'duration_min' => 45, 'status' => 'cancelled', 'payment_status' => 'unpaid', 'price' => '280.00'],
    ];
    $summary = ReceptionOverview::summarize($today, [], 3);
    assert_same(3, $summary['today'], 'today count');
    assert_same(1, $summary['pending'], 'pending count');
    assert_same(1, $summary['confirmed'], 'confirmed count');
    assert_same(350.0, $summary['paid_revenue'], 'recorded payments');
    assert_same(870.0, $summary['expected_revenue'], 'active appointment value');
    assert_same(3, $summary['unread_messages'], 'unread messages');
    assert_same(2, $summary['workload'][0]['appointments'], 'therapist workload');
    assert_same(150, $summary['workload'][0]['minutes'], 'therapist minutes');
    assert_same(1, count($summary['workload']), 'cancelled appointment excluded from workload rows');
    assert_same('Ama S.', $summary['workload'][0]['staff_name'], 'cancelled therapist does not appear in workload');
    assert_same(3, $summary['today'], 'cancelled appointment remains in overall today count');
}

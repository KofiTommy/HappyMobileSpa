<?php
declare(strict_types=1);

function test_availability_excludes_breaks_buffers_and_time_off(): void
{
    seed_availability_fixture();
    $slots = Availability::slots(1, 1, fixture_date());
    $labels = array_column($slots, 'label');
    assert_same('10:30 AM', $labels[0], 'first slot follows booking buffer and break');
    assert_same(false, in_array('10:15 AM', $labels, true), 'buffer and break excluded');
    assert_same(true, in_array('1:00 PM', $labels, true), 'first lunch-end slot included');
    $start = new DateTimeImmutable(fixture_datetime('09:00'), new DateTimeZone('UTC'));
    assert_same(false, Availability::isAvailable(1, 3, $start), 'unassigned staff rejected');
    assert_same(false, Availability::isAvailable(1, 1, $start->modify('+1 day')), 'time off rejected');
}

function test_any_available_assigns_only_eligible_free_staff(): void
{
    seed_availability_fixture();
    $slots = Availability::slots(1, null, fixture_date());
    assert_same([2, 1], array_values(array_unique(array_column($slots, 'staff_id'))), 'eligible staff in chronological order');
}

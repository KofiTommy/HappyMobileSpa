<?php
declare(strict_types=1);

function test_active_catalog_and_price_types(): void
{
    seed_test_catalog();
    $services = Catalog::activeServices();
    assert_same(6, count($services), 'six active seed services');
    assert_same('Swedish Massage', $services[0]['name'], 'first service');
    assert_same('350.00', $services[0]['price'], 'decimal price remains exact');
    $staff = Catalog::activeStaff((int) $services[0]['id']);
    assert_same(true, count($staff) >= 2, 'eligible therapists returned');
}

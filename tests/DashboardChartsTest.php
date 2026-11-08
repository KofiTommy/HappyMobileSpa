<?php
declare(strict_types=1);

function dashboard_chart_bookings(): array
{
    return [
        ['slot_start' => '2026-11-15 09:00:00', 'status' => 'confirmed', 'service_name' => 'Swedish Massage'],
        ['slot_start' => '2026-11-14 23:30:00', 'status' => 'completed', 'service_name' => 'Deep Tissue Ritual'],
        ['slot_start' => '2026-11-01 09:00:00', 'status' => 'pending', 'service_name' => 'Swedish Massage'],
        ['slot_start' => '2026-11-10 09:00:00', 'status' => 'cancelled', 'service_name' => '<script>Facial</script>'],
        ['slot_start' => '2026-11-11 09:00:00', 'status' => 'no_show', 'service_name' => 'Mani + Pedi'],
        ['slot_start' => '2026-11-12 09:00:00', 'status' => 'confirmed', 'service_name' => 'Swedish Massage'],
        ['slot_start' => '2026-11-16 09:00:00', 'status' => 'confirmed', 'service_name' => 'Hot Stone Massage'],
        ['slot_start' => '2026-10-20 09:00:00', 'status' => 'confirmed', 'service_name' => 'Swedish Massage'],
    ];
}

function dashboard_cash_rows(): array
{
    $rows = [];
    foreach (['2026-06', '2026-07', '2026-08', '2026-09', '2026-10', '2026-11'] as $index => $month) {
        $rows[] = ['month' => $month, 'label' => (new DateTimeImmutable($month . '-01'))->format('M'), 'income' => (string) (1000 + $index * 100), 'spending' => (string) (600 + $index * 50), 'net' => '400.00'];
    }
    return $rows;
}

function test_dashboard_charts_group_local_dates_statuses_and_treatments(): void
{
    $today = new DateTimeImmutable('2026-11-15 12:00:00', new DateTimeZone('Africa/Accra'));
    $charts = DashboardCharts::fromRows(dashboard_chart_bookings(), dashboard_cash_rows(), $today);
    assert_same(14, count($charts['trend']), 'fourteen daily points');
    assert_same(4, $charts['trend_total'], 'cancelled and out-of-range appointments excluded from trend');
    assert_same(1, $charts['trend'][13]['count'], 'UTC appointments grouped into Ghana calendar day');
    assert_same(3, $charts['statuses']['confirmed'], 'current-month confirmed count');
    assert_same(1, $charts['statuses']['pending'], 'current-month pending count');
    assert_same(1, $charts['statuses']['completed'], 'current-month completed count');
    assert_same(1, $charts['statuses']['no_show'], 'current-month no-show count');
    assert_same(1, $charts['statuses']['cancelled'], 'cancelled shown in status mix');
    assert_same(7, $charts['status_total'], 'previous-month status excluded');
    assert_same('Swedish Massage', $charts['services'][0]['name'], 'top treatment ranked first');
    assert_same(3, $charts['services'][0]['appointments'], 'treatment count excludes cancelled and previous month');
    assert_same(false, in_array('<script>Facial</script>', array_column($charts['services'], 'name'), true), 'cancelled treatment excluded from ranking');
    assert_same(6, count($charts['cash']), 'six-month cash dataset retained');
    assert_same('November 2026', $charts['month_label'], 'current month label');
}

function test_dashboard_finance_cash_trend_uses_actual_paid_records(): void
{
    seed_test_catalog();
    $adminId = seed_admin('chart-owner', password_hash('a long chart password', PASSWORD_DEFAULT));
    $month = Finance::month();
    Database::connection()->exec('UPDATE staff SET monthly_salary=1000 WHERE id=1');
    Finance::generateSalaries($month, $adminId);
    $salary = Finance::salaries($month)[0];
    Finance::saveSalary([
        'id' => $salary['id'], 'staff_id' => 1, 'salary_month' => $month,
        'basic_salary' => '1000', 'bonus' => '0', 'deduction' => '0',
        'status' => 'paid', 'paid_at' => $month . '-01', 'payment_method' => 'cash',
    ], $adminId);
    Finance::saveExpense(finance_expense_input([
        'expense_date' => $month . '-02', 'paid_at' => $month . '-02', 'amount' => '100',
    ]), $adminId);
    $pdo = Database::connection();
    $pdo->exec("INSERT INTO customers (id,phone,name,created_at,updated_at) VALUES (1,'0244000001','Chart Client',datetime(),datetime())");
    $pdo->prepare("INSERT INTO bookings (id,reference,customer_id,service_id,staff_id,slot_start,slot_end,price,status,payment_method,payment_status,notes,accepted_policy,created_at,updated_at) VALUES (1,'SPA-CHART1',1,1,1,?,?,350,'confirmed','pay_at_spa','paid','','Policy',?,?)")
        ->execute([$month . '-15 09:00:00', $month . '-15 10:00:00', $month . '-01 09:00:00', $month . '-02 09:00:00']);
    $pdo->prepare("INSERT INTO booking_events (booking_id,actor_type,admin_id,event_type,details,created_at) VALUES (1,'admin',?,'payment_changed',?,?)")
        ->execute([$adminId, json_encode(['from' => 'unpaid', 'to' => 'paid']), $month . '-02 09:00:00']);

    $trend = Finance::cashTrend(6);
    assert_same(6, count($trend), 'six cash-flow months');
    $current = $trend[5];
    assert_same($month, $current['month'], 'latest month last');
    assert_same('350.00', $current['income'], 'actual payment income');
    assert_same('1000.00', $current['salaries'], 'paid salary amount');
    assert_same('100.00', $current['expenses'], 'paid expense amount');
    assert_same('1100.00', $current['spending'], 'combined spending');
    assert_same('-750.00', $current['net'], 'net cash movement');
    assert_same(2, count(Finance::cashTrend(2)), 'cash trend range can be shortened');
}

function test_dashboard_charts_render_accessibly_without_exposing_finance_to_receptionists(): void
{
    seed_test_catalog();
    $today = new DateTimeImmutable('2026-11-15 12:00:00', new DateTimeZone('Africa/Accra'));
    $render = static function (string $role, array $cash): string {
        $charts = DashboardCharts::fromRows(dashboard_chart_bookings(), $cash, new DateTimeImmutable('2026-11-15 12:00:00', new DateTimeZone('Africa/Accra')));
        $admin = ['id' => 1, 'username' => 'dashboard-user', 'role' => $role];
        $summary = ReceptionOverview::summarize([], [], $role === 'admin' ? 0 : null);
        ob_start();
        render_admin('Dashboard', 'dashboard', [
            'admin' => $admin, 'today' => '2026-11-15', 'bookings' => [], 'upcoming' => [],
            'paidRevenue' => 0, 'summary' => $summary, 'charts' => $charts,
        ]);
        return (string) ob_get_clean();
    };
    $html = $render('admin', dashboard_cash_rows());
    assert_contains('See how the business is performing', $html, 'management insights heading');
    assert_contains('Appointment activity', $html, 'booking trend chart');
    assert_contains('Appointment status', $html, 'status chart');
    assert_contains('Popular treatments', $html, 'treatment chart');
    assert_contains('Income vs spending', $html, 'admin cash chart');
    assert_contains('role="img" aria-label="Confirmed: 3', $html, 'status chart accessible summary');
    assert_contains('tabindex="0" class="chart-point"', $html, 'daily points keyboard accessible');
    assert_contains('tabindex="0" class="chart-bar', $html, 'cash bars keyboard accessible');
    assert_not_contains('<script>Facial</script>', $html, 'treatment names cannot inject markup');

    $reception = $render('receptionist', []);
    assert_contains('Appointment activity', $reception, 'receptionist retains operational charts');
    assert_not_contains('Income vs spending', $reception, 'receptionist cash chart hidden');
    assert_not_contains('chart-bar-income', $reception, 'receptionist financial values not rendered');
}

function test_dashboard_charts_empty_state_is_stable(): void
{
    $today = new DateTimeImmutable('2026-11-15 12:00:00', new DateTimeZone('Africa/Accra'));
    $charts = DashboardCharts::fromRows([], [], $today);
    assert_same(0, $charts['trend_total'], 'empty trend total');
    assert_same(0, $charts['status_total'], 'empty status total');
    assert_same([], $charts['services'], 'empty treatment ranking');
    ob_start();
    $admin = ['id' => 1, 'username' => 'owner', 'role' => 'admin'];
    include APP_ROOT . '/templates/admin/dashboard-charts.php';
    $html = (string) ob_get_clean();
    assert_contains('status-donut is-empty', $html, 'empty donut style');
    assert_contains('No treatment data yet', $html, 'empty treatment explanation');
    assert_not_contains('Income vs spending', $html, 'empty cash data does not divide by zero');

    $charts = DashboardCharts::fromRows([['slot_start' => '2026-11-15 09:00:00', 'status' => 'confirmed', 'service_name' => 'Only Treatment']], [], $today);
    ob_start();
    include APP_ROOT . '/templates/admin/dashboard-charts.php';
    $singleServiceHtml = (string) ob_get_clean();
    assert_contains('Only Treatment', $singleServiceHtml, 'single treatment ranking renders');
    assert_contains('width:100.00%', $singleServiceHtml, 'single treatment receives full bar width');

    $charts = DashboardCharts::fromRows([], [], $today, false);
    ob_start();
    include APP_ROOT . '/templates/admin/dashboard-charts.php';
    $pendingSetupHtml = (string) ob_get_clean();
    assert_contains('Appointment activity', $pendingSetupHtml, 'operational charts survive missing Finance setup');
    assert_contains('Finance charts are not connected yet', $pendingSetupHtml, 'administrator sees Finance setup guidance');
    assert_contains('Existing appointment records are not affected', $pendingSetupHtml, 'setup message explains booking safety');
}

<?php
declare(strict_types=1);

function finance_expense_input(array $overrides = []): array
{
    return array_replace([
        'expense_date' => '2026-11-10',
        'category' => 'supplies',
        'vendor' => 'Accra Wellness Supplies',
        'description' => 'Massage oils and clean linen',
        'amount' => '100.25',
        'status' => 'paid',
        'paid_at' => '2026-11-10',
        'payment_method' => 'cash',
        'reference' => 'RCPT-100',
        'notes' => 'November stock',
    ], $overrides);
}

function test_finance_salary_generation_prevents_duplicates_and_calculates_net(): void
{
    seed_test_catalog();
    $adminId = seed_admin('finance-owner', password_hash('a long finance password', PASSWORD_DEFAULT));
    $pdo = Database::connection();
    $pdo->exec("UPDATE staff SET monthly_salary=1200.50 WHERE id=1");
    $pdo->exec("UPDATE staff SET monthly_salary=800 WHERE id=3");
    $pdo->exec("UPDATE staff SET monthly_salary=500,active=0 WHERE id=4");

    assert_same(2, Finance::generateSalaries('2026-11', $adminId), 'only active salaried staff generated');
    assert_same(0, Finance::generateSalaries('2026-11', $adminId), 'repeat generation is idempotent');
    $salaries = Finance::salaries('2026-11');
    assert_same(2, count($salaries), 'one salary per eligible worker');
    $salaryId = null;
    foreach ($salaries as $salary) {
        assert_same('pending', $salary['status'], 'generated salary starts pending');
        if ((int) $salary['staff_id'] === 1) { $salaryId = (int) $salary['id']; }
    }

    Finance::saveSalary([
        'id' => $salaryId, 'staff_id' => 1, 'salary_month' => '2026-11',
        'basic_salary' => '1200.50', 'bonus' => '100', 'deduction' => '50.25',
        'status' => 'paid', 'paid_at' => '2026-11-25', 'payment_method' => 'mobile_money',
        'reference' => 'MOMO-SALARY-1', 'notes' => 'November payroll',
    ], $adminId);
    $salary = Finance::salary($salaryId);
    assert_same(1250.25, (float) $salary['net_salary'], 'net salary adds bonus and subtracts deduction');
    assert_same('paid', $salary['status'], 'salary marked paid');
    assert_same('1250.25', Finance::overview('2026-11')['salary_paid'], 'paid salary included in cash summary');
    assert_same('800.00', Finance::overview('2026-11')['salary_outstanding'], 'pending salary tracked separately');

    assert_throws(fn () => Finance::saveSalary([
        'staff_id' => 1, 'salary_month' => '2026-11', 'basic_salary' => '1200.50',
        'bonus' => '0', 'deduction' => '0', 'status' => 'pending',
    ], $adminId), 'already has a salary record');
    assert_throws(fn () => Finance::saveSalary([
        'staff_id' => 2, 'salary_month' => '2026-11', 'basic_salary' => '100',
        'bonus' => '0', 'deduction' => '100.01', 'status' => 'pending',
    ], $adminId), 'Net salary must be greater than zero');
    assert_throws(fn () => Finance::saveSalary([
        'staff_id' => 2, 'salary_month' => '2026-11', 'basic_salary' => '100',
        'bonus' => '0', 'deduction' => '0', 'status' => 'paid', 'paid_at' => '2026-11-25',
        'payment_method' => '',
    ], $adminId), 'Choose a valid payment method');
}

function test_finance_expenses_budgets_cancellation_and_audit_trail(): void
{
    seed_test_catalog();
    $adminId = seed_admin('finance-owner', password_hash('a long finance password', PASSWORD_DEFAULT));
    $paidId = Finance::saveExpense(finance_expense_input(), $adminId);
    Finance::saveExpense(finance_expense_input([
        'expense_date' => '2026-11-12', 'category' => 'transport', 'description' => 'Client visit transport',
        'amount' => '50', 'status' => 'pending', 'payment_method' => '', 'reference' => '',
    ]), $adminId);
    Finance::saveBudget([
        'salary_month' => '2026-11', 'expected_income' => '5000', 'salary_budget' => '2000',
        'expense_budgets' => ['supplies' => '300', 'transport' => '100'], 'notes' => 'Keep transport within plan.',
    ], $adminId);

    $summary = Finance::overview('2026-11');
    assert_same('100.25', $summary['expenses_paid'], 'paid expense total');
    assert_same('50.00', $summary['expenses_pending'], 'pending expense total');
    assert_same('400.00', $summary['expense_budget'], 'all category budgets totalled');
    assert_same('199.75', $summary['expense_categories'][0]['remaining'], 'category remaining budget');
    assert_same(5000.0, (float) Finance::budget('2026-11')['expected_income'], 'income budget saved');
    assert_same('Keep transport within plan.', Finance::budget('2026-11')['notes'], 'budget notes saved');

    Finance::cancel('expense', $paidId, $adminId);
    assert_same('cancelled', Finance::expense($paidId)['status'], 'cancelled expense retained');
    assert_same('0.00', Finance::overview('2026-11')['expenses_paid'], 'cancelled expense excluded from totals');
    assert_throws(fn () => Finance::cancel('expense', $paidId, $adminId), 'already cancelled');

    Finance::saveExpense(finance_expense_input(['id' => $paidId]), $adminId);
    assert_same('paid', Finance::expense($paidId)['status'], 'cancelled expense can be restored');
    assert_same('100.25', Finance::overview('2026-11')['expenses_paid'], 'restored expense returns to totals');
    $actions = array_column(Finance::events(20), 'action');
    foreach (['created', 'saved', 'cancelled', 'restored'] as $action) {
        assert_same(true, in_array($action, $actions, true), 'audit action recorded: ' . $action);
    }
    $event = Finance::events(1)[0];
    assert_same($adminId, (int) $event['admin_id'], 'audit records administrator');
    assert_same('finance-owner', $event['username'], 'audit displays administrator username');
}

function test_finance_actual_income_is_separate_from_expected_appointment_value(): void
{
    seed_test_catalog();
    $pdo = Database::connection();
    $pdo->exec("INSERT INTO customers (id,phone,name,created_at,updated_at) VALUES (1,'0244000000','Finance Client','2026-11-01 00:00:00','2026-11-01 00:00:00')");
    $insert = $pdo->prepare("INSERT INTO bookings (id,reference,customer_id,service_id,staff_id,slot_start,slot_end,price,status,payment_method,payment_status,notes,accepted_policy,created_at,updated_at) VALUES (?,?,1,1,1,?,? ,?,'confirmed',? ,?,'','Policy',?,?)");
    $insert->execute([1, 'SPA-FIN001', '2026-11-20 09:00:00', '2026-11-20 10:00:00', '350.00', 'pay_at_spa', 'paid', '2026-11-05 10:00:00', '2026-11-06 10:00:00']);
    $insert->execute([2, 'SPA-FIN002', '2026-11-22 09:00:00', '2026-11-22 10:00:00', '520.00', 'pay_at_spa', 'unpaid', '2026-11-05 10:00:00', '2026-11-05 10:00:00']);
    $insert->execute([3, 'SPA-FIN003', '2026-11-25 09:00:00', '2026-11-25 10:00:00', '280.00', 'paystack_momo', 'paid', '2026-11-05 10:00:00', '2026-12-01 10:00:00']);
    $pdo->prepare("INSERT INTO booking_events (booking_id,actor_type,event_type,details,created_at) VALUES (1,'admin','payment_changed',?,?)")
        ->execute([json_encode(['from' => 'unpaid', 'to' => 'paid']), '2026-11-06 10:00:00']);
    $pdo->prepare("INSERT INTO paystack_payments (booking_id,reference,amount_subunit,currency,email,mode,state,created_at,paid_at) VALUES (3,'SPA-FIN003',28000,'GHS','client@example.com','live','paid','2026-11-05 10:00:00','2026-12-01 10:00:00')")
        ->execute();
    $adminId = seed_admin('finance-owner', password_hash('a long finance password', PASSWORD_DEFAULT));
    Finance::saveExpense(finance_expense_input(['amount' => '280', 'description' => 'November equipment']), $adminId);

    $november = Finance::overview('2026-11');
    assert_same('350.00', $november['income_received'], 'manual payment recognized in transition month');
    assert_same('1150.00', $november['expected_appointments'], 'expected value includes active November appointments');
    assert_same('70.00', $november['remaining_cash'], 'remaining cash subtracts a paid expense without double counting income');
    assert_same('280.00', Finance::overview('2026-12')['income_received'], 'Paystack income recognized on paid date');
    assert_same('0.00', Finance::overview('2026-12')['expected_appointments'], 'November appointment excluded from December expectation');
}

function test_finance_mutations_require_an_active_administrator(): void
{
    seed_test_catalog();
    $ownerId = seed_admin('finance-owner', password_hash('a long finance password', PASSWORD_DEFAULT));
    $pdo = Database::connection();
    $pdo->prepare("INSERT INTO admin_users (username,password_hash,role,staff_id,active,created_at,updated_at) VALUES ('finance-reception',?,'receptionist',1,1,datetime(),datetime())")
        ->execute([password_hash('a long reception password', PASSWORD_DEFAULT)]);
    $receptionId = (int) $pdo->lastInsertId();
    $pdo->exec('UPDATE staff SET monthly_salary=1000 WHERE id=1');

    assert_throws(fn () => Finance::generateSalaries('2026-11', $receptionId), 'Administrator access required');
    assert_throws(fn () => Finance::saveExpense(finance_expense_input(), $receptionId), 'Administrator access required');
    assert_throws(fn () => Finance::saveBudget(['salary_month' => '2026-11'], $receptionId), 'Administrator access required');
    assert_same(1, Finance::generateSalaries('2026-11', $ownerId), 'administrator can generate payroll');
    $salaryId = (int) Finance::salaries('2026-11')[0]['id'];
    assert_throws(fn () => Finance::cancel('salary', $salaryId, $receptionId), 'Administrator access required');
    assert_same('pending', Finance::salary($salaryId)['status'], 'denied cancellation leaves salary unchanged');
    $pdo->exec('UPDATE admin_users SET active=0 WHERE id=' . $ownerId);
    assert_throws(fn () => Finance::cancel('salary', $salaryId, $ownerId), 'Administrator account not found');
}

function test_finance_receipt_validation_private_storage_and_csv_cells(): void
{
    $path = tempnam(sys_get_temp_dir(), 'spa-receipt-');
    $discardPath = APP_ROOT . '/storage/receipts/' . str_repeat('b', 32) . '.png';
    try {
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a2ioAAAAASUVORK5CYII='));
        assert_same('png', ExpenseReceipt::inspect($path), 'valid PNG receipt recognized');
        file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        assert_throws(fn () => ExpenseReceipt::inspect($path), 'JPG, PNG, WebP, or PDF');
        file_put_contents($path, str_repeat('x', 5242881));
        clearstatcache(true, $path);
        assert_throws(fn () => ExpenseReceipt::inspect($path), 'smaller than 5 MB');
        assert_same(null, ExpenseReceipt::upload(['error' => UPLOAD_ERR_NO_FILE]), 'no upload preserves receipt');
        assert_throws(fn () => ExpenseReceipt::upload(['error' => UPLOAD_ERR_INI_SIZE]), 'upload failed');
        assert_throws(fn () => ExpenseReceipt::upload(['error' => UPLOAD_ERR_OK, 'tmp_name' => $path]), 'Invalid receipt upload');

        file_put_contents($discardPath, 'private');
        ExpenseReceipt::discard('storage/receipts/' . str_repeat('b', 32) . '.png');
        assert_same(false, is_file($discardPath), 'replaced receipt securely discarded');
        assert_same('Require all denied', trim((string) file_get_contents(APP_ROOT . '/storage/.htaccess')), 'storage denied by Apache');
        assert_same('Require all denied', trim((string) file_get_contents(APP_ROOT . '/storage/receipts/.htaccess')), 'receipt directory denied by Apache');
        assert_contains("~^/(?:storage|config)(?:/|$)~", file_get_contents(APP_ROOT . '/router.php'), 'PHP preview blocks direct storage access');
        assert_contains('Auth::requireAdmin()', file_get_contents(APP_ROOT . '/admin/expense-receipt.php'), 'receipt route requires administrator');
    } finally {
        if (is_file($path)) { unlink($path); }
        if (is_file($discardPath)) { unlink($discardPath); }
    }

    foreach (['=1+1', '+CMD', '-2+3', '@SUM(A1:A2)', '  =HYPERLINK("bad")'] as $value) {
        assert_same("'" . $value, Finance::spreadsheetCell($value), 'spreadsheet formula neutralized');
    }
    assert_same('Ordinary text', Finance::spreadsheetCell('Ordinary text'), 'ordinary CSV text unchanged');
    assert_same('100.25', Finance::spreadsheetCell('100.25'), 'positive amounts unchanged');
}

function test_finance_views_render_and_receptionists_do_not_see_finance_navigation(): void
{
    seed_test_catalog();
    $adminId = seed_admin('finance-owner', password_hash('a long finance password', PASSWORD_DEFAULT));
    Database::connection()->exec('UPDATE staff SET monthly_salary=1000 WHERE id=1');
    Finance::generateSalaries('2026-11', $adminId);
    Finance::saveExpense(finance_expense_input(), $adminId);
    Finance::saveBudget(['salary_month' => '2026-11', 'expected_income' => '5000', 'salary_budget' => '1000'], $adminId);
    $admin = ['id' => $adminId, 'username' => 'finance-owner', 'role' => 'admin'];
    $month = '2026-11';
    $common = [
        'admin' => $admin, 'month' => $month, 'error' => '', 'success' => '',
        'categories' => Finance::categories(), 'paymentMethods' => Finance::paymentMethods(), 'today' => '2026-11-15',
    ];
    $_SERVER['SCRIPT_NAME'] = 'finance.php';
    $render = static function (string $view, array $data) use ($common): string {
        ob_start();
        render_admin('Finance', 'finance-' . $view, array_replace($common, ['view' => $view], $data));
        return (string) ob_get_clean();
    };

    $summary = Finance::overview($month);
    $html = $render('overview', ['summary' => $summary, 'events' => Finance::events()]);
    assert_contains('Monthly financial overview', $html, 'overview heading');
    assert_contains('expected appointment value, not cash received', $html, 'income distinction rendered');
    assert_contains('Recent finance activity', $html, 'audit activity rendered');
    assert_contains('Finance &amp; budgets', $html, 'admin navigation includes finance');

    $html = $render('salaries', ['salaries' => Finance::salaries($month), 'staff' => Catalog::adminStaff(), 'salaryForm' => ['salary_month' => $month, 'status' => 'pending', 'basic_salary' => '0.00', 'bonus' => '0.00', 'deduction' => '0.00', 'paid_at' => '2026-11-15']]);
    assert_contains('Generate monthly salaries', $html, 'generation form rendered');
    assert_contains('Net salary = basic salary + bonus − deduction', $html, 'net calculation explained');
    assert_contains('name="staff_id"', $html, 'salary staff selector rendered');

    $html = $render('expenses', ['expenses' => Finance::expenses($month), 'expenseForm' => ['expense_date' => '2026-11-15', 'category' => 'supplies', 'status' => 'pending', 'paid_at' => '2026-11-15']]);
    assert_contains('Record an expense', $html, 'expense form rendered');
    assert_contains('accept="image/jpeg,image/png,image/webp,application/pdf"', $html, 'receipt upload types restricted');
    assert_contains('View receipt', file_get_contents(APP_ROOT . '/templates/admin/finance-expenses.php'), 'private receipt link available when present');

    $html = $render('budget', ['budget' => Finance::budget($month), 'summary' => $summary]);
    assert_contains('Set the monthly budget', $html, 'budget form rendered');
    assert_contains('name="expense_budgets[supplies]"', $html, 'category budget input rendered');

    ob_start();
    render_admin('New booking', 'manual-booking', ['admin' => ['id' => 2, 'username' => 'reception', 'role' => 'receptionist'], 'services' => Catalog::activeServices(), 'staff' => Catalog::activeStaff(), 'old' => [], 'error' => '']);
    $receptionHtml = (string) ob_get_clean();
    assert_not_contains('href="finance.php"', $receptionHtml, 'receptionist navigation excludes finance');
}

function test_finance_input_validation_rejects_invalid_months_amounts_and_dates(): void
{
    assert_throws(fn () => Finance::month('2026-13'), 'valid finance month');
    assert_throws(fn () => Finance::month('11/2026'), 'valid finance month');
    assert_throws(fn () => Finance::amount('1,000.00'), 'valid amount');
    assert_throws(fn () => Finance::amount('1.234'), 'at most two decimal places');
    assert_throws(fn () => Finance::amount('-10'), 'valid amount');
    assert_same('0.00', Finance::amount('0', 'Amount', true), 'zero allowed for optional amounts');
    assert_throws(fn () => Finance::amount('0'), 'greater than zero');
    seed_test_catalog();
    $adminId = seed_admin('finance-owner', password_hash('a long finance password', PASSWORD_DEFAULT));
    assert_throws(fn () => Finance::saveExpense(finance_expense_input(['expense_date' => '2026-02-30']), $adminId), 'valid date');
    assert_throws(fn () => Finance::saveExpense(finance_expense_input(['category' => 'unknown']), $adminId), 'valid expense category');
}

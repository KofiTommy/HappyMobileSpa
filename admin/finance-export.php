<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
Auth::requireAdmin();
$view = (string) ($_GET['view'] ?? 'overview');
if (!in_array($view, ['overview', 'salaries', 'expenses'], true)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Choose a valid finance export.';
    exit;
}
try {
    $month = Finance::month(isset($_GET['month']) ? (string) $_GET['month'] : null);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $exception->getMessage();
    exit;
}

$spreadsheetCell = static fn (mixed $value): string => Finance::spreadsheetCell($value);
$writeRow = static function (array $row) use ($spreadsheetCell): void {
    static $output = null;
    if ($output === null) { $output = fopen('php://output', 'wb'); }
    fputcsv($output, array_map($spreadsheetCell, $row), ',', '"', '\\');
};

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="happy-mobile-spa-' . $view . '-' . $month . '.csv"');
header('Cache-Control: private, no-store');
echo "\xEF\xBB\xBF";

if ($view === 'salaries') {
    $writeRow(['Staff member', 'Salary month', 'Basic salary (GHS)', 'Bonus (GHS)', 'Deduction (GHS)', 'Net salary (GHS)', 'Status', 'Payment date', 'Payment method', 'Transaction reference', 'Notes', 'Created at', 'Updated at', 'Cancelled at']);
    foreach (Finance::salaries($month) as $salary) {
        $writeRow([$salary['staff_name'], $salary['salary_month'], $salary['basic_salary'], $salary['bonus'], $salary['deduction'], $salary['net_salary'], $salary['status'], $salary['paid_at'], $salary['payment_method'], $salary['reference'], $salary['notes'], $salary['created_at'], $salary['updated_at'], $salary['cancelled_at']]);
    }
} elseif ($view === 'expenses') {
    $writeRow(['Expense date', 'Category', 'Description', 'Vendor or payee', 'Amount (GHS)', 'Status', 'Payment date', 'Payment method', 'Transaction reference', 'Notes', 'Receipt filename', 'Created at', 'Updated at', 'Cancelled at']);
    $categories = Finance::categories();
    foreach (Finance::expenses($month) as $expense) {
        $writeRow([$expense['expense_date'], $categories[$expense['category']] ?? $expense['category'], $expense['description'], $expense['vendor'], $expense['amount'], $expense['status'], $expense['paid_at'], $expense['payment_method'], $expense['reference'], $expense['notes'], $expense['receipt_name'], $expense['created_at'], $expense['updated_at'], $expense['cancelled_at']]);
    }
} else {
    $summary = Finance::overview($month);
    $writeRow(['Happy Mobile Spa monthly finance summary', $month]);
    $writeRow(['Metric', 'Amount (GHS)']);
    foreach ([
        'Income received' => 'income_received',
        'Expected appointment value (not received income)' => 'expected_appointments',
        'Salaries paid' => 'salary_paid',
        'Salaries pending' => 'salary_outstanding',
        'Other expenses paid' => 'expenses_paid',
        'Other expenses pending' => 'expenses_pending',
        'Cash remaining after paid salaries and expenses' => 'remaining_cash',
        'Expected income budget' => 'expected_income_budget',
        'Salary budget' => 'salary_budget',
        'Other expense budget' => 'expense_budget',
    ] as $label => $key) {
        $writeRow([$label, $summary[$key]]);
    }
    $writeRow([]);
    $writeRow(['Expense category', 'Budget (GHS)', 'Paid (GHS)', 'Remaining (GHS)']);
    foreach ($summary['expense_categories'] as $row) {
        $writeRow([$row['label'], $row['budget'], $row['paid'], $row['remaining']]);
    }
}
exit;

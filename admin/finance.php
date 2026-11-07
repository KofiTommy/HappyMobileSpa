<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
$admin = Auth::requireAdmin();
$adminId = (int) $admin['id'];
$error = '';
$view = (string) ($_GET['view'] ?? 'overview');
if (!in_array($view, ['overview', 'salaries', 'expenses', 'budget'], true)) { $view = 'overview'; }
try {
    $month = Finance::month(isset($_POST['salary_month']) ? (string) $_POST['salary_month'] : (isset($_POST['month']) ? (string) $_POST['month'] : ($_GET['month'] ?? null)));
} catch (InvalidArgumentException $exception) {
    $month = Finance::month();
    $error = $exception->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    try {
        if (!Csrf::verify($_POST['csrf'] ?? null)) { throw new DomainException('Your form session expired.'); }
        $action = (string) ($_POST['action'] ?? '');
        $targetView = $view;
        $notice = 'saved';
        if ($action === 'generate_salaries') {
            $created = Finance::generateSalaries($month, $adminId);
            $targetView = 'salaries';
            $notice = 'generated=' . $created;
        } elseif ($action === 'save_salary') {
            Finance::saveSalary($_POST, $adminId);
            $targetView = 'salaries';
            $notice = 'saved=salary';
        } elseif ($action === 'cancel_salary') {
            Finance::cancel('salary', (int) ($_POST['id'] ?? 0), $adminId);
            $targetView = 'salaries';
            $notice = 'cancelled=salary';
        } elseif ($action === 'save_expense') {
            Finance::saveExpense($_POST, $adminId, $_FILES['receipt'] ?? null);
            $targetView = 'expenses';
            $notice = 'saved=expense';
        } elseif ($action === 'cancel_expense') {
            Finance::cancel('expense', (int) ($_POST['id'] ?? 0), $adminId);
            $targetView = 'expenses';
            $notice = 'cancelled=expense';
        } elseif ($action === 'save_budget') {
            Finance::saveBudget($_POST, $adminId);
            $targetView = 'budget';
            $notice = 'saved=budget';
        } else {
            throw new InvalidArgumentException('Unknown finance action.');
        }
        $query = str_contains($notice, '=') ? $notice : $notice . '=1';
        header('Location: finance.php?view=' . $targetView . '&month=' . rawurlencode($month) . '&' . $query);
        exit;
    } catch (DomainException | InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    }
}

$success = '';
if (isset($_GET['saved'])) {
    $success = ucfirst((string) $_GET['saved']) . ' saved.';
} elseif (isset($_GET['cancelled'])) {
    $success = ucfirst((string) $_GET['cancelled']) . ' cancelled. The audit record has been preserved.';
} elseif (isset($_GET['generated'])) {
    $count = (int) $_GET['generated'];
    $success = $count === 0 ? 'No new salaries were needed. Add usual monthly salaries to active staff profiles first.' : $count . ' monthly salary ' . ($count === 1 ? 'record' : 'records') . ' generated.';
}
$categories = Finance::categories();
$paymentMethods = Finance::paymentMethods();
$today = (new DateTimeImmutable('now', business_timezone()))->format('Y-m-d');
$template = 'finance-' . $view;
$data = compact('admin', 'view', 'month', 'error', 'success', 'categories', 'paymentMethods', 'today');

if ($view === 'overview') {
    $data['summary'] = Finance::overview($month);
    $data['events'] = Finance::events();
} elseif ($view === 'salaries') {
    $data['salaries'] = Finance::salaries($month);
    $data['staff'] = Catalog::adminStaff();
    $salaryForm = ['salary_month' => $month, 'status' => 'pending', 'basic_salary' => '0.00', 'bonus' => '0.00', 'deduction' => '0.00', 'paid_at' => $today, 'payment_method' => '', 'reference' => '', 'notes' => ''];
    if (!empty($_GET['edit_salary'])) {
        $salaryForm = Finance::salary((int) $_GET['edit_salary']) ?: $salaryForm;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_salary' && $error !== '') {
        $salaryForm = $_POST;
    }
    $data['salaryForm'] = $salaryForm;
} elseif ($view === 'expenses') {
    $data['expenses'] = Finance::expenses($month);
    $expenseForm = ['expense_date' => $today, 'category' => 'supplies', 'status' => 'pending', 'paid_at' => $today, 'payment_method' => '', 'vendor' => '', 'description' => '', 'amount' => '', 'reference' => '', 'notes' => ''];
    if (!empty($_GET['edit_expense'])) {
        $expenseForm = Finance::expense((int) $_GET['edit_expense']) ?: $expenseForm;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_expense' && $error !== '') {
        $expenseForm = $_POST;
    }
    $data['expenseForm'] = $expenseForm;
} else {
    $budget = Finance::budget($month);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_budget' && $error !== '') {
        $budget = array_merge($budget, $_POST);
    }
    $data['budget'] = $budget;
    $data['summary'] = Finance::overview($month);
}

render_admin('Finance', $template, $data);

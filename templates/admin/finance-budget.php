<?php require __DIR__ . '/finance-tabs.php'; ?>
<header class="page-lead">
    <div><p class="admin-eyebrow">MONTHLY PLANNING</p><h2>Set the monthly budget</h2><p>Plan expected income, payroll, and spending by category. Compare the plan with actual paid records as the month progresses.</p></div>
</header>
<div class="metric-grid finance-metrics finance-budget-metrics">
    <article class="metric"><span>Income budget</span><strong><?= money($summary['expected_income_budget']) ?></strong><small><?= money($summary['income_received']) ?> actually received</small></article>
    <article class="metric"><span>Salary budget</span><strong><?= money($summary['salary_budget']) ?></strong><small><?= money($summary['salary_paid'] + $summary['salary_outstanding']) ?> paid or pending</small></article>
    <article class="metric"><span>Expense budget</span><strong><?= money($summary['expense_budget']) ?></strong><small><?= money($summary['expenses_paid'] + $summary['expenses_pending']) ?> paid or pending</small></article>
    <article class="metric"><span>Expected appointments</span><strong><?= money($summary['expected_appointments']) ?></strong><small>Not counted as received income</small></article>
</div>
<form method="post" class="admin-form finance-budget-form">
    <input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>"><input type="hidden" name="action" value="save_budget"><input type="hidden" name="salary_month" value="<?= escape($month) ?>">
    <section class="admin-section">
        <div class="panel-heading"><div><p class="admin-eyebrow">INCOME & PAYROLL</p><h2>Main monthly targets</h2></div></div>
        <div class="form-two"><label>Expected income budget (₵)<input type="number" name="expected_income" min="0" max="99999999.99" step="0.01" required value="<?= escape((string) $budget['expected_income']) ?>"><small class="help-text">Your income goal for the month; this does not change actual payment records.</small></label><label>Salary budget (₵)<input type="number" name="salary_budget" min="0" max="99999999.99" step="0.01" required value="<?= escape((string) $budget['salary_budget']) ?>"><small class="help-text">Initially based on active staff salary defaults; you can adjust it for this month.</small></label></div>
    </section>
    <section class="admin-section">
        <div class="panel-heading"><div><p class="admin-eyebrow">EXPENSE LIMITS</p><h2>Category budgets</h2><p>Enter zero for categories you do not plan to use.</p></div></div>
        <div class="finance-budget-grid">
            <?php foreach ($categories as $key => $label): $actual = 0.0; foreach ($summary['expense_categories'] as $row) { if ($row['category'] === $key) { $actual = (float) $row['paid']; break; } } ?>
                <label><span><?= escape($label) ?> (₵)</span><input type="number" name="expense_budgets[<?= $key ?>]" min="0" max="99999999.99" step="0.01" required value="<?= escape((string) ($budget['expense_budgets'][$key] ?? '0.00')) ?>"><small class="help-text"><?= money($actual) ?> paid this month</small></label>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="admin-section"><label>Monthly budget notes<textarea name="notes" rows="4" maxlength="1000" placeholder="Plans, upcoming purchases, or items to watch"><?= escape((string) $budget['notes']) ?></textarea></label></section>
    <button class="admin-button" type="submit">Save monthly budget</button>
</form>

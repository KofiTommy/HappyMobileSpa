<?php require __DIR__ . '/finance-tabs.php'; ?>
<header class="page-lead">
    <div><p class="admin-eyebrow">MONTHLY PAYROLL</p><h2>Staff salaries</h2><p>Generate pending salary records from each active worker’s usual monthly salary, then add bonuses, deductions, and payment details.</p></div>
</header>
<section class="admin-section finance-generate-panel">
    <div><h2>Prepare this month’s payroll</h2><p>Existing records are never duplicated or overwritten, including cancelled records.</p></div>
    <form method="post" class="inline-actions"><input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>"><input type="hidden" name="action" value="generate_salaries"><input type="hidden" name="salary_month" value="<?= escape($month) ?>"><button class="admin-button" type="submit">Generate monthly salaries</button><a class="small-button" href="staff.php">Set staff salary defaults</a></form>
</section>
<div class="management-grid finance-entry-grid">
<section class="admin-section">
    <div class="panel-heading"><div><p class="admin-eyebrow">PAYROLL RECORDS</p><h2><?= count($salaries) ?> salary <?= count($salaries) === 1 ? 'record' : 'records' ?></h2></div></div>
    <div class="record-list">
        <?php if (!$salaries): ?><div class="admin-empty"><h3>No salary records for this month</h3><p>Generate salaries from staff defaults or add one manually with the form.</p></div><?php endif; ?>
        <?php foreach ($salaries as $salary): ?>
            <article class="record-card finance-record">
                <div class="finance-record-head"><div><p class="admin-eyebrow">STAFF SALARY · #<?= (int) $salary['id'] ?></p><h3><?= escape($salary['staff_name']) ?></h3></div><span class="status finance-status-<?= escape($salary['status']) ?>"><?= escape($salary['status']) ?></span></div>
                <strong class="finance-record-amount"><?= money($salary['net_salary']) ?></strong>
                <dl class="finance-detail-grid"><div><dt>Basic salary</dt><dd><?= money($salary['basic_salary']) ?></dd></div><div><dt>Bonus</dt><dd><?= money($salary['bonus']) ?></dd></div><div><dt>Deduction</dt><dd>−<?= money($salary['deduction']) ?></dd></div><div><dt>Payment date</dt><dd><?= $salary['paid_at'] ? escape((new DateTimeImmutable($salary['paid_at']))->format('j M Y')) : 'Not paid' ?></dd></div></dl>
                <?php if ($salary['payment_method'] || $salary['reference']): ?><p class="finance-meta"><?= escape($paymentMethods[$salary['payment_method']] ?? $salary['payment_method']) ?><?= $salary['reference'] ? ' · ' . escape($salary['reference']) : '' ?></p><?php endif; ?>
                <?php if ($salary['notes']): ?><p class="finance-notes"><?= nl2br(escape($salary['notes'])) ?></p><?php endif; ?>
                <?php if ($salary['status'] === 'cancelled'): ?><p class="finance-cancelled-note">Cancelled on <?= escape((new DateTimeImmutable($salary['cancelled_at'], new DateTimeZone('UTC')))->setTimezone(business_timezone())->format('j M Y, H:i')) ?>. Excluded from all totals.</p><?php endif; ?>
                <?php if (!$salary['staff_active']): ?><p class="finance-meta">This staff profile is inactive; the salary history is preserved.</p><?php endif; ?>
                <div class="inline-actions"><a class="small-button" href="finance.php?view=salaries&amp;month=<?= rawurlencode($month) ?>&amp;edit_salary=<?= (int) $salary['id'] ?>#salary-form"><?= $salary['status'] === 'cancelled' ? 'Restore / edit' : 'Edit salary' ?></a>
                <?php if ($salary['status'] !== 'cancelled'): ?><form method="post" data-confirm="Cancel this salary record? It will remain in the audit history but will no longer count in totals."><input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>"><input type="hidden" name="action" value="cancel_salary"><input type="hidden" name="month" value="<?= escape($month) ?>"><input type="hidden" name="id" value="<?= (int) $salary['id'] ?>"><button class="small-button danger-button" type="submit">Cancel record</button></form><?php endif; ?></div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<section class="admin-section form-panel" id="salary-form">
    <div class="panel-heading"><div><p class="admin-eyebrow"><?= !empty($salaryForm['id']) ? 'EDIT SALARY' : 'MANUAL ENTRY' ?></p><h2><?= !empty($salaryForm['id']) ? 'Update salary record' : 'Add a salary record' ?></h2></div><?php if (!empty($salaryForm['id'])): ?><a class="small-button" href="finance.php?view=salaries&amp;month=<?= rawurlencode($month) ?>">Clear</a><?php endif; ?></div>
    <form method="post" class="admin-form">
        <input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>"><input type="hidden" name="action" value="save_salary"><input type="hidden" name="id" value="<?= escape((string) ($salaryForm['id'] ?? '')) ?>">
        <label>Staff member<select name="staff_id" required><option value="">Choose worker</option><?php foreach ($staff as $person): ?><option value="<?= (int) $person['id'] ?>" <?= (string) ($salaryForm['staff_id'] ?? '') === (string) $person['id'] ? 'selected' : '' ?>><?= escape($person['name']) ?><?= $person['active'] ? '' : ' (inactive)' ?></option><?php endforeach; ?></select></label>
        <label>Salary month<input type="month" name="salary_month" required value="<?= escape((string) ($salaryForm['salary_month'] ?? $month)) ?>"><small class="help-text">One salary record is allowed per worker per month.</small></label>
        <label>Basic salary (₵)<input type="number" name="basic_salary" min="0" max="99999999.99" step="0.01" required value="<?= escape((string) ($salaryForm['basic_salary'] ?? '0.00')) ?>"></label>
        <div class="form-two"><label>Bonus (₵)<input type="number" name="bonus" min="0" max="99999999.99" step="0.01" required value="<?= escape((string) ($salaryForm['bonus'] ?? '0.00')) ?>"></label><label>Deduction (₵)<input type="number" name="deduction" min="0" max="99999999.99" step="0.01" required value="<?= escape((string) ($salaryForm['deduction'] ?? '0.00')) ?>"></label></div>
        <p class="finance-form-hint">Net salary = basic salary + bonus − deduction. The calculated net must be greater than zero.</p>
        <div class="form-two"><label>Status<select name="status" required><option value="pending" <?= ($salaryForm['status'] ?? 'pending') === 'pending' ? 'selected' : '' ?>>Pending</option><option value="paid" <?= ($salaryForm['status'] ?? '') === 'paid' ? 'selected' : '' ?>>Paid</option></select></label><label>Payment date<input type="date" name="paid_at" value="<?= escape((string) ($salaryForm['paid_at'] ?? $today)) ?>"><small class="help-text">Required when paid.</small></label></div>
        <label>Payment method<select name="payment_method"><option value="">Choose when paid</option><?php foreach ($paymentMethods as $key => $label): ?><option value="<?= $key ?>" <?= ($salaryForm['payment_method'] ?? '') === $key ? 'selected' : '' ?>><?= escape($label) ?></option><?php endforeach; ?></select></label>
        <label>Transaction reference<input name="reference" maxlength="120" value="<?= escape((string) ($salaryForm['reference'] ?? '')) ?>"></label>
        <label>Notes<textarea name="notes" rows="3" maxlength="1000"><?= escape((string) ($salaryForm['notes'] ?? '')) ?></textarea></label>
        <button class="admin-button" type="submit"><?= ($salaryForm['status'] ?? '') === 'cancelled' ? 'Restore and save salary' : 'Save salary record' ?></button>
    </form>
</section>
</div>

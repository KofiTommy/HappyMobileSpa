<?php require __DIR__ . '/finance-tabs.php'; ?>
<header class="page-lead">
    <div><p class="admin-eyebrow">OPERATING COSTS</p><h2>Other business expenses</h2><p>Track supplies, transport, rent, utilities, marketing, fees, and other costs. Attach a receipt when one is available.</p></div>
</header>
<div class="management-grid finance-entry-grid">
<section class="admin-section">
    <div class="panel-heading"><div><p class="admin-eyebrow">EXPENSE RECORDS</p><h2><?= count($expenses) ?> expense <?= count($expenses) === 1 ? 'record' : 'records' ?></h2></div></div>
    <div class="record-list">
        <?php if (!$expenses): ?><div class="admin-empty"><h3>No expenses for this month</h3><p>Use the form to record a business cost. Paid and pending costs are reported separately.</p></div><?php endif; ?>
        <?php foreach ($expenses as $expense): ?>
            <article class="record-card finance-record">
                <div class="finance-record-head"><div><p class="admin-eyebrow"><?= escape($categories[$expense['category']] ?? $expense['category']) ?> · #<?= (int) $expense['id'] ?></p><h3><?= escape($expense['description']) ?></h3></div><span class="status finance-status-<?= escape($expense['status']) ?>"><?= escape($expense['status']) ?></span></div>
                <strong class="finance-record-amount"><?= money($expense['amount']) ?></strong>
                <dl class="finance-detail-grid"><div><dt>Expense date</dt><dd><?= escape((new DateTimeImmutable($expense['expense_date']))->format('j M Y')) ?></dd></div><div><dt>Vendor / payee</dt><dd><?= escape($expense['vendor'] ?: 'Not recorded') ?></dd></div><div><dt>Payment date</dt><dd><?= $expense['paid_at'] ? escape((new DateTimeImmutable($expense['paid_at']))->format('j M Y')) : 'Not paid' ?></dd></div><div><dt>Payment method</dt><dd><?= escape($paymentMethods[$expense['payment_method']] ?? 'Not recorded') ?></dd></div></dl>
                <?php if ($expense['reference']): ?><p class="finance-meta">Reference: <?= escape($expense['reference']) ?></p><?php endif; ?>
                <?php if ($expense['notes']): ?><p class="finance-notes"><?= nl2br(escape($expense['notes'])) ?></p><?php endif; ?>
                <?php if ($expense['status'] === 'cancelled'): ?><p class="finance-cancelled-note">Cancelled on <?= escape((new DateTimeImmutable($expense['cancelled_at'], new DateTimeZone('UTC')))->setTimezone(business_timezone())->format('j M Y, H:i')) ?>. Excluded from all totals.</p><?php endif; ?>
                <div class="inline-actions"><a class="small-button" href="finance.php?view=expenses&amp;month=<?= rawurlencode($month) ?>&amp;edit_expense=<?= (int) $expense['id'] ?>#expense-form"><?= $expense['status'] === 'cancelled' ? 'Restore / edit' : 'Edit expense' ?></a>
                <?php if ($expense['receipt_path']): ?><a class="small-button" href="expense-receipt.php?id=<?= (int) $expense['id'] ?>" target="_blank" rel="noopener">View receipt ↗</a><?php endif; ?>
                <?php if ($expense['status'] !== 'cancelled'): ?><form method="post" data-confirm="Cancel this expense record? It will remain in the audit history but will no longer count in totals."><input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>"><input type="hidden" name="action" value="cancel_expense"><input type="hidden" name="month" value="<?= escape($month) ?>"><input type="hidden" name="id" value="<?= (int) $expense['id'] ?>"><button class="small-button danger-button" type="submit">Cancel record</button></form><?php endif; ?></div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<section class="admin-section form-panel" id="expense-form">
    <div class="panel-heading"><div><p class="admin-eyebrow"><?= !empty($expenseForm['id']) ? 'EDIT EXPENSE' : 'NEW EXPENSE' ?></p><h2><?= !empty($expenseForm['id']) ? 'Update expense record' : 'Record an expense' ?></h2></div><?php if (!empty($expenseForm['id'])): ?><a class="small-button" href="finance.php?view=expenses&amp;month=<?= rawurlencode($month) ?>">Clear</a><?php endif; ?></div>
    <form method="post" class="admin-form" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>"><input type="hidden" name="action" value="save_expense"><input type="hidden" name="id" value="<?= escape((string) ($expenseForm['id'] ?? '')) ?>">
        <label>Description<input name="description" minlength="2" maxlength="500" required value="<?= escape((string) ($expenseForm['description'] ?? '')) ?>" placeholder="e.g. Massage oils and linen"></label>
        <div class="form-two"><label>Expense date<input type="date" name="expense_date" required value="<?= escape((string) ($expenseForm['expense_date'] ?? $today)) ?>"></label><label>Amount (₵)<input type="number" name="amount" min="0.01" max="99999999.99" step="0.01" required value="<?= escape((string) ($expenseForm['amount'] ?? '')) ?>"></label></div>
        <div class="form-two"><label>Category<select name="category" required><?php foreach ($categories as $key => $label): ?><option value="<?= $key ?>" <?= ($expenseForm['category'] ?? 'supplies') === $key ? 'selected' : '' ?>><?= escape($label) ?></option><?php endforeach; ?></select></label><label>Status<select name="status" required><option value="pending" <?= ($expenseForm['status'] ?? 'pending') === 'pending' ? 'selected' : '' ?>>Pending</option><option value="paid" <?= ($expenseForm['status'] ?? '') === 'paid' ? 'selected' : '' ?>>Paid</option></select></label></div>
        <label>Vendor or payee<input name="vendor" maxlength="160" value="<?= escape((string) ($expenseForm['vendor'] ?? '')) ?>"></label>
        <div class="form-two"><label>Payment date<input type="date" name="paid_at" value="<?= escape((string) ($expenseForm['paid_at'] ?? $today)) ?>"><small class="help-text">Required when paid.</small></label><label>Payment method<select name="payment_method"><option value="">Choose when paid</option><?php foreach ($paymentMethods as $key => $label): ?><option value="<?= $key ?>" <?= ($expenseForm['payment_method'] ?? '') === $key ? 'selected' : '' ?>><?= escape($label) ?></option><?php endforeach; ?></select></label></div>
        <label>Transaction reference<input name="reference" maxlength="120" value="<?= escape((string) ($expenseForm['reference'] ?? '')) ?>"></label>
        <label>Receipt upload<input type="file" name="receipt" accept="image/jpeg,image/png,image/webp,application/pdf"><small class="help-text">JPG, PNG, WebP, or PDF. Maximum 5 MB and 4096 × 4096 pixels for images. Receipts are private and visible only to signed-in administrators.</small></label>
        <?php if (!empty($expenseForm['receipt_path'])): ?><label class="check-row"><input type="checkbox" name="remove_receipt" value="1"> Remove current receipt (<?= escape((string) ($expenseForm['receipt_name'] ?? 'receipt')) ?>)</label><?php endif; ?>
        <label>Notes<textarea name="notes" rows="3" maxlength="1000"><?= escape((string) ($expenseForm['notes'] ?? '')) ?></textarea></label>
        <button class="admin-button" type="submit"><?= ($expenseForm['status'] ?? '') === 'cancelled' ? 'Restore and save expense' : 'Save expense record' ?></button>
    </form>
</section>
</div>

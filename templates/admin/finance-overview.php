<?php require __DIR__ . '/finance-tabs.php'; ?>
<header class="page-lead">
    <div><p class="admin-eyebrow">BUSINESS FINANCE</p><h2>Monthly financial overview</h2><p>A simple cash view of income, payroll, and operating costs. Only paid records count as money received or spent.</p></div>
    <a class="admin-button" href="finance.php?view=expenses&amp;month=<?= rawurlencode($month) ?>">+ Record an expense</a>
</header>
<div class="metric-grid finance-metrics">
    <article class="metric"><span>Income received</span><strong><?= money($summary['income_received']) ?></strong><small>Payments received this month</small></article>
    <article class="metric"><span>Cash remaining</span><strong class="<?= (float) $summary['remaining_cash'] < 0 ? 'finance-negative' : '' ?>"><?= money($summary['remaining_cash']) ?></strong><small>After paid salaries and expenses</small></article>
    <article class="metric"><span>Salaries paid</span><strong><?= money($summary['salary_paid']) ?></strong><small><?= money($summary['salary_outstanding']) ?> still pending</small></article>
    <article class="metric"><span>Other expenses paid</span><strong><?= money($summary['expenses_paid']) ?></strong><small><?= money($summary['expenses_pending']) ?> still pending</small></article>
</div>
<div class="finance-summary-grid">
    <section class="admin-section">
        <div class="panel-heading"><div><p class="admin-eyebrow">CASH POSITION</p><h2>Income and commitments</h2></div></div>
        <dl class="finance-cash-list">
            <div><dt>Actual income received</dt><dd><?= money($summary['income_received']) ?></dd></div>
            <div><dt>Paid salaries</dt><dd>−<?= money($summary['salary_paid']) ?></dd></div>
            <div><dt>Paid other expenses</dt><dd>−<?= money($summary['expenses_paid']) ?></dd></div>
            <div class="finance-cash-total"><dt>Cash remaining</dt><dd class="<?= (float) $summary['remaining_cash'] < 0 ? 'finance-negative' : '' ?>"><?= money($summary['remaining_cash']) ?></dd></div>
            <div><dt>Pending salary commitments</dt><dd><?= money($summary['salary_outstanding']) ?></dd></div>
            <div><dt>Pending expense commitments</dt><dd><?= money($summary['expenses_pending']) ?></dd></div>
        </dl>
        <p class="finance-note"><strong><?= money($summary['expected_appointments']) ?></strong> is attached to pending or confirmed appointments this month. It is expected appointment value, not cash received.</p>
    </section>
    <section class="admin-section">
        <div class="panel-heading"><div><p class="admin-eyebrow">MONTHLY PLAN</p><h2>Budget at a glance</h2></div><a class="small-button" href="finance.php?view=budget&amp;month=<?= rawurlencode($month) ?>">Edit budget</a></div>
        <dl class="finance-cash-list">
            <div><dt>Expected income budget</dt><dd><?= money($summary['expected_income_budget']) ?></dd></div>
            <div><dt>Salary budget</dt><dd><?= money($summary['salary_budget']) ?></dd></div>
            <div><dt>Other expense budget</dt><dd><?= money($summary['expense_budget']) ?></dd></div>
            <div><dt>Salary records</dt><dd><?= (int) $summary['salary_count'] ?></dd></div>
            <div><dt>Expense records</dt><dd><?= (int) $summary['expense_count'] ?></dd></div>
        </dl>
        <div class="inline-actions"><a class="small-button" href="finance.php?view=salaries&amp;month=<?= rawurlencode($month) ?>">Manage salaries</a><a class="small-button" href="finance.php?view=expenses&amp;month=<?= rawurlencode($month) ?>">Manage expenses</a></div>
    </section>
</div>
<section class="admin-section">
    <div class="panel-heading"><div><p class="admin-eyebrow">OPERATING COSTS</p><h2>Expense budget by category</h2><p>Actual spending includes paid expenses only.</p></div></div>
    <div class="finance-table-wrap"><table class="finance-table"><thead><tr><th scope="col">Category</th><th scope="col">Budget</th><th scope="col">Paid</th><th scope="col">Remaining</th></tr></thead><tbody>
    <?php foreach ($summary['expense_categories'] as $row): ?><tr><th scope="row"><?= escape($row['label']) ?></th><td><?= money($row['budget']) ?></td><td><?= money($row['paid']) ?></td><td class="<?= (float) $row['remaining'] < 0 ? 'finance-negative' : '' ?>"><?= money($row['remaining']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>
<section class="admin-section">
    <div class="panel-heading"><div><p class="admin-eyebrow">ACCOUNTABILITY</p><h2>Recent finance activity</h2><p>Every save, generation, cancellation, and restoration is recorded.</p></div></div>
    <?php if (!$events): ?><div class="admin-empty"><h3>No finance activity yet</h3><p>Generate salaries, add an expense, or save a budget to begin the audit history.</p></div><?php endif; ?>
    <ol class="finance-event-list">
        <?php foreach ($events as $event): $time = new DateTimeImmutable($event['created_at'], new DateTimeZone('UTC')); ?>
            <li><span class="finance-event-icon" aria-hidden="true">✓</span><div><strong><?= escape(ucfirst($event['entity_type']) . ' ' . $event['action']) ?></strong><p><?= $event['entity_id'] ? 'Record #' . (int) $event['entity_id'] . ' · ' : '' ?><?= escape($event['username'] ?: 'System') ?></p></div><time datetime="<?= escape($event['created_at']) ?>"><?= escape($time->setTimezone(business_timezone())->format('j M Y, H:i')) ?></time></li>
        <?php endforeach; ?>
    </ol>
</section>

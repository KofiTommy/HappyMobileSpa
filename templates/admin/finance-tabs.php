<?php
$monthDate = DateTimeImmutable::createFromFormat('!Y-m', $month, business_timezone());
$monthLabel = $monthDate ? $monthDate->format('F Y') : $month;
?>
<?php if ($error): ?><div class="admin-error" role="alert"><?= escape($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="admin-success" role="status"><?= escape($success) ?></div><?php endif; ?>
<div class="finance-toolbar">
    <nav class="agenda-tabs finance-tabs" aria-label="Finance sections">
        <?php foreach (['overview' => 'Overview', 'salaries' => 'Salaries', 'expenses' => 'Expenses', 'budget' => 'Monthly budget'] as $key => $label): ?>
            <a href="finance.php?view=<?= $key ?>&amp;month=<?= rawurlencode($month) ?>" <?= $view === $key ? 'aria-current="page"' : '' ?>><?= $label ?></a>
        <?php endforeach; ?>
    </nav>
    <form method="get" class="finance-month-form">
        <input type="hidden" name="view" value="<?= escape($view) ?>">
        <label for="finance-month">Reporting month</label>
        <div><input id="finance-month" type="month" name="month" required value="<?= escape($month) ?>"><button class="small-button" type="submit">View month</button></div>
    </form>
    <?php if ($view !== 'budget'): ?><a class="small-button finance-export" href="finance-export.php?view=<?= escape($view) ?>&amp;month=<?= rawurlencode($month) ?>">Export <?= $view === 'overview' ? 'summary' : $view ?> CSV</a><?php endif; ?>
</div>
<p class="finance-period">Showing records for <strong><?= escape($monthLabel) ?></strong>. Cancelled records stay in the audit history and are excluded from totals.</p>

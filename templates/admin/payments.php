<?php
$listing = $listing ?? ['payments'=>$payments, 'total'=>count($payments), 'page'=>1, 'pages'=>1, 'search'=>''];
$payments = $listing['payments'];
?>
<?php if ($error): ?><div class="admin-error" role="alert"><?= escape($error) ?></div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="admin-success" role="status">Payment settings or verification updated.</div><?php endif; ?>
<header class="page-lead">
    <div><p class="admin-eyebrow">RECEPTION WORKSPACE</p><h2>Paystack mobile money</h2><p>Customers can pay their full appointment total in Ghana cedis through Paystack mobile money checkout. Pay-at-spa remains available. Appointments are saved before checkout; unpaid bookings remain confirmed.</p></div>
</header>
<section class="admin-section">
<p><span class="status"><?= $paystackConfig['enabled'] ? (str_starts_with($paystackConfig['secret_key'],'sk_live_') ? 'Live payments enabled' : 'Test mode — payments do not collect real money') : 'Online payments disabled' ?></span></p>
<form method="post" class="admin-form form-section"><input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>">
<label>Website URL<input type="url" name="site_url" required placeholder="https://www.happymobilespa.com" value="<?= escape($paystackConfig['site_url']) ?>"><small class="help-text">Include the project subfolder if applicable. Live payments require HTTPS.</small></label>
<label>Paystack secret key<input type="password" name="secret_key" autocomplete="new-password" placeholder="sk_test_… or sk_live_…"><small class="help-text">Leave blank to keep your saved key. Your secret key is never displayed or sent to the browser.</small></label>
<label class="check-row"><input type="checkbox" name="enabled" value="1" <?= $paystackConfig['enabled'] ? 'checked' : '' ?>> Enable mobile money checkout</label><button class="admin-button">Save settings</button></form>
<?php if ($paystackConfig['site_url']): ?><p>Set this webhook URL in your Paystack dashboard:</p><code class="payment-webhook-url"><?= escape($paystackConfig['site_url'] . '/api/paystack-webhook.php') ?></code><?php endif; ?>
<p>Enable the mobile money channel in your Ghana Paystack account. The webhook must be publicly accessible; localhost cannot receive Paystack notifications. Start with your test key before accepting live payments.</p></section>
<section class="admin-section"><div class="panel-heading"><div><h2>Recent online payments</h2><p>Cancelled appointments with payments, or payments received after an appointment changed, may require review. Refunds must be handled in Paystack; cancelling a booking does not refund it.</p></div></div>
<form method="get" class="filter-form"><label class="filter-search">Search payments<input type="search" name="search" value="<?= escape($listing['search']) ?>" placeholder="Payment or booking reference, client, email, phone or status"></label><button class="admin-button">Search</button><?php if ($listing['search'] !== ''): ?><a class="clear-filter" href="payments.php">Clear</a><?php endif; ?></form>
<p><?= $listing['total'] ?> matching payments</p>
<div class="record-list">
<?php if (!$payments): ?><div class="admin-empty"><h3><?= $listing['search'] === '' ? 'No online payments started yet' : 'No matching payments' ?></h3><p>Change the search or check again after new activity.</p></div><?php endif; ?>
<?php foreach ($payments as $payment): ?><article class="management-row record-card"><div><h3><a href="bookings.php?id=<?= $payment['booking_id'] ?>"><?= escape($payment['booking_reference']) ?></a> · <?= money((int)$payment['amount_subunit']/100) ?></h3><?php if (isset($payment['customer_name'])): ?><p><?= escape($payment['customer_name']) ?> · <?= escape($payment['customer_email']) ?></p><?php endif; ?><p><span class="status"><?= escape(str_replace('_',' ',ucfirst($payment['state']))) ?></span> · <?= escape(strtoupper($payment['mode'])) ?></p><small><?= escape($payment['reference']) ?></small>
<?php if ($payment['state'] === 'paid_review'): ?><p role="status">Payment received; review the booking and arrange any refund directly in Paystack.</p><?php endif; ?>
<form method="post" class="inline-actions"><input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>"><input type="hidden" name="reference" value="<?= escape($payment['reference']) ?>"><button class="small-button" name="action" value="verify">Verify with Paystack</button></form></div></article><?php endforeach; ?></div>
<?php $pagination = $listing; $paginationParams = ['search'=>$listing['search']]; $paginationLabel = 'Payment pages'; require __DIR__ . '/pagination.php'; ?></section>

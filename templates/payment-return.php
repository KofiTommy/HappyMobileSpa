<section class="manage-shell"><p class="eyebrow">Paystack</p><h1>Payment<br><em>status.</em></h1>
<?php if ($error): ?><div class="form-error status-banner status-error" role="alert"><?= escape($error) ?></div>
<?php elseif ($state === 'paid'): ?><div class="status-banner status-success"><h2>Payment verified</h2><p>Thank you. Your appointment payment has been recorded.</p></div>
<?php elseif ($state === 'paid_review'): ?><div class="status-banner status-pending"><h2>Payment received — contact the spa</h2><p>Your payment was received, but the appointment needs review. Contact the spa to confirm arrangements or discuss a refund.</p></div>
<?php else: ?><div class="status-banner status-pending"><h2>Awaiting confirmation</h2><p>If you approved payment on your phone, allow a little time for confirmation. Check the payment status from your appointment before paying again.</p></div><?php endif; ?>
<a class="button" href="manage.php">View my appointment</a></section>

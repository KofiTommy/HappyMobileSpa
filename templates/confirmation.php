<section class="confirmation-shell">
<?php if (!$booking): ?>
    <div class="confirmation-card"><p class="eyebrow">Confirmation unavailable</p><h1>We could not show this receipt.</h1><p>For privacy, confirmations are only shown immediately after booking in this browser. You can still retrieve your appointment securely.</p><a class="button" href="manage.php">Find my booking</a></div>
<?php else: $local = (new DateTimeImmutable($booking['slot_start'], new DateTimeZone('UTC')))->setTimezone(business_timezone()); ?>
    <div class="confirmation-mark" aria-hidden="true">✓</div>
    <p class="eyebrow">Appointment confirmed</p>
    <h1>Your time is<br><em>set aside.</em></h1>
    <p class="confirmation-lead">Thank you, <?= escape($booking['customer_name']) ?>. Save this reference to manage your appointment.</p>
    <div class="reference-box">
        <span>BOOKING REFERENCE</span>
        <strong data-reference><?= escape($booking['reference']) ?></strong>
        <button type="button" data-copy-reference data-reference-value="<?= escape($booking['reference']) ?>">Copy reference</button>
        <span class="sr-only" role="status" aria-live="polite" data-copy-status></span>
    </div>
    <div class="appointment-card">
        <dl class="confirmation-details">
            <div><dt>Treatment</dt><dd><?= escape($booking['service_name']) ?></dd></div>
            <div><dt>Date</dt><dd><?= escape($local->format('l, j F Y')) ?></dd></div>
            <div><dt>Time</dt><dd><?= escape($local->format('g:i A')) ?> · <?= (int) $booking['duration_min'] ?> min</dd></div>
            <div><dt>Therapist</dt><dd><?= escape($booking['staff_name']) ?></dd></div>
            <div><dt>Total</dt><dd><?= money($booking['price']) ?></dd></div>
            <div><dt>Payment</dt><dd><?= $booking['payment_status'] === 'paid' ? 'Paid' : (Paystack::ready() ? 'Unpaid — payable at the spa or by mobile money' : 'Due at the spa') ?></dd></div>
        </dl>
    </div>
    <div class="confirmation-actions appointment-actions"><?php if (Paystack::ready() && $booking['payment_status'] !== 'paid'): ?><a class="button" href="payment.php?booking=<?= escape($booking['reference']) ?>">Pay by mobile money</a><?php endif; ?><a class="button" href="manage.php?reference=<?= rawurlencode($booking['reference']) ?>">Manage booking</a><button class="button-back" type="button" onclick="window.print()">Print confirmation</button></div>
    <p class="confirmation-policy"><?= escape($booking['accepted_policy']) ?></p>
<?php endif; ?>
</section>

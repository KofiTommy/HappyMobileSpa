<?php $local = (new DateTimeImmutable($booking['slot_start'], new DateTimeZone('UTC')))->setTimezone(business_timezone()); ?>
<section class="manage-shell">
    <p class="eyebrow"><span></span> <?= escape($booking['reference']) ?></p>
    <h1>Your<br><em>appointment.</em></h1>
    <?php if ($error): ?><div class="form-error" role="alert"><?= escape($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="payment-note" role="status"><?= escape($success) ?></div><?php endif; ?>
    <div class="appointment-card">
        <dl class="confirmation-details">
            <div><dt>Status</dt><dd><?= escape(ucfirst(str_replace('_', ' ', $booking['status']))) ?></dd></div>
            <div><dt>Treatment</dt><dd><?= escape($booking['service_name']) ?></dd></div>
            <div><dt>Date &amp; time</dt><dd><?= escape($local->format('l, j F Y · g:i A')) ?></dd></div>
            <div><dt>Therapist</dt><dd><?= escape($booking['staff_name']) ?></dd></div>
            <div><dt>Total</dt><dd><?= money($booking['price']) ?> · <?= $booking['payment_status'] === 'paid' ? 'Paid' : (Paystack::ready() ? 'Unpaid' : 'Due at the spa') ?></dd></div>
        </dl>
    </div>
    <?php if (Paystack::ready() && $booking['status'] === 'confirmed' && $booking['payment_status'] !== 'paid'): ?><p><a class="button" href="payment.php?booking=<?= escape($booking['reference']) ?>">Pay by mobile money</a></p><?php endif; ?>
    <?php if ($canChange): ?>
        <div class="content-panel">
            <form class="manage-form" method="get" action="manage.php">
                <label class="field-label" for="manage_date">Check another date</label>
                <input id="manage_date" name="date" type="date" min="<?= gmdate('Y-m-d') ?>" value="<?= escape($_GET['date'] ?? $local->format('Y-m-d')) ?>" required>
                <button class="button-back" type="submit">Show available times</button>
            </form>
            <?php if ($slots): ?>
            <form class="manage-form" method="post" action="manage.php">
                <input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>"><input type="hidden" name="action" value="reschedule"><input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                <label class="field-label" for="new_start">New appointment time</label>
                <select id="new_start" name="start" required><option value="">Choose a time</option><?php foreach ($slots as $slot): ?><option value="<?= escape($slot['start']) ?>"><?= escape($slot['label']) ?></option><?php endforeach; ?></select>
                <button class="button" type="submit">Confirm new time</button>
            </form>
            <?php else: ?><p class="slot-status">No other times are available on this date.</p><?php endif; ?>
        </div>
        <section class="danger-zone" aria-labelledby="cancel-heading">
            <h2 id="cancel-heading">Cancel this appointment</h2>
            <p id="cancel-policy">This action cannot be undone. <?= $booking['payment_status'] === 'paid' ? 'Cancellation does not automatically refund a payment; contact the spa about a refund.' : escape($booking['accepted_policy']) ?></p>
            <form class="manage-form" method="post" action="manage.php" onsubmit="return confirm('Cancel this appointment? This cannot be undone.')">
                <input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>">
                <input type="hidden" name="action" value="cancel"><input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                <button class="button button-danger" type="submit" aria-describedby="cancel-policy">Cancel appointment</button>
            </form>
        </section>
    <?php elseif (in_array($booking['status'], ['pending', 'confirmed'], true)): ?>
        <?php if ($booking['payment_status'] === 'paid'): ?><p>Cancellation does not automatically refund a payment. Contact the spa about a refund.</p><?php endif; ?>
        <div class="payment-note"><strong>Need to make a change?</strong><p>This appointment is inside the online change window. Please contact the spa for help.</p></div>
    <?php endif; ?>
    <form method="post" action="manage.php" class="manage-form"><input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>"><input type="hidden" name="action" value="forget"><button class="button-back" type="submit">Finish and clear this session</button></form>
</section>

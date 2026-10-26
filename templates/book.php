<?php declare(strict_types=1); ?>
<section class="booking-shell">
    <div class="booking-heading">
        <p class="eyebrow"><span></span> Your appointment</p>
        <h1>Time for <em>you.</em></h1>
        <p>Four simple steps. No account needed. Payment is made at the spa.</p>
    </div>
    <?php if (!$selectedService): ?>
        <p class="empty-state">Online booking will be available once treatments are published.</p>
    <?php else: ?>
    <form method="post" action="book.php" class="booking-form" data-booking-form novalidate>
        <input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>">
        <ol class="booking-progress" aria-label="Booking progress">
            <li class="is-active"><span>01</span>Treatment</li><li><span>02</span>Time</li><li><span>03</span>Details</li><li><span>04</span>Review</li>
        </ol>
        <?php if ($error): ?><div class="form-error" role="alert"><?= escape($error) ?></div><?php endif; ?>
        <div class="form-alert" role="alert" aria-live="polite" data-form-alert></div>

        <fieldset class="booking-step is-active" data-step="0">
            <legend><span>01</span> Choose your treatment</legend>
            <div class="booking-services">
            <?php foreach ($services as $service): ?>
                <label class="booking-service">
                    <input type="radio" name="service_id" value="<?= $service['id'] ?>" <?= $selectedService['id'] === $service['id'] ? 'checked' : '' ?> required>
                    <span><strong><?= escape($service['name']) ?></strong><small><?= escape($service['description']) ?></small><span class="service-facts"><?= $service['duration_min'] ?> min · <?= money($service['price']) ?></span></span>
                </label>
            <?php endforeach; ?>
            </div>
            <label class="field-label" for="staff_id">Therapist preference</label>
            <select id="staff_id" name="staff_id" data-staff-select>
                <option value="">Any available therapist</option>
                <?php foreach ($staff as $person): ?><option value="<?= $person['id'] ?>" <?= (string) ($old['staff_id'] ?? '') === (string) $person['id'] ? 'selected' : '' ?>><?= escape($person['name']) ?></option><?php endforeach; ?>
            </select>
            <div class="step-actions"><button class="button" type="button" data-next>Choose a time <span aria-hidden="true">→</span></button></div>
        </fieldset>

        <fieldset class="booking-step" data-step="1">
            <legend><span>02</span> Find your time</legend>
            <label class="field-label" for="appointment_date">Appointment date</label>
            <input id="appointment_date" type="date" min="<?= gmdate('Y-m-d') ?>" required data-date-input>
            <input type="hidden" name="start" value="<?= escape($old['start'] ?? '') ?>" data-start-input>
            <div class="slot-status" aria-live="polite" data-slot-status>Select a date to see available times.</div>
            <div class="slot-grid" data-slots></div>
            <div class="step-actions"><button class="button-back" type="button" data-back>← Back</button><button class="button" type="button" data-next>Your details <span aria-hidden="true">→</span></button></div>
        </fieldset>

        <fieldset class="booking-step" data-step="2">
            <legend><span>03</span> Tell us who is coming</legend>
            <div class="field-grid">
                <div><label class="field-label" for="name">Full name</label><input id="name" name="name" autocomplete="name" maxlength="160" value="<?= escape($old['name'] ?? '') ?>" required></div>
                <div><label class="field-label" for="phone">Ghana phone number</label><input id="phone" name="phone" type="tel" autocomplete="tel" placeholder="024 000 0000" value="<?= escape($old['phone'] ?? '') ?>" required></div>
                <div class="field-wide"><label class="field-label" for="email">Email <span>Optional</span></label><input id="email" name="email" type="email" autocomplete="email" value="<?= escape($old['email'] ?? '') ?>"></div>
                <div class="field-wide"><label class="field-label" for="notes">Anything your therapist should know? <span>Optional</span></label><textarea id="notes" name="notes" rows="4" maxlength="1000" placeholder="Pressure preference, sensitive areas, pregnancy, or other comfort notes"><?= escape($old['notes'] ?? '') ?></textarea></div>
            </div>
            <div class="step-actions"><button class="button-back" type="button" data-back>← Back</button><button class="button" type="button" data-next>Review booking <span aria-hidden="true">→</span></button></div>
        </fieldset>

        <fieldset class="booking-step" data-step="3">
            <legend><span>04</span> Review and confirm</legend>
            <dl class="booking-review" data-review></dl>
            <div class="payment-note"><strong>Payment at the spa</strong><p>No online payment is required today. Your appointment total is payable when you visit.</p></div>
            <label class="policy-check"><input type="checkbox" name="policy_accepted" value="1" required><span>I accept the cancellation policy: <?= escape($settings['cancellation_policy'] ?? 'Contact the spa before changing your appointment.') ?></span></label>
            <div class="step-actions"><button class="button-back" type="button" data-back>← Back</button><button class="button" type="submit">Confirm appointment <span aria-hidden="true">✓</span></button></div>
        </fieldset>
    </form>
    <?php endif; ?>
</section>
<script src="assets/js/booking.js" defer></script>

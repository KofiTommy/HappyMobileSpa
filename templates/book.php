<?php declare(strict_types=1);
$availableSlots = $availableSlots ?? [];
$availabilityError = $availabilityError ?? '';
?>
<section class="booking-shell">
    <div class="booking-heading">
        <p class="eyebrow"><span></span> Your appointment</p>
        <h1>Time for <em>you.</em></h1>
        <p>Four simple steps. No account needed. <?= Paystack::ready() ? 'Pay by mobile money or at the spa.' : 'Payment is made at the spa.' ?></p>
    </div>
    <?php if (!$selectedService): ?>
        <p class="empty-state">Online booking will be available once treatments are published.</p>
    <?php else: ?>
    <form method="post" action="book.php" class="booking-form" data-booking-form novalidate>
        <input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>">
        <div class="booking-layout">
            <div class="booking-main">
                <ol class="booking-progress" aria-label="Booking progress">
                    <li class="is-active"><span>01</span>Treatment</li><li><span>02</span>Time</li><li><span>03</span>Details</li><li><span>04</span>Review</li>
                </ol>
                <?php if ($error): ?><div class="form-error" role="alert"><?= escape($error) ?></div><?php endif; ?>
                <div class="form-alert" role="alert" aria-live="polite" data-form-alert></div>

                <fieldset class="booking-step is-active" data-step="0">
                    <legend tabindex="-1"><span>01</span> Choose your treatment</legend>
                    <div class="booking-services">
                    <?php foreach ($services as $service): ?>
                        <label class="booking-service">
                            <input type="radio" name="service_id" value="<?= $service['id'] ?>" <?= $selectedService['id'] === $service['id'] ? 'checked' : '' ?> data-service-label="<?= escape($service['name']) ?>" data-service-duration="<?= $service['duration_min'] ?>" data-service-price="<?= escape(money($service['price'])) ?>" required>
                            <span><strong><?= escape($service['name']) ?></strong><small><?= escape($service['description']) ?></small><span class="service-facts"><?= $service['duration_min'] ?> min · <?= money($service['price']) ?></span></span>
                        </label>
                    <?php endforeach; ?>
                    </div>
                    <label class="field-label" for="staff_id">Therapist preference</label>
                    <select id="staff_id" name="staff_id" data-staff-select>
                        <option value="">Any available therapist</option>
                        <?php foreach ($staff as $person): ?><option value="<?= $person['id'] ?>" <?= (string) ($old['staff_id'] ?? '') === (string) $person['id'] ? 'selected' : '' ?>><?= escape($person['name']) ?></option><?php endforeach; ?>
                    </select>
                    <div class="therapist-cards" data-therapist-cards aria-label="Choose your therapist"></div>
                    <script type="application/json" data-therapist-profiles><?= json_encode(array_map(static fn(array $person): array => ['id'=>(int)$person['id'], 'name'=>$person['name'], 'photo'=>$person['photo'], 'biography'=>$person['biography'], 'service_ids'=>$person['service_ids']], array_values(array_filter(Catalog::adminStaff(), static fn(array $person): bool => (bool)$person['active']))), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
                    <div class="step-actions"><button class="button" type="button" data-next>Choose a time <span aria-hidden="true">→</span></button></div>
                </fieldset>

                <fieldset class="booking-step" data-step="1">
                    <legend tabindex="-1"><span>02</span> Find your time</legend>
                    <label class="field-label" for="appointment_date">Appointment date</label>
                    <input id="appointment_date" name="appointment_date" type="date" min="<?= gmdate('Y-m-d') ?>" value="<?= escape($old['appointment_date'] ?? '') ?>" required data-date-input>
                    <input type="hidden" name="start" value="<?= escape($old['start'] ?? '') ?>" data-start-input>
                    <div class="slot-status" aria-live="polite" data-slot-status><?php if ($availabilityError): ?><?= escape($availabilityError) ?><?php elseif (($old['appointment_date'] ?? '') !== ''): ?><?php if ($availableSlots): ?><?= count($availableSlots) ?> available <?= count($availableSlots) === 1 ? 'time' : 'times' ?> · Ghana time<?php else: ?>No times are available on this date. Try another day.<?php endif; ?><?php else: ?>Select a date to see available times.<?php endif; ?></div>
                    <div class="slot-grid" data-slots>
                        <?php foreach ($availableSlots as $slot): ?>
                            <label class="time-slot"><input type="radio" name="slot_choice" value="<?= escape($slot['start']) ?>" data-time-label="<?= escape($slot['label']) ?>" <?= ($old['slot_choice'] ?? '') === $slot['start'] ? 'checked' : '' ?> required><span><?= escape($slot['label']) ?><small><?= escape($slot['staff_name']) ?></small></span></label>
                        <?php endforeach; ?>
                    </div>
                    <button class="button-back" type="submit" name="refresh_availability" value="1" formnovalidate>Show available times</button>
                    <div class="step-actions"><button class="button-back" type="button" data-back>← Back</button><button class="button" type="button" data-next>Your details <span aria-hidden="true">→</span></button></div>
                </fieldset>

                <fieldset class="booking-step" data-step="2">
                    <legend tabindex="-1"><span>03</span> Tell us who is coming</legend>
                    <div class="field-grid">
                        <div><label class="field-label" for="name">Full name</label><input id="name" name="name" autocomplete="name" maxlength="160" value="<?= escape($old['name'] ?? '') ?>" required></div>
                        <div><label class="field-label" for="phone">Ghana phone number</label><input id="phone" name="phone" type="tel" autocomplete="tel" placeholder="024 000 0000" value="<?= escape($old['phone'] ?? '') ?>" required><?php if (SmsConfig::ready()): ?><small>We use this number for your booking confirmation and appointment reminder by SMS.</small><?php endif; ?></div>
                        <div class="field-wide"><label class="field-label" for="email">Email <span><?= Paystack::ready() ? 'Required for mobile money' : 'Optional' ?></span></label><input id="email" name="email" type="email" autocomplete="email" value="<?= escape($old['email'] ?? '') ?>"></div>
                        <div class="field-wide"><label class="field-label" for="notes">Anything your therapist should know? <span>Optional</span></label><textarea id="notes" name="notes" rows="4" maxlength="1000" placeholder="Pressure preference, sensitive areas, pregnancy, or other comfort notes"><?= escape($old['notes'] ?? '') ?></textarea></div>
                    </div>
                    <div class="step-actions"><button class="button-back" type="button" data-back>← Back</button><button class="button" type="button" data-next>Review booking <span aria-hidden="true">→</span></button></div>
                </fieldset>

                <fieldset class="booking-step" data-step="3">
                    <legend tabindex="-1"><span>04</span> Review and confirm</legend>
                    <dl class="booking-review" data-review></dl>
                    <div class="payment-note"><strong>How would you like to pay?</strong>
                    <?php if (Paystack::ready()): ?><label class="policy-check"><input type="radio" name="payment_choice" value="mobile_money" <?= ($old['payment_choice'] ?? '') === 'mobile_money' ? 'checked' : '' ?>><span>Mobile money via Paystack — MTN, ATMoney, or Telecel. An email address is required. You will authorize payment through Paystack after your appointment is saved.</span></label><?php endif; ?>
                    <label class="policy-check"><input type="radio" name="payment_choice" value="spa" <?= ($old['payment_choice'] ?? 'spa') !== 'mobile_money' || !Paystack::ready() ? 'checked' : '' ?>><span>Pay at the spa</span></label></div>
                    <label class="policy-check"><input type="checkbox" name="policy_accepted" value="1" required><span>I accept the cancellation policy: <?= escape($settings['cancellation_policy'] ?? 'Contact the spa before changing your appointment.') ?></span></label>
                    <div class="step-actions"><button class="button-back" type="button" data-back>← Back</button><button class="button" type="submit">Confirm appointment <span aria-hidden="true">✓</span></button></div>
                </fieldset>
            </div>
            <aside class="appointment-summary" aria-label="Appointment summary">
                <p class="eyebrow">Your selection</p>
                <h2>Appointment summary</h2>
                <dl data-summary aria-live="polite"></dl>
                <p class="summary-policy"><?= escape($settings['cancellation_policy'] ?? 'Contact the spa before changing your appointment.') ?></p>
            </aside>
        </div>
    </form>
    <?php endif; ?>
</section>
<script src="assets/js/booking.js" defer></script>

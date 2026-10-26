<section class="manage-shell">
    <p class="eyebrow"><span></span> Your appointment</p>
    <h1>Find your<br><em>booking.</em></h1>
    <p>Enter the reference from your confirmation and the phone number used for the appointment.</p>
    <?php if ($error): ?><div class="form-error" role="alert"><?= escape($error) ?></div><?php endif; ?>
    <form class="manage-form" method="post" action="manage.php">
        <input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>"><input type="hidden" name="action" value="lookup">
        <label class="field-label" for="reference">Booking reference</label>
        <input id="reference" name="reference" value="<?= escape($reference) ?>" placeholder="SPA-XXXXXXXX" maxlength="20" required>
        <label class="field-label" for="phone">Phone number</label>
        <input id="phone" name="phone" type="tel" autocomplete="tel" placeholder="024 000 0000" required>
        <button class="button" type="submit">Find my appointment <span aria-hidden="true">→</span></button>
    </form>
</section>

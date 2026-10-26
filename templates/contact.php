<?php $settings = $settings ?? []; ?>
<section class="page-intro section-grid">
    <p class="eyebrow"><span></span> Contact</p>
    <h1>We are here<br><em>to help.</em></h1>
    <p>Questions about a treatment or an existing appointment? Reach out using the details below, or manage your booking securely online.</p>
</section>
<section class="contact-grid">
    <div>
        <p class="section-index">VISIT &amp; CONTACT</p>
        <?php if (!empty($settings['address'])): ?><h2>Find us</h2><p><?= nl2br(escape($settings['address'])) ?></p><?php endif; ?>
        <?php if (!empty($settings['phone'])): ?><h2>Call us</h2><p><a class="text-link" href="tel:<?= escape($settings['phone']) ?>"><?= escape($settings['phone']) ?></a></p><?php endif; ?>
        <?php if (!empty($settings['email'])): ?><h2>Email</h2><p><a class="text-link" href="mailto:<?= escape($settings['email']) ?>"><?= escape($settings['email']) ?></a></p><?php endif; ?>
        <?php if (!empty($settings['opening_text'])): ?><h2>Opening hours</h2><p><?= nl2br(escape($settings['opening_text'])) ?></p><?php endif; ?>
    </div>
    <div class="contact-booking">
        <p class="eyebrow eyebrow-light">Already booked?</p>
        <h2>Your appointment,<br>at your fingertips.</h2>
        <p>View, reschedule, or cancel using your booking reference and the phone number used for the appointment.</p>
        <a class="button button-ivory" href="manage.php">Manage my booking</a>
    </div>
</section>

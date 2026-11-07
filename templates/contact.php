<?php $settings = $settings ?? []; ?>
<section class="page-intro editorial-hero section-grid">
    <p class="eyebrow"><span></span> Contact</p>
    <h1>We are here<br><em>to help.</em></h1>
    <p>Questions about a treatment or an existing appointment? Reach out using the details below, or manage your booking securely online.</p>
</section>
<section class="contact-grid">
    <div class="contact-methods">
        <p class="section-index">VISIT &amp; CONTACT</p>
        <?php if (!empty($settings['address'])): ?><div class="contact-method"><?= ui_icon('pin') ?><div><h2>Find us</h2><p><?= nl2br(escape($settings['address'])) ?></p></div></div><?php endif; ?>
        <?php if (!empty($settings['phone'])): ?><div class="contact-method"><?= ui_icon('phone') ?><div><h2>Call us</h2><p><a class="text-link" href="tel:<?= escape($settings['phone']) ?>"><?= escape($settings['phone']) ?></a></p></div></div><?php endif; ?>
        <?php if (!empty($settings['email'])): ?><div class="contact-method"><?= ui_icon('mail') ?><div><h2>Email</h2><p><a class="text-link" href="mailto:<?= escape($settings['email']) ?>"><?= escape($settings['email']) ?></a></p></div></div><?php endif; ?>
        <?php if (!empty($settings['opening_text'])): ?><div class="contact-method"><?= ui_icon('clock') ?><div><h2>Opening hours</h2><p><?= nl2br(escape($settings['opening_text'])) ?></p></div></div><?php endif; ?>
    </div>
    <div class="contact-booking">
        <p class="eyebrow eyebrow-light">Already booked?</p>
        <h2>Your appointment,<br>at your fingertips.</h2>
        <p>View, reschedule, or cancel using your booking reference and the phone number used for the appointment.</p>
        <a class="button button-ivory" href="manage.php">Manage my booking</a>
    </div>
</section>

<section class="contact-message-section" id="send-message">
    <p class="eyebrow"><span></span> Send us a message</p>
    <h2>What can we help you with?</h2>
    <p>Tell us about your enquiry and leave your email so we can reply. To make an appointment, <a class="text-link" href="book.php">book online</a>.</p>
    <?php if (!empty($sent)): ?><div class="contact-success" role="status"><strong>Thank you. Your message has been sent.</strong><p>Our team will review your enquiry and reply using the contact details you provided.</p></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="form-error" role="alert"><?= escape($error) ?></div><?php endif; ?>
    <?php $old = $old ?? []; ?>
    <form method="post" action="contact.php#send-message" class="contact-message-form form-panel">
        <input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>">
        <div class="contact-honeypot" aria-hidden="true"><label>Leave this field empty<input name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="field-grid">
            <div><label class="field-label" for="contact-name">Your name</label><input id="contact-name" name="name" autocomplete="name" minlength="2" maxlength="160" required value="<?= escape($old['name'] ?? '') ?>"></div>
            <div><label class="field-label" for="contact-email">Email address</label><input id="contact-email" type="email" name="email" autocomplete="email" maxlength="190" required value="<?= escape($old['email'] ?? '') ?>"></div>
            <div class="field-wide"><label class="field-label" for="contact-phone">Phone number <span>Optional</span></label><input id="contact-phone" type="tel" name="phone" autocomplete="tel" maxlength="30" value="<?= escape($old['phone'] ?? '') ?>"></div>
            <div class="field-wide"><label class="field-label" for="contact-subject">Subject</label><input id="contact-subject" name="subject" minlength="2" maxlength="160" required value="<?= escape($old['subject'] ?? '') ?>"></div>
            <div class="field-wide"><label class="field-label" for="contact-message">Your message</label><textarea id="contact-message" name="message" rows="6" minlength="10" maxlength="5000" required><?= escape($old['message'] ?? '') ?></textarea></div>
        </div>
        <p class="contact-form-note">We use your contact details to respond to your enquiry. Sending a message does not create or change an appointment.</p>
        <button class="button" type="submit">Send message <span aria-hidden="true">&#8594;</span></button>
    </form>
</section>

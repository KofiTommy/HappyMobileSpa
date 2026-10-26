<?php declare(strict_types=1); $services = $services ?? []; ?>
<section class="hero">
    <div class="hero-copy">
        <p class="eyebrow"><span></span> Massage &amp; mindful care</p>
        <h1>Come back to<br><em>yourself.</em></h1>
        <p class="hero-intro">Thoughtful massage and spa rituals, chosen around what your body needs today. Book your moment in a few simple steps.</p>
        <div class="hero-actions">
            <a class="button" href="book.php">Book your treatment <span aria-hidden="true">↗</span></a>
            <a class="text-link" href="services.php">Explore treatments</a>
        </div>
        <p class="booking-note"><span aria-hidden="true">✓</span> Real availability &nbsp;·&nbsp; No account needed &nbsp;·&nbsp; Pay at the spa</p>
    </div>
    <div class="hero-visual">
        <img src="assets/images/hero-spa.jpg" alt="A relaxing oil massage treatment" width="1800" height="1200">
        <div class="hero-caption"><span>01</span><p>Slow down.<br>Breathe deeply.</p></div>
    </div>
    <div class="hero-seal" aria-hidden="true"><span>REST</span><span>RESTORE</span><span>RENEW</span></div>
</section>

<section class="intro section-grid">
    <p class="section-index">01 / OUR APPROACH</p>
    <div class="intro-copy">
        <h2>Care that meets you<br>where you are.</h2>
        <div class="intro-columns">
            <p>Whether you need to release tension, recover from a demanding week, or simply enjoy uninterrupted quiet, each visit begins with your comfort.</p>
            <p>Choose your treatment and preferred therapist online. We will take care of the time, the space, and the little details.</p>
        </div>
        <a class="text-link" href="about.php">What to expect</a>
    </div>
</section>

<section class="treatments section-grid" id="treatments">
    <p class="section-index">02 / TREATMENTS</p>
    <div class="treatment-content">
        <div class="section-heading-row"><h2>Find your ritual.</h2><a class="text-link" href="services.php">View all treatments</a></div>
        <?php if ($services): ?>
            <div class="treatment-list">
            <?php foreach (array_slice($services, 0, 4) as $index => $service): ?>
                <a class="treatment-row" href="book.php?service=<?= escape($service['slug']) ?>">
                    <span class="treatment-number"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                    <span class="treatment-name"><strong><?= escape($service['name']) ?></strong><small><?= escape($service['description']) ?></small></span>
                    <span class="treatment-meta"><?= (int) $service['duration_min'] ?> min<br><b><?= money($service['price']) ?></b></span>
                    <span class="round-arrow" aria-hidden="true">↗</span>
                </a>
            <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="treatment-feature">
                <img src="assets/images/spa-ritual.jpg" alt="Hot stones arranged for a spa ritual" loading="lazy" width="1400" height="933">
                <div><h3>Restorative by nature.</h3><p>Massage, body rituals, and quiet moments selected with your wellbeing in mind.</p></div>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="ritual-story">
    <div class="ritual-image"><img src="assets/images/massage-treatment.jpg" alt="Fresh towels, flowers, and treatment oil" loading="lazy" width="1400" height="933"></div>
    <div class="ritual-panel">
        <p class="eyebrow eyebrow-light"><span></span> Your appointment</p>
        <h2>A little preparation.<br>A lot of <em>letting go.</em></h2>
        <ol class="ritual-steps">
            <li><span>01</span><div><h3>Choose what you need</h3><p>Browse treatments, compare durations, and select a therapist if you have a preference.</p></div></li>
            <li><span>02</span><div><h3>Find your time</h3><p>See appointment times based on our actual schedule—no back-and-forth messages.</p></div></li>
            <li><span>03</span><div><h3>Arrive and exhale</h3><p>Receive your booking reference instantly. Payment is made when you visit the spa.</p></div></li>
        </ol>
        <a class="button button-ivory" href="book.php">Find an appointment <span aria-hidden="true">↗</span></a>
    </div>
</section>

<section class="closing-note section-grid">
    <p class="section-index">03 / A QUIET REMINDER</p>
    <blockquote>“You do not have to earn<br>a moment of <em>rest.</em>”</blockquote>
</section>

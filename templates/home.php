<?php
declare(strict_types=1);
$services = $services ?? [];
$staff = $staff ?? [];
$galleryImages = $galleryImages ?? [];
$featuredService = $featuredService ?? ($services[0] ?? null);
?>
<section class="hero">
    <div class="hero-copy">
        <p class="eyebrow"><span></span> Massage &amp; mindful care</p>
        <h1>Come back to<br><em>yourself.</em></h1>
        <p class="hero-intro">Thoughtful massage and spa rituals, chosen around what your body needs today. Book your moment in a few simple steps.</p>
        <div class="hero-actions">
            <a class="button" href="book.php">Book your treatment <span aria-hidden="true">↗</span></a>
            <a class="text-link" href="services.php">Explore treatments</a>
        </div>
        <p class="booking-note"><span aria-hidden="true">✓</span> Real availability &nbsp;·&nbsp; No account needed &nbsp;·&nbsp; <?= Paystack::ready() ? 'Mobile money or pay at spa' : 'Pay at the spa' ?></p>
    </div>
    <?php $heroImages = $heroImages ?? Gallery::defaults(); ?>
    <div class="hero-visual hero-carousel" data-hero-carousel role="region" aria-roledescription="carousel" aria-label="Spa moments">
        <?php foreach ($heroImages as $i => $image): ?><div class="hero-slide <?= $i === 0 ? 'is-active' : '' ?>" data-hero-slide role="group" aria-roledescription="slide" aria-label="<?= $i + 1 ?> of <?= count($heroImages) ?>" aria-hidden="<?= $i === 0 ? 'false' : 'true' ?>">
            <img src="<?= escape($image['path']) ?>" alt="<?= escape($image['alt_text']) ?>" width="1800" height="1200" <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
        </div><?php endforeach; ?>
        <div class="hero-caption"><span data-hero-counter>01 / <?= str_pad((string) count($heroImages), 2, '0', STR_PAD_LEFT) ?></span><p data-hero-caption><?= escape($heroImages[0]['caption']) ?></p></div>
        <?php if (count($heroImages) > 1): ?><div class="hero-carousel-controls" data-hero-controls hidden>
            <button type="button" data-hero-prev aria-label="Previous photo">&#8592;</button>
            <button type="button" data-hero-pause>Pause slideshow</button>
            <button type="button" data-hero-next aria-label="Next photo">&#8594;</button>
        </div><?php endif; ?>
        <script type="application/json" data-hero-captions><?= json_encode(array_column($heroImages, 'caption'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
    </div>
    <div class="hero-seal" aria-hidden="true"><span>REST</span><span>RESTORE</span><span>RENEW</span></div>
</section>

<section class="trust-strip" aria-label="Booking benefits">
    <span><?= ui_icon('calendar') ?> Real availability</span>
    <span><?= ui_icon('check') ?> Secure booking</span>
    <span><?= ui_icon('user') ?> No account needed</span>
    <span><?= ui_icon('clock') ?> <?= Paystack::ready() ? 'Mobile money or pay at spa' : 'Pay at the spa' ?></span>
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

<section class="experience-section" id="experience" aria-labelledby="experience-title">
    <div class="experience-heading">
        <p class="eyebrow"><span></span> Your spa experience</p>
        <h2 id="experience-title">From booking<br>to <em>bliss.</em></h2>
        <p>A simple, thoughtful experience designed around your comfort from the very first click.</p>
    </div>
    <ol class="experience-steps">
        <li><span>01</span><div><h3>Choose your ritual</h3><p>Explore the treatments, compare durations and prices, and select the experience your body needs.</p></div></li>
        <li><span>02</span><div><h3>Make it yours</h3><p>Choose an available time and preferred therapist, then share anything that helps us care for you.</p></div></li>
        <li><span>03</span><div><h3>Take your moment</h3><p>Receive your booking confirmation and leave the rest to us. Your only task is to slow down.</p></div></li>
    </ol>
</section>

<section class="treatments section-grid" id="treatments">
    <p class="section-index">02 / TREATMENTS</p>
    <div class="treatment-content">
        <div class="section-heading-row"><h2>Find your ritual.</h2><a class="text-link" href="services.php">View all treatments</a></div>
        <?php if ($featuredService): ?>
            <?php $featuredImage = $galleryImages[0] ?? ($heroImages[0] ?? null); ?>
            <article class="featured-treatment">
                <?php if ($featuredImage): ?><a class="featured-treatment-media" href="book.php?service=<?= escape($featuredService['slug']) ?>" aria-label="Book <?= escape($featuredService['name']) ?>"><img src="<?= escape($featuredImage['path']) ?>" alt="<?= escape($featuredImage['alt_text']) ?>" width="1400" height="933" loading="lazy"><span>Featured ritual</span></a><?php endif; ?>
                <div class="featured-treatment-copy">
                    <p class="featured-label">Featured treatment</p>
                    <h3><?= escape($featuredService['name']) ?></h3>
                    <p><?= escape($featuredService['description']) ?></p>
                </div>
                <div class="featured-booking">
                    <p class="service-facts"><span><?= (int) $featuredService['duration_min'] ?> min</span><strong><?= money($featuredService['price']) ?></strong></p>
                    <a class="button button-ivory" href="book.php?service=<?= escape($featuredService['slug']) ?>">Book treatment <?= ui_icon('arrow') ?></a>
                </div>
            </article>
        <?php endif; ?>
        <?php if ($services): ?>
            <div class="treatment-cards">
                <?php foreach (array_slice($services, 0, 6) as $index => $service): ?>
                    <article class="treatment-card">
                        <span class="treatment-card-number" aria-hidden="true"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                        <h3><?= escape($service['name']) ?></h3>
                        <p><?= escape($service['description']) ?></p>
                        <p class="service-facts"><span><?= (int) $service['duration_min'] ?> min</span><strong><?= money($service['price']) ?></strong></p>
                        <a class="text-link" href="book.php?service=<?= escape($service['slug']) ?>">Book treatment <?= ui_icon('arrow') ?></a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="empty-state">Explore our treatments to find the right appointment for you.</p>
        <?php endif; ?>
    </div>
</section>

<?php if ($staff): ?>
<section class="team-section section-grid" id="team">
    <p class="section-index">03 / YOUR THERAPISTS</p>
    <div class="team-content">
        <div class="section-heading-row"><h2>Choose your therapist.</h2><p>Select a preferred therapist during booking, or choose any available therapist.</p></div>
        <div class="team-grid">
            <?php foreach ($staff as $person): ?>
                <article class="team-card">
                    <div class="team-portrait">
                        <?php if ($person['photo'] !== null): ?>
                            <img src="<?= escape($person['photo']) ?>" alt="<?= escape($person['name']) ?>" width="800" height="1000" loading="lazy">
                        <?php else: ?>
                            <?php
                            $initials = '';
                            $nameParts = preg_split('/\s+/', trim($person['name'])) ?: [];
                            foreach (array_slice($nameParts, 0, 2) as $part) {
                                $initials .= mb_strtoupper(mb_substr($part, 0, 1));
                            }
                            ?>
                            <div class="team-initials" aria-hidden="true"><?= escape($initials) ?></div>
                        <?php endif; ?>
                        <span>Your therapist</span>
                    </div>
                    <h3><?= escape($person['name']) ?></h3>
                    <?php if (!empty($person['biography'])): ?><p><?= escape($person['biography']) ?></p><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($galleryImages): ?>
<section class="home-gallery section-grid" id="gallery-preview">
    <p class="section-index">04 / SPA MOMENTS</p>
    <div class="home-gallery-content">
        <div class="section-heading-row"><h2>A glimpse inside.</h2><a class="text-link" href="gallery.php">View gallery</a></div>
        <div class="home-gallery-grid">
            <?php foreach ($galleryImages as $index => $image): ?>
                <figure><img src="<?= escape($image['path']) ?>" alt="<?= escape($image['alt_text']) ?>" width="1400" height="933" loading="lazy"><figcaption><span><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span><?= escape($image['caption']) ?></figcaption></figure>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="home-faq section-grid" id="faqs">
    <p class="section-index">05 / PRACTICAL DETAILS</p>
    <div><h2>Before you book.</h2>
        <details><summary>How do I book? <?= ui_icon('plus') ?></summary><p>Choose a treatment, select an available time, enter your details, and review your appointment. No account is required.</p></details>
        <details><summary>Can I change or cancel? <?= ui_icon('plus') ?></summary><p>Use Manage booking with your reference and phone number. Online changes follow the cancellation policy shown during booking.</p></details>
        <details><summary>How do I pay? <?= ui_icon('plus') ?></summary><p><?= Paystack::ready() ? 'Choose mobile money through Paystack or pay at the spa. Your review page shows the available choices.' : 'Payment is made at the spa.' ?></p></details>
        <details><summary>What should I tell my therapist? <?= ui_icon('plus') ?></summary><p>Add pressure preferences, sensitive areas, pregnancy, injuries, or comfort notes to your booking, or discuss them privately when you arrive.</p></details>
    </div>
</section>

<script src="assets/js/hero-carousel.js" defer></script>

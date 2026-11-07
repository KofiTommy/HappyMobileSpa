<?php declare(strict_types=1); $services = $services ?? []; ?>
<section class="page-intro editorial-hero section-grid">
    <p class="eyebrow"><span></span> Treatments</p>
    <h1>What does your body<br><em>need today?</em></h1>
    <p>Every treatment is time set aside for your comfort. Choose a ritual to see available appointment times.</p>
</section>
<section class="services-catalog">
<?php if ($services): foreach ($services as $index => $service): ?>
    <article class="catalog-card">
        <span class="treatment-number"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
        <div class="catalog-card-copy"><h2><?= escape($service['name']) ?></h2><p><?= escape($service['description']) ?></p></div>
        <dl class="catalog-card-facts"><div><dt>Duration</dt><dd><?= (int) $service['duration_min'] ?> min</dd></div><div><dt>Price</dt><dd><?= money($service['price']) ?></dd></div></dl>
        <a class="button button-small" href="book.php?service=<?= escape($service['slug']) ?>">Book treatment <?= ui_icon('arrow') ?></a>
    </article>
<?php endforeach; else: ?>
    <div class="empty-state"><h2>Our treatment menu is being prepared.</h2><p>Please check back soon or contact us for help choosing a treatment.</p><a class="button" href="contact.php">Contact us</a></div>
<?php endif; ?>
</section>

<?php declare(strict_types=1); $services = $services ?? []; ?>
<section class="page-intro section-grid">
    <p class="eyebrow"><span></span> Treatments</p>
    <h1>What does your body<br><em>need today?</em></h1>
    <p>Every treatment is time set aside for your comfort. Choose a ritual to see available appointment times.</p>
</section>
<section class="services-catalog">
<?php if ($services): foreach ($services as $index => $service): ?>
    <article class="catalog-row">
        <span class="treatment-number"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
        <div><h2><?= escape($service['name']) ?></h2><p><?= escape($service['description']) ?></p></div>
        <p class="catalog-meta"><?= (int) $service['duration_min'] ?> minutes<br><strong><?= money($service['price']) ?></strong></p>
        <a class="button button-small" href="book.php?service=<?= escape($service['slug']) ?>">Book treatment</a>
    </article>
<?php endforeach; else: ?>
    <p class="empty-state">Our treatment menu will be available shortly. Please check back soon.</p>
<?php endif; ?>
</section>

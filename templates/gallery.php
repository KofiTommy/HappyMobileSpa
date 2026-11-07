<section class="page-intro editorial-hero section-grid">
    <p class="eyebrow"><span></span> A sense of calm</p>
    <h1>Small details.<br><em>Deep exhales.</em></h1>
    <p>A glimpse of the rituals and quiet moments that inspire your time with us.</p>
</section>
<section class="editorial-gallery managed-gallery">
    <?php if ($images): foreach ($images as $i => $image): ?><figure><img src="<?= escape($image['path']) ?>" alt="<?= escape($image['alt_text']) ?>" width="1400" height="933" loading="lazy"><figcaption><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?> / <?= escape($image['caption']) ?></figcaption></figure><?php endforeach; else: ?>
        <div class="empty-state"><h2>Our gallery is being prepared.</h2><p>Explore our treatments or contact us to plan your visit.</p><a class="button" href="services.php">Explore treatments</a></div>
    <?php endif; ?>
</section>

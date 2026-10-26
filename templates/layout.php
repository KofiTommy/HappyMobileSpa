<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#173F35">
    <meta name="description" content="Happy Mobile Spa massage and wellness treatments with simple online appointment booking.">
    <title><?= escape($title) ?> · Happy Mobile Spa</title>
    <link rel="icon" href="assets/images/favicon.png" type="image/png">
    <link rel="stylesheet" href="assets/css/site.css">
    <link rel="stylesheet" href="assets/css/booking.css">
    <script src="assets/js/site.js" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header" data-header>
    <a class="brand" href="index.php" aria-label="Happy Mobile Spa home">
        <img src="assets/images/logo.jpg" alt="Happy Mobile Massage" width="180" height="97">
        <span>Happy Mobile Spa</span>
    </a>
    <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-nav" data-menu-toggle>
        <span></span><span></span><span></span><span class="sr-only">Toggle navigation</span>
    </button>
    <nav id="site-nav" class="site-nav" aria-label="Main navigation" data-menu>
        <a href="index.php" <?= $activePage === 'index.php' ? 'aria-current="page"' : '' ?>>Home</a>
        <a href="services.php" <?= $activePage === 'services.php' ? 'aria-current="page"' : '' ?>>Treatments</a>
        <a href="about.php" <?= $activePage === 'about.php' ? 'aria-current="page"' : '' ?>>Our approach</a>
        <a href="gallery.php" <?= $activePage === 'gallery.php' ? 'aria-current="page"' : '' ?>>Gallery</a>
        <a href="contact.php" <?= $activePage === 'contact.php' ? 'aria-current="page"' : '' ?>>Contact</a>
    </nav>
    <a class="button button-small header-book" href="book.php">Book Now <span aria-hidden="true">↗</span></a>
</header>
<main id="main">
<?= $content ?>
</main>
<footer class="site-footer">
    <div class="footer-invitation">
        <p class="eyebrow eyebrow-light">Your time. Your treatment.</p>
        <h2>Make room for<br><em>feeling better.</em></h2>
        <a class="button button-ivory" href="book.php">Choose your treatment <span aria-hidden="true">↗</span></a>
    </div>
    <div class="footer-bottom">
        <a class="footer-brand" href="index.php">Happy Mobile Spa</a>
        <div class="footer-contact">
            <?php if (!empty($settings['phone'])): ?><a href="tel:<?= escape($settings['phone']) ?>"><?= escape($settings['phone']) ?></a><?php endif; ?>
            <?php if (!empty($settings['email'])): ?><a href="mailto:<?= escape($settings['email']) ?>"><?= escape($settings['email']) ?></a><?php endif; ?>
            <?php if (!empty($settings['address'])): ?><span><?= escape($settings['address']) ?></span><?php endif; ?>
        </div>
        <div class="footer-links"><a href="manage.php">Manage booking</a><a href="admin/login.php">Staff login</a></div>
    </div>
    <p class="copyright">&copy; <?= date('Y') ?> Happy Mobile Spa. Time set aside for you.</p>
</footer>
</body>
</html>

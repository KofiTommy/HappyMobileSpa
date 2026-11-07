<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#173F35">
    <title><?= escape($title) ?> · Happy Mobile Spa</title>
    <link rel="icon" href="../assets/images/favicon.png">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="../assets/js/admin.js" defer></script>
</head>
<body>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a class="admin-brand" href="index.php">
            <img src="../assets/images/logo.jpg" alt="" width="115">
            <span>Happy Mobile Spa<small>Studio management</small></span>
        </a>
        <button class="admin-menu-toggle" type="button" data-admin-menu-toggle aria-expanded="false" aria-controls="admin-nav" aria-label="Open navigation">
            <?= ui_icon('menu') ?><span>Menu</span>
        </button>
        <nav id="admin-nav" data-admin-nav aria-label="Admin navigation">
            <a href="index.php" <?= $activeAdminPage === 'index.php' ? 'class="active" aria-current="page"' : '' ?>><?= ui_icon('calendar') ?><span>Overview</span></a>
            <a href="bookings.php" <?= $activeAdminPage === 'bookings.php' ? 'class="active" aria-current="page"' : '' ?>><?= ui_icon('clock') ?><span>Bookings &amp; calendar</span></a>
            <a href="manual-booking.php" <?= $activeAdminPage === 'manual-booking.php' ? 'class="active" aria-current="page"' : '' ?>><?= ui_icon('plus') ?><span>New booking</span></a>
            <?php if ($admin['role'] === 'admin'): ?>
                <a href="services.php" <?= $activeAdminPage === 'services.php' ? 'class="active" aria-current="page"' : '' ?>><?= ui_icon('check') ?><span>Treatments</span></a>
                <a href="staff.php" <?= $activeAdminPage === 'staff.php' ? 'class="active" aria-current="page"' : '' ?>><?= ui_icon('user') ?><span>Team &amp; schedules</span></a>
                <a href="payments.php" <?= $activeAdminPage === 'payments.php' ? 'class="active" aria-current="page"' : '' ?>><?= ui_icon('check') ?><span>Mobile money payments</span></a>
                <a href="sms.php" <?= $activeAdminPage === 'sms.php' ? 'class="active" aria-current="page"' : '' ?>><?= ui_icon('mail') ?><span>SMS notifications</span></a>
                <a href="messages.php" <?= $activeAdminPage === 'messages.php' ? 'class="active" aria-current="page"' : '' ?>><?= ui_icon('mail') ?><span>Customer messages</span></a>
                <a href="gallery.php" <?= $activeAdminPage === 'gallery.php' ? 'class="active" aria-current="page"' : '' ?>><?= ui_icon('pin') ?><span>Gallery &amp; homepage</span></a>
                <a href="settings.php" <?= $activeAdminPage === 'settings.php' ? 'class="active" aria-current="page"' : '' ?>><?= ui_icon('user') ?><span>Business settings</span></a>
            <?php endif; ?>
        </nav>
        <div class="sidebar-bottom">
            <a href="../index.php" target="_blank" rel="noopener">View public website ↗</a>
            <form method="post" action="logout.php">
                <input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>">
                <button type="submit">Sign out</button>
            </form>
        </div>
    </aside>
    <main class="admin-main">
        <header class="admin-top">
            <div>
                <p class="admin-eyebrow">HAPPY MOBILE SPA</p>
                <h1><?= escape($title) ?></h1>
            </div>
            <div class="admin-top-actions">
                <a class="admin-button admin-new-booking" href="manual-booking.php"><?= ui_icon('plus') ?><span>New booking</span></a>
                <div class="admin-user">
                    <span><?= escape($admin['username']) ?></span>
                    <small><?= $admin['role'] === 'admin' ? 'Administrator' : 'Receptionist' ?></small>
                </div>
            </div>
        </header>
        <?= $content ?>
    </main>
</div>
</body>
</html>

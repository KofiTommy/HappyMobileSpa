<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
$admin = Auth::requireAdmin();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Csrf::verify($_POST['csrf'] ?? null)) { throw new DomainException('Your form session expired.'); }
        Gallery::manage($admin, $_POST, $_FILES['photo'] ?? null);
        header('Location: gallery.php?saved=1'); exit;
    } catch (DomainException | InvalidArgumentException $exception) { $error = $exception->getMessage(); }
}
$images = Database::connection()->query('SELECT * FROM gallery_images ORDER BY display_order,id')->fetchAll();
render_admin('Gallery & homepage photos', 'gallery', compact('admin', 'images', 'error'));

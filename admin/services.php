<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
$admin = Auth::requireAdmin();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Csrf::verify($_POST['csrf'] ?? null)) { throw new DomainException('Your form session expired.'); }
        if (($_POST['action'] ?? '') === 'archive') {
            Catalog::archiveService((int) $_POST['id']);
            header('Location: services.php?archived=1'); exit;
        }
        $id = !empty($_POST['id']) ? (int) $_POST['id'] : null;
        Catalog::saveService($_POST, $id);
        header('Location: services.php?saved=1'); exit;
    } catch (DomainException | InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    }
}
$services = Catalog::adminServices();
$editing = null;
if (!empty($_GET['edit'])) {
    foreach ($services as $service) {
        if ((int) $service['id'] === (int) $_GET['edit']) { $editing = $service; break; }
    }
}
$success = isset($_GET['saved']) ? 'Treatment saved.' : (isset($_GET['archived']) ? 'Treatment archived. Booking history is unchanged.' : '');
render_admin('Treatments', 'services', compact('admin', 'services', 'editing', 'error', 'success'));

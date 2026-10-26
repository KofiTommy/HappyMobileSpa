<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
$admin = Auth::requireUser();
$error = '';
$old = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;
    try {
        if (!Csrf::verify($_POST['csrf'] ?? null)) { throw new DomainException('Your form session expired.'); }
        $booking = Booking::createManual($_POST, (int) $admin['id'], ($_POST['override'] ?? null) === '1');
        header('Location: bookings.php?id=' . $booking['id']); exit;
    } catch (DomainException | InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    }
}
$services = Catalog::activeServices();
$staff = Catalog::activeStaff();
render_admin('New booking', 'manual-booking', compact('admin', 'services', 'staff', 'old', 'error'));

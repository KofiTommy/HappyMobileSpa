<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
$admin = Auth::requireUser();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Csrf::verify($_POST['csrf'] ?? null)) { throw new DomainException('Your form session expired.'); }
        $id = (int) $_POST['booking_id'];
        $action = (string) $_POST['action'];
        if ($action === 'status') { Booking::setStatus($id, (string) $_POST['status'], (int) $admin['id']); }
        if ($action === 'payment') { Booking::setPaymentStatus($id, (string) $_POST['payment_status'], (int) $admin['id']); }
        header('Location: bookings.php?id=' . $id); exit;
    } catch (DomainException | InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    }
}
$filters = array_intersect_key($_GET, array_flip(['id','date','status','staff_id','search']));
$bookings = Booking::adminList($filters);
$staff = Catalog::activeStaff();
render_admin('Bookings & calendar', 'bookings', compact('admin', 'bookings', 'staff', 'filters', 'error'));

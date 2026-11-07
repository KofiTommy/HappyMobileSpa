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
        if ($action === 'check_in') { Booking::checkIn($id, (int) $admin['id']); }
        if ($action === 'status') { Booking::setStatus($id, (string) $_POST['status'], (int) $admin['id']); }
        if ($action === 'payment') { Booking::setPaymentStatus($id, (string) $_POST['payment_status'], (int) $admin['id']); }
        header('Location: bookings.php?id=' . $id); exit;
    } catch (DomainException | InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    }
}
$view = (string) ($_GET['view'] ?? 'all');
if (!in_array($view, ['all', 'day', 'week'], true)) { $view = 'all'; }
$agendaDate = (string) ($_GET['date'] ?? (new DateTimeImmutable('today', business_timezone()))->format('Y-m-d'));
$parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $agendaDate, business_timezone());
if (!$parsedDate || $parsedDate->format('Y-m-d') !== $agendaDate) {
    $parsedDate = new DateTimeImmutable('today', business_timezone());
    $agendaDate = $parsedDate->format('Y-m-d');
}
$weekStart = $parsedDate->modify('monday this week');
$weekEnd = $weekStart->modify('+6 days');
$filters = array_intersect_key($_GET, array_flip(['id','date','status','staff_id','search']));
if ($view === 'day') { $filters['start_date'] = $filters['end_date'] = $agendaDate; unset($filters['date']); }
if ($view === 'week') { $filters['start_date'] = $weekStart->format('Y-m-d'); $filters['end_date'] = $weekEnd->format('Y-m-d'); unset($filters['date']); }
$bookings = Booking::adminList($filters);
$staff = Catalog::activeStaff();
render_admin('Bookings & calendar', 'bookings', compact('admin', 'bookings', 'staff', 'filters', 'error', 'view', 'agendaDate', 'weekStart', 'weekEnd'));

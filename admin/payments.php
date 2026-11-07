<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
$admin = Auth::requireAdmin();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Csrf::verify($_POST['csrf'] ?? null)) { throw new DomainException('Your form session expired.'); }
        if (($_POST['action'] ?? '') === 'verify') {
            PaystackPayment::verify((string) ($_POST['reference'] ?? ''));
        } else { Paystack::save($admin, $_POST); }
        header('Location: payments.php?saved=1'); exit;
    } catch (DomainException | InvalidArgumentException $exception) { $error = $exception->getMessage(); }
}
$paystackConfig = Paystack::config();
$payments = Database::connection()->query('SELECT p.*,b.reference AS booking_reference FROM paystack_payments p JOIN bookings b ON b.id=p.booking_id ORDER BY p.id DESC LIMIT 100')->fetchAll();
render_admin('Mobile money payments', 'payments', compact('admin','error','paystackConfig','payments'));

<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
$admin = Auth::requireAdmin();
$error = '';
$search = (string) ($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Csrf::verify($_POST['csrf'] ?? null)) { throw new DomainException('Your form session expired.'); }
        if (($_POST['action'] ?? '') === 'verify') {
            PaystackPayment::verify((string) ($_POST['reference'] ?? ''));
        } else { Paystack::save($admin, $_POST); }
        header('Location: payments.php?' . http_build_query(['search'=>$search, 'page'=>$page, 'saved'=>1])); exit;
    } catch (DomainException | InvalidArgumentException $exception) { $error = $exception->getMessage(); }
}
$paystackConfig = Paystack::config();
$listing = PaystackPayment::adminList($search, $page);
$payments = $listing['payments'];
render_admin('Mobile money payments', 'payments', compact('admin','error','paystackConfig','payments','listing'));

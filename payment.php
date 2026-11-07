<?php
declare(strict_types=1);
require_once __DIR__ . '/src/bootstrap.php';
$reference = (string) ($_GET['booking'] ?? '');
$recent = $_SESSION['recent_booking'] ?? [];
$manage = $_SESSION['manage_booking'] ?? [];
$allowed = (($recent['time'] ?? 0) > time()-3600 && hash_equals((string) ($recent['reference'] ?? ''), $reference))
    || (($manage['expires'] ?? 0) > time() && hash_equals((string) ($manage['reference'] ?? ''), $reference));
if (!$allowed || $reference === '') { header('Location: manage.php'); exit; }
$query = Database::connection()->prepare('SELECT id FROM bookings WHERE reference=?');
$query->execute([$reference]);
$id = (int) $query->fetchColumn();
$booking = PaystackPayment::booking($id);
$error = '';
$query = Database::connection()->prepare('SELECT state FROM paystack_payments WHERE booking_id=?');
$query->execute([$id]);
$paymentState = (string) ($query->fetchColumn() ?: '');
$message = $paymentState === 'paid_review' ? 'Your payment was received, but the appointment needs review. Contact the spa to confirm arrangements or discuss a refund.' : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Csrf::verify($_POST['csrf'] ?? null)) { throw new DomainException('Your form session expired.'); }
        RateLimit::check('paystack-checkout', (string) $id, 15, 900);
        if (($_POST['action'] ?? '') === 'check') {
            $query = Database::connection()->prepare('SELECT reference FROM paystack_payments WHERE booking_id=?');
            $query->execute([$id]);
            $paymentReference = $query->fetchColumn();
            if (!$paymentReference) { throw new DomainException('No online payment has been started yet.'); }
            $paymentState = PaystackPayment::verify($paymentReference);
            $message = $paymentState === 'paid' ? 'Your payment has been verified.' : ($paymentState === 'paid_review' ? 'Your payment was received, but the appointment needs review. Contact the spa to confirm arrangements or discuss a refund.' : 'Payment has not yet been confirmed. If you approved it on your phone, please wait and check again.');
        } else {
            if (!$booking['customer_email']) {
                $email = trim((string) ($_POST['email'] ?? ''));
                if (strlen($email)>190 || !filter_var($email,FILTER_VALIDATE_EMAIL)) { throw new InvalidArgumentException('Enter a valid email address for your payment receipt.'); }
                Database::connection()->prepare('UPDATE customers SET email=? WHERE id=?')->execute([$email,$booking['customer_id']]);
            }
            $url = PaystackPayment::start($id);
            header('Location: ' . $url, true, 303); exit;
        }
    } catch (DomainException | InvalidArgumentException $exception) { $error = $exception->getMessage(); }
    $booking = PaystackPayment::booking($id);
}
$autoStart = $_SERVER['REQUEST_METHOD'] === 'GET'
    && ($_GET['start'] ?? '') === '1'
    && in_array($paymentState, ['', 'pending'], true)
    && Paystack::ready()
    && filter_var($booking['customer_email'], FILTER_VALIDATE_EMAIL)
    && $booking['status'] === 'confirmed'
    && $booking['slot_start'] > gmdate('Y-m-d H:i:s')
    && $booking['payment_status'] !== 'paid';
render_public('Pay for your appointment', 'payment', ['booking'=>$booking,'error'=>$error,'message'=>$message,'paymentState'=>$paymentState,'autoStart'=>$autoStart,'settings'=>Catalog::settings()]);

<?php
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';
$state = 'pending';
$error = '';
try {
    RateLimit::check('paystack-return', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 30, 900);
    $reference = (string) ($_GET['reference'] ?? '');
    if (!preg_match('/^SPA-PAY-[a-f0-9]{32}$/D', $reference)) { throw new DomainException('Invalid payment reference.'); }
    $state = PaystackPayment::verify($reference);
} catch (DomainException | InvalidArgumentException $exception) { $error = $exception->getMessage(); }
render_public('Payment status', 'payment-return', ['state'=>$state,'error'=>$error,'settings'=>Catalog::settings()]);

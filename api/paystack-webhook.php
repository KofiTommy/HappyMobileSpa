<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
$raw = (string) file_get_contents('php://input');
if (strlen($raw) > 1048576) { http_response_code(413); exit; }
$config = Paystack::config();
if (!Paystack::validSignature($raw, (string) ($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? ''), $config['secret_key'])) { http_response_code(401); exit; }
$event = json_decode($raw, true);
if (!is_array($event)) { http_response_code(400); exit; }
if (($event['event'] ?? '') !== 'charge.success') { http_response_code(200); exit; }
$reference = (string) ($event['data']['reference'] ?? '');
$query = Database::connection()->prepare('SELECT reference FROM paystack_payments WHERE reference=?');
$query->execute([$reference]);
if (!$query->fetch()) { http_response_code(200); exit; }
try {
    // Signed event plus independent provider verification; never trust browser payment flags.
    PaystackPayment::verify($reference);
    http_response_code(200);
} catch (Throwable $error) {
    error_log('Paystack webhook verification deferred.');
    http_response_code(503); // Paystack retries; no personal data or keys in logs.
}

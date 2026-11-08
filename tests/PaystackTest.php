<?php
declare(strict_types=1);

function paystack_fixture(): array
{
    $booking = seed_confirmed_booking('+48 hours');
    Database::connection()->exec("UPDATE customers SET email='client@example.com' WHERE id=1");
    return $booking;
}

function paystack_fake_client(array &$requests): callable
{
    return function (string $method, string $path, array $payload) use (&$requests): array {
        $requests[] = [$method,$path,$payload];
        return ['reference'=>$payload['reference'], 'authorization_url'=>'https://checkout.paystack.com/test-checkout'];
    };
}

function paystack_test_config(): array
{
    return ['secret_key'=>'sk_test_fakekey','site_url'=>'https://spa.example.com'];
}

function paystack_success(array $payment): array
{
    return ['reference'=>$payment['reference'],'amount'=>(int)$payment['amount_subunit'],'currency'=>'GHS','status'=>'success','domain'=>'test','channel'=>'mobile_money','customer'=>['email'=>'client@example.com']];
}

function test_paystack_initialization_uses_server_amount_and_single_reference(): void
{
    $booking = paystack_fixture();
    $requests = [];
    $client = paystack_fake_client($requests);
    $url = PaystackPayment::start($booking['id'], $client, paystack_test_config());
    assert_same('https://checkout.paystack.com/test-checkout', $url, 'hosted checkout');
    assert_same(35000, $requests[0][2]['amount'], 'cedis converted to pesewas');
    assert_same('GHS', $requests[0][2]['currency'], 'Ghana currency');
    assert_same(['mobile_money'], $requests[0][2]['channels'], 'MoMo checkout only');
    assert_same('https://spa.example.com/payment-return.php', $requests[0][2]['callback_url'], 'configured callback URL');
    assert_same($url, PaystackPayment::start($booking['id'],$client,paystack_test_config()), 'existing checkout reused');
    assert_same(1, count($requests), 'refresh cannot create a second charge');
    assert_same('unpaid', PaystackPayment::booking($booking['id'])['payment_status'], 'initialization never marks paid');
    assert_same(105, PaystackPayment::amount('1.05'), 'exact minor unit conversion');
    assert_throws(fn () => PaystackPayment::amount('1.001'), 'Invalid payment amount');
}

function test_paystack_verification_is_strict_and_idempotent(): void
{
    $booking = paystack_fixture();
    $requests = [];
    PaystackPayment::start($booking['id'],paystack_fake_client($requests),paystack_test_config());
    $payment = Database::connection()->query('SELECT * FROM paystack_payments')->fetch();
    $data = paystack_success($payment);
    foreach (['amount'=>1,'currency'=>'NGN','domain'=>'live','reference'=>'other','channel'=>'card','customer'=>['email'=>'other@example.com']] as $field=>$value) {
        assert_throws(fn () => PaystackPayment::settle($payment['reference'],array_replace($data,[$field=>$value])), 'did not match');
    }
    assert_same('pending', PaystackPayment::settle($payment['reference'],array_replace($data,['status'=>'pending'])), 'pending is not paid');
    assert_same('unpaid', PaystackPayment::booking($booking['id'])['payment_status'], 'mismatches never credit booking');
    assert_same('paid', PaystackPayment::verify($payment['reference'], fn () => $data), 'verified payment accepted');
    assert_same('paid', PaystackPayment::settle($payment['reference'],$data), 'duplicate webhook harmless');
    assert_same(1, (int)Database::connection()->query("SELECT COUNT(*) FROM booking_events WHERE event_type='payment_verified'")->fetchColumn(), 'one payment event');
    assert_same('paystack_momo', PaystackPayment::booking($booking['id'])['payment_method'], 'payment method recorded');
    assert_throws(fn () => Booking::setPaymentStatus($booking['id'],'unpaid',1), 'managed through Paystack');
    assert_throws(fn () => PaystackPayment::start($booking['id'],paystack_fake_client($requests),paystack_test_config()), 'another payment');
}

function test_paystack_late_payment_requires_review_without_reopening_cancelled_booking(): void
{
    $booking = paystack_fixture();
    $requests = [];
    PaystackPayment::start($booking['id'],paystack_fake_client($requests),paystack_test_config());
    Booking::setStatus($booking['id'],'cancelled',1);
    $payment = Database::connection()->query('SELECT * FROM paystack_payments')->fetch();
    assert_same('paid_review', PaystackPayment::settle($payment['reference'],paystack_success($payment)), 'late payment flagged');
    assert_same('cancelled', PaystackPayment::booking($booking['id'])['status'], 'cancelled booking stays cancelled');
    assert_same('paid', PaystackPayment::booking($booking['id'])['payment_status'], 'money received recorded');
}

function test_paystack_failed_initialization_never_repeats_request(): void
{
    $booking = paystack_fixture();
    $calls = 0;
    $client = function () use (&$calls): array { $calls++; throw new DomainException('Timeout'); };
    assert_throws(fn () => PaystackPayment::start($booking['id'],$client,paystack_test_config()), 'appointment is saved');
    assert_throws(fn () => PaystackPayment::start($booking['id'],$client,paystack_test_config()), 'needs verification');
    assert_same(1, $calls, 'uncertain initialization not retried');
    assert_same('unpaid', PaystackPayment::booking($booking['id'])['payment_status'], 'booking survives failure');
}

function test_paystack_signatures_email_and_checkout_url_validation(): void
{
    $raw = '{"event":"charge.success"}';
    $key = 'sk_test_fakekey';
    $signature = hash_hmac('sha512',$raw,$key);
    assert_same(true, Paystack::validSignature($raw,$signature,$key), 'valid webhook signature');
    assert_same(false, Paystack::validSignature($raw.' ',$signature,$key), 'tampering rejected');
    assert_same(false, Paystack::validSignature($raw,$signature,''), 'empty key rejected');
    $booking = paystack_fixture();
    Database::connection()->exec('UPDATE customers SET email=NULL');
    $requests = [];
    assert_throws(fn () => PaystackPayment::start($booking['id'],paystack_fake_client($requests),paystack_test_config()), 'email address');
    Database::connection()->exec("UPDATE customers SET email='client@example.com'");
    assert_throws(fn () => PaystackPayment::start($booking['id'],fn ($m,$p,$data) => ['reference'=>$data['reference'],'authorization_url'=>'https://evil.example/phishing'],paystack_test_config()), 'could not open checkout');
    assert_throws(fn () => Paystack::save(['role'=>'receptionist'],[]), 'Administrator access required');
    assert_same(false, Paystack::ready(), 'test suite cannot send real payment requests');
}

function test_paystack_admin_does_not_render_secrets(): void
{
    seed_test_catalog();
    ob_start();
    render_admin('Paystack','payments',['admin'=>['username'=>'owner','role'=>'admin'],'error'=>'','payments'=>[], 'paystackConfig'=>['enabled'=>false,'site_url'=>'https://spa.example.com','secret_key'=>'sk_test_NEVERDISPLAY']]);
    $html = (string)ob_get_clean();
    assert_not_contains('sk_test_NEVERDISPLAY',$html,'secret omitted');
    assert_contains('https://spa.example.com/api/paystack-webhook.php',$html,'webhook URL provided');
}

function test_paystack_payment_received_after_manual_payment_requires_review(): void
{
    $booking = paystack_fixture();
    $requests = [];
    PaystackPayment::start($booking['id'],paystack_fake_client($requests),paystack_test_config());
    Booking::setPaymentStatus($booking['id'],'paid',1);
    $payment = Database::connection()->query('SELECT * FROM paystack_payments')->fetch();
    assert_same('paid_review', PaystackPayment::settle($payment['reference'],paystack_success($payment)), 'possible duplicate payment flagged');
    assert_throws(fn () => Booking::setPaymentStatus($booking['id'],'unpaid',1), 'managed through Paystack');
}

function test_paystack_paid_review_is_not_rendered_as_unqualified_success(): void
{
    $booking = PaystackPayment::booking(paystack_fixture()['id']);
    $booking['payment_status'] = 'paid';
    $booking['status'] = 'cancelled';
    $reviewMessage = 'Your payment was received, but the appointment needs review. Contact the spa to confirm arrangements or discuss a refund.';
    $html = render_template_for_test('payment', [
        'booking' => $booking, 'error' => '', 'message' => $reviewMessage,
        'paymentState' => 'paid_review', 'settings' => [],
    ]);
    assert_contains('Payment received — contact the spa', $html, 'paid review heading');
    assert_contains($reviewMessage, $html, 'review and refund guidance');
    assert_contains('status-banner status-pending', $html, 'paid review pending presentation');
    assert_not_contains('Your payment has been recorded. Thank you.', $html, 'unqualified success suppressed');
    assert_not_contains('Check payment status', $html, 'review state does not offer another checkout action');
    $controller = file_get_contents(APP_ROOT . '/payment.php');
    assert_contains('$paymentState = PaystackPayment::verify($paymentReference);', $controller, 'actual verification state retained');
    assert_contains("'paymentState'=>\$paymentState", $controller, 'verification state passed to payment template');
}

function test_paystack_persisted_paid_review_is_used_on_subsequent_get(): void
{
    $booking = paystack_fixture();
    $requests = [];
    PaystackPayment::start($booking['id'], paystack_fake_client($requests), paystack_test_config());
    Booking::setStatus($booking['id'], 'cancelled', 1);
    $payment = Database::connection()->query('SELECT * FROM paystack_payments')->fetch();
    assert_same('paid_review', PaystackPayment::settle($payment['reference'], paystack_success($payment)), 'persisted review fixture');

    $originalGet = $_GET;
    $originalServer = $_SERVER;
    $originalSession = $_SESSION ?? null;
    $bufferLevel = ob_get_level();
    try {
        $_GET = ['booking' => $booking['reference']];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['SCRIPT_NAME'] = 'payment.php';
        $_SESSION['recent_booking'] = ['reference' => $booking['reference'], 'time' => time()];
        ob_start();
        require APP_ROOT . '/payment.php';
        $html = (string) ob_get_clean();
    } finally {
        while (ob_get_level() > $bufferLevel) {
            ob_end_clean();
        }
        $_GET = $originalGet;
        $_SERVER = $originalServer;
        if ($originalSession === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $originalSession;
        }
    }

    assert_contains('Payment received — contact the spa', $html, 'subsequent GET uses persisted review state');
    assert_contains('the appointment needs review', $html, 'subsequent GET review guidance');
    assert_contains('discuss a refund', $html, 'subsequent GET refund guidance');
    assert_not_contains('Your payment has been recorded. Thank you.', $html, 'subsequent GET suppresses generic paid success');
    assert_not_contains('Check payment status', $html, 'subsequent GET suppresses checkout actions');
}

function test_paystack_checkout_auto_starts_only_after_booking(): void
{
    $bookingController = file_get_contents(APP_ROOT . '/book.php');
    $paymentController = file_get_contents(APP_ROOT . '/payment.php');
    $template = file_get_contents(APP_ROOT . '/templates/payment.php');
    $script = file_get_contents(APP_ROOT . '/assets/js/payment.js');

    assert_contains("payment.php?start=1&booking=", $bookingController, 'new mobile-money bookings request checkout handoff');
    assert_contains("(\$_GET['start'] ?? '') === '1'", $paymentController, 'automatic handoff requires explicit post-booking signal');
    assert_contains("\$_SERVER['REQUEST_METHOD'] === 'GET'", $paymentController, 'failed POST never automatically retries initialization');
    assert_contains("in_array(\$paymentState, ['', 'pending'], true)", $paymentController, 'uncertain or completed payment states do not auto-start');
    assert_contains("'autoStart'=>\$autoStart", $paymentController, 'controller supplies auto-start state');
    assert_contains('data-auto-submit', $template, 'eligible checkout form exposes enhancement hook');
    assert_contains('name="action" value="pay"', $template, 'manual payment button remains as fallback');
    assert_contains('assets/js/payment.js', $template, 'payment enhancement is loaded');
    assert_contains('mobile money app or phone prompt', $template, 'customer knows where to approve the wallet payment');
    assert_contains('requestSubmit()', $script, 'browser performs native form submission to hosted checkout');
}

function test_payment_confirmation_sms_waits_for_verification_and_never_duplicates(): void
{
    $booking = paystack_fixture();
    $requests = [];
    PaystackPayment::start($booking['id'], paystack_fake_client($requests), paystack_test_config());
    $payment = Database::connection()->query('SELECT * FROM paystack_payments')->fetch();
    $data = paystack_success($payment);
    $countReceipts = fn (): int => (int) Database::connection()->query("SELECT COUNT(*) FROM sms_notifications WHERE kind='payment_confirmation'")->fetchColumn();

    assert_same('pending', PaystackPayment::settle($payment['reference'], array_replace($data, ['status'=>'pending'])), 'pending payment remains pending');
    assert_same(0, $countReceipts(), 'pending payment queues no receipt');
    assert_same('paid', PaystackPayment::settle($payment['reference'], $data), 'verified payment succeeds');
    assert_same(1, $countReceipts(), 'verified payment queues one receipt');
    assert_same('paid', PaystackPayment::settle($payment['reference'], $data), 'repeated verification is harmless');
    assert_same(1, $countReceipts(), 'repeated verification does not duplicate receipt');

    $messages = [];
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $sender = function (string $phone, string $body) use (&$messages): array {
        $messages[] = [$phone, $body];
        return ['state'=>'submitted'];
    };
    assert_same(1, SmsNotifications::process($sender, $now), 'payment receipt submitted');
    assert_same(0, SmsNotifications::process($sender, $now), 'submitted receipt not repeated');
    assert_same(1, count($messages), 'exactly one payment SMS sent');
    assert_contains('Payment received: GHS 350.00', $messages[0][1], 'verified amount included');
    assert_contains('Swedish Massage', $messages[0][1], 'service included');
    assert_contains('SPA-MANAGE01', $messages[0][1], 'booking reference included');
}

function test_payment_confirmation_sms_not_sent_for_review_or_failed_payments(): void
{
    $booking = paystack_fixture();
    $requests = [];
    PaystackPayment::start($booking['id'], paystack_fake_client($requests), paystack_test_config());
    $payment = Database::connection()->query('SELECT * FROM paystack_payments')->fetch();
    Booking::setStatus($booking['id'], 'cancelled', 1);
    assert_same('paid_review', PaystackPayment::settle($payment['reference'], paystack_success($payment)), 'late payment requires review');
    assert_same(0, (int) Database::connection()->query("SELECT COUNT(*) FROM sms_notifications WHERE kind='payment_confirmation'")->fetchColumn(), 'review payment sends no success receipt');
    assert_same(0, SmsNotifications::process(fn () => throw new RuntimeException('Review receipt must not send')), 'no review receipt is processable');
}

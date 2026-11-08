<?php
declare(strict_types=1);

final class PaystackPayment
{
    public static function amount(string $price): int
    {
        if (!preg_match('/^(\d{1,8})(?:\.(\d{1,2}))?$/D', $price, $match)) { throw new InvalidArgumentException('Invalid payment amount.'); }
        return (int) $match[1] * 100 + (int) str_pad($match[2] ?? '', 2, '0');
    }

    public static function booking(int $id): array
    {
        $query = Database::connection()->prepare('SELECT b.*,c.email AS customer_email,c.phone AS customer_phone,c.name AS customer_name,s.name AS service_name FROM bookings b JOIN customers c ON c.id=b.customer_id JOIN services s ON s.id=b.service_id WHERE b.id=?');
        $query->execute([$id]);
        return $query->fetch() ?: throw new DomainException('Appointment not found.');
    }

    public static function start(int $id, ?callable $client = null, ?array $config = null): string
    {
        if ($client === null && !Paystack::ready()) { throw new DomainException('Online payments are currently unavailable.'); }
        $config ??= Paystack::config();
        $pdo = Database::connection();
        $payment = Database::transaction(function (PDO $pdo) use ($id, $config): array {
            $lock = $pdo->prepare('SELECT id FROM bookings WHERE id=?' . ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : ''));
            $lock->execute([$id]);
            $booking = self::booking($id);
            if ($booking['status'] !== 'confirmed' || $booking['slot_start'] <= gmdate('Y-m-d H:i:s') || $booking['payment_status'] === 'paid') { throw new DomainException('This appointment cannot accept another payment.'); }
            if (!filter_var($booking['customer_email'], FILTER_VALIDATE_EMAIL)) { throw new DomainException('An email address is required for Paystack payment.'); }
            if (self::amount((string) $booking['price']) <= 0) { throw new DomainException('This appointment does not require payment.'); }
            $query = $pdo->prepare('SELECT * FROM paystack_payments WHERE booking_id=?');
            $query->execute([$id]);
            if ($existing = $query->fetch()) { return $existing; }
            $reference = 'SPA-PAY-' . bin2hex(random_bytes(16));
            $mode = str_starts_with($config['secret_key'], 'sk_live_') ? 'live' : 'test';
            $pdo->prepare("INSERT INTO paystack_payments (booking_id,reference,amount_subunit,currency,email,mode,state,created_at) VALUES (?,?,?,'GHS',?,?,'created',?)")
                ->execute([$id,$reference,self::amount((string) $booking['price']),$booking['customer_email'],$mode,gmdate('Y-m-d H:i:s')]);
            return ['reference'=>$reference,'amount_subunit'=>self::amount((string) $booking['price']),'email'=>$booking['customer_email'],'state'=>'created','authorization_url'=>null];
        });
        if (!empty($payment['authorization_url'])) { return $payment['authorization_url']; }
        if ($payment['state'] !== 'created') { throw new DomainException('Checkout is being prepared or needs verification. Use Check payment status before trying again.'); }
        $claim = $pdo->prepare("UPDATE paystack_payments SET state='initializing' WHERE reference=? AND state='created'");
        $claim->execute([$payment['reference']]);
        if ($claim->rowCount() !== 1) { throw new DomainException('Checkout is already being prepared.'); }
        try {
            $data = ($client ?? [Paystack::class,'request'])('POST','/transaction/initialize',[
                'email'=>$payment['email'],'amount'=>$payment['amount_subunit'],'currency'=>'GHS','reference'=>$payment['reference'],
                'channels'=>['mobile_money'],'callback_url'=>$config['site_url'] . '/payment-return.php',
                'metadata'=>['booking_id'=>$id],
            ]);
            $url = (string) ($data['authorization_url'] ?? '');
            $parts = parse_url($url);
            if (($data['reference'] ?? '') !== $payment['reference'] || ($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== 'checkout.paystack.com' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) { throw new DomainException('Paystack returned an invalid checkout address.'); }
            $pdo->prepare("UPDATE paystack_payments SET authorization_url=?,state='pending' WHERE reference=? AND state='initializing'")->execute([$url,$payment['reference']]);
            return $url;
        } catch (Throwable $error) {
            $pdo->prepare("UPDATE paystack_payments SET state='initialization_unknown' WHERE reference=? AND state='initializing'")->execute([$payment['reference']]);
            throw new DomainException('We could not open checkout. Your appointment is saved. Check payment status or contact the spa before making another payment.');
        }
    }

    public static function verify(string $reference, ?callable $client = null): string
    {
        $query = Database::connection()->prepare('SELECT * FROM paystack_payments WHERE reference=?');
        $query->execute([$reference]);
        if (!$query->fetch()) { throw new DomainException('Payment reference not found.'); }
        $data = ($client ?? [Paystack::class,'request'])('GET','/transaction/verify/' . rawurlencode($reference),[]);
        return self::settle($reference, $data);
    }

    public static function settle(string $reference, array $data): string
    {
        $bookingId = null;
        $state = Database::transaction(function (PDO $pdo) use ($reference, $data, &$bookingId): string {
            $query = $pdo->prepare('SELECT * FROM paystack_payments WHERE reference=?' . ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : ''));
            $query->execute([$reference]);
            $payment = $query->fetch();
            if (!$payment) { throw new DomainException('Payment reference not found.'); }
            if (($data['status'] ?? '') !== 'success') { return 'pending'; }
            if (($data['reference'] ?? '') !== $reference || ($data['currency'] ?? '') !== 'GHS' || !is_int($data['amount'] ?? null) || $data['amount'] !== (int) $payment['amount_subunit'] || strtolower((string) ($data['customer']['email'] ?? '')) !== strtolower($payment['email']) || ($data['domain'] ?? '') !== $payment['mode'] || ($data['channel'] ?? '') !== 'mobile_money') {
                throw new DomainException('Payment verification details did not match the appointment. Contact the spa.');
            }
            if (in_array($payment['state'], ['paid','paid_review'], true)) { return $payment['state']; }
            $lock = $pdo->prepare('SELECT id FROM bookings WHERE id=?' . ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : ''));
            $lock->execute([$payment['booking_id']]);
            $booking = self::booking((int) $payment['booking_id']);
            $review = $booking['status'] !== 'confirmed' || $booking['payment_status'] === 'paid' || self::amount((string) $booking['price']) !== (int) $payment['amount_subunit'];
            $state = $review ? 'paid_review' : 'paid';
            $pdo->prepare('UPDATE paystack_payments SET state=?,paid_at=? WHERE id=?')->execute([$state,gmdate('Y-m-d H:i:s'),$payment['id']]);
            if ($booking['payment_status'] !== 'paid') {
                $pdo->prepare("UPDATE bookings SET payment_status='paid',payment_method='paystack_momo',updated_at=? WHERE id=?")->execute([gmdate('Y-m-d H:i:s'),$booking['id']]);
            }
            $pdo->prepare("INSERT INTO booking_events (booking_id,actor_type,admin_id,event_type,details,created_at) VALUES (?,'paystack',NULL,'payment_verified',?,?)")
                ->execute([$booking['id'],json_encode(['reference'=>$reference,'amount_subunit'=>(int)$payment['amount_subunit'],'mode'=>$payment['mode'],'review_required'=>$review],JSON_THROW_ON_ERROR),gmdate('Y-m-d H:i:s')]);
            if ($state === 'paid') {
                SmsNotifications::schedulePaymentConfirmation($booking, $pdo);
                $bookingId = (int) $booking['id'];
            }
            return $state;
        });
        if ($state === 'paid' && $bookingId !== null) { SmsNotifications::dispatchPaymentConfirmation($bookingId); }
        return $state;
    }
}

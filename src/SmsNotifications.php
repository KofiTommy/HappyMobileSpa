<?php
declare(strict_types=1);

final class SmsNotifications
{
    public static function schedule(array $booking, PDO $pdo, bool $confirmation = true, ?DateTimeImmutable $now = null): void
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        // Obsolete pending notifications never follow a booking to a new time.
        $pdo->prepare("UPDATE sms_notifications SET state='skipped',last_error='Appointment changed or cancelled.' WHERE booking_id=? AND state IN ('pending','failed') AND (slot_start<>? OR ?<>'confirmed')")
            ->execute([$booking['id'], $booking['slot_start'], $booking['status']]);
        if ($booking['status'] !== 'confirmed') { return; }
        $start = new DateTimeImmutable($booking['slot_start'], new DateTimeZone('UTC'));
        if ($start <= $now) { return; }
        $jobs = $confirmation ? ['confirmation'=>$now] : [];
        $reminder = $start->modify('-24 hours');
        if ($reminder > $now) { $jobs['reminder'] = $reminder; }
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        foreach ($jobs as $kind=>$due) {
            $sql = 'INSERT INTO sms_notifications (booking_id,kind,slot_start,due_at,state,attempts,created_at) VALUES (?,?,?,?,\'pending\',0,?)';
            $sql .= $driver === 'sqlite' ? ' ON CONFLICT(booking_id,kind,slot_start) DO NOTHING' : ' ON DUPLICATE KEY UPDATE id=id';
            $pdo->prepare($sql)->execute([$booking['id'], $kind, $booking['slot_start'], $due->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s')]);
        }
    }

    public static function text(array $booking, string $kind): string
    {
        $date = (new DateTimeImmutable($booking['slot_start'], new DateTimeZone('UTC')))->setTimezone(business_timezone());
        $prefix = $kind === 'reminder' ? 'Reminder' : 'Booking confirmed';
        $payment = ($booking['payment_status'] ?? 'unpaid') === 'paid' ? ' Payment recorded.' : (Paystack::ready() ? ' Payment pending.' : ' Payment at spa.');
        return 'Happy Mobile Spa: ' . $prefix . '. ' . $booking['service_name'] . ' on ' . $date->format('d M Y') . ' at ' . $date->format('g:i A') . ' (Ghana time). Ref ' . $booking['reference'] . '.' . $payment;
    }

    public static function process(?callable $sender = null, ?DateTimeImmutable $now = null, ?int $bookingId = null): int
    {
        if ($sender === null && !SmsConfig::ready()) { return 0; }
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $timestamp = $now->format('Y-m-d H:i:s');
        $pdo = Database::connection();
        // A crashed worker might have sent the SMS. Never blindly resubmit it.
        $pdo->prepare("UPDATE sms_notifications SET state='unknown',last_error='Worker interrupted. Check provider history before sending again.' WHERE state='processing' AND claimed_at<?")
            ->execute([$now->modify('-10 minutes')->format('Y-m-d H:i:s')]);
        $sql = "SELECT * FROM sms_notifications WHERE state='pending' AND due_at<=? AND (next_attempt_at IS NULL OR next_attempt_at<=?)";
        $params = [$timestamp, $timestamp];
        if ($bookingId !== null) { $sql .= " AND booking_id=? AND kind='confirmation'"; $params[] = $bookingId; }
        $query = $pdo->prepare($sql . ' ORDER BY due_at,id LIMIT 25');
        $query->execute($params);
        $sent = 0;
        foreach ($query->fetchAll() as $job) {
            $claim = $pdo->prepare("UPDATE sms_notifications SET state='processing',claimed_at=?,attempts=attempts+1 WHERE id=? AND state='pending'");
            $claim->execute([$timestamp, $job['id']]);
            if ($claim->rowCount() !== 1) { continue; }
            $queryBooking = $pdo->prepare('SELECT b.*,c.phone AS customer_phone,s.name AS service_name FROM bookings b JOIN customers c ON c.id=b.customer_id JOIN services s ON s.id=b.service_id WHERE b.id=?');
            $queryBooking->execute([$job['booking_id']]);
            $booking = $queryBooking->fetch();
            if (!$booking || $booking['status'] !== 'confirmed' || $booking['slot_start'] !== $job['slot_start'] || $booking['slot_start'] <= $timestamp || $job['due_at'] < $now->modify('-1 hour')->format('Y-m-d H:i:s')) {
                $pdo->prepare("UPDATE sms_notifications SET state='skipped',last_error='Appointment changed, cancelled, or notification expired.' WHERE id=?")->execute([$job['id']]);
                continue;
            }
            try {
                $result = ($sender ?? [BulkSms::class, 'send'])($booking['customer_phone'], self::text($booking, $job['kind']));
            } catch (Throwable $error) {
                $result = ['state'=>'unknown', 'error'=>'Sending interrupted. Check provider history before sending again.'];
            }
            $state = $result['state'] ?? 'unknown';
            if (!in_array($state, ['submitted','retry','failed','unknown'], true)) { $state = 'unknown'; }
            $next = null;
            if ($state === 'retry') {
                $state = (int) $job['attempts'] + 1 < 3 ? 'pending' : 'failed';
                $next = $now->modify('+5 minutes')->format('Y-m-d H:i:s');
            }
            $pdo->prepare('UPDATE sms_notifications SET state=?,next_attempt_at=?,provider_id=?,last_error=?,submitted_at=? WHERE id=?')
                ->execute([$state, $next, $result['provider_id'] ?? null, $result['error'] ?? null, $state === 'submitted' ? $timestamp : null, $job['id']]);
            if ($state === 'submitted') { $sent++; }
        }
        return $sent;
    }

    public static function dispatchConfirmation(int $id): void
    {
        try { self::process(null, null, $id); }
        catch (Throwable $error) { error_log('SMS confirmation deferred to background worker.'); }
    }

    public static function backfillReminders(): void
    {
        $pdo = Database::connection();
        $query = $pdo->prepare("SELECT id,slot_start,status FROM bookings WHERE status='confirmed' AND slot_start>? ORDER BY slot_start LIMIT 10000");
        $query->execute([gmdate('Y-m-d H:i:s', time() + 86400)]);
        foreach ($query->fetchAll() as $booking) { self::schedule($booking, $pdo, false); }
    }
}

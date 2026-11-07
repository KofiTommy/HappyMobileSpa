<?php
declare(strict_types=1);

final class Booking
{
    public static function create(array $input): array
    {
        $booking = self::persist(self::validate($input), [
            'actor' => 'customer', 'admin_id' => null, 'status' => 'confirmed',
            'payment_status' => 'unpaid', 'override' => false,
        ]);
        SmsNotifications::dispatchConfirmation((int) $booking['id']);
        return $booking;
    }

    public static function createManual(array $input, int $adminId, bool $override = false): array
    {
        $statement = Database::connection()->prepare('SELECT id, role FROM admin_users WHERE id=? AND active=1');
        $statement->execute([$adminId]);
        if (!($user = $statement->fetch())) {
            throw new InvalidArgumentException('Staff account not found');
        }
        if ($override) { Auth::assertAdmin($user); }
        $status = (string) ($input['status'] ?? 'confirmed');
        $paymentStatus = (string) ($input['payment_status'] ?? 'unpaid');
        if (!in_array($status, ['pending', 'confirmed'], true)) {
            throw new InvalidArgumentException('Manual booking status must be pending or confirmed');
        }
        if (!in_array($paymentStatus, ['paid', 'unpaid'], true)) {
            throw new InvalidArgumentException('Invalid payment status');
        }
        if (empty($input['start']) && !empty($input['local_start'])) {
            $local = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', (string) $input['local_start'], business_timezone());
            if ($local) {
                $input['start'] = $local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            }
        }
        $booking = self::persist(self::validate($input), [
            'actor' => 'admin', 'admin_id' => $adminId, 'status' => $status,
            'payment_status' => $paymentStatus, 'override' => $override,
        ]);
        SmsNotifications::dispatchConfirmation((int) $booking['id']);
        return $booking;
    }

    private static function persist(array $data, array $options): array
    {
        return Database::transaction(function (PDO $pdo) use ($data, $options): array {
            $candidates = $data['staff_id'] !== null
                ? [['id' => $data['staff_id']]]
                : Catalog::activeStaff($data['service_id']);
            usort($candidates, fn (array $a, array $b): int => (int) $a['id'] <=> (int) $b['id']);

            $staffId = null;
            $start = new DateTimeImmutable($data['start'], new DateTimeZone('UTC'));
            foreach ($candidates as $candidate) {
                $candidateId = (int) $candidate['id'];
                self::lockStaff($candidateId, $pdo);
                if (Availability::isAvailable($data['service_id'], $candidateId, $start, null, $pdo, true, $options['override'])) {
                    $staffId = $candidateId;
                    break;
                }
            }
            if ($staffId === null) {
                throw new DomainException('Slot is no longer available');
            }

            $service = self::service($data['service_id'], $pdo);
            $end = $start->modify('+' . (int) $service['duration_min'] . ' minutes');
            $customerId = self::upsertCustomer($data, $pdo);
            $reference = self::reference();
            $policy = self::settings($pdo)['cancellation_policy'] ?? 'Contact the spa before changing your appointment.';
            $now = gmdate('Y-m-d H:i:s');
            $statement = $pdo->prepare(
                'INSERT INTO bookings (reference,customer_id,service_id,staff_id,slot_start,slot_end,price,status,payment_method,payment_status,notes,accepted_policy,created_at,updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $statement->execute([
                $reference, $customerId, $data['service_id'], $staffId,
                $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'),
                $service['price'], $options['status'], 'pay_at_spa', $options['payment_status'], $data['notes'], $policy, $now, $now,
            ]);
            $bookingId = (int) $pdo->lastInsertId();
            $details = ['reference' => $reference];
            if ($options['override']) {
                $details['conflict_override'] = true;
                $details['reason'] = 'Administrator confirmed the booking conflict override';
            }
            self::event($bookingId, $options['actor'], 'created', $details, $pdo, $options['admin_id']);
            $booking = self::read($bookingId, $pdo);
            SmsNotifications::schedule($booking, $pdo);
            return $booking;
        });
    }

    public static function findForCustomer(string $reference, string $phone): ?array
    {
        $reference = strtoupper(trim($reference));
        $phone = self::phone($phone);
        $pdo = Database::connection();
        $statement = $pdo->prepare(
            'SELECT b.id, b.reference, c.phone FROM bookings b JOIN customers c ON c.id = b.customer_id WHERE b.reference = ?'
        );
        $statement->execute([$reference]);
        $booking = $statement->fetch();
        if (!$booking || !hash_equals($booking['reference'], $reference) || !hash_equals($booking['phone'], $phone)) {
            return null;
        }
        return self::read((int) $booking['id'], $pdo);
    }

    public static function rescheduleForCustomer(int $id, string $start): array
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $start, new DateTimeZone('UTC'));
        if (!$parsed || $parsed->format('Y-m-d H:i:s') !== $start) {
            throw new InvalidArgumentException('Choose a valid appointment time');
        }
        $updated = Database::transaction(function (PDO $pdo) use ($id, $parsed): array {
            $booking = self::read($id, $pdo);
            self::assertCustomerChange($booking, $pdo);
            self::lockStaff((int) $booking['staff_id'], $pdo);
            if (!Availability::isAvailable((int) $booking['service_id'], (int) $booking['staff_id'], $parsed, $id, $pdo)) {
                throw new DomainException('Slot is no longer available');
            }
            $end = $parsed->modify('+' . (int) $booking['duration_min'] . ' minutes');
            $statement = $pdo->prepare('UPDATE bookings SET slot_start = ?, slot_end = ?, updated_at = ? WHERE id = ?');
            $statement->execute([$parsed->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s'), $id]);
            self::event($id, 'customer', 'rescheduled', ['from' => $booking['slot_start'], 'to' => $parsed->format('Y-m-d H:i:s')], $pdo);
            $updated = self::read($id, $pdo);
            SmsNotifications::schedule($updated, $pdo);
            return $updated;
        });
        SmsNotifications::dispatchConfirmation($id);
        return $updated;
    }

    public static function cancelForCustomer(int $id): void
    {
        Database::transaction(function (PDO $pdo) use ($id): void {
            $booking = self::read($id, $pdo);
            self::assertCustomerChange($booking, $pdo);
            $statement = $pdo->prepare('UPDATE bookings SET status = \'cancelled\', updated_at = ? WHERE id = ?');
            $statement->execute([gmdate('Y-m-d H:i:s'), $id]);
            self::event($id, 'customer', 'cancelled', ['previous_status' => $booking['status']], $pdo);
            SmsNotifications::schedule(self::read($id, $pdo), $pdo);
        });
    }

    private static function assertCustomerChange(array $booking, PDO $pdo): void
    {
        if (!in_array($booking['status'], ['pending', 'confirmed'], true)) {
            throw new DomainException('This appointment can no longer be changed');
        }
        $settings = self::settings($pdo);
        $cutoff = (int) ($settings['cutoff_hours'] ?? 24);
        $start = new DateTimeImmutable($booking['slot_start'], new DateTimeZone('UTC'));
        if ($start <= new DateTimeImmutable('+' . $cutoff . ' hours', new DateTimeZone('UTC'))) {
            throw new DomainException('Contact the spa to change this appointment');
        }
    }

    public static function adminList(array $filters = []): array
    {
        $pdo = Database::connection();
        $sql = 'SELECT b.*, c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email,
                       s.name AS service_name, s.duration_min, st.name AS staff_name,
                       (SELECT MIN(be.created_at) FROM booking_events be WHERE be.booking_id=b.id AND be.event_type=\'checked_in\') AS checked_in_at
                FROM bookings b JOIN customers c ON c.id=b.customer_id JOIN services s ON s.id=b.service_id
                JOIN staff st ON st.id=b.staff_id WHERE 1=1';
        $params = [];
        if (!empty($filters['id'])) { $sql .= ' AND b.id = ?'; $params[] = (int) $filters['id']; }
        if (!empty($filters['date'])) { $sql .= ' AND DATE(b.slot_start) = ?'; $params[] = $filters['date']; }
        if (!empty($filters['start_date'])) {
            $sql .= ' AND DATE(b.slot_start) >= ?'; $params[] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $sql .= ' AND DATE(b.slot_start) <= ?'; $params[] = $filters['end_date'];
        }
        if (!empty($filters['status'])) { $sql .= ' AND b.status = ?'; $params[] = $filters['status']; }
        if (!empty($filters['staff_id'])) { $sql .= ' AND b.staff_id = ?'; $params[] = (int) $filters['staff_id']; }
        if (!empty($filters['search'])) {
            $sql .= ' AND (b.reference LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)';
            $term = '%' . $filters['search'] . '%';
            array_push($params, $term, $term, $term);
        }
        $sql .= ' ORDER BY b.slot_start ASC LIMIT 300';
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll();
        foreach ($rows as &$row) {
            $row['price'] = number_format((float) $row['price'], 2, '.', '');
        }
        return $rows;
    }

    public static function setStatus(int $id, string $status, int $adminId): void
    {
        Database::transaction(function (PDO $pdo) use ($id, $status, $adminId): void {
            $booking = self::read($id, $pdo);
            $allowed = [
                'pending' => ['confirmed', 'cancelled'],
                'confirmed' => ['completed', 'cancelled', 'no_show'],
                'completed' => [], 'cancelled' => [], 'no_show' => [],
            ];
            if (!in_array($status, $allowed[$booking['status']] ?? [], true)) {
                throw new DomainException('Invalid status transition');
            }
            $pdo->prepare('UPDATE bookings SET status = ?, updated_at = ? WHERE id = ?')
                ->execute([$status, gmdate('Y-m-d H:i:s'), $id]);
            self::event($id, 'admin', 'status_changed', ['from' => $booking['status'], 'to' => $status], $pdo, $adminId);
            SmsNotifications::schedule(self::read($id, $pdo), $pdo);
        });
        if ($status === 'confirmed') { SmsNotifications::dispatchConfirmation($id); }
    }

    public static function checkIn(int $id, int $userId): void
    {
        Database::transaction(function (PDO $pdo) use ($id, $userId): void {
            $sql = 'SELECT id FROM bookings WHERE id = ?';
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') { $sql .= ' FOR UPDATE'; }
            $statement = $pdo->prepare($sql);
            $statement->execute([$id]);
            $booking = self::read($id, $pdo);
            $date = (new DateTimeImmutable($booking['slot_start'], new DateTimeZone('UTC')))->setTimezone(business_timezone())->format('Y-m-d');
            if ($booking['status'] !== 'confirmed' || $date !== (new DateTimeImmutable('today', business_timezone()))->format('Y-m-d')) {
                throw new DomainException('Only confirmed appointments for today can be checked in.');
            }
            $statement = $pdo->prepare("SELECT id FROM booking_events WHERE booking_id = ? AND event_type = 'checked_in'");
            $statement->execute([$id]);
            if ($statement->fetch()) { throw new DomainException('This client is already checked in.'); }
            self::event($id, 'admin', 'checked_in', [], $pdo, $userId);
        });
    }

    public static function setPaymentStatus(int $id, string $status, int $adminId): void
    {
        if (!in_array($status, ['paid', 'unpaid'], true)) {
            throw new InvalidArgumentException('Invalid payment status');
        }
        Database::transaction(function (PDO $pdo) use ($id, $status, $adminId): void {
            $lock = $pdo->prepare('SELECT id FROM bookings WHERE id=?' . ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : ''));
            $lock->execute([$id]);
            $booking = self::read($id, $pdo);
            $online = $pdo->prepare("SELECT id FROM paystack_payments WHERE booking_id=? AND state IN ('paid','paid_review')");
            $online->execute([$id]);
            if ($booking['payment_method'] === 'paystack_momo' || $online->fetch()) { throw new DomainException('Paystack payments must be managed through Paystack. This action cannot refund or reverse a payment.'); }
            $pdo->prepare('UPDATE bookings SET payment_status = ?, updated_at = ? WHERE id = ?')
                ->execute([$status, gmdate('Y-m-d H:i:s'), $id]);
            self::event($id, 'admin', 'payment_changed', ['from' => $booking['payment_status'], 'to' => $status], $pdo, $adminId);
        });
    }

    public static function reference(int $length = 8): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($index = 0; $index < $length; $index++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return 'SPA-' . $code;
    }

    private static function validate(array $input): array
    {
        $data = [
            'service_id' => filter_var($input['service_id'] ?? null, FILTER_VALIDATE_INT),
            'staff_id' => !empty($input['staff_id']) ? filter_var($input['staff_id'], FILTER_VALIDATE_INT) : null,
            'start' => (string) ($input['start'] ?? ''),
            'name' => trim((string) ($input['name'] ?? '')),
            'phone' => self::phone((string) ($input['phone'] ?? '')),
            'email' => trim((string) ($input['email'] ?? '')),
            'notes' => trim((string) ($input['notes'] ?? '')),
        ];
        if (!$data['service_id'] || ($data['staff_id'] === false)) {
            throw new InvalidArgumentException('Choose a valid service and therapist');
        }
        $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $data['start'], new DateTimeZone('UTC'));
        if (!$start || $start->format('Y-m-d H:i:s') !== $data['start']) {
            throw new InvalidArgumentException('Choose a valid appointment time');
        }
        if (mb_strlen($data['name']) < 2 || mb_strlen($data['name']) > 160) {
            throw new InvalidArgumentException('Enter your full name');
        }
        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid email address');
        }
        if (mb_strlen($data['notes']) > 1000) {
            throw new InvalidArgumentException('Appointment notes must be 1000 characters or fewer');
        }
        if (($input['policy_accepted'] ?? null) !== '1') {
            throw new InvalidArgumentException('Accept the cancellation policy to continue');
        }
        return $data;
    }

    private static function phone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($digits, '233')) {
            $digits = '0' . substr($digits, 3);
        }
        if (!preg_match('/^0[2-5][0-9]{8}$/', $digits)) {
            throw new InvalidArgumentException('Enter a valid Ghana phone number');
        }
        return $digits;
    }

    private static function lockStaff(int $staffId, PDO $pdo): void
    {
        $sql = 'SELECT id FROM staff WHERE id = ? AND active = 1';
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        $statement = $pdo->prepare($sql);
        $statement->execute([$staffId]);
        if (!$statement->fetch()) {
            throw new DomainException('Slot is no longer available');
        }
    }

    private static function service(int $serviceId, PDO $pdo): array
    {
        $statement = $pdo->prepare('SELECT * FROM services WHERE id = ? AND active = 1 AND archived_at IS NULL');
        $statement->execute([$serviceId]);
        $service = $statement->fetch();
        if (!$service) {
            throw new InvalidArgumentException('Choose an available service');
        }
        return $service;
    }

    private static function settings(PDO $pdo): array
    {
        $rows = $pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }

    private static function upsertCustomer(array $data, PDO $pdo): int
    {
        $statement = $pdo->prepare('SELECT id FROM customers WHERE phone = ?');
        $statement->execute([$data['phone']]);
        $existing = $statement->fetch();
        $now = gmdate('Y-m-d H:i:s');
        if ($existing) {
            $statement = $pdo->prepare('UPDATE customers SET name = ?, email = NULLIF(?, \'\'), updated_at = ? WHERE id = ?');
            $statement->execute([$data['name'], $data['email'], $now, $existing['id']]);
            return (int) $existing['id'];
        }
        $statement = $pdo->prepare('INSERT INTO customers (phone,name,email,created_at,updated_at) VALUES (?,?,?,?,?)');
        $statement->execute([$data['phone'], $data['name'], $data['email'] ?: null, $now, $now]);
        return (int) $pdo->lastInsertId();
    }

    private static function event(int $bookingId, string $actor, string $type, array $details, PDO $pdo, ?int $adminId = null): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO booking_events (booking_id,actor_type,admin_id,event_type,details,created_at) VALUES (?,?,?,?,?,?)'
        );
        $statement->execute([$bookingId, $actor, $adminId, $type, json_encode($details, JSON_THROW_ON_ERROR), gmdate('Y-m-d H:i:s')]);
    }

    private static function read(int $bookingId, PDO $pdo): array
    {
        $statement = $pdo->prepare(
            'SELECT b.*, c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email,
                    s.name AS service_name, s.duration_min, st.name AS staff_name,
                    (SELECT be.actor_type FROM booking_events be WHERE be.booking_id=b.id ORDER BY be.id DESC LIMIT 1) AS last_event_actor,
                    (SELECT be.admin_id FROM booking_events be WHERE be.booking_id=b.id ORDER BY be.id DESC LIMIT 1) AS last_event_admin_id
             FROM bookings b JOIN customers c ON c.id = b.customer_id
             JOIN services s ON s.id = b.service_id JOIN staff st ON st.id = b.staff_id WHERE b.id = ?'
        );
        $statement->execute([$bookingId]);
        $booking = $statement->fetch();
        if (!$booking) {
            throw new RuntimeException('Booking could not be loaded');
        }
        $booking['price'] = number_format((float) $booking['price'], 2, '.', '');
        return $booking;
    }
}

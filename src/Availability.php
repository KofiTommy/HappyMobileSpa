<?php
declare(strict_types=1);

final class Availability
{
    public static function slots(int $serviceId, ?int $staffId, string $date): array
    {
        $localDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date, business_timezone());
        if (!$localDate || $localDate->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Invalid appointment date');
        }

        $staff = $staffId === null
            ? Catalog::activeStaff($serviceId)
            : array_values(array_filter(Catalog::activeStaff($serviceId), fn (array $person): bool => (int) $person['id'] === $staffId));
        usort($staff, fn (array $a, array $b): int => (int) $a['id'] <=> (int) $b['id']);

        $byStart = [];
        foreach ($staff as $person) {
            $dayStart = $localDate->setTime(0, 0);
            for ($minute = 0; $minute < 24 * 60; $minute += 15) {
                $localStart = $dayStart->modify('+' . $minute . ' minutes');
                $utcStart = $localStart->setTimezone(new DateTimeZone('UTC'));
                if (!self::isAvailable($serviceId, (int) $person['id'], $utcStart, null, null, false)) {
                    continue;
                }
                $key = $utcStart->format('Y-m-d H:i:s');
                if (isset($byStart[$key])) {
                    continue;
                }
                $service = self::service($serviceId);
                $utcEnd = $utcStart->modify('+' . $service['duration_min'] . ' minutes');
                $byStart[$key] = [
                    'staff_id' => (int) $person['id'],
                    'staff_name' => $person['name'],
                    'start' => $key,
                    'end' => $utcEnd->format('Y-m-d H:i:s'),
                    'label' => $localStart->format('g:i A'),
                ];
            }
        }
        ksort($byStart);
        return array_values($byStart);
    }

    public static function isAvailable(
        int $serviceId,
        int $staffId,
        DateTimeImmutable $start,
        ?int $ignoreBookingId = null,
        ?PDO $pdo = null,
        bool $rejectPast = true,
        bool $ignoreBookingConflicts = false
    ): bool {
        $pdo ??= Database::connection();
        $start = $start->setTimezone(new DateTimeZone('UTC'));
        if ($rejectPast && $start <= new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
            return false;
        }
        $service = self::service($serviceId, $pdo);
        if (!$service || !self::eligibleStaff($serviceId, $staffId, $pdo)) {
            return false;
        }

        $localStart = $start->setTimezone(business_timezone());
        $date = $localStart->format('Y-m-d');
        $weekday = (int) $localStart->format('w');
        $treatmentEnd = $start->modify('+' . $service['duration_min'] . ' minutes');
        $reservedEnd = $start->modify('+' . ($service['duration_min'] + $service['buffer_min']) . ' minutes');
        $localEnd = $reservedEnd->setTimezone(business_timezone());

        $statement = $pdo->prepare('SELECT open_time, close_time, closed FROM business_hours WHERE weekday = ?');
        $statement->execute([$weekday]);
        $business = $statement->fetch();
        if (!$business || $business['closed'] || !$business['open_time'] || !$business['close_time']) {
            return false;
        }
        if (!self::withinDayWindow($localStart, $localEnd, $business['open_time'], $business['close_time'])) {
            return false;
        }

        $statement = $pdo->prepare('SELECT COUNT(*) FROM holidays WHERE holiday_date = ?');
        $statement->execute([$date]);
        if ((int) $statement->fetchColumn() > 0) {
            return false;
        }
        $statement = $pdo->prepare('SELECT COUNT(*) FROM staff_time_off WHERE staff_id = ? AND off_date = ?');
        $statement->execute([$staffId, $date]);
        if ((int) $statement->fetchColumn() > 0) {
            return false;
        }

        $statement = $pdo->prepare('SELECT start_time, end_time FROM staff_hours WHERE staff_id = ? AND weekday = ?');
        $statement->execute([$staffId, $weekday]);
        $insideShift = false;
        foreach ($statement->fetchAll() as $shift) {
            if (self::withinDayWindow($localStart, $localEnd, $shift['start_time'], $shift['end_time'])) {
                $insideShift = true;
                break;
            }
        }
        if (!$insideShift) {
            return false;
        }

        $statement = $pdo->prepare('SELECT start_time, end_time FROM staff_breaks WHERE staff_id = ? AND weekday = ?');
        $statement->execute([$staffId, $weekday]);
        foreach ($statement->fetchAll() as $break) {
            $breakStart = self::atLocalTime($localStart, $break['start_time']);
            $breakEnd = self::atLocalTime($localStart, $break['end_time']);
            if ($localStart < $breakEnd && $localEnd > $breakStart) {
                return false;
            }
        }

        if ($ignoreBookingConflicts) {
            return true;
        }
        $queryStart = $start->modify('-1 day')->format('Y-m-d H:i:s');
        $statement = $pdo->prepare(
            "SELECT b.id, b.slot_start, b.slot_end, s.buffer_min
             FROM bookings b JOIN services s ON s.id = b.service_id
             WHERE b.staff_id = ? AND b.status IN ('pending','confirmed','completed')
             AND b.slot_start < ? AND b.slot_end > ?"
        );
        $statement->execute([$staffId, $reservedEnd->format('Y-m-d H:i:s'), $queryStart]);
        foreach ($statement->fetchAll() as $booking) {
            if ($ignoreBookingId !== null && (int) $booking['id'] === $ignoreBookingId) {
                continue;
            }
            $bookedStart = new DateTimeImmutable($booking['slot_start'], new DateTimeZone('UTC'));
            $bookedEnd = (new DateTimeImmutable($booking['slot_end'], new DateTimeZone('UTC')))
                ->modify('+' . (int) $booking['buffer_min'] . ' minutes');
            if ($bookedStart < $reservedEnd && $bookedEnd > $start) {
                return false;
            }
        }
        return true;
    }

    private static function service(int $serviceId, ?PDO $pdo = null): ?array
    {
        $pdo ??= Database::connection();
        $statement = $pdo->prepare('SELECT * FROM services WHERE id = ? AND active = 1 AND archived_at IS NULL');
        $statement->execute([$serviceId]);
        $service = $statement->fetch();
        if (!$service) {
            return null;
        }
        $service['duration_min'] = (int) $service['duration_min'];
        $service['buffer_min'] = (int) $service['buffer_min'];
        return $service;
    }

    private static function eligibleStaff(int $serviceId, int $staffId, PDO $pdo): bool
    {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM staff s JOIN staff_services ss ON ss.staff_id = s.id
             WHERE s.id = ? AND ss.service_id = ? AND s.active = 1'
        );
        $statement->execute([$staffId, $serviceId]);
        return (int) $statement->fetchColumn() === 1;
    }

    private static function withinDayWindow(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        string $open,
        string $close
    ): bool {
        $openAt = self::atLocalTime($start, $open);
        $closeAt = self::atLocalTime($start, $close);
        return $start >= $openAt && $end <= $closeAt && $start->format('Y-m-d') === $end->format('Y-m-d');
    }

    private static function atLocalTime(DateTimeImmutable $date, string $time): DateTimeImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($time, 0, 5)));
        return $date->setTime($hour, $minute, 0);
    }
}

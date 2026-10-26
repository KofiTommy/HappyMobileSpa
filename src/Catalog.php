<?php
declare(strict_types=1);

final class Catalog
{
    public static function activeServices(): array
    {
        $rows = Database::connection()->query(
            'SELECT * FROM services WHERE active = 1 AND archived_at IS NULL ORDER BY display_order, id'
        )->fetchAll();
        return array_map([self::class, 'presentService'], $rows);
    }

    public static function activeStaff(?int $serviceId = null): array
    {
        $sql = 'SELECT s.* FROM staff s WHERE s.active = 1';
        $params = [];
        if ($serviceId !== null) {
            $sql .= ' AND EXISTS (SELECT 1 FROM staff_services ss WHERE ss.staff_id = s.id AND ss.service_id = ?)';
            $params[] = $serviceId;
        }
        $sql .= ' ORDER BY s.name, s.id';
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public static function settings(): array
    {
        $rows = Database::connection()->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }

    public static function findService(string $slug): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM services WHERE slug = ? AND active = 1 AND archived_at IS NULL'
        );
        $statement->execute([$slug]);
        $service = $statement->fetch();
        return $service ? self::presentService($service) : null;
    }

    public static function adminServices(): array
    {
        $rows = Database::connection()->query('SELECT * FROM services ORDER BY display_order, id')->fetchAll();
        return array_map([self::class, 'presentService'], $rows);
    }

    public static function adminStaff(): array
    {
        $staff = Database::connection()->query('SELECT * FROM staff ORDER BY name, id')->fetchAll();
        $statement = Database::connection()->prepare('SELECT service_id FROM staff_services WHERE staff_id = ?');
        foreach ($staff as &$person) {
            $statement->execute([$person['id']]);
            $person['service_ids'] = array_map('intval', array_column($statement->fetchAll(), 'service_id'));
        }
        return $staff;
    }

    public static function schedule(int $staffId): array
    {
        $result = ['hours' => [], 'breaks' => []];
        foreach (['hours' => 'staff_hours', 'breaks' => 'staff_breaks'] as $key => $table) {
            $statement = Database::connection()->prepare("SELECT weekday, start_time, end_time FROM {$table} WHERE staff_id = ? ORDER BY weekday, start_time");
            $statement->execute([$staffId]);
            $result[$key] = $statement->fetchAll();
        }
        return $result;
    }

    public static function businessHours(): array
    {
        $rows = Database::connection()->query('SELECT * FROM business_hours ORDER BY weekday')->fetchAll();
        $hours = [];
        foreach ($rows as $row) {
            $row['open_time'] = $row['open_time'] ? substr($row['open_time'], 0, 5) : '';
            $row['close_time'] = $row['close_time'] ? substr($row['close_time'], 0, 5) : '';
            $hours[(int) $row['weekday']] = $row;
        }
        return $hours;
    }

    public static function holidays(): array
    {
        return Database::connection()->query('SELECT * FROM holidays ORDER BY holiday_date')->fetchAll();
    }

    public static function saveService(array $input, ?int $id = null): int
    {
        $name = trim((string) ($input['name'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $duration = filter_var($input['duration_min'] ?? null, FILTER_VALIDATE_INT);
        $buffer = filter_var($input['buffer_min'] ?? null, FILTER_VALIDATE_INT);
        if (mb_strlen($name) < 2 || mb_strlen($name) > 160 || $description === '') {
            throw new InvalidArgumentException('Enter a treatment name and description');
        }
        if ($duration === false || $duration < 15 || $duration > 480) {
            throw new InvalidArgumentException('Duration must be between 15 and 480 minutes');
        }
        if ($buffer === false || $buffer < 0 || $buffer > 120) {
            throw new InvalidArgumentException('Buffer must be between 0 and 120 minutes');
        }
        if (!is_numeric($input['price'] ?? null) || (float) $input['price'] < 0) {
            throw new InvalidArgumentException('Price must be zero or greater');
        }
        $price = number_format((float) $input['price'], 2, '.', '');
        $active = !empty($input['active']) ? 1 : 0;
        $order = filter_var($input['display_order'] ?? 0, FILTER_VALIDATE_INT);
        if ($order === false) {
            throw new InvalidArgumentException('Display order must be a whole number');
        }
        $pdo = Database::connection();
        if ($id !== null) {
            $statement = $pdo->prepare('SELECT id FROM services WHERE id = ?');
            $statement->execute([$id]);
            if (!$statement->fetch()) {
                throw new InvalidArgumentException('Treatment not found');
            }
            $statement = $pdo->prepare('UPDATE services SET name=?, description=?, duration_min=?, buffer_min=?, price=?, active=?, display_order=?, updated_at=? WHERE id=?');
            $statement->execute([$name, $description, $duration, $buffer, $price, $active, $order, gmdate('Y-m-d H:i:s'), $id]);
            return $id;
        }
        $slug = self::uniqueSlug($name, $pdo);
        $now = gmdate('Y-m-d H:i:s');
        $statement = $pdo->prepare('INSERT INTO services (name,slug,description,duration_min,buffer_min,price,active,display_order,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $statement->execute([$name, $slug, $description, $duration, $buffer, $price, $active, $order, $now, $now]);
        return (int) $pdo->lastInsertId();
    }

    public static function archiveService(int $id): void
    {
        $statement = Database::connection()->prepare('UPDATE services SET active=0, archived_at=?, updated_at=? WHERE id=?');
        $statement->execute([gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s'), $id]);
        if ($statement->rowCount() !== 1) {
            throw new InvalidArgumentException('Treatment not found');
        }
    }

    public static function saveStaff(array $input, ?int $id = null): int
    {
        $name = trim((string) ($input['name'] ?? ''));
        $biography = trim((string) ($input['biography'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 160) {
            throw new InvalidArgumentException('Enter a valid team member name');
        }
        $serviceIds = array_values(array_unique(array_map('intval', (array) ($input['service_ids'] ?? []))));
        if (!$serviceIds) {
            throw new InvalidArgumentException('Assign at least one treatment');
        }
        $pdo = Database::connection();
        Database::transaction(function (PDO $pdo) use (&$id, $name, $biography, $serviceIds, $input): void {
            $now = gmdate('Y-m-d H:i:s');
            if ($id === null) {
                $statement = $pdo->prepare('INSERT INTO staff (name,biography,active,created_at,updated_at) VALUES (?,?,?,?,?)');
                $statement->execute([$name, $biography, !empty($input['active']) ? 1 : 0, $now, $now]);
                $id = (int) $pdo->lastInsertId();
            } else {
                $statement = $pdo->prepare('UPDATE staff SET name=?, biography=?, active=?, updated_at=? WHERE id=?');
                $statement->execute([$name, $biography, !empty($input['active']) ? 1 : 0, $now, $id]);
                if ($statement->rowCount() !== 1 && !self::staffExists($id, $pdo)) {
                    throw new InvalidArgumentException('Team member not found');
                }
                $pdo->prepare('DELETE FROM staff_services WHERE staff_id=?')->execute([$id]);
            }
            $check = $pdo->prepare('SELECT id FROM services WHERE id=? AND archived_at IS NULL');
            $link = $pdo->prepare('INSERT INTO staff_services (staff_id,service_id) VALUES (?,?)');
            foreach ($serviceIds as $serviceId) {
                $check->execute([$serviceId]);
                if (!$check->fetch()) {
                    throw new InvalidArgumentException('Choose valid treatments for this team member');
                }
                $link->execute([$id, $serviceId]);
            }
        });
        return (int) $id;
    }

    public static function saveSchedule(int $staffId, array $hours, array $breaks): void
    {
        if (!self::staffExists($staffId, Database::connection())) {
            throw new InvalidArgumentException('Team member not found');
        }
        $hours = self::validateIntervals($hours, 'Working hours');
        $breaks = self::validateIntervals($breaks, 'Breaks');
        Database::transaction(function (PDO $pdo) use ($staffId, $hours, $breaks): void {
            $pdo->prepare('DELETE FROM staff_hours WHERE staff_id=?')->execute([$staffId]);
            $pdo->prepare('DELETE FROM staff_breaks WHERE staff_id=?')->execute([$staffId]);
            $insertHours = $pdo->prepare('INSERT INTO staff_hours (staff_id,weekday,start_time,end_time) VALUES (?,?,?,?)');
            $insertBreaks = $pdo->prepare('INSERT INTO staff_breaks (staff_id,weekday,start_time,end_time) VALUES (?,?,?,?)');
            foreach ($hours as $row) { $insertHours->execute([$staffId, $row['weekday'], $row['start_time'], $row['end_time']]); }
            foreach ($breaks as $row) { $insertBreaks->execute([$staffId, $row['weekday'], $row['start_time'], $row['end_time']]); }
        });
    }

    public static function saveSettings(array $input): void
    {
        if (isset($input['cutoff_hours'])) {
            $cutoff = filter_var($input['cutoff_hours'], FILTER_VALIDATE_INT);
            if ($cutoff === false || $cutoff < 0 || $cutoff > 168) {
                throw new InvalidArgumentException('Cutoff must be between 0 and 168 hours');
            }
            $input['cutoff_hours'] = (string) $cutoff;
        }
        if (isset($input['timezone']) && $input['timezone'] !== 'Africa/Accra') {
            throw new InvalidArgumentException('Timezone must remain Africa/Accra');
        }
        if (isset($input['email']) && $input['email'] !== '' && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid business email address');
        }
        $hours = isset($input['business_hours']) ? self::validateBusinessHours((array) $input['business_hours']) : null;
        $holidays = isset($input['holidays']) ? self::validateHolidays((array) $input['holidays']) : null;
        $allowed = ['cutoff_hours', 'phone', 'email', 'address', 'timezone', 'cancellation_policy', 'opening_text'];
        Database::transaction(function (PDO $pdo) use ($input, $allowed, $hours, $holidays): void {
            $exists = $pdo->prepare('SELECT setting_key FROM settings WHERE setting_key=?');
            $update = $pdo->prepare('UPDATE settings SET setting_value=? WHERE setting_key=?');
            $insert = $pdo->prepare('INSERT INTO settings (setting_key,setting_value) VALUES (?,?)');
            foreach ($allowed as $key) {
                if (!array_key_exists($key, $input)) { continue; }
                $value = trim((string) $input[$key]);
                $exists->execute([$key]);
                if ($exists->fetch()) {
                    $update->execute([$value, $key]);
                } else {
                    $insert->execute([$key, $value]);
                }
            }
            if ($hours !== null) {
                $pdo->exec('DELETE FROM business_hours');
                $statement = $pdo->prepare('INSERT INTO business_hours (weekday,open_time,close_time,closed) VALUES (?,?,?,?)');
                foreach ($hours as $day => $row) { $statement->execute([$day, $row['open_time'], $row['close_time'], $row['closed']]); }
            }
            if ($holidays !== null) {
                $pdo->exec('DELETE FROM holidays');
                $statement = $pdo->prepare('INSERT INTO holidays (holiday_date,name) VALUES (?,?)');
                foreach ($holidays as $row) { $statement->execute([$row['holiday_date'], $row['name']]); }
            }
        });
    }

    private static function uniqueSlug(string $name, PDO $pdo): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
        if ($base === '') { throw new InvalidArgumentException('Treatment name must contain letters or numbers'); }
        $slug = $base;
        $suffix = 2;
        $statement = $pdo->prepare('SELECT COUNT(*) FROM services WHERE slug=?');
        while (true) {
            $statement->execute([$slug]);
            if ((int) $statement->fetchColumn() === 0) { return $slug; }
            $slug = $base . '-' . $suffix++;
        }
    }

    private static function staffExists(int $staffId, PDO $pdo): bool
    {
        $statement = $pdo->prepare('SELECT id FROM staff WHERE id=?');
        $statement->execute([$staffId]);
        return (bool) $statement->fetch();
    }

    private static function validateIntervals(array $rows, string $label): array
    {
        $valid = [];
        foreach ($rows as $row) {
            $day = filter_var($row['weekday'] ?? null, FILTER_VALIDATE_INT);
            $start = (string) ($row['start_time'] ?? '');
            $end = (string) ($row['end_time'] ?? '');
            if ($start === '' && $end === '') { continue; }
            if ($day === false || $day < 0 || $day > 6 || !self::validTime($start) || !self::validTime($end) || $start >= $end) {
                throw new InvalidArgumentException($label . ' must have a valid weekday and start/end time');
            }
            $valid[] = ['weekday' => $day, 'start_time' => $start, 'end_time' => $end];
        }
        foreach ($valid as $index => $row) {
            foreach (array_slice($valid, $index + 1) as $other) {
                if ($row['weekday'] === $other['weekday'] && $row['start_time'] < $other['end_time'] && $row['end_time'] > $other['start_time']) {
                    throw new InvalidArgumentException($label === 'Breaks' ? 'Breaks cannot overlap' : 'Working hours cannot overlap');
                }
            }
        }
        return $valid;
    }

    private static function validateBusinessHours(array $rows): array
    {
        $valid = [];
        for ($day = 0; $day <= 6; $day++) {
            $row = (array) ($rows[$day] ?? []);
            $closed = !empty($row['closed']) ? 1 : 0;
            $open = $closed ? null : (string) ($row['open_time'] ?? '');
            $close = $closed ? null : (string) ($row['close_time'] ?? '');
            if (!$closed && (!self::validTime($open) || !self::validTime($close) || $open >= $close)) {
                throw new InvalidArgumentException('Enter valid opening and closing times for every open day');
            }
            $valid[$day] = ['closed' => $closed, 'open_time' => $open, 'close_time' => $close];
        }
        return $valid;
    }

    private static function validateHolidays(array $rows): array
    {
        $valid = [];
        $dates = [];
        foreach ($rows as $row) {
            $date = (string) ($row['holiday_date'] ?? '');
            $name = trim((string) ($row['name'] ?? ''));
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$parsed || $parsed->format('Y-m-d') !== $date || $name === '' || in_array($date, $dates, true)) {
                throw new InvalidArgumentException('Each holiday needs a unique valid date and name');
            }
            $dates[] = $date;
            $valid[] = ['holiday_date' => $date, 'name' => $name];
        }
        return $valid;
    }

    private static function validTime(string $time): bool
    {
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) { return false; }
        return true;
    }

    private static function presentService(array $service): array
    {
        $service['price'] = number_format((float) $service['price'], 2, '.', '');
        $service['duration_min'] = (int) $service['duration_min'];
        $service['buffer_min'] = (int) $service['buffer_min'];
        $service['id'] = (int) $service['id'];
        return $service;
    }
}

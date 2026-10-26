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

    private static function presentService(array $service): array
    {
        $service['price'] = number_format((float) $service['price'], 2, '.', '');
        $service['duration_min'] = (int) $service['duration_min'];
        $service['buffer_min'] = (int) $service['buffer_min'];
        $service['id'] = (int) $service['id'];
        return $service;
    }
}

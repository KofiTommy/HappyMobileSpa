<?php
declare(strict_types=1);

// Explicit administrative migration. Never run schema changes on public requests.
final class SecurityMigration
{
    public static function apply(PDO $pdo): void
    {
        $accountColumns = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? array_column($pdo->query('PRAGMA table_info(admin_users)')->fetchAll(), 'name')
            : $pdo->query('SHOW COLUMNS FROM admin_users')->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('session_version', $accountColumns, true)) {
            $pdo->exec('ALTER TABLE admin_users ADD COLUMN session_version INT NOT NULL DEFAULT 1');
        }
        $columns = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? array_column($pdo->query('PRAGMA table_info(bookings)')->fetchAll(), 'name')
            : $pdo->query('SHOW COLUMNS FROM bookings')->fetchAll(PDO::FETCH_COLUMN);
        foreach (['contact_name'=>160, 'contact_phone'=>30, 'contact_email'=>190] as $column => $length) {
            if (!in_array($column, $columns, true)) {
                $pdo->exec("ALTER TABLE bookings ADD COLUMN {$column} VARCHAR({$length}) NULL");
            }
        }
        $pdo->exec("UPDATE bookings SET contact_name=(SELECT name FROM customers WHERE customers.id=bookings.customer_id) WHERE contact_name IS NULL");
        $pdo->exec("UPDATE bookings SET contact_phone=(SELECT phone FROM customers WHERE customers.id=bookings.customer_id) WHERE contact_phone IS NULL");
        $pdo->exec("UPDATE bookings SET contact_email=COALESCE((SELECT email FROM customers WHERE customers.id=bookings.customer_id),'') WHERE contact_email IS NULL");
        $pdo->exec('CREATE TABLE IF NOT EXISTS uploaded_photos (path VARCHAR(255) PRIMARY KEY, contents MEDIUMBLOB NOT NULL)');
    }
}

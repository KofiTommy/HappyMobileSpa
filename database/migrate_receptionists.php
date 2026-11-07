<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
if (PHP_SAPI !== 'cli') { throw new RuntimeException('Run this script from the command line.'); }
$pdo = Database::connection();
$columns = $pdo->query('SHOW COLUMNS FROM admin_users')->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('role', $columns, true)) {
    $pdo->exec("ALTER TABLE admin_users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'admin' AFTER password_hash");
    echo "Added account roles. Existing accounts remain administrators.\n";
} else {
    echo "Account roles already installed.\n";
}

if (!in_array('staff_id', $columns, true)) {
    $pdo->exec('ALTER TABLE admin_users ADD COLUMN staff_id INT UNSIGNED NULL UNIQUE, ADD CONSTRAINT fk_admin_staff FOREIGN KEY(staff_id) REFERENCES staff(id)');
    echo "Added team member login links.\n";
}

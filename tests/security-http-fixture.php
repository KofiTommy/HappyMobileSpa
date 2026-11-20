<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/bootstrap.php';
seed_availability_fixture();
seed_admin('audit-owner', password_hash('isolated test password', PASSWORD_DEFAULT));
$id = seed_admin('audit-reception', password_hash('isolated test password', PASSWORD_DEFAULT));
Database::connection()->prepare("UPDATE admin_users SET role='receptionist' WHERE id=?")->execute([$id]);
Database::connection()->exec('VACUUM INTO ' . Database::connection()->quote($argv[1]));
echo fixture_date();

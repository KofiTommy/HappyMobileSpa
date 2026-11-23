<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/src/bootstrap.php';
$pdo = Database::connection();
$columns = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
    ? array_column($pdo->query('PRAGMA table_info(staff)')->fetchAll(), 'name')
    : $pdo->query('SHOW COLUMNS FROM staff')->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('video_url', $columns, true)) { $pdo->exec('ALTER TABLE staff ADD COLUMN video_url VARCHAR(2048) NULL'); }
echo "Therapist video links enabled. Existing profile pictures preserved.\n";

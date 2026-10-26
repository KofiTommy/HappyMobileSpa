<?php
declare(strict_types=1);

return [
    'db_dsn' => getenv('SPA_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=happy_mobile_spa;charset=utf8mb4',
    'db_user' => getenv('SPA_DB_USER') ?: 'root',
    'db_pass' => getenv('SPA_DB_PASS') ?: '',
    'timezone' => 'Africa/Accra',
    'environment' => getenv('SPA_ENV') ?: 'development',
];

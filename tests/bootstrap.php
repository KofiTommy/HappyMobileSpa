<?php
declare(strict_types=1);

putenv('SPA_DB_DSN=sqlite::memory:');
require dirname(__DIR__) . '/src/bootstrap.php';
require __DIR__ . '/DatabaseTest.php';

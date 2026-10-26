<?php
declare(strict_types=1);

putenv('SPA_DB_DSN=sqlite::memory:');
require dirname(__DIR__) . '/src/bootstrap.php';

function assert_contains(string $needle, string $haystack, string $label): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($label . ': missing ' . $needle);
    }
}

function assert_not_contains(string $needle, string $haystack, string $label): void
{
    if (str_contains($haystack, $needle)) {
        throw new RuntimeException($label . ': unexpectedly found ' . $needle);
    }
}

require __DIR__ . '/DatabaseTest.php';
require __DIR__ . '/PublicPagesTest.php';

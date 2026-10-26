<?php
declare(strict_types=1);

const APP_ROOT = __DIR__ . '/..';
$config = is_file(APP_ROOT . '/config/config.php')
    ? require APP_ROOT . '/config/config.php'
    : require APP_ROOT . '/config/config.example.php';

function config(string $key, mixed $default = null): mixed
{
    global $config;
    return $config[$key] ?? $default;
}

function escape(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function assert_same(mixed $expected, mixed $actual, string $label): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $label . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)
        );
    }
}

spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/' . basename(str_replace('\\', '/', $class)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require __DIR__ . '/view.php';
date_default_timezone_set('UTC');

if (PHP_SAPI !== 'cli') {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

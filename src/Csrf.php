<?php
declare(strict_types=1);

final class Csrf
{
    private static ?string $cliToken = null;

    public static function token(): string
    {
        if (PHP_SAPI === 'cli') {
            return self::$cliToken ??= bin2hex(random_bytes(32));
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function verify(?string $token): bool
    {
        if (!is_string($token)) {
            return false;
        }
        if (PHP_SAPI === 'cli') {
            return self::$cliToken !== null && hash_equals(self::$cliToken, $token);
        }
        return session_status() === PHP_SESSION_ACTIVE
            && isset($_SESSION['csrf'])
            && hash_equals($_SESSION['csrf'], $token);
    }
}

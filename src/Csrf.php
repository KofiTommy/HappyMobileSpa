<?php
declare(strict_types=1);

final class Csrf
{
    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function verify(?string $token): bool
    {
        return session_status() === PHP_SESSION_ACTIVE
            && is_string($token)
            && isset($_SESSION['csrf'])
            && hash_equals($_SESSION['csrf'], $token);
    }
}

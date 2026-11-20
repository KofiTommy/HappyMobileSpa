<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $dsn = (string) config('db_dsn');
            if (config('environment') === 'production' && str_starts_with($dsn, 'mysql:')
                && (strtolower((string) config('db_user')) === 'root' || (string) config('db_pass') === '')) {
                throw new RuntimeException('Production requires a dedicated database user and password.');
            }
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            if (str_starts_with($dsn, 'mysql:')) {
                $options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci';
            }
            self::$pdo = new PDO($dsn, config('db_user'), config('db_pass'), $options);
        }

        return self::$pdo;
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        $pdo->beginTransaction();

        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $error;
        }
    }
}

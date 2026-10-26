<?php
declare(strict_types=1);

final class RateLimit
{
    public static function check(string $action, string $key, int $max = 8, int $seconds = 900): void
    {
        $pdo = Database::connection();
        $hash = hash('sha256', $key);
        $now = gmdate('Y-m-d H:i:s');
        $statement = $pdo->prepare('SELECT * FROM rate_limits WHERE action = ? AND key_hash = ?');
        $statement->execute([$action, $hash]);
        $record = $statement->fetch();
        if ($record && $record['blocked_until'] && $record['blocked_until'] > $now) {
            throw new DomainException('Too many attempts. Please try again later.');
        }
        if (!$record || $record['window_start'] <= gmdate('Y-m-d H:i:s', time() - $seconds)) {
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $sql = 'INSERT INTO rate_limits (action,key_hash,attempts,window_start,blocked_until) VALUES (?,?,1,?,NULL)
                        ON CONFLICT(action,key_hash) DO UPDATE SET attempts=1,window_start=excluded.window_start,blocked_until=NULL';
            } else {
                $sql = 'INSERT INTO rate_limits (action,key_hash,attempts,window_start,blocked_until) VALUES (?,?,1,?,NULL)
                        ON DUPLICATE KEY UPDATE attempts = 1, window_start = VALUES(window_start), blocked_until = NULL';
            }
            $statement = $pdo->prepare($sql);
            $statement->execute([$action, $hash, $now]);
            return;
        }
        $attempts = (int) $record['attempts'] + 1;
        $blockedUntil = $attempts > $max ? gmdate('Y-m-d H:i:s', time() + $seconds) : null;
        $statement = $pdo->prepare('UPDATE rate_limits SET attempts = ?, blocked_until = ? WHERE id = ?');
        $statement->execute([$attempts, $blockedUntil, $record['id']]);
        if ($attempts > $max) {
            throw new DomainException('Too many attempts. Please try again later.');
        }
    }
}

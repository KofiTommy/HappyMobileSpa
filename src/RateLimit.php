<?php
declare(strict_types=1);

final class RateLimit
{
    public static function check(string $action, string $key, int $max = 8, int $seconds = 900): void
    {
        $pdo = Database::connection();
        $hash = hash('sha256', $key);
        if ($max < 1 || $seconds < 1) { throw new InvalidArgumentException('Invalid rate limit.'); }
        $now = gmdate('Y-m-d H:i:s');
        $cutoff = gmdate('Y-m-d H:i:s', time() - $seconds);
        $conflict = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'ON CONFLICT(action,key_hash) DO UPDATE SET' : 'ON DUPLICATE KEY UPDATE';
        // Increment in the database: concurrent requests must not lose attempts.
        $sql = 'INSERT INTO rate_limits (action,key_hash,attempts,window_start,blocked_until) VALUES (?,?,1,?,NULL) '
            . $conflict . ' attempts=CASE WHEN window_start<=? THEN 1 WHEN attempts<? THEN attempts+1 ELSE attempts END,'
            . 'window_start=CASE WHEN window_start<=? THEN ? ELSE window_start END,blocked_until=NULL';
        $pdo->prepare($sql)->execute([$action, $hash, $now, $cutoff, $max + 1, $cutoff, $now]);
        $statement = $pdo->prepare('SELECT attempts FROM rate_limits WHERE action=? AND key_hash=?');
        $statement->execute([$action, $hash]);
        if ((int) $statement->fetchColumn() > $max) {
            if (PHP_SAPI !== 'cli') {
                http_response_code(429);
                header('Retry-After: ' . $seconds);
            }
            throw new RateLimitException('Too many attempts. Please try again later.');
        }
    }
}

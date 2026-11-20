<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/src/bootstrap.php';
$pdo = Database::connection();
if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') { throw new RuntimeException('This check requires MySQL or MariaDB.'); }
$action = 'security-test-' . bin2hex(random_bytes(8));
try {
    RateLimit::check($action, 'isolated-key', 2, 900);
    RateLimit::check($action, 'isolated-key', 2, 900);
    $blocked = false;
    try { RateLimit::check($action, 'isolated-key', 2, 900); }
    catch (RateLimitException) { $blocked = true; }
    assert_same(true, $blocked, 'native MySQL limiter blocks request above limit');
    $pdo->prepare("UPDATE rate_limits SET window_start='2000-01-01 00:00:00' WHERE action=?")->execute([$action]);
    RateLimit::check($action, 'isolated-key', 2, 900);
    $query = $pdo->prepare('SELECT attempts FROM rate_limits WHERE action=?');
    $query->execute([$action]);
    assert_same(1, (int) $query->fetchColumn(), 'native MySQL limiter resets expired window');
    echo "PASS MySQL/MariaDB atomic rate limiter and window expiry.\n";
} finally {
    $pdo->prepare('DELETE FROM rate_limits WHERE action=?')->execute([$action]);
}

<?php
declare(strict_types=1);

final class Auth
{
    public static function attempt(string $username, string $password): bool
    {
        $username = trim($username);
        RateLimit::check('admin-login-ip', (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'), 40, 900);
        RateLimit::check('admin-login-account', strtolower($username), 20, 900);
        RateLimit::check('admin-login', $username . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'cli'), 8, 900);
        if (strlen($username) > 80 || strlen($password) > 72) { return false; }
        $statement = Database::connection()->prepare('SELECT * FROM admin_users WHERE username = ? AND active = 1');
        $statement->execute([$username]);
        $admin = $statement->fetch();
        $verified = password_verify($password, $admin['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (!$admin || !$verified) {
            return false;
        }
        if (!in_array($admin['role'], ['admin','receptionist'], true)) { return false; }
        if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $update = Database::connection()->prepare('UPDATE admin_users SET password_hash=? WHERE id=? AND password_hash=?');
            $update->execute([$hash, $admin['id'], $admin['password_hash']]);
            if ($update->rowCount() !== 1) { return false; }
            $admin['password_hash'] = $hash;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_issued_at'] = time();
        $_SESSION['admin_last_seen'] = time();
        $_SESSION['admin_credentials'] = self::credentials($admin);
        unset($_SESSION['csrf']);
        Database::connection()->prepare('UPDATE admin_users SET last_login_at = ? WHERE id = ?')
            ->execute([gmdate('Y-m-d H:i:s'), $admin['id']]);
        return true;
    }

    public static function requireUser(): array
    {
        $id = (int) ($_SESSION['admin_id'] ?? 0);
        $statement = Database::connection()->prepare('SELECT id, username, role, password_hash, session_version FROM admin_users WHERE id = ? AND active = 1');
        $statement->execute([$id]);
        $admin = $statement->fetch();
        if (!$admin || !in_array($admin['role'], ['admin','receptionist'], true) || !self::sessionValid($admin)) {
            if ($id > 0) { self::logout(); }
            if (PHP_SAPI === 'cli') {
                throw new RuntimeException('Authentication required');
            }
            header('Location: login.php');
            exit;
        }
        $_SESSION['admin_last_seen'] = time();
        unset($admin['password_hash']);
        return $admin;
    }

    private static function credentials(array $admin): string
    {
        return hash('sha256', $admin['password_hash'] . '|' . ($admin['role'] ?? 'admin') . '|' . ($admin['session_version'] ?? 1));
    }

    public static function sessionValid(array $admin): bool
    {
        return (int) ($_SESSION['admin_issued_at'] ?? 0) > time() - 28800
            && (int) ($_SESSION['admin_last_seen'] ?? 0) > time() - 1800
            && hash_equals(self::credentials($admin), (string) ($_SESSION['admin_credentials'] ?? ''));
    }

    public static function requireAdmin(): array
    {
        $user = self::requireUser();
        self::assertAdmin($user);
        return $user;
    }

    public static function assertAdmin(array $user): void
    {
        if (($user['role'] ?? '') !== 'admin') {
            if (PHP_SAPI !== 'cli') { http_response_code(403); }
            throw new DomainException('Administrator access required.');
        }
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}

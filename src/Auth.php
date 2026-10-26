<?php
declare(strict_types=1);

final class Auth
{
    public static function attempt(string $username, string $password): bool
    {
        $username = trim($username);
        RateLimit::check('admin-login', $username . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'cli'), 8, 900);
        $statement = Database::connection()->prepare('SELECT * FROM admin_users WHERE username = ? AND active = 1');
        $statement->execute([$username]);
        $admin = $statement->fetch();
        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            return false;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        Database::connection()->prepare('UPDATE admin_users SET last_login_at = ? WHERE id = ?')
            ->execute([gmdate('Y-m-d H:i:s'), $admin['id']]);
        return true;
    }

    public static function requireUser(): array
    {
        $id = (int) ($_SESSION['admin_id'] ?? 0);
        $statement = Database::connection()->prepare('SELECT id, username FROM admin_users WHERE id = ? AND active = 1');
        $statement->execute([$id]);
        $admin = $statement->fetch();
        if (!$admin) {
            if (PHP_SAPI === 'cli') {
                throw new RuntimeException('Authentication required');
            }
            header('Location: login.php');
            exit;
        }
        return $admin;
    }

    public static function logout(): void
    {
        unset($_SESSION['admin_id'], $_SESSION['admin_username']);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}

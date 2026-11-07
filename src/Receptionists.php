<?php
declare(strict_types=1);

final class Receptionists
{
    public static function manage(array $actor, array $input): void
    {
        Auth::assertAdmin($actor);
        $pdo = Database::connection();
        $action = (string) ($input['action'] ?? '');
        $password = (string) ($input['password'] ?? '');
        if (in_array($action, ['create', 'password'], true) && (strlen($password) < 12 || strlen($password) > 72)) {
            throw new InvalidArgumentException('Use a password between 12 and 72 characters.');
        }
        $now = gmdate('Y-m-d H:i:s');
        if ($action === 'create') {
            $staffId = (int) ($input['staff_id'] ?? 0);
            $query = $pdo->prepare('SELECT id FROM staff WHERE id = ?');
            $query->execute([$staffId]);
            if (!$query->fetch()) { throw new DomainException('Choose an existing team member.'); }
            $query = $pdo->prepare('SELECT id FROM admin_users WHERE staff_id = ?');
            $query->execute([$staffId]);
            if ($query->fetch()) { throw new DomainException('This team member already has a login.'); }

            $username = trim((string) ($input['username'] ?? ''));
            if (!preg_match('/^[A-Za-z0-9_.-]{3,80}$/', $username)) {
                throw new InvalidArgumentException('Username must be 3–80 letters, numbers, dots, dashes, or underscores.');
            }
            $query = $pdo->prepare('SELECT id FROM admin_users WHERE username = ?');
            $query->execute([$username]);
            if ($query->fetch()) { throw new DomainException('That username already exists.'); }
            try {
                $pdo->prepare("INSERT INTO admin_users (username,password_hash,role,active,created_at,updated_at,staff_id) VALUES (?,?,'receptionist',1,?,?,?)")
                    ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $now, $now, $staffId]);
            } catch (PDOException $error) {
                if (in_array((string) $error->getCode(), ['23000', '23505'], true)) { throw new DomainException('That username already exists.'); }
                throw $error;
            }
            return;
        }
        $id = (int) ($input['user_id'] ?? 0);
        $query = $pdo->prepare("SELECT id FROM admin_users WHERE id = ? AND role = 'receptionist'");
        $query->execute([$id]);
        if (!$query->fetch()) { throw new DomainException('Receptionist account not found.'); }
        if ($action === 'password') {
            $pdo->prepare("UPDATE admin_users SET password_hash=?, updated_at=? WHERE id=? AND role='receptionist'")
                ->execute([password_hash($password, PASSWORD_DEFAULT), $now, $id]);
        } elseif ($action === 'active') {
            $pdo->prepare("UPDATE admin_users SET active=?, updated_at=? WHERE id=? AND role='receptionist'")
                ->execute([($input['active'] ?? '') === '1' ? 1 : 0, $now, $id]);
        } else {
            throw new InvalidArgumentException('Choose a valid account action.');
        }
    }
}

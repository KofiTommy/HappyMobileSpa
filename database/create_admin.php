<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('Run this script from the command line.');
}
$username = trim((string) readline('Admin username: '));
if (!preg_match('/^[A-Za-z0-9_.-]{3,80}$/', $username)) {
    throw new InvalidArgumentException('Username must be 3–80 letters, numbers, dots, dashes, or underscores.');
}
$password = (string) (getenv('SPA_ADMIN_PASSWORD') ?: '');
if ($password === '' && DIRECTORY_SEPARATOR === '\\') {
    fwrite(STDOUT, 'Admin password: ');
    $command = 'powershell -NoProfile -Command "$p=Read-Host -AsSecureString; [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($p))"';
    $password = trim((string) shell_exec($command));
} elseif ($password === '') {
    fwrite(STDOUT, 'Admin password: ');
    shell_exec('stty -echo');
    $password = trim((string) fgets(STDIN));
    shell_exec('stty echo');
    fwrite(STDOUT, PHP_EOL);
}
if (strlen($password) < 12) {
    throw new InvalidArgumentException('Password must contain at least 12 characters.');
}
$statement = Database::connection()->prepare('SELECT id FROM admin_users WHERE username = ?');
$statement->execute([$username]);
if ($statement->fetch()) {
    throw new RuntimeException('That username already exists.');
}
$statement = Database::connection()->prepare('INSERT INTO admin_users (username,password_hash,active,created_at,updated_at) VALUES (?,?,1,?,?)');
$statement->execute([$username, password_hash($password, PASSWORD_DEFAULT), gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s')]);
echo "Admin user created.\n";

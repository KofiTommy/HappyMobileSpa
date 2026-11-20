<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
if (!empty($_SESSION['admin_id'])) {
    $query = Database::connection()->prepare('SELECT password_hash,role,session_version FROM admin_users WHERE id=? AND active=1');
    $query->execute([(int) $_SESSION['admin_id']]);
    $user = $query->fetch();
    if ($user && Auth::sessionValid($user)) { header('Location: index.php'); exit; }
    Auth::logout();
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        try {
            if (Auth::attempt((string) $_POST['username'], (string) $_POST['password'])) {
                header('Location: index.php'); exit;
            }
            $error = 'The username or password is incorrect.';
        } catch (DomainException $exception) {
            $error = $exception->getMessage();
        }
    }
}
require APP_ROOT . '/templates/admin/login.php';

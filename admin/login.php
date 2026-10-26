<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
if (!empty($_SESSION['admin_id'])) { header('Location: index.php'); exit; }
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

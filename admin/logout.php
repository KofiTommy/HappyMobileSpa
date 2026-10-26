<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::verify($_POST['csrf'] ?? null)) {
    http_response_code(405); exit('Method not allowed');
}
Auth::logout();
header('Location: login.php');

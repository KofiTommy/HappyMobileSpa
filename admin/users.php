<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
Auth::requireAdmin();
header('Location: staff.php');
exit;

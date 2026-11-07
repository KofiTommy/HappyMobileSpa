<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('~^/(?:storage|config)(?:/|$)~', $path)) {
    http_response_code(404);
    require __DIR__ . '/templates/404.php';
    return true;
}
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}
if ($path === '/') {
    require __DIR__ . '/index.php';
    return true;
}
http_response_code(404);
require __DIR__ . '/templates/404.php';

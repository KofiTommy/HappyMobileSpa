<?php
declare(strict_types=1);
header('X-Content-Type-Options: nosniff');

$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (str_contains($path, "\0") || str_contains($path, '\\') || preg_match('~(?:^|/)\.|^/(?:storage|config|src|database|scripts|tests|templates|docs|logo-assets)(?:/|$)|^/(?:server\.mjs|router\.php|package(?:-lock)?\.json|AGENTS\.md)$|\.(?:sql|sqlite3?|db|bak|backup|log|ini|env|ps1|py|ya?ml|zip|tar|gz)$~i', $path)) {
    http_response_code(404);
    echo 'Not found';
    return true;
}
$file = __DIR__ . $path;
if (preg_match('~^/assets/videos/gallery/~i', $path) && !preg_match('~^/assets/videos/gallery/[a-f0-9]{32}\.mp4$~D', $path)) {
    http_response_code(404); echo 'Not found'; return true;
}
if (preg_match('~^/assets/images/(?:team|gallery)/~i', $path)
    && !preg_match('~^/assets/images/(?:team|gallery)/[a-f0-9]{32}\.(?:jpg|png|webp)$~D', $path)) {
    http_response_code(404); echo 'Not found'; return true;
}
if ($path !== '/' && is_file($file)) {
    $resolved = realpath($file);
    if ($resolved === false || !str_starts_with($resolved, __DIR__ . DIRECTORY_SEPARATOR)) {
        http_response_code(404); echo 'Not found'; return true;
    }
    return false;
}
if ($path === '/') {
    require __DIR__ . '/index.php';
    return true;
}
http_response_code(404);
echo 'Not found';

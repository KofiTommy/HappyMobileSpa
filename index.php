<?php
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';
$services = [];
$settings = [];
render_public('Massage & wellness', 'home', compact('services', 'settings'));

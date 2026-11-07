<?php
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';
$services = Catalog::activeServices();
$settings = Catalog::settings();
$heroImages = Gallery::images(true);
$staff = Catalog::activeStaff();
$galleryImages = array_slice(Gallery::images(), 0, 3);
$featuredService = $services[0] ?? null;
render_public('Massage & wellness', 'home', compact('services', 'settings', 'heroImages', 'staff', 'galleryImages', 'featuredService'));

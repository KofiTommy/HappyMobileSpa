<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
if (PHP_SAPI !== 'cli') { throw new RuntimeException('Run from the command line.'); }
Database::connection()->exec('CREATE TABLE IF NOT EXISTS gallery_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    path VARCHAR(255) NOT NULL,
    caption VARCHAR(160) NOT NULL,
    alt_text VARCHAR(255) NOT NULL,
    published TINYINT(1) NOT NULL DEFAULT 1,
    show_in_hero TINYINT(1) NOT NULL DEFAULT 1,
    display_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB');
echo "Gallery storage ready.\n";

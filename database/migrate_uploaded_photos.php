<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/src/bootstrap.php';

// Run on the existing installation BEFORE replacing its image folders.
$pdo = Database::connection();
$pdo->exec('CREATE TABLE IF NOT EXISTS uploaded_photos (path VARCHAR(255) PRIMARY KEY, contents MEDIUMBLOB NOT NULL)');
$paths = $pdo->query("SELECT path FROM gallery_images UNION SELECT photo AS path FROM staff WHERE photo IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
$preserved = 0;
foreach ($paths as $path) {
    if (!preg_match('~^assets/images/(team|gallery)/[a-f0-9]{32}\.(jpg|png|webp)$~D', $path)) { continue; }
    if (TeamPhoto::available($path)) { $preserved++; }
}
echo "Protected {$preserved} existing photos. Missing files must be restored from a backup or uploaded again.\n";

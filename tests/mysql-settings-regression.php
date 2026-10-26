<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

$settings = Catalog::settings();
if (!isset($settings['cutoff_hours'])) {
    throw new RuntimeException('The MySQL regression check requires seeded settings.');
}
Catalog::saveSettings(['cutoff_hours' => $settings['cutoff_hours']]);
Catalog::saveSettings(['cutoff_hours' => $settings['cutoff_hours']]);
echo "PASS MySQL unchanged settings can be saved repeatedly.\n";

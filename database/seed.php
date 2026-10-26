<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
$pdo = Database::connection();
if ((int) $pdo->query('SELECT COUNT(*) FROM services')->fetchColumn() > 0) {
    echo "Catalog already contains services; no seed data inserted.\n";
    exit(0);
}
$services = [
    ['Swedish Massage','swedish-massage','Gentle, flowing massage to ease everyday tension.',60,15,350],
    ['Deep Tissue Ritual','deep-tissue-ritual','Focused pressure for deeper muscle tension.',90,15,520],
    ['Hot Stone Massage','hot-stone-massage','Warm stones and slow massage for deep relaxation.',75,15,480],
    ['Facial Glow','facial-glow','A refreshing cleanse and restorative facial ritual.',45,10,280],
    ['Mani + Pedi','mani-pedi','Care for hands and feet, finished your way.',90,0,220],
    ['Prenatal Massage','prenatal-massage','Comfort-led massage adapted for pregnancy.',60,15,400],
];
Database::transaction(function(PDO $pdo) use ($services): void {
    $statement = $pdo->prepare('INSERT INTO services (name,slug,description,duration_min,buffer_min,price,display_order) VALUES (?,?,?,?,?,?,?)');
    foreach ($services as $index => $service) {
        $statement->execute([...$service, $index + 1]);
    }
    $staff = $pdo->prepare('INSERT INTO staff (name,biography) VALUES (?,?)');
    foreach (['Ama S.','Kofi M.','Kwame O.','Esi D.'] as $name) {
        $staff->execute([$name, 'Demonstration therapist record — update this profile in admin.']);
    }
    $links = [[1,1],[1,2],[1,3],[1,6],[2,1],[2,2],[2,3],[3,4],[4,5]];
    $link = $pdo->prepare('INSERT INTO staff_services (staff_id,service_id) VALUES (?,?)');
    foreach ($links as $ids) { $link->execute($ids); }
    $hours = $pdo->prepare('INSERT INTO staff_hours (staff_id,weekday,start_time,end_time) VALUES (?,?,?,?)');
    $breaks = $pdo->prepare('INSERT INTO staff_breaks (staff_id,weekday,start_time,end_time) VALUES (?,?,?,?)');
    for ($staffId = 1; $staffId <= 4; $staffId++) {
        for ($day = 2; $day <= 6; $day++) {
            $hours->execute([$staffId, $day, '09:00', '18:00']);
            $breaks->execute([$staffId, $day, '12:00', '13:00']);
        }
    }
    $business = $pdo->prepare('INSERT INTO business_hours (weekday,open_time,close_time,closed) VALUES (?,?,?,?)');
    for ($day = 0; $day <= 6; $day++) {
        $closed = $day < 2 ? 1 : 0;
        $business->execute([$day, $closed ? null : '09:00', $closed ? null : '18:00', $closed]);
    }
    $settings = $pdo->prepare('INSERT INTO settings (setting_key,setting_value) VALUES (?,?)');
    foreach ([
        'cutoff_hours' => '24',
        'timezone' => 'Africa/Accra',
        'cancellation_policy' => 'Free changes up to 24 hours before your appointment. Later changes must be arranged with the spa.',
        'opening_text' => 'Tuesday–Saturday, 9:00 AM–6:00 PM',
    ] as $key => $value) { $settings->execute([$key, $value]); }
});
echo "Seeded Happy Mobile Spa demonstration catalog.\n";

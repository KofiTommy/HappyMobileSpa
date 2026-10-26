<?php
declare(strict_types=1);

function create_test_schema(PDO $pdo): void
{
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec(<<<'SQL'
CREATE TABLE services (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, slug TEXT UNIQUE NOT NULL, description TEXT NOT NULL, duration_min INTEGER NOT NULL, buffer_min INTEGER NOT NULL DEFAULT 15, price NUMERIC NOT NULL, active INTEGER NOT NULL DEFAULT 1, archived_at TEXT NULL, display_order INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
CREATE TABLE staff (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, biography TEXT NOT NULL DEFAULT '', photo TEXT NULL, active INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
CREATE TABLE staff_services (staff_id INTEGER NOT NULL, service_id INTEGER NOT NULL, PRIMARY KEY(staff_id, service_id));
CREATE TABLE staff_hours (id INTEGER PRIMARY KEY AUTOINCREMENT, staff_id INTEGER NOT NULL, weekday INTEGER NOT NULL, start_time TEXT NOT NULL, end_time TEXT NOT NULL);
CREATE TABLE staff_breaks (id INTEGER PRIMARY KEY AUTOINCREMENT, staff_id INTEGER NOT NULL, weekday INTEGER NOT NULL, start_time TEXT NOT NULL, end_time TEXT NOT NULL);
CREATE TABLE staff_time_off (id INTEGER PRIMARY KEY AUTOINCREMENT, staff_id INTEGER NOT NULL, off_date TEXT NOT NULL, note TEXT NOT NULL DEFAULT '');
CREATE TABLE business_hours (weekday INTEGER PRIMARY KEY, open_time TEXT NULL, close_time TEXT NULL, closed INTEGER NOT NULL DEFAULT 0);
CREATE TABLE holidays (id INTEGER PRIMARY KEY AUTOINCREMENT, holiday_date TEXT UNIQUE NOT NULL, name TEXT NOT NULL);
CREATE TABLE settings (setting_key TEXT PRIMARY KEY, setting_value TEXT NOT NULL);
CREATE TABLE customers (id INTEGER PRIMARY KEY AUTOINCREMENT, phone TEXT UNIQUE NOT NULL, name TEXT NOT NULL, email TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
CREATE TABLE bookings (id INTEGER PRIMARY KEY AUTOINCREMENT, reference TEXT UNIQUE NOT NULL, customer_id INTEGER NOT NULL, service_id INTEGER NOT NULL, staff_id INTEGER NOT NULL, slot_start TEXT NOT NULL, slot_end TEXT NOT NULL, price NUMERIC NOT NULL, status TEXT NOT NULL, payment_method TEXT NOT NULL DEFAULT 'pay_at_spa', payment_status TEXT NOT NULL DEFAULT 'unpaid', notes TEXT NOT NULL DEFAULT '', accepted_policy TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
CREATE TABLE booking_events (id INTEGER PRIMARY KEY AUTOINCREMENT, booking_id INTEGER NOT NULL, actor_type TEXT NOT NULL, admin_id INTEGER NULL, event_type TEXT NOT NULL, details TEXT NOT NULL DEFAULT '{}', created_at TEXT NOT NULL);
CREATE TABLE admin_users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE NOT NULL, password_hash TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1, last_login_at TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
CREATE TABLE rate_limits (id INTEGER PRIMARY KEY AUTOINCREMENT, action TEXT NOT NULL, key_hash TEXT NOT NULL, attempts INTEGER NOT NULL DEFAULT 0, window_start TEXT NOT NULL, blocked_until TEXT NULL, UNIQUE(action, key_hash));
SQL);
}

function reset_test_database(): PDO
{
    static $ready = false;
    $pdo = Database::connection();
    if (!$ready) {
        create_test_schema($pdo);
        $ready = true;
    } else {
        $tables = ['rate_limits','admin_users','booking_events','bookings','customers','settings','holidays','business_hours','staff_time_off','staff_breaks','staff_hours','staff_services','staff','services'];
        foreach ($tables as $table) {
            $pdo->exec('DELETE FROM ' . $table);
        }
    }
    return $pdo;
}

function seed_test_catalog(): void
{
    $pdo = reset_test_database();
    $services = [
        [1, 'Swedish Massage', 'swedish-massage', 'Gentle, flowing massage to ease everyday tension.', 60, 15, '350.00'],
        [2, 'Deep Tissue Ritual', 'deep-tissue-ritual', 'Focused pressure for deeper muscle tension.', 90, 15, '520.00'],
        [3, 'Hot Stone Massage', 'hot-stone-massage', 'Warm stones and slow massage for deep relaxation.', 75, 15, '480.00'],
        [4, 'Facial Glow', 'facial-glow', 'A refreshing cleanse and restorative facial ritual.', 45, 10, '280.00'],
        [5, 'Mani + Pedi', 'mani-pedi', 'Care for hands and feet, finished your way.', 90, 0, '220.00'],
        [6, 'Prenatal Massage', 'prenatal-massage', 'Comfort-led massage adapted for pregnancy.', 60, 15, '400.00'],
    ];
    $statement = $pdo->prepare('INSERT INTO services (id,name,slug,description,duration_min,buffer_min,price,active,display_order,created_at,updated_at) VALUES (?,?,?,?,?,?,?,1,?,datetime(),datetime())');
    foreach ($services as $service) {
        $statement->execute([...$service, $service[0]]);
    }
    $staff = [[1,'Ama S.'],[2,'Kofi M.'],[3,'Kwame O.'],[4,'Esi D.']];
    $statement = $pdo->prepare('INSERT INTO staff (id,name,active,created_at,updated_at) VALUES (?,?,1,datetime(),datetime())');
    foreach ($staff as $person) {
        $statement->execute($person);
    }
    $links = [[1,1],[1,2],[1,3],[1,6],[2,1],[2,2],[2,3],[3,4],[4,5]];
    $statement = $pdo->prepare('INSERT INTO staff_services (staff_id,service_id) VALUES (?,?)');
    foreach ($links as $link) {
        $statement->execute($link);
    }
    $hours = $pdo->prepare('INSERT INTO business_hours (weekday,open_time,close_time,closed) VALUES (?,?,?,?)');
    for ($day = 0; $day <= 6; $day++) {
        $closed = $day === 0 ? 1 : 0;
        $hours->execute([$day, $closed ? null : '09:00', $closed ? null : '18:00', $closed]);
    }
    $settings = $pdo->prepare('INSERT INTO settings (setting_key,setting_value) VALUES (?,?)');
    foreach (['cutoff_hours'=>'24','cancellation_policy'=>'Free changes up to 24 hours before your appointment.','timezone'=>'Africa/Accra'] as $key => $value) {
        $settings->execute([$key, $value]);
    }
}

function seed_availability_fixture(): void
{
    seed_test_catalog();
    $pdo = Database::connection();
    $hours = $pdo->prepare('INSERT INTO staff_hours (staff_id,weekday,start_time,end_time) VALUES (?,?,?,?)');
    $breaks = $pdo->prepare('INSERT INTO staff_breaks (staff_id,weekday,start_time,end_time) VALUES (?,?,?,?)');
    foreach ([1, 2] as $staffId) {
        $hours->execute([$staffId, 2, '09:00', '18:00']);
        $breaks->execute([$staffId, 2, '12:00', '13:00']);
    }
    $breaks->execute([1, 2, '10:00', '10:30']);
    $pdo->exec("INSERT INTO staff_time_off (staff_id,off_date,note) VALUES (1,'2026-10-28','Leave')");
    $pdo->exec("INSERT INTO customers (id,phone,name,created_at,updated_at) VALUES (1,'0244127788','Existing Client',datetime(),datetime())");
    $pdo->exec("INSERT INTO bookings (reference,customer_id,service_id,staff_id,slot_start,slot_end,price,status,payment_method,payment_status,notes,accepted_policy,created_at,updated_at) VALUES ('SPA-EXISTING',1,1,1,'2026-10-27 09:00:00','2026-10-27 10:00:00',350,'confirmed','pay_at_spa','unpaid','','Policy',datetime(),datetime())");
}

function seed_confirmed_booking(string $startModifier = '+48 hours'): array
{
    seed_availability_fixture();
    $pdo = Database::connection();
    $start = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $start = $start->modify($startModifier);
    $end = $start->modify('+60 minutes');
    $statement = $pdo->prepare("INSERT INTO bookings (reference,customer_id,service_id,staff_id,slot_start,slot_end,price,status,payment_method,payment_status,notes,accepted_policy,created_at,updated_at) VALUES ('SPA-MANAGE01',1,1,1,?,?,350,'confirmed','pay_at_spa','unpaid','','Policy',datetime(),datetime())");
    $statement->execute([$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
    $id = (int) $pdo->lastInsertId();
    $query = $pdo->prepare('SELECT * FROM bookings WHERE id = ?');
    $query->execute([$id]);
    $booking = $query->fetch();
    $booking['id'] = $id;
    return $booking;
}

function seed_admin(string $username, string $hash): int
{
    $pdo = Database::connection();
    $statement = $pdo->prepare('INSERT INTO admin_users (username,password_hash,active,created_at,updated_at) VALUES (?,?,1,datetime(),datetime())');
    $statement->execute([$username, $hash]);
    return (int) $pdo->lastInsertId();
}

function seed_manual_input(): array
{
    $input = booking_input();
    $input['staff_id'] = 1;
    return $input;
}

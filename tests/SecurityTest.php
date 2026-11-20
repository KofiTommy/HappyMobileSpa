<?php
declare(strict_types=1);

function test_security_rejects_malformed_parameters_and_preserves_legitimate_forms(): void
{
    Security::validateInput(['service_id'=>'1', 'date'=>'2030-01-01']);
    Security::validateInput(['hours'=>[['weekday'=>'2','start_time'=>'09:00']], 'service_ids'=>['1','2'], 'expense_budgets'=>['rent'=>'100']], true);
    assert_throws(fn () => Security::validateInput(['csrf'=>['token']], true), 'Invalid request');
    assert_throws(fn () => Security::validateInput(['date'=>['2030-01-01']]), 'Invalid request');
    assert_throws(fn () => Security::validateInput(['service_ids'=>[['1']]], true), 'Invalid request');
    assert_throws(fn () => Security::validateInput(['name'=>"a\0b"], true), 'Invalid request');
    assert_throws(fn () => Security::validateInput(['name'=>"\xFF"], true), 'Invalid request');
    assert_throws(fn () => Security::validateInput(['name'=>str_repeat('x', 32769)], true), 'Invalid request');
    assert_same(false, Csrf::verify(['token']), 'array token denied without a server error');
    assert_same(false, Security::https(['HTTPS'=>'off','HTTP_X_FORWARDED_PROTO'=>'https']), 'untrusted forwarding header ignored');
    assert_same(true, Security::https(['HTTPS'=>'on']), 'TLS detected');
}

function test_staff_sessions_expire_and_password_resets_revoke_access(): void
{
    seed_test_catalog();
    $id = seed_admin('security-owner', password_hash('secure test password', PASSWORD_DEFAULT));
    assert_same(true, Auth::attempt('security-owner', 'secure test password'), 'login accepted');
    $_SESSION['admin_last_seen'] = time() - 1801;
    assert_throws(fn () => Auth::requireUser(), 'Authentication required');
    assert_same(true, Auth::attempt('security-owner', 'secure test password'), 'fresh login accepted');
    $_SESSION['admin_issued_at'] = time() - 28801;
    assert_throws(fn () => Auth::requireUser(), 'Authentication required');
    Auth::attempt('security-owner', 'secure test password');
    Database::connection()->prepare('UPDATE admin_users SET password_hash=? WHERE id=?')->execute([password_hash('replacement test password', PASSWORD_DEFAULT), $id]);
    assert_throws(fn () => Auth::requireUser(), 'Authentication required');
    Auth::attempt('security-owner', 'replacement test password');
    Database::connection()->prepare('UPDATE admin_users SET session_version=session_version+1,active=0 WHERE id=?')->execute([$id]);
    Database::connection()->prepare('UPDATE admin_users SET active=1 WHERE id=?')->execute([$id]);
    assert_throws(fn () => Auth::requireUser(), 'Authentication required');
    assert_same(false, Auth::attempt("security-owner' OR 1=1 --", 'secure test password'), 'SQL injection cannot authenticate');
    Auth::logout();
}

function test_rate_limits_survive_rotating_usernames_and_expire(): void
{
    seed_test_catalog();
    $pdo = Database::connection();
    $pdo->prepare('INSERT INTO rate_limits (action,key_hash,attempts,window_start) VALUES (?,?,?,?)')->execute(['admin-login-ip', hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli')), 40, gmdate('Y-m-d H:i:s')]);
    assert_throws(fn () => Auth::attempt('new-unknown-user', 'anything'), 'Too many attempts');
    $pdo->exec("UPDATE rate_limits SET window_start='2000-01-01 00:00:00'");
    assert_same(false, Auth::attempt('new-unknown-user', 'anything'), 'expired window permits another attempt');
    RateLimit::check('atomic-test', 'key', 2, 900);
    RateLimit::check('atomic-test', 'key', 2, 900);
    for ($i=0; $i<5; $i++) {
        assert_throws(fn () => RateLimit::check('atomic-test', 'key', 2, 900), 'Too many attempts');
    }
    assert_same(3, (int) $pdo->query("SELECT attempts FROM rate_limits WHERE action='atomic-test'")->fetchColumn(), 'blocked counters saturate instead of overflowing');
}

function test_booking_contact_details_are_isolated_from_other_appointments(): void
{
    seed_availability_fixture();
    $pdo = Database::connection();
    $pdo->exec("UPDATE customers SET email='original@example.com' WHERE id=1");
    SecurityMigration::apply($pdo);
    $original = Booking::adminList(['search'=>'SPA-EXISTING'])[0];
    $input = booking_input();
    $input['name'] = 'Different Customer';
    $input['email'] = 'new@example.com';
    $new = Booking::create($input);
    assert_same('Different Customer', $new['customer_name'], 'new booking uses its own supplied name');
    assert_same('new@example.com', PaystackPayment::booking((int) $new['id'])['customer_email'], 'payment uses appointment email');
    SecurityMigration::apply($pdo);
    $old = Booking::adminList(['id'=>$original['id']])[0];
    assert_same('Existing Client', $old['customer_name'], 'original customer name preserved');
    assert_same('original@example.com', $old['customer_email'], 'original receipt email preserved');
    assert_same('original@example.com', $pdo->query('SELECT email FROM customers WHERE id=1')->fetchColumn(), 'guest cannot modify shared customer profile');
    assert_same('new@example.com', PaystackPayment::booking((int) $new['id'])['customer_email'], 'rerunning migration preserves new appointment details');
    $input['start'] = fixture_datetime('14:00');
    $input['email'] = '';
    $blank = Booking::create($input);
    assert_same('', $blank['customer_email'], 'blank email never reveals another customer email');
}

function test_inactive_staff_cannot_mutate_bookings(): void
{
    $booking = seed_confirmed_booking();
    $id = seed_admin('disabled-user', password_hash('secure test password', PASSWORD_DEFAULT));
    Database::connection()->prepare('UPDATE admin_users SET active=0 WHERE id=?')->execute([$id]);
    assert_throws(fn () => Booking::setStatus((int) $booking['id'], 'cancelled', $id), 'Active staff access required');
    assert_throws(fn () => Booking::setPaymentStatus((int) $booking['id'], 'paid', $id), 'Active staff access required');
    assert_throws(fn () => Booking::checkIn((int) $booking['id'], $id), 'Active staff access required');
    assert_same('confirmed', Booking::adminList(['id'=>$booking['id']])[0]['status'], 'failed authorization leaves booking intact');
}

function test_provider_credentials_are_saved_as_private_data(): void
{
    global $config;
    $previous = $config['private_storage'] ?? null;
    $directory = tempnam(sys_get_temp_dir(), 'spa-private-test-');
    unlink($directory);
    mkdir($directory, 0700);
    $config['private_storage'] = $directory;
    try {
        SmsConfig::save(['role'=>'admin'], ['enabled'=>'1','sender'=>'HappySpa','api_key'=>"test-key-<?php throw new Exception('must not execute'); ?>"]);
        assert_same("test-key-<?php throw new Exception('must not execute'); ?>", SmsConfig::load()['api_key'], 'credential content is inert JSON');
        assert_same(false, is_file($directory . '/sms.php'), 'no executable configuration written');
        Paystack::save(['role'=>'admin'], ['enabled'=>'1','secret_key'=>'sk_test_isolatedtestkey','site_url'=>'https://spa.example.com']);
        assert_same('sk_test_isolatedtestkey', Paystack::config()['secret_key'], 'payment configuration round trips');
        assert_throws(fn () => PrivateConfig::save('../escape', []), 'Invalid configuration');
    } finally {
        foreach (['sms.json','paystack.json'] as $file) {
            if (is_file($directory . '/' . $file)) { unlink($directory . '/' . $file); }
        }
        rmdir($directory);
        if ($previous === null) { unset($config['private_storage']); }
        else { $config['private_storage'] = $previous; }
    }
}

function test_security_migration_upgrades_legacy_bookings_without_overwriting(): void
{
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $pdo->exec('CREATE TABLE customers (id INTEGER PRIMARY KEY, name TEXT, phone TEXT, email TEXT)');
    $pdo->exec('CREATE TABLE bookings (id INTEGER PRIMARY KEY, customer_id INTEGER)');
    $pdo->exec('CREATE TABLE admin_users (id INTEGER PRIMARY KEY)');
    $pdo->exec("INSERT INTO customers VALUES (1,'Original','0244127788',NULL)");
    $pdo->exec('INSERT INTO bookings VALUES (1,1)');
    SecurityMigration::apply($pdo);
    assert_same('Original', $pdo->query('SELECT contact_name FROM bookings')->fetchColumn(), 'legacy booking gets original contact details');
    assert_same('', $pdo->query('SELECT contact_email FROM bookings')->fetchColumn(), 'legacy blank email becomes explicit blank snapshot');
    $pdo->exec("UPDATE bookings SET contact_name='Appointment-specific name'");
    SecurityMigration::apply($pdo);
    assert_same('Appointment-specific name', $pdo->query('SELECT contact_name FROM bookings')->fetchColumn(), 'rerunning migration preserves snapshots');
}

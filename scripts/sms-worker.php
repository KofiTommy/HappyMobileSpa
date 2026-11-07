<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
if (PHP_SAPI !== 'cli') { throw new RuntimeException('Run from the command line.'); }
try {
    $pdo = Database::connection();
    $sql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
        ? "INSERT INTO settings (setting_key,setting_value) VALUES ('sms_worker_last_run',?) ON CONFLICT(setting_key) DO UPDATE SET setting_value=excluded.setting_value"
        : "INSERT INTO settings (setting_key,setting_value) VALUES ('sms_worker_last_run',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)";
    $pdo->prepare($sql)->execute([gmdate('Y-m-d H:i:s')]);
    if (!SmsConfig::ready()) { echo "SMS disabled or awaiting provider credentials.\n"; exit(0); }
    SmsNotifications::backfillReminders();
    $sent = SmsNotifications::process();
    echo "Submitted {$sent} SMS notifications to provider.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "SMS worker failed. Check database connection, schema, and server configuration.\n");
    exit(1);
}

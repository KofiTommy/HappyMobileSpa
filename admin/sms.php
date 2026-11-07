<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
$admin = Auth::requireAdmin();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Csrf::verify($_POST['csrf'] ?? null)) { throw new DomainException('Your form session expired.'); }
        SmsConfig::save($admin, $_POST);
        header('Location: sms.php?saved=1'); exit;
    } catch (DomainException | InvalidArgumentException $exception) { $error = $exception->getMessage(); }
}
$smsConfig = SmsConfig::load();
$notifications = Database::connection()->query('SELECT n.*,b.reference FROM sms_notifications n JOIN bookings b ON b.id=n.booking_id ORDER BY n.id DESC LIMIT 100')->fetchAll();
$workerLastRun = Catalog::settings()['sms_worker_last_run'] ?? null;
render_admin('SMS notifications', 'sms', compact('admin','smsConfig','notifications','workerLastRun','error'));

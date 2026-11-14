<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
$admin = Auth::requireAdmin();
$error = '';
$search = (string) ($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Csrf::verify($_POST['csrf'] ?? null)) { throw new DomainException('Your form session expired.'); }
        SmsConfig::save($admin, $_POST);
        header('Location: sms.php?' . http_build_query(['search'=>$search, 'page'=>$page, 'saved'=>1])); exit;
    } catch (DomainException | InvalidArgumentException $exception) { $error = $exception->getMessage(); }
}
$smsConfig = SmsConfig::load();
$listing = SmsNotifications::adminList($search, $page);
$notifications = $listing['notifications'];
$workerLastRun = Catalog::settings()['sms_worker_last_run'] ?? null;
render_admin('SMS notifications', 'sms', compact('admin','smsConfig','notifications','listing','workerLastRun','error'));

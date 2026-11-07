<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
$admin = Auth::requireAdmin();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Csrf::verify($_POST['csrf'] ?? null)) { throw new DomainException('Your form session expired.'); }
        $input = $_POST;
        $input['timezone'] = 'Africa/Accra';
        $holidays = [];
        foreach (preg_split('/\R/', (string) ($_POST['holiday_lines'] ?? '')) as $line) {
            if (trim($line) === '') { continue; }
            [$date, $name] = array_pad(explode('|', $line, 2), 2, '');
            $holidays[] = ['holiday_date' => trim($date), 'name' => trim($name)];
        }
        $input['holidays'] = $holidays;
        Catalog::saveSettings($input);
        header('Location: settings.php?saved=1'); exit;
    } catch (DomainException | InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    }
}
$settings = Catalog::settings();
$hours = Catalog::businessHours();
$holidays = Catalog::holidays();
$success = isset($_GET['saved']) ? 'Business settings saved. Public pages now use these values.' : '';
render_admin('Business settings', 'settings', compact('admin', 'settings', 'hours', 'holidays', 'error', 'success'));

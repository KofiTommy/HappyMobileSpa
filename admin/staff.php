<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
$admin = Auth::requireAdmin();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Csrf::verify($_POST['csrf'] ?? null)) { throw new DomainException('Your form session expired.'); }
        if (in_array($_POST['action'] ?? '', ['create','password','active'], true)) {
            Receptionists::manage($admin, $_POST);
            header('Location: staff.php?edit=' . (int) $_POST['staff_id'] . '&access_saved=1'); exit;
        }
        $id = !empty($_POST['id']) ? (int) $_POST['id'] : null;
        if (($_POST['action'] ?? '') === 'schedule') {
            Catalog::saveSchedule((int) $_POST['id'], array_values($_POST['hours'] ?? []), array_values($_POST['breaks'] ?? []));
            header('Location: staff.php?edit=' . $id . '&schedule_saved=1'); exit;
        }
        $id = Catalog::saveStaff($_POST, $id, $_FILES['photo'] ?? null);
        header('Location: staff.php?edit=' . $id . '&saved=1'); exit;
    } catch (DomainException | InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    }
}
$staff = Catalog::adminStaff();
$services = Catalog::adminServices();
$editing = null;
if (!empty($_GET['edit'])) {
    foreach ($staff as $person) {
        if ((int) $person['id'] === (int) $_GET['edit']) { $editing = $person; break; }
    }
}
$receptionist = null;
if ($editing) {
    $query = Database::connection()->prepare("SELECT id,username,active,last_login_at FROM admin_users WHERE staff_id=? AND role='receptionist'");
    $query->execute([$editing['id']]);
    $receptionist = $query->fetch() ?: null;
}
$schedule = $editing ? Catalog::schedule((int) $editing['id']) : ['hours' => [], 'breaks' => []];
$success = isset($_GET['access_saved']) ? 'Receptionist access updated.' : (isset($_GET['saved']) ? 'Team member saved.' : (isset($_GET['schedule_saved']) ? 'Weekly schedule saved.' : ''));
render_admin('Team & schedules', 'staff', compact('admin', 'staff', 'services', 'editing', 'schedule', 'error', 'success', 'receptionist'));

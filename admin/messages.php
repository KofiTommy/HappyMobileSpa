<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
$admin = Auth::requireAdmin();
$error = '';
$status = (string) ($_GET['status'] ?? 'new');
$page = max(1, (int) ($_GET['page'] ?? 1));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Csrf::verify($_POST['csrf'] ?? null)) { throw new DomainException('Your form session expired.'); }
        ContactMessage::setStatus($admin, (int) ($_POST['id'] ?? 0), (string) ($_POST['status'] ?? ''));
        header('Location: messages.php?' . http_build_query(['status'=>$status, 'page'=>$page, 'saved'=>1])); exit;
    } catch (DomainException | InvalidArgumentException $exception) { $error = $exception->getMessage(); }
}
$inbox = ContactMessage::inbox($status, $page);
render_admin('Customer messages', 'messages', compact('admin', 'inbox', 'error'));

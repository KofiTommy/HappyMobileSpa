<?php
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';
$error = '';
$old = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;
    try {
        if (!Csrf::verify($_POST['csrf'] ?? null)) { throw new DomainException('Your form session expired. Please try again.'); }
        ContactMessage::send($_POST, (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $_SESSION['contact_sent'] = true;
        header('Location: contact.php#send-message'); exit;
    } catch (DomainException | InvalidArgumentException $exception) { $error = $exception->getMessage(); }
}
$sent = !empty($_SESSION['contact_sent']);
unset($_SESSION['contact_sent']);
render_public('Contact', 'contact', ['settings' => Catalog::settings(), 'error'=>$error, 'old'=>$old, 'sent'=>$sent]);

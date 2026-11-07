<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
Auth::requireAdmin();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$expense = $id ? Finance::expense($id) : null;
if (!$expense) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Receipt not found.';
    exit;
}
try {
    ExpenseReceipt::send($expense);
} catch (DomainException $exception) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Receipt not found.';
}
exit;

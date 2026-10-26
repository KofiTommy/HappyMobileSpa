<?php
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';
$settings = Catalog::settings();

if (isset($_GET['confirmation'])) {
    $reference = strtoupper(trim((string) $_GET['confirmation']));
    $recent = $_SESSION['recent_booking'] ?? null;
    if (!$recent || ($recent['time'] ?? 0) < time() - 3600 || !hash_equals((string) $recent['reference'], $reference)) {
        http_response_code(403);
        render_public('Confirmation unavailable', 'confirmation', ['booking' => null, 'settings' => $settings]);
        exit;
    }
    $statement = Database::connection()->prepare(
        'SELECT b.*, c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email,
                s.name AS service_name, s.duration_min, st.name AS staff_name
         FROM bookings b JOIN customers c ON c.id=b.customer_id JOIN services s ON s.id=b.service_id
         JOIN staff st ON st.id=b.staff_id WHERE b.reference = ?'
    );
    $statement->execute([$reference]);
    $booking = $statement->fetch() ?: null;
    render_public('Booking confirmed', 'confirmation', compact('booking', 'settings'));
    exit;
}

$services = Catalog::activeServices();
$error = '';
$old = $_POST;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['csrf'] ?? null)) {
        $error = 'Your form session expired. Please review and submit again.';
    } else {
        try {
            $booking = Booking::create($_POST);
            $_SESSION['recent_booking'] = ['reference' => $booking['reference'], 'time' => time()];
            header('Location: book.php?confirmation=' . rawurlencode($booking['reference']));
            exit;
        } catch (DomainException | InvalidArgumentException $exception) {
            $error = $exception->getMessage();
        }
    }
}

$selectedService = null;
if (!empty($old['service_id'])) {
    foreach ($services as $service) {
        if ((int) $service['id'] === (int) $old['service_id']) { $selectedService = $service; break; }
    }
} elseif (!empty($_GET['service'])) {
    $selectedService = Catalog::findService((string) $_GET['service']);
}
$selectedService ??= $services[0] ?? null;
$staff = $selectedService ? Catalog::activeStaff($selectedService['id']) : [];
render_public('Book an appointment', 'book', compact('services', 'staff', 'selectedService', 'settings', 'error', 'old'));

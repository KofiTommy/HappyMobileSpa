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
    } elseif (!isset($_POST['refresh_availability'])) {
        try {
            if (isset($_POST['slot_choice']) && is_string($_POST['slot_choice'])) {
                $_POST['start'] = (string) $_POST['slot_choice'];
            }
            $payOnline = ($_POST['payment_choice'] ?? 'spa') === 'mobile_money';
            if ($payOnline && (!Paystack::ready() || !filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL))) {
                throw new InvalidArgumentException('Mobile money payment requires an email address and available online checkout.');
            }
            $booking = Booking::create($_POST);
            $_SESSION['recent_booking'] = ['reference' => $booking['reference'], 'time' => time()];
            header('Location: ' . ($payOnline ? 'payment.php?start=1&booking=' : 'book.php?confirmation=') . rawurlencode($booking['reference']));
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
$availableSlots = [];
$availabilityError = '';
$appointmentDate = isset($old['appointment_date']) && is_string($old['appointment_date']) ? $old['appointment_date'] : '';
if ($selectedService && $appointmentDate !== '') {
    $staffId = !empty($old['staff_id']) ? filter_var($old['staff_id'], FILTER_VALIDATE_INT) : null;
    if ($staffId === false) {
        $availabilityError = 'Choose a valid therapist.';
    } else {
        try {
            $availableSlots = Availability::slots((int) $selectedService['id'], $staffId, $appointmentDate);
        } catch (InvalidArgumentException) {
            $availabilityError = 'Choose a valid appointment date.';
        }
    }
}
render_public('Book an appointment', 'book', compact('services', 'staff', 'selectedService', 'settings', 'error', 'old', 'availableSlots', 'availabilityError'));

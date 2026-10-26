<?php
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';
$settings = Catalog::settings();
$error = '';
$success = '';
$reference = strtoupper(trim((string) ($_GET['reference'] ?? '')));
$manage = $_SESSION['manage_booking'] ?? null;
$booking = null;
if ($manage && ($manage['expires'] ?? 0) > time()) {
    try {
        $booking = Booking::findForCustomer($manage['reference'], $manage['phone']);
    } catch (InvalidArgumentException) {
        $booking = null;
    }
    if (!$booking) {
        unset($_SESSION['manage_booking']);
    }
} elseif ($manage) {
    unset($_SESSION['manage_booking']);
    $error = 'Your secure management session expired. Enter your booking details again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!Csrf::verify($_POST['csrf'] ?? null)) {
        $error = 'Your form session expired. Please try again.';
    } elseif ($action === 'lookup') {
        $reference = strtoupper(trim((string) $_POST['reference']));
        $phone = trim((string) $_POST['phone']);
        try {
            RateLimit::check('booking-lookup', ($_SERVER['REMOTE_ADDR'] ?? 'local') . '|' . $reference);
            $found = Booking::findForCustomer($reference, $phone);
            if (!$found) {
                $error = 'We could not find a booking with those details.';
            } else {
                session_regenerate_id(true);
                $_SESSION['manage_booking'] = ['reference' => $reference, 'phone' => $found['customer_phone'], 'expires' => time() + 1800];
                header('Location: manage.php');
                exit;
            }
        } catch (DomainException $exception) {
            $error = str_contains($exception->getMessage(), 'Too many') ? $exception->getMessage() : 'We could not find a booking with those details.';
        } catch (InvalidArgumentException) {
            $error = 'We could not find a booking with those details.';
        }
    } elseif (!$booking || !hash_equals((string) ($_POST['booking_id'] ?? ''), (string) $booking['id'])) {
        $error = 'Verify your booking again before making changes.';
    } else {
        try {
            if ($action === 'cancel') {
                Booking::cancelForCustomer((int) $booking['id']);
                $success = 'Your appointment has been cancelled.';
            } elseif ($action === 'reschedule') {
                $booking = Booking::rescheduleForCustomer((int) $booking['id'], (string) $_POST['start']);
                $success = 'Your new appointment time is confirmed.';
            } elseif ($action === 'forget') {
                unset($_SESSION['manage_booking']);
                header('Location: manage.php');
                exit;
            }
            $booking = Booking::findForCustomer($manage['reference'], $manage['phone']);
        } catch (DomainException | InvalidArgumentException $exception) {
            $error = $exception->getMessage();
        }
    }
}

$slots = [];
$canChange = false;
if ($booking && in_array($booking['status'], ['pending', 'confirmed'], true)) {
    $cutoff = (int) ($settings['cutoff_hours'] ?? 24);
    $appointmentStart = new DateTimeImmutable($booking['slot_start'], new DateTimeZone('UTC'));
    $canChange = $appointmentStart > new DateTimeImmutable('+' . $cutoff . ' hours', new DateTimeZone('UTC'));
    if ($canChange) {
        $date = (string) ($_GET['date'] ?? $appointmentStart->setTimezone(business_timezone())->format('Y-m-d'));
        try { $slots = Availability::slots((int) $booking['service_id'], (int) $booking['staff_id'], $date); }
        catch (InvalidArgumentException) { $slots = []; }
    }
}
render_public('Manage booking', $booking ? 'manage-booking' : 'manage-login', compact('booking', 'settings', 'error', 'success', 'reference', 'slots', 'canChange'));

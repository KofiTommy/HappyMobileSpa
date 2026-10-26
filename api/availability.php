<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $serviceId = filter_var($_GET['service_id'] ?? null, FILTER_VALIDATE_INT);
    $staffId = !empty($_GET['staff_id']) ? filter_var($_GET['staff_id'], FILTER_VALIDATE_INT) : null;
    $date = (string) ($_GET['date'] ?? '');
    if (!$serviceId || $staffId === false) {
        throw new InvalidArgumentException('Invalid service or therapist');
    }
    echo json_encode(['slots' => Availability::slots($serviceId, $staffId, $date)], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    http_response_code(422);
    echo json_encode(['error' => 'We could not load times for this date. Please try another date.'], JSON_THROW_ON_ERROR);
}

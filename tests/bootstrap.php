<?php
declare(strict_types=1);

putenv('SPA_DB_DSN=sqlite::memory:');
putenv('SPA_SMS_DISABLED=1');
putenv('SPA_PAYSTACK_DISABLED=1');
require dirname(__DIR__) . '/src/bootstrap.php';

function assert_contains(string $needle, string $haystack, string $label): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($label . ': missing ' . $needle);
    }
}

function assert_not_contains(string $needle, string $haystack, string $label): void
{
    if (str_contains($haystack, $needle)) {
        throw new RuntimeException($label . ': unexpectedly found ' . $needle);
    }
}

function assert_throws(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        if (!str_contains($error->getMessage(), $message)) {
            throw new RuntimeException('Unexpected exception: ' . $error->getMessage());
        }
        return;
    }
    throw new RuntimeException('Expected exception: ' . $message);
}

require __DIR__ . '/Fixtures.php';
require __DIR__ . '/DatabaseTest.php';
require __DIR__ . '/PublicPagesTest.php';
require __DIR__ . '/CatalogTest.php';
require __DIR__ . '/AvailabilityTest.php';
require __DIR__ . '/BookingTest.php';
require __DIR__ . '/ManageBookingTest.php';
require __DIR__ . '/AuthTest.php';
require __DIR__ . '/AdminManagementTest.php';
require __DIR__ . '/ReceptionistTest.php';
require __DIR__ . '/TeamPhotoTest.php';
require __DIR__ . '/GalleryTest.php';
require __DIR__ . '/ContactMessageTest.php';
require __DIR__ . '/SmsTest.php';
require __DIR__ . '/PaystackTest.php';
require __DIR__ . '/ReceptionOverviewTest.php';
require __DIR__ . '/FinanceTest.php';
require __DIR__ . '/DashboardChartsTest.php';
require __DIR__ . '/AdminSearchTest.php';

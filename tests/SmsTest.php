<?php
declare(strict_types=1);

function sms_job_booking(string $start, string $status = 'confirmed'): array
{
    $pdo = Database::connection();
    $pdo->prepare("UPDATE bookings SET slot_start=?,slot_end=?,status=? WHERE reference='SPA-EXISTING'")
        ->execute([$start, (new DateTimeImmutable($start, new DateTimeZone('UTC')))->modify('+1 hour')->format('Y-m-d H:i:s'), $status]);
    return $pdo->query("SELECT * FROM bookings WHERE reference='SPA-EXISTING'")->fetch();
}

function test_sms_booking_hooks_and_atomic_queue(): void
{
    seed_availability_fixture();
    $input = array_replace(booking_input(), ['staff_id'=>1]);
    $booking = Booking::create($input);
    $jobs = Database::connection()->query('SELECT * FROM sms_notifications')->fetchAll();
    assert_same(true, count($jobs) >= 1, 'confirmation queued');
    assert_same('confirmation', $jobs[0]['kind'], 'confirmation type');
    assert_same($booking['id'], (int) $jobs[0]['booking_id'], 'booking linked');
    assert_same('pending', $jobs[0]['state'], 'disabled SMS makes no API call');
    $count = count($jobs);
    assert_throws(fn () => Booking::create($input), 'Slot is no longer available');
    assert_same($count, (int) Database::connection()->query('SELECT COUNT(*) FROM sms_notifications')->fetchColumn(), 'failed booking queues nothing');
    Booking::setStatus((int) $booking['id'], 'cancelled', 1);
    assert_same(0, (int) Database::connection()->query("SELECT COUNT(*) FROM sms_notifications WHERE state='pending'")->fetchColumn(), 'cancellation invalidates queue');
}

function test_sms_reminder_timing_and_no_duplicate_submission(): void
{
    seed_availability_fixture();
    $now = (new DateTimeImmutable(fixture_datetime('10:00'), new DateTimeZone('UTC')))->modify('-2 days');
    $booking = sms_job_booking(fixture_datetime('10:00'));
    SmsNotifications::schedule($booking, Database::connection(), true, $now);
    SmsNotifications::schedule($booking, Database::connection(), true, $now);
    $jobs = Database::connection()->query('SELECT * FROM sms_notifications ORDER BY id')->fetchAll();
    assert_same(2, count($jobs), 'one confirmation and one reminder');
    assert_same($now->modify('+1 day')->format('Y-m-d H:i:s'), $jobs[1]['due_at'], 'exactly 24 hours before appointment');
    $messages = [];
    $sender = function (string $phone, string $body) use (&$messages, $now): array {
        $messages[] = [$phone, $body];
        // A second worker cannot claim the in-flight message.
        SmsNotifications::process(fn () => throw new RuntimeException('Duplicate send'), $now);
        return ['state'=>'submitted'];
    };
    assert_same(1, SmsNotifications::process($sender, $now), 'confirmation sends now');
    assert_same(0, SmsNotifications::process($sender, $now), 'confirmation not repeated');
    assert_same(0, SmsNotifications::process($sender, $now->modify('+23 hours')), 'reminder not early');
    assert_same(1, SmsNotifications::process($sender, $now->modify('+24 hours')), 'reminder sends at due time');
    assert_same(2, count($messages), 'exactly two sends');
    assert_contains('Reminder', $messages[1][1], 'reminder text');
    $appointmentDate = (new DateTimeImmutable(fixture_datetime('10:00'), new DateTimeZone('UTC')))->format('d M Y');
    assert_contains($appointmentDate . ' at 10:00 AM', $messages[1][1], 'local appointment time');
    foreach ($messages as $message) {
        assert_not_contains('Ghana time', $message[1], 'customer SMS omits timezone label');
    }
    assert_contains('SPA-EXISTING', $messages[0][1], 'reference included');
}

function test_sms_reschedule_pending_and_short_notice_behaviour(): void
{
    seed_availability_fixture();
    $now = (new DateTimeImmutable(fixture_datetime('10:00'), new DateTimeZone('UTC')))->modify('-2 days');
    $pdo = Database::connection();
    $booking = sms_job_booking(fixture_datetime('10:00'), 'pending');
    SmsNotifications::schedule($booking, $pdo, true, $now);
    assert_same(0, (int) $pdo->query('SELECT COUNT(*) FROM sms_notifications')->fetchColumn(), 'pending bookings do not notify');
    $booking = sms_job_booking(fixture_datetime('10:00'));
    SmsNotifications::schedule($booking, $pdo, true, $now);
    $nextDay = (new DateTimeImmutable(fixture_datetime('11:00'), new DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d H:i:s');
    $booking = sms_job_booking($nextDay);
    SmsNotifications::schedule($booking, $pdo, true, $now);
    assert_same(2, (int) $pdo->query("SELECT COUNT(*) FROM sms_notifications WHERE state='skipped'")->fetchColumn(), 'old jobs invalidated');
    assert_same(2, (int) $pdo->query("SELECT COUNT(*) FROM sms_notifications WHERE state='pending'")->fetchColumn(), 'new jobs queued');
    $booking = sms_job_booking($now->modify('+10 hours')->format('Y-m-d H:i:s'));
    SmsNotifications::schedule($booking, $pdo, true, $now);
    assert_same(1, (int) $pdo->query("SELECT COUNT(*) FROM sms_notifications WHERE state='pending'")->fetchColumn(), 'short notice gets confirmation only');
    $booking = sms_job_booking($now->modify('+10 hours')->format('Y-m-d H:i:s'), 'cancelled');
    SmsNotifications::schedule($booking, $pdo, true, $now);
    assert_same(0, SmsNotifications::process(fn () => throw new RuntimeException('Cancelled should not send'), $now), 'cancelled jobs skipped');
}

function test_sms_retry_uncertainty_and_expired_notifications(): void
{
    seed_availability_fixture();
    $now = (new DateTimeImmutable(fixture_datetime('10:00'), new DateTimeZone('UTC')))->modify('-2 days');
    $booking = sms_job_booking($now->modify('+10 hours')->format('Y-m-d H:i:s'));
    $pdo = Database::connection();
    SmsNotifications::schedule($booking, $pdo, true, $now);
    $retry = fn () => ['state'=>'retry','error'=>'Not sent'];
    SmsNotifications::process($retry, $now);
    assert_same(0, SmsNotifications::process($retry, $now->modify('+1 minute')), 'backoff honoured');
    SmsNotifications::process($retry, $now->modify('+5 minutes'));
    SmsNotifications::process($retry, $now->modify('+10 minutes'));
    assert_same('failed', $pdo->query('SELECT state FROM sms_notifications')->fetchColumn(), 'three attempts maximum');
    $pdo->exec("UPDATE sms_notifications SET state='pending',attempts=0,next_attempt_at=NULL");
    SmsNotifications::process(fn () => ['state'=>'unknown'], $now);
    assert_same(0, SmsNotifications::process($retry, $now->modify('+5 minutes')), 'unknown result never automatically duplicated');
    $pdo->exec("UPDATE sms_notifications SET state='pending'");
    assert_same(0, SmsNotifications::process(fn () => throw new RuntimeException('Expired should not send'), $now->modify('+2 hours')), 'late notification skipped');
    assert_same('skipped', $pdo->query('SELECT state FROM sms_notifications')->fetchColumn(), 'expired state');
}

function test_bulk_sms_ghana_request_and_response_contract(): void
{
    assert_same('+233244127788', BulkSms::phone('0244127788'), 'Ghana number normalized');
    assert_same('+233244127788', BulkSms::phone('+233244127788'), 'international number preserved');
    assert_throws(fn () => BulkSms::phone('123'), 'Invalid Ghana');
    $request = BulkSms::request(['api_key'=>'not-a-real-key','sender'=>'HappySpa'], '0244127788', 'Confirmation & reminder');
    $url = parse_url($request['url']);
    parse_str($url['query'], $params);
    assert_same('https', $url['scheme'], 'TLS required');
    assert_same('clientlogin.bulksmsgh.com', $url['host'], 'official endpoint');
    assert_same('233244127788', $params['to'], 'recipient');
    assert_same('Confirmation & reminder', $params['msg'], 'message encoded');
    assert_same('submitted', BulkSms::interpret(200, '1000')['state'], 'accepted response');
    assert_same('failed', BulkSms::interpret(200, '1003')['state'], 'insufficient balance');
    assert_same('retry', BulkSms::interpret(200, '1002')['state'], 'definite failure retry');
    assert_same('unknown', BulkSms::interpret(200, '<html>error</html>')['state'], 'unexpected response not success');
    assert_same('unknown', BulkSms::interpret(503, '')['state'], 'ambiguous server error');
    assert_throws(fn () => SmsConfig::save(['role'=>'receptionist'], []), 'Administrator access required');
    ob_start();
    render_admin('SMS', 'sms', ['admin'=>['username'=>'owner','role'=>'admin'], 'smsConfig'=>['enabled'=>false,'sender'=>'HappySpa','api_key'=>'secret-must-not-display'], 'notifications'=>[], 'workerLastRun'=>null,'error'=>'']);
    $html = (string) ob_get_clean();
    assert_not_contains('secret-must-not-display', $html, 'secret not exposed');
    assert_contains('BulkSMS Ghana', $html, 'provider settings');
}

function test_sms_stale_worker_claims_and_failed_delivery_do_not_break_bookings(): void
{
    seed_availability_fixture();
    $now = (new DateTimeImmutable(fixture_datetime('10:00'), new DateTimeZone('UTC')))->modify('-2 days');
    $booking = sms_job_booking($now->modify('+10 hours')->format('Y-m-d H:i:s'));
    $pdo = Database::connection();
    SmsNotifications::schedule($booking, $pdo, true, $now);
    $pdo->prepare("UPDATE sms_notifications SET state='processing',claimed_at=?")
        ->execute([$now->modify('-1 hour')->format('Y-m-d H:i:s')]);
    assert_same(0, SmsNotifications::process(fn () => throw new RuntimeException('Never resend uncertain job'), $now), 'interrupted worker not resent');
    assert_same('unknown', $pdo->query('SELECT state FROM sms_notifications')->fetchColumn(), 'stale processing becomes unknown');
    $pdo->exec("UPDATE sms_notifications SET state='pending'");
    SmsNotifications::process(fn () => throw new RuntimeException('Network interrupted'), $now);
    assert_same('unknown', $pdo->query('SELECT state FROM sms_notifications')->fetchColumn(), 'sender exception recorded');
    assert_same('confirmed', $pdo->query("SELECT status FROM bookings WHERE reference='SPA-EXISTING'")->fetchColumn(), 'booking preserved after failed SMS');
}

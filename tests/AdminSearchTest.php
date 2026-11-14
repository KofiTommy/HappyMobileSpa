<?php
declare(strict_types=1);

function test_admin_sms_search_and_pagination(): void
{
    seed_availability_fixture();
    $pdo = Database::connection();
    $bookingId = (int) $pdo->query("SELECT id FROM bookings WHERE reference='SPA-EXISTING'")->fetchColumn();
    $slotStart = $pdo->query("SELECT slot_start FROM bookings WHERE id={$bookingId}")->fetchColumn();
    $insert = $pdo->prepare("INSERT INTO sms_notifications (booking_id,kind,slot_start,due_at,state,attempts,created_at) VALUES (?,?,?,?,'pending',0,?)");
    for ($i = 0; $i < 21; $i++) {
        $now = gmdate('Y-m-d H:i:s');
        $insert->execute([$bookingId, sprintf('audit_%02d', $i), $slotStart, $now, $now]);
    }

    $listing = SmsNotifications::adminList('SPA-EXISTING', 1);
    assert_same(21, $listing['total'], 'SMS search counts every matching record');
    assert_same(2, $listing['pages'], 'SMS results use 20-item pages');
    assert_same(20, count($listing['notifications']), 'first SMS page bounded');
    assert_same(1, count(SmsNotifications::adminList('SPA-EXISTING', 2)['notifications']), 'second SMS page available');
    assert_same(2, SmsNotifications::adminList('SPA-EXISTING', 99)['page'], 'SMS page clamped to available pages');
    assert_same(21, SmsNotifications::adminList('Existing Client', 1)['total'], 'SMS customer-name search');
    assert_same(21, SmsNotifications::adminList('0244127788', 1)['total'], 'SMS phone search');
    assert_same(1, SmsNotifications::adminList('audit_20', 1)['total'], 'SMS type search');
    assert_same(0, SmsNotifications::adminList('no-such-notification', 1)['total'], 'SMS empty search result');

    ob_start();
    render_admin('SMS', 'sms', ['admin'=>['username'=>'owner','role'=>'admin'], 'smsConfig'=>['enabled'=>false,'sender'=>'HappySpa','api_key'=>''], 'notifications'=>$listing['notifications'], 'listing'=>$listing, 'workerLastRun'=>gmdate('Y-m-d H:i:s'), 'error'=>'']);
    $html = (string) ob_get_clean();
    assert_contains('name="search"', $html, 'SMS search field rendered');
    assert_contains('value="SPA-EXISTING"', $html, 'SMS search retained');
    assert_contains('search=SPA-EXISTING&amp;page=2', $html, 'SMS next page retains search');
}

function test_admin_message_search_filter_and_pagination(): void
{
    seed_test_catalog();
    for ($i = 0; $i < 21; $i++) {
        ContactMessage::send(array_replace(contact_test_input(), [
            'name'=>'Search Client', 'email'=>'client' . $i . '@example.com', 'subject'=>'Treatment enquiry',
        ]), 'search-sender-' . $i);
    }
    ContactMessage::send(array_replace(contact_test_input(), ['name'=>'Different Person', 'subject'=>'Corporate voucher']), 'other-sender');

    $inbox = ContactMessage::inbox('new', 1, 'Treatment enquiry');
    assert_same(21, $inbox['total'], 'message subject search');
    assert_same(2, $inbox['pages'], 'message search pages');
    assert_same(20, count($inbox['messages']), 'first message search page bounded');
    assert_same(1, count(ContactMessage::inbox('new', 2, 'Treatment enquiry')['messages']), 'second message search page');
    assert_same(1, ContactMessage::inbox('', 1, 'client20@example.com')['total'], 'message email search');
    assert_same(1, ContactMessage::inbox('', 1, 'Different Person')['total'], 'message name search');
    assert_same(0, ContactMessage::inbox('', 1, 'not-present')['total'], 'message empty search result');

    $id = (int) Database::connection()->query("SELECT id FROM contact_messages WHERE email='client20@example.com'")->fetchColumn();
    ContactMessage::setStatus(['role'=>'admin'], $id, 'read');
    assert_same(0, ContactMessage::inbox('new', 1, 'client20@example.com')['total'], 'message status and search combine');
    assert_same(1, ContactMessage::inbox('read', 1, 'client20@example.com')['total'], 'read message found by search');

    ob_start();
    render_admin('Messages', 'messages', ['admin'=>['username'=>'owner','role'=>'admin'], 'inbox'=>$inbox, 'error'=>'']);
    $html = (string) ob_get_clean();
    assert_contains('name="search"', $html, 'message search field rendered');
    assert_contains('value="Treatment enquiry"', $html, 'message search retained');
    assert_contains('status=new&amp;search=Treatment+enquiry&amp;page=2', $html, 'message next page retains status and search');

    $unsafe = ContactMessage::inbox('', 1, '"><script>alert(1)</script>');
    ob_start();
    render_admin('Messages', 'messages', ['admin'=>['username'=>'owner','role'=>'admin'], 'inbox'=>$unsafe, 'error'=>'']);
    $html = (string) ob_get_clean();
    assert_not_contains('<script>alert(1)</script>', $html, 'message search escaped');
}

function test_admin_payment_search_and_pagination(): void
{
    $booking = paystack_fixture();
    $pdo = Database::connection();
    $bookingStatement = $pdo->prepare("INSERT INTO bookings (reference,customer_id,service_id,staff_id,slot_start,slot_end,price,status,payment_method,payment_status,notes,accepted_policy,created_at,updated_at) VALUES (?,1,1,1,?,?,350,'confirmed','pay_at_spa','unpaid','','Policy',?,?)");
    $paymentStatement = $pdo->prepare("INSERT INTO paystack_payments (booking_id,reference,amount_subunit,currency,email,mode,state,created_at) VALUES (?,?,35000,'GHS','client@example.com','test','created',?)");
    for ($i = 0; $i < 21; $i++) {
        $bookingId = (int) $booking['id'];
        if ($i > 0) {
            $start = (new DateTimeImmutable($booking['slot_start'], new DateTimeZone('UTC')))->modify('+' . $i . ' days');
            $now = gmdate('Y-m-d H:i:s');
            $bookingStatement->execute([sprintf('SPA-PAYLIST-%02d', $i), $start->format('Y-m-d H:i:s'), $start->modify('+1 hour')->format('Y-m-d H:i:s'), $now, $now]);
            $bookingId = (int) $pdo->lastInsertId();
        }
        $paymentStatement->execute([$bookingId, sprintf('PAYLIST-%02d', $i), gmdate('Y-m-d H:i:s')]);
    }

    $listing = PaystackPayment::adminList('PAYLIST-', 1);
    assert_same(21, $listing['total'], 'payment reference search');
    assert_same(2, $listing['pages'], 'payment results use 20-item pages');
    assert_same(20, count($listing['payments']), 'first payment page bounded');
    assert_same(1, count(PaystackPayment::adminList('PAYLIST-', 2)['payments']), 'second payment page available');
    assert_same(21, PaystackPayment::adminList('client@example.com', 1)['total'], 'payment customer-email search');
    assert_same(21, PaystackPayment::adminList('created', 1)['total'], 'payment status search');
    assert_same(1, PaystackPayment::adminList('SPA-PAYLIST-20', 1)['total'], 'payment booking-reference search');
    assert_same(0, PaystackPayment::adminList('not-present', 1)['total'], 'payment empty search result');

    ob_start();
    render_admin('Paystack', 'payments', ['admin'=>['username'=>'owner','role'=>'admin'], 'error'=>'', 'payments'=>$listing['payments'], 'listing'=>$listing, 'paystackConfig'=>['enabled'=>false,'site_url'=>'','secret_key'=>'']]);
    $html = (string) ob_get_clean();
    assert_contains('name="search"', $html, 'payment search field rendered');
    assert_contains('value="PAYLIST-"', $html, 'payment search retained');
    assert_contains('search=PAYLIST-&amp;page=2', $html, 'payment next page retains search');
}

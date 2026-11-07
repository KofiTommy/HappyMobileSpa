<?php
declare(strict_types=1);

function contact_test_input(): array
{
    return ['name'=>'Ama Client','email'=>'ama@example.com','phone'=>'0244127788','subject'=>'Treatment enquiry','message'=>'Please tell me more about your massage treatments.'];
}

function test_contact_message_validation_and_spam_protection(): void
{
    seed_test_catalog();
    $input = contact_test_input();
    assert_throws(fn () => ContactMessage::send(array_replace($input,['email'=>'bad']), 'test-ip'), 'valid email');
    assert_throws(fn () => ContactMessage::send(array_replace($input,['message'=>'short']), 'test-ip'), '10 and 5000');
    assert_throws(fn () => ContactMessage::send(array_replace($input,['message'=>str_repeat('a',5001)]), 'test-ip'), '10 and 5000');
    assert_throws(fn () => ContactMessage::send(array_replace($input,['phone'=>'<script>']), 'test-ip'), 'valid phone');
    ContactMessage::send($input + ['website'=>'spam.example'], 'test-ip');
    assert_same(0, ContactMessage::inbox('', 1)['total'], 'honeypot discarded');
    for ($i=0; $i<5; $i++) { ContactMessage::send($input, 'test-ip'); }
    assert_throws(fn () => ContactMessage::send($input, 'test-ip'), 'Too many attempts');
    assert_same(5, ContactMessage::inbox('new', 1)['total'], 'five submissions stored');
}

function test_contact_inbox_status_permissions_and_pagination(): void
{
    seed_test_catalog();
    for ($i=0; $i<21; $i++) { ContactMessage::send(contact_test_input(), 'sender-' . $i); }
    $inbox = ContactMessage::inbox('new', 1);
    assert_same(20, count($inbox['messages']), 'page size bounded');
    assert_same(1, count(ContactMessage::inbox('new', 2)['messages']), 'second page');
    $id = (int) $inbox['messages'][0]['id'];
    assert_throws(fn () => ContactMessage::setStatus(['role'=>'receptionist'],$id,'read'), 'Administrator access required');
    ContactMessage::setStatus(['role'=>'admin'],$id,'read');
    assert_same(1, ContactMessage::inbox('read', 1)['total'], 'read filter');
    ContactMessage::setStatus(['role'=>'admin'],$id,'replied');
    assert_same(1, ContactMessage::inbox('replied', 1)['total'], 'replied status');
    assert_throws(fn () => ContactMessage::setStatus(['role'=>'admin'],$id,'invalid'), 'valid message status');
    assert_throws(fn () => ContactMessage::setStatus(['role'=>'admin'],99999,'read'), 'not found');
}

function test_contact_forms_and_inbox_escape_messages(): void
{
    seed_test_catalog();
    $input = contact_test_input();
    $input['message'] = '<script>alert("unsafe")</script>';
    ContactMessage::send($input, 'safe-test');
    $html = render_template_for_test('contact', ['settings'=>[], 'error'=>'Please retry', 'old'=>$input, 'sent'=>false]);
    assert_contains('name="csrf"', $html, 'form csrf');
    assert_contains('Send message', $html, 'send button');
    assert_not_contains('<script>alert(', $html, 'contact input escaped');
    ob_start();
    render_admin('Messages', 'messages', ['admin'=>['username'=>'owner','role'=>'admin'], 'inbox'=>ContactMessage::inbox('',1), 'error'=>'']);
    $html = (string) ob_get_clean();
    assert_not_contains('<script>alert(', $html, 'stored message escaped');
    assert_contains('Reply by email', $html, 'reply action');
}

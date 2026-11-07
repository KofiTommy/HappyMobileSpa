<?php
declare(strict_types=1);

function test_receptionist_account_lifecycle_and_permissions(): void
{
    seed_availability_fixture();
    $owner = seed_admin('owner', password_hash('owner password long', PASSWORD_DEFAULT));
    $admin = ['id' => $owner, 'role' => 'admin'];
    $input = ['action' => 'create', 'staff_id' => 1, 'username' => 'reception', 'password' => 'a secure reception password'];
    assert_throws(fn () => Receptionists::manage($admin, array_replace($input, ['staff_id'=>999])), 'existing team member');
    Receptionists::manage($admin, $input);
    assert_same(true, Auth::attempt('reception', $input['password']), 'receptionist can log in');
    $user = Auth::requireUser();
    assert_same('receptionist', $user['role'], 'restricted role');
    $linked = Database::connection()->query("SELECT staff_id FROM admin_users WHERE username='reception'")->fetchColumn();
    assert_same(1, (int) $linked, 'login linked to team member');
    assert_throws(fn () => Auth::requireAdmin(), 'Administrator access required');
    assert_throws(fn () => Receptionists::manage($user, $input), 'Administrator access required');
    assert_throws(fn () => Receptionists::manage($admin, $input), 'already has a login');
    assert_throws(fn () => Booking::createManual(seed_manual_input(), (int) $user['id'], true), 'Administrator access required');
    $booking = Booking::createManual(seed_manual_input(), (int) $user['id']);
    assert_same($booking['id'], (int) Booking::adminList(['date' => fixture_date(), 'search' => $booking['reference']])[0]['id'], 'date and reference lookup');
    $nextDay = (new DateTimeImmutable(fixture_date(), new DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d');
    assert_same([], Booking::adminList(['date' => $nextDay, 'search' => $booking['reference']]), 'different day excluded');
    Receptionists::manage($admin, ['action'=>'password', 'user_id'=>$user['id'], 'password'=>'replacement secure password']);
    assert_same(false, Auth::attempt('reception', $input['password']), 'old password denied');
    assert_same(true, Auth::attempt('reception', 'replacement secure password'), 'reset password accepted');
    Receptionists::manage($admin, ['action'=>'active', 'user_id'=>$user['id'], 'active'=>'0']);
    assert_throws(fn () => Auth::requireUser(), 'Authentication required');
    assert_same(false, Auth::attempt('reception', 'replacement secure password'), 'disabled login denied');
    assert_throws(fn () => Receptionists::manage($admin, ['action'=>'active', 'user_id'=>$owner]), 'not found');
    Auth::logout();
}

function test_reception_check_in_preserves_status_and_records_actor(): void
{
    $booking = seed_confirmed_booking('today +10 hours');
    $id = seed_admin('reception', password_hash('reception password long', PASSWORD_DEFAULT));
    Booking::checkIn($booking['id'], $id);
    $row = Booking::adminList(['id' => $booking['id']])[0];
    assert_same('confirmed', $row['status'], 'arrival preserves reservation');
    assert_same(true, !empty($row['checked_in_at']), 'arrival recorded');
    assert_throws(fn () => Booking::checkIn($booking['id'], $id), 'already checked in');
    Booking::setStatus($booking['id'], 'completed', $id);
    assert_throws(fn () => Booking::checkIn($booking['id'], $id), 'Only confirmed');
    $booking = seed_confirmed_booking('+48 hours');
    assert_throws(fn () => Booking::checkIn($booking['id'], $id), 'for today');
}

function test_receptionist_views_hide_admin_controls(): void
{
    seed_test_catalog();
    $admin = ['id'=>1, 'username'=>'reception', 'role'=>'receptionist'];
    ob_start();
    render_admin('New booking', 'manual-booking', ['admin'=>$admin, 'services'=>Catalog::activeServices(), 'staff'=>Catalog::activeStaff(), 'old'=>[], 'error'=>'']);
    $html = (string) ob_get_clean();
    assert_not_contains('href="settings.php"', $html, 'settings hidden');
    assert_not_contains('href="users.php"', $html, 'accounts hidden');
    assert_not_contains('name="override"', $html, 'override hidden');
    assert_contains('Receptionist', $html, 'role displayed');
    ob_start();
    render_admin('Team', 'staff', ['admin'=>['id'=>2,'username'=>'owner','role'=>'admin'], 'staff'=>Catalog::adminStaff(), 'services'=>Catalog::adminServices(), 'editing'=>Catalog::adminStaff()[0], 'schedule'=>['hours'=>[], 'breaks'=>[]], 'error'=>'', 'success'=>'', 'receptionist'=>null]);
    $html = (string) ob_get_clean();
    assert_contains('Assign as receptionist', $html, 'team assignment form');
    assert_contains('name="staff_id" value="1"', $html, 'team member link');
}

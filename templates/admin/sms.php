<?php if ($error): ?><div class="admin-error" role="alert"><?= escape($error) ?></div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="admin-success" role="status">SMS settings saved.</div><?php endif; ?>
<header class="page-lead">
    <div><p class="admin-eyebrow">RECEPTION WORKSPACE</p><h2>Booking texts &amp; reminders</h2><p>Confirmed bookings receive a confirmation text and a reminder 24 hours before the appointment. Successfully verified online payments receive a separate payment receipt. Appointments booked less than 24 hours ahead receive the confirmation only. Cancelled appointments are skipped; rescheduled appointments use their new time.</p></div>
</header>
<section class="admin-section">
<p><span class="status"><?= SmsConfig::ready($smsConfig) ? 'SMS enabled' : 'SMS disabled or awaiting credentials' ?></span></p>
<p>Background worker last ran: <?= $workerLastRun ? escape((new DateTimeImmutable($workerLastRun, new DateTimeZone('UTC')))->setTimezone(business_timezone())->format('j M Y, g:i A')) . ' (Ghana time)' : 'Not yet — the reminder task must be installed on the server.' ?></p>
<?php if (!$workerLastRun || strtotime($workerLastRun . ' UTC') < time() - 300): ?><div class="admin-error" role="status">Automatic reminders are not currently running. Keep the server and database online and check the scheduled reminder task.</div><?php endif; ?>
<form method="post" class="admin-form form-section"><input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>">
<p>Provider: <strong>BulkSMS Ghana</strong></p>
<label>Approved sender ID<input name="sender" required maxlength="11" pattern="[A-Za-z0-9]+" value="<?= escape($smsConfig['sender']) ?>"></label>
<p class="help-text">Enter your BulkSMS Ghana API key. Leave it blank to keep the saved key. Credentials are never displayed here.</p>
<label>BulkSMS Ghana API key<input type="password" name="api_key" autocomplete="new-password"></label>
<label class="check-row"><input type="checkbox" name="enabled" value="1" <?= $smsConfig['enabled'] ? 'checked' : '' ?>> Enable SMS notifications</label>
<button class="admin-button">Save SMS settings</button></form></section>
<section class="admin-section"><div class="panel-heading"><div><h2>Recent notifications</h2><p>“Submitted” means the provider accepted the message; it does not confirm handset delivery. If a result is unknown, check your provider's history before sending again. Temporary, definite rejections are retried up to three times. Notifications more than one hour late are skipped.</p></div></div>
<div class="record-list">
<?php if (!$notifications): ?><div class="admin-empty"><h3>No notifications queued yet</h3><p>Change the filters or check again after new activity.</p></div><?php endif; ?>
<?php foreach ($notifications as $notification): ?><article class="management-row record-card"><div><h3><a href="bookings.php?id=<?= $notification['booking_id'] ?>"><?= escape($notification['reference']) ?></a> · <?= escape(ucfirst(str_replace('_', ' ', $notification['kind']))) ?></h3><p><span class="status"><?= escape(ucfirst($notification['state'])) ?></span> · <?= (int) $notification['attempts'] ?> attempts</p><small>Due <?= escape((new DateTimeImmutable($notification['due_at'],new DateTimeZone('UTC')))->setTimezone(business_timezone())->format('j M Y, g:i A')) ?> (Ghana time)</small><?php if ($notification['last_error']): ?><p><?= escape($notification['last_error']) ?></p><?php endif; ?></div></article><?php endforeach; ?></div></section>

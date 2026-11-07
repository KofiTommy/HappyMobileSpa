<section class="admin-section">
<div class="panel-heading"><div><p class="admin-eyebrow">RECEPTION WORKSPACE</p><h2>Receptionist access</h2><p>Assign <?= escape($editing['name']) ?> to reception so they can book clients, find today's appointments, check arrivals, and record payments. Treatment assignments and appointment availability are managed separately above.</p></div></div>
<?php if (empty($receptionist)): ?>
<form method="post" class="admin-form form-section">
<input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>"><input type="hidden" name="action" value="create"><input type="hidden" name="staff_id" value="<?= $editing['id'] ?>">
<label>Login username<input name="username" required minlength="3" maxlength="80" autocomplete="off"></label>
<label>Initial password<input name="password" type="password" required minlength="12" maxlength="72" autocomplete="new-password"></label>
<button class="admin-button">Assign as receptionist</button>
</form>
<?php else: ?>
<p><strong><?= escape($receptionist['username']) ?></strong> — <span class="status"><?= $receptionist['active'] ? 'Receptionist access enabled' : 'Receptionist access removed' ?></span></p>
<form method="post" class="inline-actions"><input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>"><input type="hidden" name="staff_id" value="<?= $editing['id'] ?>"><input type="hidden" name="user_id" value="<?= $receptionist['id'] ?>"><input type="hidden" name="action" value="active"><input type="hidden" name="active" value="<?= $receptionist['active'] ? '0' : '1' ?>"><button class="admin-button"><?= $receptionist['active'] ? 'Remove receptionist access' : 'Restore receptionist access' ?></button></form>
<form method="post" class="admin-form form-section"><input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>"><input type="hidden" name="staff_id" value="<?= $editing['id'] ?>"><input type="hidden" name="user_id" value="<?= $receptionist['id'] ?>"><input type="hidden" name="action" value="password"><label>New password<input name="password" type="password" required minlength="12" maxlength="72" autocomplete="new-password"></label><button class="admin-button">Reset password</button></form>
<?php endif; ?>
<p class="help-text">They sign in through the staff login page. Removing reception access preserves their team profile and booking history.</p>
</section>

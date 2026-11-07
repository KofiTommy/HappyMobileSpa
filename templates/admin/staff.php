<?php if ($error): ?><div class="admin-error" role="alert"><?= escape($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="admin-success" role="status"><?= escape($success) ?></div><?php endif; ?>
<header class="page-lead">
    <div><p class="admin-eyebrow">RECEPTION WORKSPACE</p><h2>Team members</h2></div><a class="admin-button" href="staff.php">+ Add team member</a>
</header>
<div class="management-grid">
<section class="admin-section">
    <div class="management-list record-list">
        <?php if (!$staff): ?><div class="admin-empty"><h3>No records in this view.</h3><p>Change the filters or check again after new activity.</p></div><?php endif; ?>
        <?php foreach ($staff as $person): ?><article class="management-row record-card"><div><?php if ($person['photo']): ?><img class="team-avatar" src="../<?= escape($person['photo']) ?>" alt="" width="64" height="64"><?php endif; ?><h3><?= escape($person['name']) ?></h3><p><?= count($person['service_ids']) ?> treatments assigned</p><small class="status"><?= $person['active'] ? 'Available for booking' : 'Inactive — history preserved' ?></small></div><div class="inline-actions"><a class="small-button" href="staff.php?edit=<?= $person['id'] ?>">Edit & schedule</a></div></article><?php endforeach; ?>
    </div>
</section>
<section class="admin-section form-panel">
    <div class="panel-heading"><div><p class="admin-eyebrow"><?= $editing ? 'TEAM MEMBER' : 'NEW TEAM MEMBER' ?></p><h2><?= $editing ? escape($editing['name']) : 'Add team member' ?></h2></div></div>
    <form method="post" class="admin-form form-section" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $editing['id'] ?? '' ?>">
        <label>Name<input name="name" maxlength="160" required value="<?= escape($editing['name'] ?? '') ?>"></label>
        <label>Usual monthly salary (₵)<input type="number" name="monthly_salary" min="0" max="99999999.99" step="0.01" required value="<?= escape($editing['monthly_salary'] ?? '0.00') ?>"><small class="help-text">Used to prefill monthly salary records in Finance. Enter 0 if this worker is not salaried.</small></label>
        <?php if (!empty($editing['photo'])): ?><img class="team-photo-preview" src="../<?= escape($editing['photo']) ?>" alt="Current photo of <?= escape($editing['name']) ?>" width="128" height="128"><label class="check-row"><input type="checkbox" name="remove_photo" value="1"> Remove current photo</label><?php endif; ?>
        <label>Profile picture<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" aria-describedby="photo-help"></label>
        <small id="photo-help" class="help-text">JPG, PNG, or WebP. Maximum 2 MB and 4096 × 4096 pixels. Customers see this photo when choosing a therapist. Uploading a new photo replaces the current one.</small>
        <label>Biography<textarea name="biography" rows="3"><?= escape($editing['biography'] ?? '') ?></textarea></label>
        <fieldset class="check-grid"><legend>Treatments offered</legend><?php foreach ($services as $service): if ($service['archived_at']) continue; ?><label><input type="checkbox" name="service_ids[]" value="<?= $service['id'] ?>" <?= $editing && in_array((int) $service['id'], $editing['service_ids'], true) ? 'checked' : '' ?>> <?= escape($service['name']) ?></label><?php endforeach; ?></fieldset>
        <label class="check-row"><input type="checkbox" name="active" value="1" <?= !$editing || $editing['active'] ? 'checked' : '' ?>> Available for appointment booking</label>
        <button class="admin-button" type="submit">Save team member</button>
    </form>
    <?php if ($editing): require __DIR__ . '/receptionist-access.php'; endif; ?>
    <?php if ($editing):
        $hoursByDay = []; $breaksByDay = [];
        foreach ($schedule['hours'] as $row) { $hoursByDay[(int) $row['weekday']] = $row; }
        foreach ($schedule['breaks'] as $row) { $breaksByDay[(int) $row['weekday']] = $row; }
    ?>
    <form method="post" class="admin-form schedule-form form-section">
        <input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>"><input type="hidden" name="action" value="schedule"><input type="hidden" name="id" value="<?= $editing['id'] ?>">
        <div class="panel-heading"><div><h2>Weekly schedule</h2><p>Leave times empty for a day off. One shift and one break can be set per day.</p></div></div>
        <div class="schedule-editor"><div class="schedule-editor-head"><span>Day</span><span>Shift start</span><span>Shift end</span><span>Break start</span><span>Break end</span></div>
        <?php foreach (['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $day => $label): $shift=$hoursByDay[$day]??null; $break=$breaksByDay[$day]??null; ?>
            <div class="schedule-editor-row"><strong><?= $label ?></strong><input type="hidden" name="hours[<?= $day ?>][weekday]" value="<?= $day ?>"><input type="hidden" name="breaks[<?= $day ?>][weekday]" value="<?= $day ?>">
                <input type="time" aria-label="<?= $label ?> shift start" name="hours[<?= $day ?>][start_time]" value="<?= $shift ? substr($shift['start_time'],0,5) : '' ?>"><input type="time" aria-label="<?= $label ?> shift end" name="hours[<?= $day ?>][end_time]" value="<?= $shift ? substr($shift['end_time'],0,5) : '' ?>">
                <input type="time" aria-label="<?= $label ?> break start" name="breaks[<?= $day ?>][start_time]" value="<?= $break ? substr($break['start_time'],0,5) : '' ?>"><input type="time" aria-label="<?= $label ?> break end" name="breaks[<?= $day ?>][end_time]" value="<?= $break ? substr($break['end_time'],0,5) : '' ?>">
            </div>
        <?php endforeach; ?></div>
        <button class="admin-button" type="submit">Save schedule</button>
    </form>
    <?php endif; ?>
</section>
</div>

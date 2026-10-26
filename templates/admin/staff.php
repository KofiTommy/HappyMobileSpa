<?php if ($error): ?><div class="admin-error" role="alert"><?= escape($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="admin-success" role="status"><?= escape($success) ?></div><?php endif; ?>
<div class="management-grid">
<section class="admin-section">
    <div class="section-title"><div><p class="admin-eyebrow">YOUR TEAM</p><h2>Therapists</h2></div><a class="admin-button" href="staff.php">+ Add team member</a></div>
    <div class="management-list"><?php foreach ($staff as $person): ?><article class="management-row"><div><h3><?= escape($person['name']) ?></h3><p><?= count($person['service_ids']) ?> treatments assigned</p><small><?= $person['active'] ? 'Available for booking' : 'Inactive — history preserved' ?></small></div><a class="small-button" href="staff.php?edit=<?= $person['id'] ?>">Edit & schedule</a></article><?php endforeach; ?></div>
</section>
<section class="admin-section form-panel">
    <p class="admin-eyebrow"><?= $editing ? 'TEAM MEMBER' : 'NEW TEAM MEMBER' ?></p><h2><?= $editing ? escape($editing['name']) : 'Add team member' ?></h2>
    <form method="post" class="admin-form">
        <input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $editing['id'] ?? '' ?>">
        <label>Name<input name="name" maxlength="160" required value="<?= escape($editing['name'] ?? '') ?>"></label>
        <label>Biography<textarea name="biography" rows="3"><?= escape($editing['biography'] ?? '') ?></textarea></label>
        <fieldset class="check-grid"><legend>Treatments offered</legend><?php foreach ($services as $service): if ($service['archived_at']) continue; ?><label><input type="checkbox" name="service_ids[]" value="<?= $service['id'] ?>" <?= $editing && in_array((int) $service['id'], $editing['service_ids'], true) ? 'checked' : '' ?>> <?= escape($service['name']) ?></label><?php endforeach; ?></fieldset>
        <label class="check-row"><input type="checkbox" name="active" value="1" <?= !$editing || $editing['active'] ? 'checked' : '' ?>> Available for appointment booking</label>
        <button class="admin-button" type="submit">Save team member</button>
    </form>
    <?php if ($editing):
        $hoursByDay = []; $breaksByDay = [];
        foreach ($schedule['hours'] as $row) { $hoursByDay[(int) $row['weekday']] = $row; }
        foreach ($schedule['breaks'] as $row) { $breaksByDay[(int) $row['weekday']] = $row; }
    ?>
    <form method="post" class="admin-form schedule-form">
        <input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>"><input type="hidden" name="action" value="schedule"><input type="hidden" name="id" value="<?= $editing['id'] ?>">
        <h2>Weekly schedule</h2><p>Leave times empty for a day off. One shift and one break can be set per day.</p>
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

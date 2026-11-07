<?php if ($error): ?><div class="admin-error" role="alert"><?= escape($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="admin-success" role="status"><?= escape($success) ?></div><?php endif; ?>
<header class="page-lead">
    <div><p class="admin-eyebrow">RECEPTION WORKSPACE</p><h2>Treatments</h2></div><a class="admin-button" href="services.php">+ Add treatment</a>
</header>
<div class="management-grid">
<section class="admin-section">
    <div class="management-list record-list">
    <?php if (!$services): ?><div class="admin-empty"><h3>No records in this view.</h3><p>Change the filters or check again after new activity.</p></div><?php endif; ?>
    <?php foreach ($services as $service): ?>
        <article class="management-row record-card">
            <div><h3><?= escape($service['name']) ?></h3><p><?= $service['duration_min'] ?> min + <?= $service['buffer_min'] ?> min buffer · <?= money($service['price']) ?></p><small class="status"><?= $service['archived_at'] ? 'Archived' : ($service['active'] ? 'Published' : 'Hidden') ?></small></div>
            <div class="row-actions inline-actions">
                <?php if (!$service['archived_at']): ?><a class="small-button" href="services.php?edit=<?= $service['id'] ?>">Edit</a>
                <form method="post" data-confirm="Archive this treatment? Existing bookings will be preserved."><input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>"><input type="hidden" name="action" value="archive"><input type="hidden" name="id" value="<?= $service['id'] ?>"><button class="small-button danger-button" type="submit">Archive</button></form><?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
    </div>
</section>
<section class="admin-section form-panel">
    <div class="panel-heading"><div><p class="admin-eyebrow"><?= $editing ? 'EDIT TREATMENT' : 'NEW TREATMENT' ?></p><h2><?= $editing ? 'Update details' : 'Add treatment' ?></h2></div></div>
    <form method="post" class="admin-form form-section">
        <input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $editing['id'] ?? '' ?>">
        <label>Name<input name="name" maxlength="160" required value="<?= escape($editing['name'] ?? '') ?>"></label>
        <label>Description<textarea name="description" rows="4" required><?= escape($editing['description'] ?? '') ?></textarea></label>
        <div class="form-two"><label>Duration (minutes)<input type="number" name="duration_min" min="15" max="480" required value="<?= $editing['duration_min'] ?? 60 ?>"></label><label>Buffer (minutes)<input type="number" name="buffer_min" min="0" max="120" required value="<?= $editing['buffer_min'] ?? 15 ?>"></label></div>
        <div class="form-two"><label>Price (GH₵)<input type="number" name="price" min="0" step="0.01" required value="<?= $editing['price'] ?? '' ?>"></label><label>Display order<input type="number" name="display_order" required value="<?= $editing['display_order'] ?? count($services) + 1 ?>"></label></div>
        <label class="check-row"><input type="checkbox" name="active" value="1" <?= !$editing || $editing['active'] ? 'checked' : '' ?>> Visible on the public website</label>
        <button class="admin-button" type="submit"><?= $editing ? 'Save treatment' : 'Add treatment' ?></button>
    </form>
</section>
</div>

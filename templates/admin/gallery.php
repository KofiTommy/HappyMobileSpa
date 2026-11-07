<?php if ($error): ?><div class="admin-error" role="alert"><?= escape($error) ?></div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="admin-success" role="status">Gallery updated.</div><?php endif; ?>
<header class="page-lead">
    <div><p class="admin-eyebrow">RECEPTION WORKSPACE</p><h2>Add a gallery photo</h2><p>Published photos appear in the gallery. Select “Show in homepage carousel” to also feature a photo on the homepage. Lower display orders appear first. Original spa images appear when no photos are selected.</p></div>
</header>
<section class="admin-section">
<?php $image = null; require __DIR__ . '/gallery-form.php'; ?></section>
<div class="gallery-admin-grid record-list">
<?php if (!$images): ?><div class="admin-empty"><h3>No gallery photos uploaded yet</h3><p>Change the filters or check again after new activity.</p></div><?php endif; ?>
<?php foreach ($images as $image): ?><section class="admin-section record-card"><img class="gallery-admin-preview" src="../<?= escape($image['path']) ?>" alt="<?= escape($image['alt_text']) ?>" width="480" height="320" loading="lazy">
<?php require __DIR__ . '/gallery-form.php'; ?>
<form method="post" class="inline-actions" data-confirm="Delete this photo? This cannot be undone."><input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>"><input type="hidden" name="id" value="<?= $image['id'] ?>"><button class="small-button danger-button" name="action" value="delete">Delete photo</button></form></section><?php endforeach; ?></div>

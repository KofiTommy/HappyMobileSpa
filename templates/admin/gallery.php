<?php if ($error): ?><div class="admin-error" role="alert"><?= escape($error) ?></div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="admin-success" role="status">Gallery updated.</div><?php endif; ?>
<header class="page-lead">
    <div><p class="admin-eyebrow">RECEPTION WORKSPACE</p><h2>Add a photo or video</h2><p>Published photos and videos appear in the gallery. Select “Show in homepage carousel” to also feature it in the homepage hero. Lower display orders appear first. Original spa images appear when no media is selected.</p></div>
</header>
<section class="admin-section">
<?php $image = null; require __DIR__ . '/gallery-form.php'; ?></section>
<div class="gallery-admin-grid record-list">
<?php if (!$images): ?><div class="admin-empty"><h3>No gallery media uploaded yet</h3><p>Change the filters or check again after new activity.</p></div><?php endif; ?>
<?php foreach ($images as $image): ?><section class="admin-section record-card"><?php if (GalleryMedia::available($image['path'])): ?><?php if (GalleryMedia::isVideo($image['path'])): ?><video class="gallery-admin-preview" src="../<?= escape($image['path']) ?>" controls playsinline preload="metadata" aria-label="<?= escape($image['alt_text']) ?>"></video><?php else: ?><img class="gallery-admin-preview" src="../<?= escape($image['path']) ?>" alt="<?= escape($image['alt_text']) ?>" width="480" height="320" loading="lazy"><?php endif; ?><?php else: ?><p class="admin-error" role="alert">This media file is unavailable on the server. Choose a replacement photo or video and save this record.</p><?php endif; ?>
<?php require __DIR__ . '/gallery-form.php'; ?>
<form method="post" class="inline-actions" data-confirm="Delete this photo or video? This cannot be undone."><input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>"><input type="hidden" name="id" value="<?= $image['id'] ?>"><button class="small-button danger-button" name="action" value="delete">Delete media</button></form></section><?php endforeach; ?></div>

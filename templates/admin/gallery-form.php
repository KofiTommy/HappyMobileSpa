<form method="post" enctype="multipart/form-data" class="admin-form form-section">
<input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $image['id'] ?? '' ?>">
<label><?= $image ? 'Replace photo (optional)' : 'Photo' ?><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" <?= $image ? '' : 'required' ?>></label>
<small class="help-text">JPG, PNG, or WebP. Up to 2 MB and 4096 × 4096 pixels. Landscape photos work best on the homepage.</small>
<label>Caption<input name="caption" required maxlength="160" value="<?= escape($image['caption'] ?? '') ?>"></label>
<label>Image description<input name="alt_text" required maxlength="255" value="<?= escape($image['alt_text'] ?? '') ?>"><small class="help-text">Describe what is in the photo for visitors using screen readers.</small></label>
<label>Display order<input name="display_order" type="number" min="0" max="9999" value="<?= (int) ($image['display_order'] ?? 0) ?>"></label>
<label class="check-row"><input type="checkbox" name="published" value="1" <?= !$image || $image['published'] ? 'checked' : '' ?>> Published in gallery</label>
<label class="check-row"><input type="checkbox" name="show_in_hero" value="1" <?= !$image || $image['show_in_hero'] ? 'checked' : '' ?>> Show in homepage carousel</label>
<button class="admin-button"><?= $image ? 'Save changes' : 'Upload photo' ?></button></form>

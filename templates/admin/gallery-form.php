<form method="post" enctype="multipart/form-data" class="admin-form form-section">
<input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $image['id'] ?? '' ?>">
<label><?= $image ? 'Replace photo or video (optional)' : 'Photo or video' ?><input type="file" name="photo" accept="image/jpeg,image/png,image/webp,video/mp4" <?= $image ? '' : 'required' ?>></label>
<small class="help-text">Photos: JPG, PNG or WebP, up to 2 MB and 4096 x 4096 pixels. Videos: MP4 up to 5 MB; export using H.264 for browser compatibility. Short landscape clips work best. Hero videos start muted and include playback controls.</small>
<label>Caption<input name="caption" required maxlength="160" value="<?= escape($image['caption'] ?? '') ?>"></label>
<label>Photo or video description<input name="alt_text" required maxlength="255" value="<?= escape($image['alt_text'] ?? '') ?>"><small class="help-text">Describe the scene for visitors using screen readers. For spoken content, include the words in the description or use a captioned video.</small></label>
<label>Display order<input name="display_order" type="number" min="0" max="9999" value="<?= (int) ($image['display_order'] ?? 0) ?>"></label>
<label class="check-row"><input type="checkbox" name="published" value="1" <?= !$image || $image['published'] ? 'checked' : '' ?>> Published in gallery</label>
<label class="check-row"><input type="checkbox" name="show_in_hero" value="1" <?= !$image || $image['show_in_hero'] ? 'checked' : '' ?>> Show in homepage carousel</label>
<button class="admin-button"><?= $image ? 'Save changes' : 'Upload media' ?></button></form>

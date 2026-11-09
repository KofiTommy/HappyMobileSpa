<?php
declare(strict_types=1);

function test_team_photo_validation_rejects_unsafe_and_oversized_files(): void
{
    $path = tempnam(sys_get_temp_dir(), 'spa-photo-');
    try {
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a2ioAAAAASUVORK5CYII='));
        assert_same('png', TeamPhoto::inspect($path), 'valid PNG recognized');
        file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        assert_throws(fn () => TeamPhoto::inspect($path), 'JPG, PNG, or WebP');
        file_put_contents($path, str_repeat('x', TeamPhoto::MAX_BYTES + 1));
        clearstatcache(true, $path);
        assert_throws(fn () => TeamPhoto::inspect($path), 'smaller than 2 MB');
        assert_same(null, TeamPhoto::upload(['error'=>UPLOAD_ERR_NO_FILE]), 'no upload preserves photo');
        assert_throws(fn () => TeamPhoto::upload(['error'=>UPLOAD_ERR_INI_SIZE]), 'upload failed');
        assert_throws(fn () => TeamPhoto::upload(['error'=>UPLOAD_ERR_OK, 'tmp_name'=>$path]), 'Invalid photo upload');
    } finally { unlink($path); }
}

function test_team_photo_preserved_removed_and_publicly_available(): void
{
    seed_test_catalog();
    $photo = 'assets/images/team/' . str_repeat('a', 32) . '.png';
    $file = APP_ROOT . '/' . $photo;
    file_put_contents($file, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a2ioAAAAASUVORK5CYII='));
    try {
        Database::connection()->prepare('UPDATE staff SET photo=? WHERE id=1')->execute([$photo]);
        $input = ['name'=>'Ama S.', 'biography'=>'Your therapist', 'service_ids'=>[1], 'active'=>1];
        Catalog::saveStaff($input, 1);
        assert_same($photo, Catalog::activeStaff(1)[0]['photo'], 'photo preserved when editing');
        $html = render_template_for_test('book', ['services'=>Catalog::activeServices(), 'staff'=>Catalog::activeStaff(1), 'selectedService'=>Catalog::activeServices()[0], 'settings'=>[], 'error'=>'', 'old'=>[]]);
        assert_contains('data-therapist-cards', $html, 'photo selection container');
        assert_contains(str_repeat('a', 32), $html, 'public profile includes photo');
        Database::connection()->exec('UPDATE staff SET active=0 WHERE id=1');
        $html = render_template_for_test('book', ['services'=>Catalog::activeServices(), 'staff'=>Catalog::activeStaff(1), 'selectedService'=>Catalog::activeServices()[0], 'settings'=>[], 'error'=>'', 'old'=>[]]);
        assert_not_contains(str_repeat('a', 32), $html, 'inactive profile excluded');
        Catalog::saveStaff($input + ['remove_photo'=>'1'], 1);
        assert_same(null, Catalog::activeStaff(1)[0]['photo'], 'photo removed');
    } finally {
        if (is_file($file)) { unlink($file); }
    }
}

function test_missing_uploaded_photos_fall_back_and_server_rules_are_portable(): void
{
    seed_test_catalog();
    $missing = 'assets/images/team/' . str_repeat('b', 32) . '.jpg';
    Database::connection()->prepare('UPDATE staff SET photo=? WHERE id=1')->execute([$missing]);
    $person = Catalog::activeStaff(1)[0];
    assert_same(null, $person['photo'], 'missing staff photo falls back to initials');
    assert_same(true, $person['photo_missing'], 'missing staff photo remains visible to admin');

    $missingGallery = 'assets/images/gallery/' . str_repeat('c', 32) . '.jpg';
    Database::connection()->prepare('INSERT INTO gallery_images (path,caption,alt_text,published,show_in_hero,display_order) VALUES (?,?,?,1,1,0)')
        ->execute([$missingGallery, 'Unavailable photo', 'Unavailable photo']);
    assert_same(3, count(Gallery::images()), 'missing gallery photo falls back to original spa images');
    ob_start();
    render_admin('Gallery', 'gallery', ['admin'=>['username'=>'owner','role'=>'admin'], 'images'=>[['id'=>1,'path'=>$missingGallery,'caption'=>'Unavailable','alt_text'=>'Unavailable','published'=>1,'show_in_hero'=>1,'display_order'=>0]],'error'=>'']);
    $html = (string) ob_get_clean();
    assert_contains('photo file is unavailable on the server', $html, 'admin receives a missing-file warning');
    assert_not_contains($missingGallery, $html, 'broken image URL is not rendered');

    foreach (['team', 'gallery'] as $collection) {
        $rules = file_get_contents(APP_ROOT . '/assets/images/' . $collection . '/.htaccess');
        assert_not_contains('RemoveHandler', $rules, $collection . ' avoids Apache-specific handler removal');
        assert_not_contains('SetHandler', $rules, $collection . ' uses normal static-file handling');
    }
    assert_same(true, TeamPhoto::available('assets/images/hero-spa.jpg'), 'existing public image recognized');
    assert_same(false, TeamPhoto::available('../config/config.php'), 'non-image paths rejected');
}

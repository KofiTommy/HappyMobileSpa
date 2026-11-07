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
}

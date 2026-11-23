<?php
declare(strict_types=1);

function test_therapist_video_links_validate_and_reject_unsafe_destinations(): void
{
    foreach (['https://www.youtube.com/watch?v=introduction', 'https://youtu.be/introduction', 'https://www.tiktok.com/@happy_mobile_massage/video/123', 'https://www.instagram.com/reel/intro/', 'https://vimeo.com/123', 'https://cdn.example.com/introduction.mp4?token=test'] as $url) {
        assert_same($url, TherapistVideo::validate($url), 'supported video link accepted');
    }
    foreach (['javascript:alert(1)', 'http://youtu.be/intro', 'https://www.youtube.com.evil.example/watch?v=x', 'https://user:password@youtube.com/watch?v=x', 'https://example.com/page.html', 'https://127.0.0.1/private.mp4', 'https://localhost/private.mp4', 'https://example.com/intro.mp4' . "\nInjected"] as $url) {
        assert_throws(fn () => TherapistVideo::validate($url), 'HTTPS video link');
        assert_same(null, TherapistVideo::publicUrl($url), 'invalid saved links are never published');
    }
    assert_same(null, TherapistVideo::validate(''), 'video optional');
}

function test_therapist_video_changes_preserve_profile_pictures(): void
{
    seed_test_catalog();
    $pdo = Database::connection();
    $photo = 'assets/images/hero-spa.jpg';
    $pdo->prepare('UPDATE staff SET photo=? WHERE id=1')->execute([$photo]);
    $input = ['name'=>'Ama S.', 'biography'=>'Your therapist', 'service_ids'=>[1], 'active'=>1];
    $url = 'https://www.youtube.com/watch?v=introduction';
    Catalog::saveStaff($input + ['video_url'=>$url], 1);
    $person = Catalog::activeStaff(1)[0];
    assert_same($photo, $person['photo'], 'adding a video preserves profile picture');
    assert_same($url, $person['video_url'], 'video stored separately');
    Catalog::saveStaff($input, 1);
    assert_same($url, Catalog::activeStaff(1)[0]['video_url'], 'unrelated edit preserves video');
    assert_throws(fn () => Catalog::saveStaff($input + ['video_url'=>'javascript:alert(1)'], 1), 'HTTPS video link');
    assert_same($photo, Catalog::activeStaff(1)[0]['photo'], 'invalid video edit preserves photo');
    Catalog::saveStaff($input + ['video_url'=>''], 1);
    assert_same(null, Catalog::activeStaff(1)[0]['video_url'], 'clearing video removes only video');
    assert_same($photo, Catalog::activeStaff(1)[0]['photo'], 'clearing video keeps photo');
    Catalog::saveStaff($input + ['video_url'=>$url,'remove_photo'=>'1'], 1);
    assert_same($url, Catalog::activeStaff(1)[0]['video_url'], 'removing a photo does not remove video');
    assert_same(null, Catalog::activeStaff(1)[0]['photo'], 'photo removal remains independent');
}

function test_therapist_video_links_render_on_customer_and_admin_pages(): void
{
    seed_test_catalog();
    $url = 'https://www.youtube.com/watch?v=introduction';
    Database::connection()->prepare('UPDATE staff SET video_url=?,photo=? WHERE id=1')->execute([$url,'assets/images/hero-spa.jpg']);
    $staff = Catalog::activeStaff(1);
    $html = render_template_for_test('home', ['staff'=>$staff,'services'=>[],'settings'=>[]]);
    assert_contains('Watch introduction', $html, 'home page offers introduction');
    assert_contains('assets/images/hero-spa.jpg', $html, 'profile picture still rendered');
    $html = render_template_for_test('book', ['services'=>Catalog::activeServices(),'staff'=>$staff,'selectedService'=>Catalog::activeServices()[0],'settings'=>[],'error'=>'','old'=>[]]);
    assert_contains('video_url', $html, 'booking profiles include video');
    assert_contains('therapist-video-list', $html, 'video links work without JavaScript');
    ob_start();
    render_admin('Team', 'staff', ['admin'=>['username'=>'owner','role'=>'admin'],'staff'=>Catalog::adminStaff(),'services'=>Catalog::adminServices(),'editing'=>Catalog::adminStaff()[0],'schedule'=>['hours'=>[],'breaks'=>[]],'receptionist'=>null,'error'=>'','success'=>'']);
    $html = (string) ob_get_clean();
    assert_contains('name="video_url"', $html, 'admin can set video');
    assert_contains('name="photo"', $html, 'separate photo upload remains available');
    assert_contains('Preview introduction video', $html, 'admin can preview saved video');
}

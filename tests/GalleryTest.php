<?php
declare(strict_types=1);

function test_gallery_publishing_hero_selection_and_permissions(): void
{
    seed_test_catalog();
    assert_same(3, count(Gallery::images(true)), 'default hero photos');
    $pdo = Database::connection();
    $query = $pdo->prepare('INSERT INTO gallery_images (path,caption,alt_text,published,show_in_hero,display_order) VALUES (?,?,?,?,?,?)');
    $query->execute(['assets/images/hero-spa.jpg','Second','A spa treatment',1,1,2]);
    $id = (int) $pdo->lastInsertId();
    $query->execute(['assets/images/spa-ritual.jpg','First','Hot stones',1,0,1]);
    $query->execute(['assets/images/massage-treatment.jpg','Private','Private draft',0,1,0]);
    assert_same(['First','Second'], array_column(Gallery::images(), 'caption'), 'published order');
    assert_same(['Second'], array_column(Gallery::images(true), 'caption'), 'hero excludes drafts and gallery-only photos');
    $input = ['action'=>'save','id'=>$id,'caption'=>'Updated','alt_text'=>'A relaxing massage','display_order'=>0,'published'=>1,'show_in_hero'=>1];
    assert_throws(fn () => Gallery::manage(['role'=>'receptionist'], $input), 'Administrator access required');
    Gallery::manage(['role'=>'admin'], $input);
    assert_same('Updated', Gallery::images(true)[0]['caption'], 'metadata saved without replacement');
    assert_throws(fn () => Gallery::manage(['role'=>'admin'], array_replace($input, ['alt_text'=>''])), 'image description');
    assert_throws(fn () => Gallery::manage(['role'=>'admin'], array_replace($input, ['display_order'=>-1])), 'Display order');
    $html = render_template_for_test('home', ['services'=>[], 'settings'=>[], 'heroImages'=>Gallery::images(true)]);
    assert_contains('data-hero-slide', $html, 'hero slides render');
    assert_not_contains('data-hero-pause', $html, 'single slide has no controls');
    Gallery::manage(['role'=>'admin'], ['action'=>'delete','id'=>$id]);
    assert_same(3, count(Gallery::images(true)), 'hero fallback after last featured photo removed');
    assert_same(true, is_file(APP_ROOT . '/assets/images/hero-spa.jpg'), 'built-in photo preserved');
}

function test_gallery_and_carousel_views_escape_user_content(): void
{
    seed_test_catalog();
    $images = [['id'=>1,'path'=>'assets/images/hero-spa.jpg','caption'=>'<script>bad()</script>','alt_text'=>'" onerror="bad()','published'=>1,'show_in_hero'=>1,'display_order'=>0]];
    $html = render_template_for_test('gallery', ['images'=>$images, 'settings'=>[]]);
    assert_not_contains('<script>bad()', $html, 'caption escaped');
    assert_contains('&lt;script&gt;', $html, 'escaped caption visible');
    $html = render_template_for_test('home', ['heroImages'=>array_merge($images, Gallery::defaults()),'services'=>[], 'settings'=>[]]);
    assert_contains('Pause slideshow', $html, 'pause control');
    assert_contains('data-hero-next', $html, 'next control');
    assert_not_contains('<script>bad()', $html, 'hero caption and JSON escaped');
    ob_start();
    render_admin('Gallery', 'gallery', ['admin'=>['username'=>'owner','role'=>'admin'], 'images'=>$images,'error'=>'']);
    $html = (string) ob_get_clean();
    assert_contains('multipart/form-data', $html, 'upload form');
    assert_contains('Show in homepage carousel', $html, 'feature control');
}

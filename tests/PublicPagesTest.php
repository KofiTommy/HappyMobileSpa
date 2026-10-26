<?php
declare(strict_types=1);

function render_template_for_test(string $template, array $data = []): string
{
    ob_start();
    render_public('Happy Mobile Spa', $template, $data);
    return (string) ob_get_clean();
}

function test_public_layout_branding(): void
{
    assert_same(true, is_file(APP_ROOT . '/assets/images/logo.jpg'), 'optimized logo exists');
    $html = render_template_for_test('home', ['services' => [], 'settings' => []]);
    assert_contains('Happy Mobile Spa', $html, 'brand name');
    assert_contains('assets/images/logo.jpg', $html, 'logo asset');
    assert_contains('Book Now', $html, 'booking call to action');
    assert_not_contains('Serenity Spa', $html, 'old branding removed');
}

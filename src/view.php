<?php
declare(strict_types=1);

function render_public(string $title, string $template, array $data = []): void
{
    $template = basename($template);
    $templateFile = APP_ROOT . '/templates/' . $template . '.php';
    if (!is_file($templateFile)) {
        throw new RuntimeException('Unknown public template: ' . $template);
    }

    $settings = $data['settings'] ?? [];
    $activePage = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    ob_start();
    extract($data, EXTR_SKIP);
    require $templateFile;
    $content = (string) ob_get_clean();
    require APP_ROOT . '/templates/layout.php';
}

function ui_icon(string $name, string $class = ''): string
{
    return Ui::icon($name, $class);
}

function render_admin(string $title, string $template, array $data = []): void
{
    $template = basename($template);
    $templateFile = APP_ROOT . '/templates/admin/' . $template . '.php';
    if (!is_file($templateFile)) {
        throw new RuntimeException('Unknown admin template: ' . $template);
    }
    $activeAdminPage = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    ob_start();
    extract($data, EXTR_SKIP);
    require $templateFile;
    $content = (string) ob_get_clean();
    require APP_ROOT . '/templates/admin/layout.php';
}

function money(mixed $amount): string
{
    return '₵' . number_format((float) $amount, 2);
}

function business_timezone(): DateTimeZone
{
    return new DateTimeZone((string) config('timezone', 'Africa/Accra'));
}

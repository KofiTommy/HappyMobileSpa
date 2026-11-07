<?php
declare(strict_types=1);

final class Ui
{
    public static function icon(string $name, string $class = ''): string
    {
        $paths = [
            'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
            'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
            'check' => '<path d="m5 12 4 4L19 6"/>',
            'chevron' => '<path d="m6 9 6 6 6-6"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
            'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
            'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
            'phone' => '<path d="M7 3H4a1 1 0 0 0-1 1c0 9.4 7.6 17 17 17a1 1 0 0 0 1-1v-3l-4-2-2 2c-3.5-1.5-6.5-4.5-8-8l2-2-2-4Z"/>',
            'pin' => '<path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2"/>',
            'plus' => '<path d="M12 5v14M5 12h14"/>',
            'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        ];
        if (!isset($paths[$name])) { throw new InvalidArgumentException('Unknown icon: ' . $name); }
        return '<svg class="' . escape($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
    }
}

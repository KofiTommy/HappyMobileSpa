<?php
declare(strict_types=1);

final class PrivateConfig
{
    private static function path(string $name): string
    {
        if (!in_array($name, ['sms','paystack'], true)) { throw new InvalidArgumentException('Invalid configuration name.'); }
        return rtrim((string) config('private_storage', APP_ROOT . '/storage/private'), '/\\') . '/' . $name . '.json';
    }

    public static function load(string $name): ?array
    {
        $path = self::path($name);
        if (!is_file($path)) { return null; }
        $contents = file_get_contents($path, false, null, 0, 65537);
        if ($contents === false || strlen($contents) > 65536) { throw new RuntimeException('Private configuration could not be read.'); }
        $data = json_decode($contents, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($data)) { throw new RuntimeException('Private configuration is invalid.'); }
        return $data;
    }

    public static function save(string $name, array $data): void
    {
        $path = self::path($name);
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new DomainException('Private configuration folder could not be created.');
        }
        $temporary = tempnam($directory, 'config-');
        if ($temporary === false) { throw new DomainException('Private configuration could not be saved.'); }
        try {
            @chmod($temporary, 0600);
            if (file_put_contents($temporary, json_encode($data, JSON_THROW_ON_ERROR), LOCK_EX) === false || !rename($temporary, $path)) {
                throw new DomainException('Private configuration could not be saved.');
            }
        } finally {
            if (is_file($temporary)) { unlink($temporary); }
        }
    }
}

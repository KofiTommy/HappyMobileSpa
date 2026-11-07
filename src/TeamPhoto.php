<?php
declare(strict_types=1);

final class TeamPhoto
{
    public const MAX_BYTES = 2097152;

    public static function inspect(string $path): string
    {
        $size = filesize($path);
        if (!$size || $size > self::MAX_BYTES) {
            throw new InvalidArgumentException('Choose a photo smaller than 2 MB.');
        }
        $info = @getimagesize($path);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        $types = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
        if (!$info || !isset($types[$mime]) || ($info['mime'] ?? '') !== $mime) {
            throw new InvalidArgumentException('Choose a JPG, PNG, or WebP photo.');
        }
        if ($info[0] > 4096 || $info[1] > 4096) {
            throw new InvalidArgumentException('Photo dimensions must be 4096 × 4096 pixels or smaller.');
        }
        return $types[$mime];
    }

    public static function upload(?array $file, string $collection = 'team'): ?string
    {
        if (!in_array($collection, ['team', 'gallery'], true)) { throw new InvalidArgumentException('Invalid photo collection.'); }
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { return null; }
        if (($file['error'] ?? null) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Photo upload failed. Choose a JPG, PNG, or WebP smaller than 2 MB.');
        }
        $temporary = (string) ($file['tmp_name'] ?? '');
        if (!is_uploaded_file($temporary)) { throw new InvalidArgumentException('Invalid photo upload.'); }
        $extension = self::inspect($temporary);
        $directory = APP_ROOT . '/assets/images/' . $collection;
        if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
            throw new DomainException('The photo folder could not be created.');
        }
        $path = 'assets/images/' . $collection . '/' . bin2hex(random_bytes(16)) . '.' . $extension;
        if (!move_uploaded_file($temporary, APP_ROOT . '/' . $path)) {
            throw new DomainException('The photo could not be saved. Please try again.');
        }
        return $path;
    }

    public static function discard(?string $path): void
    {
        if ($path && preg_match('~^assets/images/(team|gallery)/[a-f0-9]{32}\.(jpg|png|webp)$~D', $path)) {
            $file = APP_ROOT . '/' . $path;
            if (is_file($file)) { unlink($file); }
        }
    }
}

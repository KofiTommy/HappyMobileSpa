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

    public static function available(?string $path): bool
    {
        if ($path === null || !preg_match('~^assets/images/(?:[a-z0-9-]+/)*[a-z0-9-]+\.(?:jpg|png|webp)$~D', $path)) {
            return false;
        }
        $file = APP_ROOT . '/' . $path;
        if (self::isUpload($path)) {
            self::preserve($path);
        }
        return is_file($file) && is_readable($file);
    }

    private static function isUpload(string $path): bool
    {
        return (bool) preg_match('~^assets/images/(team|gallery)/[a-f0-9]{32}\.(jpg|png|webp)$~D', $path);
    }

    // The database survives code deployments; image folders may be replaced.
    private static function photoStore(): PDO
    {
        $pdo = Database::connection();
        static $initialized = null;
        if ($initialized !== $pdo) {
            $pdo->exec('CREATE TABLE IF NOT EXISTS uploaded_photos (path VARCHAR(255) PRIMARY KEY, contents MEDIUMBLOB NOT NULL)');
            $initialized = $pdo;
        }
        return $pdo;
    }

    private static function preserve(string $path): void
    {
        $pdo = self::photoStore();
        $file = APP_ROOT . '/' . $path;
        $query = $pdo->prepare('SELECT path FROM uploaded_photos WHERE path=?');
        $query->execute([$path]);
        $stored = $query->fetchColumn() !== false;
        if (is_file($file) && is_readable($file)) {
            if (!$stored) {
                self::inspect($file);
                $insert = $pdo->prepare('INSERT INTO uploaded_photos (path,contents) VALUES (?,?)');
                $insert->bindValue(1, $path);
                $insert->bindValue(2, file_get_contents($file), PDO::PARAM_LOB);
                try { $insert->execute(); }
                catch (PDOException $error) {
                    // Another request may have backed up this same file first.
                    $query->execute([$path]);
                    if ($query->fetchColumn() === false) { throw $error; }
                }
            }
            return;
        }
        if (!$stored) { return; }
        $query = $pdo->prepare('SELECT contents FROM uploaded_photos WHERE path=?');
        $query->execute([$path]);
        $contents = $query->fetchColumn();
        if (is_resource($contents)) { $contents = stream_get_contents($contents); }
        $directory = dirname($file);
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) { return; }
        $temporary = tempnam($directory, 'restore-');
        if ($temporary === false) { return; }
        try {
            if (file_put_contents($temporary, $contents) === false) { return; }
            self::inspect($temporary);
            @chmod($temporary, 0644);
            @rename($temporary, $file);
            clearstatcache(true, $file);
        } finally {
            if (is_file($temporary)) { unlink($temporary); }
        }
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
        $destination = APP_ROOT . '/' . $path;
        if (!move_uploaded_file($temporary, $destination)) {
            throw new DomainException('The photo could not be saved. Please try again.');
        }
        @chmod($destination, 0644);
        clearstatcache(true, $destination);
        if (!self::available($path)) {
            @unlink($destination);
            throw new DomainException('The photo was saved but is not readable by the website. Check the image folder permissions.');
        }
        return $path;
    }

    public static function discard(?string $path): void
    {
        if ($path && self::isUpload($path)) {
            self::photoStore()->prepare('DELETE FROM uploaded_photos WHERE path=?')->execute([$path]);
            $file = APP_ROOT . '/' . $path;
            if (is_file($file)) { unlink($file); }
        }
    }
}

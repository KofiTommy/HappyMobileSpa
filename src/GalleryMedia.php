<?php
declare(strict_types=1);

final class GalleryMedia
{
    public const MAX_VIDEO_BYTES = 5242880;

    public static function isVideo(?string $path): bool
    {
        return $path !== null && (bool) preg_match('~^assets/videos/gallery/[a-f0-9]{32}\.mp4$~D', $path);
    }

    public static function inspectVideo(string $file): void
    {
        clearstatcache(true, $file);
        $size = filesize($file);
        if (!$size || $size > self::MAX_VIDEO_BYTES) { throw new InvalidArgumentException('Choose an MP4 video up to 5 MB.'); }
        $header = file_get_contents($file, false, null, 0, 32);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
        if (!in_array($mime, ['video/mp4','application/mp4'], true) || substr((string) $header, 4, 4) !== 'ftyp') {
            throw new InvalidArgumentException('Choose a valid MP4 video. Export using H.264 video for browser compatibility.');
        }
        $stream = fopen($file, 'rb');
        $found = [];
        try {
            while (($offset = ftell($stream)) < $size) {
                $box = fread($stream, 8);
                if (strlen($box) !== 8) { throw new InvalidArgumentException('The MP4 video is incomplete or invalid.'); }
                $length = unpack('N', substr($box, 0, 4))[1];
                $type = substr($box, 4, 4);
                $headerBytes = 8;
                if ($length === 1) {
                    $extended = fread($stream, 8);
                    if (strlen($extended) !== 8) { throw new InvalidArgumentException('The MP4 video is incomplete or invalid.'); }
                    $words = unpack('Nhigh/Nlow', $extended);
                    $length = $words['high'] === 0 ? $words['low'] : $size + 1;
                    $headerBytes = 16;
                } elseif ($length === 0) { $length = $size - $offset; }
                if ($length < $headerBytes || $length > $size - $offset) { throw new InvalidArgumentException('The MP4 video is incomplete or invalid.'); }
                if ($length > $headerBytes) { $found[$type] = true; }
                fseek($stream, $offset + $length);
            }
        } finally { fclose($stream); }
        if (!isset($found['moov'], $found['mdat'])) { throw new InvalidArgumentException('The MP4 file must contain video metadata and media.'); }
    }

    public static function available(?string $path): bool
    {
        if (!self::isVideo($path)) { return TeamPhoto::available($path); }
        $pdo = Database::connection();
        $file = APP_ROOT . '/' . $path;
        $query = $pdo->prepare('SELECT path FROM uploaded_photos WHERE path=?');
        $query->execute([$path]);
        $stored = $query->fetchColumn() !== false;
        if (is_file($file) && is_readable($file)) {
            if (!$stored) {
                self::inspectVideo($file);
                $insert = $pdo->prepare('INSERT INTO uploaded_photos (path,contents) VALUES (?,?)');
                $insert->bindValue(1, $path);
                $insert->bindValue(2, file_get_contents($file), PDO::PARAM_LOB);
                try { $insert->execute(); }
                catch (PDOException $error) {
                    $query->execute([$path]);
                    if ($query->fetchColumn() === false) { throw $error; }
                }
            }
            return true;
        }
        if (!$stored) { return false; }
        $query = $pdo->prepare('SELECT contents FROM uploaded_photos WHERE path=?');
        $query->execute([$path]);
        $contents = $query->fetchColumn();
        if (is_resource($contents)) { $contents = stream_get_contents($contents); }
        $directory = dirname($file);
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) { return false; }
        $temporary = tempnam($directory, 'restore-');
        if ($temporary === false) { return false; }
        try {
            if (file_put_contents($temporary, $contents) === false) { return false; }
            self::inspectVideo($temporary);
            @chmod($temporary, 0644);
            if (!@rename($temporary, $file)) { return false; }
            clearstatcache(true, $file);
            return true;
        } finally { if (is_file($temporary)) { unlink($temporary); } }
    }

    public static function upload(?array $file): ?string
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { return null; }
        if (($file['error'] ?? null) !== UPLOAD_ERR_OK) { throw new InvalidArgumentException('Upload failed. Photos must be up to 2 MB; MP4 videos up to 5 MB.'); }
        $temporary = (string) ($file['tmp_name'] ?? '');
        if (!is_uploaded_file($temporary)) { throw new InvalidArgumentException('Invalid media upload.'); }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary);
        if (in_array($mime, ['image/jpeg','image/png','image/webp'], true)) { return TeamPhoto::upload($file, 'gallery'); }
        self::inspectVideo($temporary);
        $directory = APP_ROOT . '/assets/videos/gallery';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) { throw new DomainException('The video folder could not be created.'); }
        $path = 'assets/videos/gallery/' . bin2hex(random_bytes(16)) . '.mp4';
        $destination = APP_ROOT . '/' . $path;
        if (!move_uploaded_file($temporary, $destination)) { throw new DomainException('The video could not be saved.'); }
        @chmod($destination, 0644);
        try {
            if (!self::available($path)) { throw new DomainException('The video could not be backed up.'); }
        } catch (Throwable $error) {
            @unlink($destination);
            throw new DomainException('The video could not be backed up. Check database upload limits, then try a smaller MP4.', 0, $error);
        }
        return $path;
    }

    public static function discard(?string $path): void
    {
        if (!self::isVideo($path)) { TeamPhoto::discard($path); return; }
        Database::connection()->prepare('DELETE FROM uploaded_photos WHERE path=?')->execute([$path]);
        $file = APP_ROOT . '/' . $path;
        if (is_file($file)) { unlink($file); }
    }
}

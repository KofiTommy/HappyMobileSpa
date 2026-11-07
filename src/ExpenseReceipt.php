<?php
declare(strict_types=1);

final class ExpenseReceipt
{
    private const MAX_BYTES = 5242880;

    public static function upload(?array $file): ?array
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { return null; }
        if (($file['error'] ?? null) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Receipt upload failed. Choose a JPG, PNG, WebP, or PDF smaller than 5 MB.');
        }
        $temporary = (string) ($file['tmp_name'] ?? '');
        if (!is_uploaded_file($temporary)) { throw new InvalidArgumentException('Invalid receipt upload.'); }
        $extension = self::inspect($temporary);
        $directory = APP_ROOT . '/storage/receipts';
        if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
            throw new DomainException('The receipt folder could not be created.');
        }
        $path = 'storage/receipts/' . bin2hex(random_bytes(16)) . '.' . $extension;
        if (!move_uploaded_file($temporary, APP_ROOT . '/' . $path)) {
            throw new DomainException('The receipt could not be saved. Please try again.');
        }
        $name = basename(str_replace('\\', '/', (string) ($file['name'] ?? 'receipt')));
        $name = trim(preg_replace('/[\x00-\x1F\x7F"]/', '', $name) ?? 'receipt');
        if ($name === '') { $name = 'receipt.' . $extension; }
        if (mb_strlen($name) > 190) { $name = 'receipt.' . $extension; }
        return ['path' => $path, 'name' => $name];
    }

    public static function inspect(string $path): string
    {
        $size = filesize($path);
        if (!$size || $size > self::MAX_BYTES) {
            throw new InvalidArgumentException('Choose a receipt smaller than 5 MB.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
        if (!isset($types[$mime])) {
            throw new InvalidArgumentException('Choose a JPG, PNG, WebP, or PDF receipt.');
        }
        if ($mime === 'application/pdf') {
            if (file_get_contents($path, false, null, 0, 5) !== '%PDF-') {
                throw new InvalidArgumentException('The PDF receipt is invalid.');
            }
        } else {
            $info = @getimagesize($path);
            if (!$info || ($info['mime'] ?? '') !== $mime || $info[0] > 4096 || $info[1] > 4096) {
                throw new InvalidArgumentException('The receipt image is invalid or exceeds 4096 × 4096 pixels.');
            }
        }
        return $types[$mime];
    }

    public static function discard(?string $path): void
    {
        if ($path && preg_match('~^storage/receipts/[a-f0-9]{32}\.(jpg|png|webp|pdf)$~D', $path)) {
            $file = APP_ROOT . '/' . $path;
            if (is_file($file)) { unlink($file); }
        }
    }

    public static function send(array $expense): void
    {
        $path = (string) ($expense['receipt_path'] ?? '');
        if (!preg_match('~^storage/receipts/[a-f0-9]{32}\.(jpg|png|webp|pdf)$~D', $path)) {
            throw new DomainException('Receipt not found.');
        }
        $file = APP_ROOT . '/' . $path;
        if (!is_file($file)) { throw new DomainException('Receipt not found.'); }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        if (!in_array($mime, $allowed, true)) { throw new DomainException('Receipt not found.'); }
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $name = (string) ($expense['receipt_name'] ?? 'receipt.' . $extension);
        $name = trim(preg_replace('/[\x00-\x1F\x7F"\\r\\n]/', '', $name) ?? '');
        if ($name === '') { $name = 'receipt.' . $extension; }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($file));
        header("Content-Disposition: inline; filename=\"receipt.{$extension}\"; filename*=UTF-8''" . rawurlencode($name));
        header("Content-Security-Policy: default-src 'none'; sandbox");
        header('Cache-Control: private, no-store');
        readfile($file);
    }
}

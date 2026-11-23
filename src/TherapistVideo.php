<?php
declare(strict_types=1);

final class TherapistVideo
{
    public static function validate(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') { return null; }
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $providers = ['youtube.com','www.youtube.com','m.youtube.com','youtu.be','vimeo.com','www.vimeo.com','player.vimeo.com','tiktok.com','www.tiktok.com','vm.tiktok.com','vt.tiktok.com','instagram.com','www.instagram.com'];
        $publicHost = str_contains($host, '.') && !preg_match('/(?:^|\.)(?:localhost|local|internal)$/D', $host)
            && (!filter_var($host, FILTER_VALIDATE_IP) || filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE));
        $directVideo = $publicHost && preg_match('/\.(?:mp4|webm)$/iD', (string) ($parts['path'] ?? ''));
        if (strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f]/', $url) || !filter_var($url, FILTER_VALIDATE_URL)
            || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
            || (!in_array($host, $providers, true) && !$directVideo)) {
            throw new InvalidArgumentException('Use an HTTPS video link from YouTube, Vimeo, TikTok or Instagram, or a direct public MP4/WebM link.');
        }
        return $url;
    }

    public static function publicUrl(?string $url): ?string
    {
        try { return self::validate($url ?? ''); }
        catch (InvalidArgumentException) { return null; }
    }
}

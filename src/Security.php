<?php
declare(strict_types=1);

final class Security
{
    public static function https(array $server): bool
    {
        // Forwarded headers are client-controlled unless a trusted proxy strips them.
        return (!empty($server['HTTPS']) && strtolower((string) $server['HTTPS']) !== 'off')
            || (string) ($server['SERVER_PORT'] ?? '') === '443';
    }

    public static function validateInput(array $input, bool $post = false): void
    {
        $structured = ['service_ids'=>1, 'hours'=>2, 'breaks'=>2, 'business_hours'=>2, 'expense_budgets'=>1];
        $validate = static function (mixed $value, int $depth) use (&$validate): void {
            if (is_string($value)) {
                if (strlen($value) > 32768 || str_contains($value, "\0") || !mb_check_encoding($value, 'UTF-8')) {
                    throw new InvalidArgumentException('Invalid request data.');
                }
                return;
            }
            if (!is_array($value) || $depth <= 0 || count($value) > 200) {
                throw new InvalidArgumentException('Invalid request data.');
            }
            foreach ($value as $child) { $validate($child, $depth - 1); }
        };
        foreach ($input as $key => $value) {
            $validate($value, $post ? ($structured[$key] ?? 0) : 0);
        }
    }

    public static function boot(): void
    {
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        ini_set('log_errors', '1');
        ini_set('zend.exception_ignore_args', '1');
        set_exception_handler(static function (Throwable $error): void {
            // Never log arguments, SQL, submitted data, credentials, or payment URLs.
            error_log('Application failure: ' . get_class($error) . ' at ' . basename($error->getFile()) . ':' . $error->getLine());
            while (ob_get_level() > 0) { ob_end_clean(); }
            http_response_code($error instanceof DomainException ? 403 : 500);
            header('Content-Type: text/plain; charset=UTF-8');
            header('Cache-Control: no-store');
            echo $error instanceof DomainException ? 'Access denied.' : 'We could not complete this request. Please try again or contact the spa.';
        });
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self'; font-src 'self' https://fonts.gstatic.com; connect-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self' https://checkout.paystack.com");
        header('Cache-Control: private, no-store');
        $secure = self::https($_SERVER);
        if (config('environment') === 'production' && !$secure) {
            http_response_code(403); exit('Please use HTTPS to access this website.');
        }
        if ($secure) { header('Strict-Transport-Security: max-age=31536000'); }
        if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 8388608) {
            http_response_code(413); exit('Request too large.');
        }
        try {
            self::validateInput($_GET);
            self::validateInput($_POST, true);
        } catch (InvalidArgumentException) {
            http_response_code(400); exit('Invalid request data.');
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        session_set_cookie_params(['httponly'=>true, 'samesite'=>'Lax', 'secure'=>$secure, 'path'=>'/']);
        if (!session_start()) { throw new RuntimeException('Session storage is unavailable.'); }
    }
}

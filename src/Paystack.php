<?php
declare(strict_types=1);

final class Paystack
{
    public static function config(): array
    {
        $path = APP_ROOT . '/config/paystack.local.php';
        return (is_file($path) ? require $path : []) + ['enabled'=>false,'secret_key'=>'','site_url'=>''];
    }

    public static function ready(): bool
    {
        $config = self::config();
        return getenv('SPA_PAYSTACK_DISABLED') !== '1' && $config['enabled'] && $config['secret_key'] !== '' && $config['site_url'] !== '';
    }

    public static function save(array $actor, array $input): void
    {
        Auth::assertAdmin($actor);
        $config = self::config();
        $config['enabled'] = !empty($input['enabled']);
        if (trim((string) ($input['secret_key'] ?? '')) !== '') { $config['secret_key'] = trim((string) $input['secret_key']); }
        $config['site_url'] = rtrim(trim((string) ($input['site_url'] ?? '')), '/');
        if ($config['secret_key'] !== '' && !preg_match('/^sk_(test|live)_[A-Za-z0-9]+$/D', $config['secret_key'])) { throw new InvalidArgumentException('Enter a Paystack secret key beginning sk_test_ or sk_live_.'); }
        $parts = parse_url($config['site_url']);
        $local = in_array($parts['host'] ?? '', ['localhost','127.0.0.1','[::1]'], true);
        if ($local && str_starts_with($config['secret_key'], 'sk_live_')) { throw new InvalidArgumentException('Live payments require your public HTTPS website URL.'); }
        if (!filter_var($config['site_url'], FILTER_VALIDATE_URL) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || (($parts['scheme'] ?? '') !== 'https' && !(($parts['scheme'] ?? '') === 'http' && $local && str_starts_with($config['secret_key'], 'sk_test_')))) {
            throw new InvalidArgumentException('Enter your public HTTPS website URL, including any project subfolder. HTTP localhost is allowed with a test key only.');
        }
        if ($config['enabled'] && $config['secret_key'] === '') { throw new InvalidArgumentException('Enter your Paystack secret key before enabling payments.'); }
        $temporary = tempnam(APP_ROOT . '/config', 'paystack-');
        try {
            if (!$temporary || !rename($temporary, $temporary . '.php')) { throw new DomainException('Could not save Paystack settings.'); }
            $temporary .= '.php';
            if (file_put_contents($temporary, "<?php\nreturn " . var_export($config, true) . ";\n") === false || !rename($temporary, APP_ROOT . '/config/paystack.local.php')) { throw new DomainException('Could not save Paystack settings.'); }
        } finally { if ($temporary && is_file($temporary)) { unlink($temporary); } }
    }

    public static function request(string $method, string $path, array $payload = []): array
    {
        $config = self::config();
        if (getenv('SPA_PAYSTACK_DISABLED') === '1' || $config['secret_key'] === '' || !function_exists('curl_init')) { throw new DomainException('Online payment verification is unavailable. Please try again later.'); }
        $curl = curl_init('https://api.paystack.co' . $path);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>['Authorization: Bearer ' . $config['secret_key'],'Content-Type: application/json','Accept: application/json'], CURLOPT_CONNECTTIMEOUT=>4, CURLOPT_TIMEOUT=>15, CURLOPT_FOLLOWLOCATION=>false, CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2]);
        if ($method === 'POST') { curl_setopt_array($curl, [CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload, JSON_THROW_ON_ERROR)]); }
        $body = curl_exec($curl);
        $http = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $data = is_string($body) ? json_decode($body, true) : null;
        if ($http < 200 || $http >= 300 || !is_array($data) || ($data['status'] ?? false) !== true || !is_array($data['data'] ?? null)) {
            throw new DomainException('Paystack could not confirm this request. Do not pay again if money was deducted; use Check payment status or contact the spa.');
        }
        return $data['data'];
    }

    public static function validSignature(string $raw, string $signature, string $key): bool
    {
        return $key !== '' && preg_match('/^[a-f0-9]{128}$/D', $signature) && hash_equals(hash_hmac('sha512', $raw, $key), $signature);
    }
}

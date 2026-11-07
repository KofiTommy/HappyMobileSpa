<?php
declare(strict_types=1);

final class SmsConfig
{
    public static function load(): array
    {
        $path = APP_ROOT . '/config/sms.local.php';
        $saved = is_file($path) ? require $path : [];
        return $saved + ['enabled'=>false, 'sender'=>'HappySpa', 'api_key'=>''];
    }

    public static function ready(?array $config = null): bool
    {
        $config ??= self::load();
        return getenv('SPA_SMS_DISABLED') !== '1' && !empty($config['enabled']) && $config['api_key'] !== '';
    }

    public static function save(array $actor, array $input): void
    {
        Auth::assertAdmin($actor);
        $config = self::load();
        $config['enabled'] = !empty($input['enabled']);
        $config['sender'] = trim((string) ($input['sender'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9]{1,11}$/D', $config['sender'])) { throw new InvalidArgumentException('Sender ID must contain 1–11 letters or numbers. Use the sender approved by your provider.'); }
        foreach (['api_key'] as $key) {
            if (trim((string) ($input[$key] ?? '')) !== '') { $config[$key] = trim((string) $input[$key]); }
        }
        if ($config['enabled'] && $config['api_key'] === '') { throw new InvalidArgumentException('Enter your BulkSMS Ghana API key.'); }
        $temporary = tempnam(APP_ROOT . '/config', 'sms-');
        try {
            if (!$temporary || !rename($temporary, $temporary . '.php')) { throw new DomainException('SMS configuration could not be saved. Check folder permissions.'); }
            $temporary .= '.php';
            if (!$temporary || file_put_contents($temporary, "<?php\nreturn " . var_export($config, true) . ";\n") === false || !rename($temporary, APP_ROOT . '/config/sms.local.php')) {
                throw new DomainException('SMS configuration could not be saved. Check folder permissions.');
            }
        } finally { if ($temporary && is_file($temporary)) { unlink($temporary); } }
    }
}

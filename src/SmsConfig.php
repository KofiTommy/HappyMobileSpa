<?php
declare(strict_types=1);

final class SmsConfig
{
    public static function load(): array
    {
        $path = APP_ROOT . '/config/sms.local.php';
        $saved = PrivateConfig::load('sms') ?? (is_file($path) ? require $path : []);
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
        PrivateConfig::save('sms', $config);
    }
}

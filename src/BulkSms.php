<?php
declare(strict_types=1);

final class BulkSms
{
    public static function phone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (preg_match('/^0[2-5][0-9]{8}$/D', $digits)) { return '+233' . substr($digits, 1); }
        if (preg_match('/^233[2-5][0-9]{8}$/D', $digits)) { return '+' . $digits; }
        throw new InvalidArgumentException('Invalid Ghana mobile number.');
    }

    public static function request(array $config, string $phone, string $body): array
    {
        $phone = self::phone($phone);
        return ['url'=>'https://clientlogin.bulksmsgh.com/smsapi?' . http_build_query([
            'key'=>$config['api_key'], 'to'=>substr($phone, 1), 'msg'=>$body, 'sender_id'=>$config['sender'],
        ], '', '&', PHP_QUERY_RFC3986), 'headers'=>['Accept: text/plain'], 'body'=>null];
    }

    public static function interpret(int $http, string $response): array
    {
        if ($http === 429) { return ['state'=>'retry', 'error'=>'Provider rate limit (HTTP 429).']; }
        if ($http >= 400 && $http < 500) { return ['state'=>'failed', 'error'=>'Provider rejected request (HTTP ' . $http . '). Check credentials and sender ID.']; }
        if ($http < 200 || $http >= 300) { return ['state'=>'unknown', 'error'=>'Provider response uncertain. Check provider history before sending again.']; }
        $code = trim($response);
        if ($code === '1000') { return ['state'=>'submitted', 'provider_id'=>'']; }
        if ($code === '1002') { return ['state'=>'retry', 'error'=>'Provider reports message not sent (1002).']; }
        $errors = ['1003'=>'Insufficient SMS balance.', '1004'=>'Invalid API key.', '1005'=>'Invalid phone number.', '1006'=>'Invalid sender ID.', '1008'=>'Empty message.'];
        if (isset($errors[$code])) { return ['state'=>'failed','error'=>$errors[$code]]; }
        return ['state'=>'unknown', 'error'=>'Unrecognised provider response. Check provider history before sending again.'];
    }

    public static function send(string $phone, string $body): array
    {
        $config = SmsConfig::load();
        if (!SmsConfig::ready($config)) { return ['state'=>'failed', 'error'=>'SMS provider is not configured.']; }
        if (!function_exists('curl_init')) { return ['state'=>'failed', 'error'=>'PHP cURL extension is required.']; }
        $request = self::request($config, $phone, $body);
        $curl = curl_init($request['url']);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>$request['headers'],
            CURLOPT_CONNECTTIMEOUT=>3, CURLOPT_TIMEOUT=>8, CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2]);
        if ($request['body'] !== null) { curl_setopt_array($curl, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$request['body']]); }
        $response = curl_exec($curl);
        $http = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_errno($curl);
        curl_close($curl);
        // Do not log request URLs, headers, raw responses, or cURL errors: they can contain credentials.
        if ($response === false) { return ['state'=>'unknown', 'error'=>'Network error (' . $error . '). Check provider history before sending again.']; }
        return self::interpret($http, (string) $response);
    }
}

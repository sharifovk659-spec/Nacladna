<?php

namespace App\Services;

class TelegramAuth
{
    private string $botToken;

    public function __construct(string $botToken)
    {
        $this->botToken = $botToken;
    }

    /**
     * Parse and verify Telegram WebApp initData.
     * Returns user data array or throws on failure.
     */
    public function verify(string $initData): array
    {
        if (empty($initData)) {
            throw new \InvalidArgumentException('initData is empty');
        }

        parse_str($initData, $params);

        $hash = $params['hash'] ?? '';
        if (empty($hash)) {
            throw new \InvalidArgumentException('Hash missing in initData');
        }
        unset($params['hash']);

        // Build data-check-string
        ksort($params);
        $checkString = implode("\n", array_map(
            fn($k, $v) => "{$k}={$v}",
            array_keys($params),
            array_values($params)
        ));

        $secretKey = hash_hmac('sha256', $this->botToken, 'WebAppData', true);
        $expected  = hash_hmac('sha256', $checkString, $secretKey);

        if (!hash_equals($expected, $hash)) {
            throw new \RuntimeException('Invalid Telegram signature');
        }

        // Check freshness (10 minutes)
        $authDate = (int)($params['auth_date'] ?? 0);
        if ($authDate < time() - 600) {
            throw new \RuntimeException('initData expired');
        }

        $user = json_decode($params['user'] ?? '{}', true);
        if (empty($user['id'])) {
            throw new \RuntimeException('User data missing');
        }

        return $user;
    }
}

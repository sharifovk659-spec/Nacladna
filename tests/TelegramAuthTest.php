<?php

namespace Tests;

use App\Services\TelegramAuth;
use PHPUnit\Framework\TestCase;

class TelegramAuthTest extends TestCase
{
    private string $botToken = '123456:TEST_BOT_TOKEN_FOR_UNIT_TESTS';

    private function buildInitData(array $user, int $authDate, ?string $botToken = null): string
    {
        $token = $botToken ?? $this->botToken;
        $params = [
            'auth_date' => (string)$authDate,
            'query_id' => 'AAEAAAEAAAE',
            'user' => json_encode($user, JSON_UNESCAPED_UNICODE),
        ];
        ksort($params);
        $checkString = implode("\n", array_map(
            fn($k, $v) => "{$k}={$v}",
            array_keys($params),
            array_values($params)
        ));
        $secretKey = hash_hmac('sha256', $token, 'WebAppData', true);
        $hash = hash_hmac('sha256', $checkString, $secretKey);
        $params['hash'] = $hash;
        return http_build_query($params);
    }

    public function testValidInitData(): void
    {
        $user = [
            'id' => 424242,
            'first_name' => 'Ali',
            'last_name' => 'Test',
            'username' => 'ali_test',
        ];
        $initData = $this->buildInitData($user, time());
        $auth = new TelegramAuth($this->botToken);
        $result = $auth->verify($initData);

        $this->assertSame(424242, $result['id']);
        $this->assertSame('Ali', $result['first_name']);
        $this->assertSame('ali_test', $result['username']);
    }

    public function testInvalidSignatureRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid Telegram signature');

        $user = ['id' => 1, 'first_name' => 'X'];
        $initData = $this->buildInitData($user, time(), 'WRONG_TOKEN');
        $auth = new TelegramAuth($this->botToken);
        $auth->verify($initData);
    }

    public function testExpiredInitDataRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('initData expired');

        $user = ['id' => 2, 'first_name' => 'Old'];
        $initData = $this->buildInitData($user, time() - 601);
        $auth = new TelegramAuth($this->botToken);
        $auth->verify($initData);
    }

    public function testEmptyInitDataRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $auth = new TelegramAuth($this->botToken);
        $auth->verify('');
    }
}

<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Auth HTTP smoke tests against local PHP built-in server when available.
 * Falls back to skipping if server is unreachable.
 */
class AuthFlowTest extends TestCase
{
    private string $baseUrl = 'http://127.0.0.1:8000';

    private function reachable(): bool
    {
        $ctx = stream_context_create(['http' => ['timeout' => 1, 'ignore_errors' => true]]);
        $body = @file_get_contents($this->baseUrl . '/health', false, $ctx);
        return $body !== false;
    }

    public function testMockLoginDisabledInProduction(): void
    {
        if (!$this->reachable()) {
            $this->markTestSkipped('Local server not running');
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => '',
                'ignore_errors' => true,
                'timeout' => 5,
            ],
        ]);
        $body = file_get_contents($this->baseUrl . '/auth/mock-login', false, $ctx);
        $code = (int)explode(' ', $http_response_header[0])[1];
        $data = json_decode((string)$body, true);

        $this->assertContains($code, [403, 401]);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('error', $data);
    }

    public function testInvalidInitDataRejected(): void
    {
        if (!$this->reachable()) {
            $this->markTestSkipped('Local server not running');
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
                'content' => 'initData=invalid',
                'ignore_errors' => true,
                'timeout' => 5,
            ],
        ]);
        $body = file_get_contents($this->baseUrl . '/api/auth/telegram', false, $ctx);
        $code = (int)explode(' ', $http_response_header[0])[1];
        $data = json_decode((string)$body, true);

        $this->assertContains($code, [401, 503]);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('error', $data);
        if ($code === 503) {
            $this->markTestIncomplete('TELEGRAM_BOT_TOKEN not configured locally; signature rejection covered by unit tests');
        }
    }

    public function testAuthStatusUnauthenticated(): void
    {
        if (!$this->reachable()) {
            $this->markTestSkipped('Local server not running');
        }

        $body = file_get_contents($this->baseUrl . '/auth/status');
        $data = json_decode((string)$body, true);

        $this->assertIsArray($data);
        $this->assertFalse($data['authenticated'] ?? true);
    }

    public function testMiniAppPageLoads(): void
    {
        if (!$this->reachable()) {
            $this->markTestSkipped('Local server not running');
        }

        $body = file_get_contents($this->baseUrl . '/mini-app');
        $this->assertStringContainsString('Nakladna Cloud', $body);
        $this->assertStringContainsString('/api/auth/telegram', $body);
        $this->assertStringContainsString('Авторизация', $body);
    }

    public function testDashboardRequiresAuth(): void
    {
        if (!$this->reachable()) {
            $this->markTestSkipped('Local server not running');
        }

        $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5]]);
        @file_get_contents($this->baseUrl . '/dashboard', false, $ctx);
        $code = (int)explode(' ', $http_response_header[0])[1];
        $this->assertContains($code, [302, 303, 401]);
    }
}

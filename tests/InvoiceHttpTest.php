<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

class InvoiceHttpTest extends TestCase
{
    private string $baseUrl = 'http://127.0.0.1:8000';

    private function reachable(): bool
    {
        $ctx = stream_context_create(['http' => ['timeout' => 1, 'ignore_errors' => true]]);
        return @file_get_contents($this->baseUrl . '/health', false, $ctx) !== false;
    }

    public function testInvoiceRoutesRequireAuth(): void
    {
        if (!$this->reachable()) {
            $this->markTestSkipped('Local server not running');
        }

        foreach ([
            '/invoices',
            '/invoices/create',
            '/api/invoices',
            '/api/invoices/1',
            '/api/invoices/1/share',
            '/api/invoices/1/cancel',
        ] as $path) {
            $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5]]);
            @file_get_contents($this->baseUrl . $path, false, $ctx);
            $code = (int)explode(' ', $http_response_header[0])[1];
            $this->assertContains($code, [302, 303, 401, 405], "Path {$path} should be protected");
        }
    }

    public function testInvoiceMutatingApiRejectsMissingCsrf(): void
    {
        if (!$this->reachable()) {
            $this->markTestSkipped('Local server not running');
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => '{}',
                'ignore_errors' => true,
                'follow_location' => 0,
                'timeout' => 5,
            ],
        ]);
        @file_get_contents($this->baseUrl . '/api/invoices', false, $ctx);
        $code = (int)explode(' ', $http_response_header[0])[1];
        // Unauthenticated or CSRF/forbidden
        $this->assertContains($code, [302, 303, 401, 403], 'POST /api/invoices must reject unauth/csrf');
    }

    public function testPublicInvoiceQrDoesNotRequireAuth(): void
    {
        if (!$this->reachable()) {
            $this->markTestSkipped('Local server not running');
        }

        $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5]]);
        @file_get_contents($this->baseUrl . '/invoice/public/not-a-valid-uuid', false, $ctx);
        $code = (int)explode(' ', $http_response_header[0])[1];
        $this->assertSame(404, $code, 'Invalid public UUID should 404 without redirect to login');
    }
}

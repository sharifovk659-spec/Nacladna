<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

class ClientsProductsHttpTest extends TestCase
{
    private string $baseUrl = 'http://127.0.0.1:8000';

    private function reachable(): bool
    {
        $ctx = stream_context_create(['http' => ['timeout' => 1, 'ignore_errors' => true]]);
        return @file_get_contents($this->baseUrl . '/health', false, $ctx) !== false;
    }

    public function testClientsAndProductsRequireAuth(): void
    {
        if (!$this->reachable()) {
            $this->markTestSkipped('Local server not running');
        }

        foreach (['/clients', '/products', '/clients/create', '/products/create', '/api/clients/search?q=a', '/api/products/search?q=a'] as $path) {
            $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5]]);
            @file_get_contents($this->baseUrl . $path, false, $ctx);
            $code = (int)explode(' ', $http_response_header[0])[1];
            $this->assertContains($code, [302, 303, 401], "Path {$path} should be protected");
        }
    }
}

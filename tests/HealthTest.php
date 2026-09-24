<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

class HealthTest extends TestCase
{
    private string $baseUrl = 'http://localhost:8000';

    public function testHealthReturns200(): void
    {
        $ctx  = stream_context_create(['http' => ['ignore_errors' => true]]);
        $body = file_get_contents($this->baseUrl . '/health', false, $ctx);
        $code = (int)explode(' ', $http_response_header[0])[1];

        $this->assertEquals(200, $code, 'Health endpoint should return 200');
    }

    public function testHealthReturnsJson(): void
    {
        $body = file_get_contents($this->baseUrl . '/health');
        $data = json_decode($body, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('ok', $data['status']);
    }

    public function testHealthShowsDatabaseStatus(): void
    {
        $body = file_get_contents($this->baseUrl . '/health');
        $data = json_decode($body, true);

        $this->assertArrayHasKey('database', $data);
        $this->assertEquals('connected', $data['database']);
    }

    public function testHealthShowsProductionEnvironment(): void
    {
        $body = file_get_contents($this->baseUrl . '/health');
        $data = json_decode($body, true);

        $this->assertIsArray($data);
        $this->assertSame('production', $data['environment'] ?? null);
    }

    public function testUnknownRouteReturns404(): void
    {
        $ctx  = stream_context_create(['http' => ['ignore_errors' => true]]);
        $body = file_get_contents($this->baseUrl . '/this-route-does-not-exist-xyz', false, $ctx);
        $code = (int)explode(' ', $http_response_header[0])[1];

        $this->assertEquals(404, $code);
    }

    public function testProductionErrorsNotExposed(): void
    {
        $ctx  = stream_context_create(['http' => ['ignore_errors' => true]]);
        $body = file_get_contents($this->baseUrl . '/health', false, $ctx);

        $this->assertStringNotContainsString('Fatal error', $body);
        $this->assertStringNotContainsString('Warning:', $body);
        $this->assertStringNotContainsString('Notice:', $body);
    }

    public function testHomepageShowsFoundationReady(): void
    {
        $body = file_get_contents($this->baseUrl . '/');

        $this->assertStringContainsString('Nakladna Cloud', $body);
        $this->assertStringContainsString('MVP Foundation Ready', $body);
        $this->assertStringContainsString('Database Connected', $body);
    }
}

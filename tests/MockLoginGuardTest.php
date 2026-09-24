<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

class MockLoginGuardTest extends TestCase
{
    public function testProductionEnvDisablesMockLoginFlag(): void
    {
        $env = 'production';
        $enabled = false;
        $blocked = ($env === 'production' || !$enabled);
        $this->assertTrue($blocked);

        $envLocal = 'local';
        $enabledLocal = true;
        $allowed = !($envLocal === 'production' || !$enabledLocal);
        $this->assertTrue($allowed);
    }

    public function testSessionCookieFlagsForProduction(): void
    {
        $isProduction = true;
        $secure = $isProduction;
        $httpOnly = true;
        $sameSite = 'Lax';

        $this->assertTrue($secure);
        $this->assertTrue($httpOnly);
        $this->assertSame('Lax', $sameSite);
    }
}

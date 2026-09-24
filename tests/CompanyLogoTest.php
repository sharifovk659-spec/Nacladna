<?php

namespace Tests;

use App\Models\Company;
use PHPUnit\Framework\TestCase;

class CompanyLogoTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('ROOT_DIR')) {
            define('ROOT_DIR', dirname(__DIR__));
        }
    }

    public function testResolveLogoFileRejectsTraversal(): void
    {
        $this->assertNull(Company::resolveLogoFile('../.env'));
        $this->assertNull(Company::resolveLogoFile('uploads/logos/../../.env'));
    }

    public function testLogoPublicUrlRequiresCompanyId(): void
    {
        $this->assertNull(Company::logoPublicUrl('logos/1/x.png', 0));
        $this->assertNull(Company::logoPublicUrl(null, 5));
    }
}

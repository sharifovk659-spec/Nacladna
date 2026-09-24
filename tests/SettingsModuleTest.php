<?php

namespace Tests;

use App\Core\Database;
use App\Models\Company;
use PHPUnit\Framework\TestCase;

class SettingsModuleTest extends TestCase
{
    private static bool $ready = false;
    private static int $companyA = 0;
    private static int $companyB = 0;

    public static function setUpBeforeClass(): void
    {
        $root = dirname(__DIR__);
        if (!defined('ROOT_DIR')) {
            define('ROOT_DIR', $root);
        }
        if (is_file($root . '/vendor/autoload.php')) {
            require_once $root . '/vendor/autoload.php';
        }
        if (class_exists(\Dotenv\Dotenv::class) && is_file($root . '/.env')) {
            \Dotenv\Dotenv::createImmutable($root)->safeLoad();
        }

        try {
            $db = Database::getInstance();
            $db->query('SELECT 1');
            $db->query(
                "SELECT language FROM companies LIMIT 1"
            );

            $suffix = (string)random_int(100000, 999999);
            $db->prepare(
                "INSERT INTO companies (name, owner_name, phone, currency, timezone, language, invoice_prefix, next_invoice_number, status)
                 VALUES (?, 'Owner A', '+992900000301', 'TJS', 'Asia/Dushanbe', 'ru', 'SA', 1, 'active')"
            )->execute(['Settings A ' . $suffix]);
            self::$companyA = (int)$db->lastInsertId();

            $db->prepare(
                "INSERT INTO companies (name, owner_name, phone, currency, timezone, language, invoice_prefix, next_invoice_number, status)
                 VALUES (?, 'Owner B', '+992900000302', 'TJS', 'Asia/Dushanbe', 'ru', 'SB', 1, 'active')"
            )->execute(['Settings B ' . $suffix]);
            self::$companyB = (int)$db->lastInsertId();

            self::$ready = self::$companyA > 0 && self::$companyB > 0;
        } catch (\Throwable) {
            self::$ready = false;
        }
    }

    protected function setUp(): void
    {
        if (!self::$ready) {
            $this->markTestSkipped('Database or language column not available');
        }
    }

    public function testUpdateSettingsAndDefaults(): void
    {
        Company::updateSettings(self::$companyA, self::$companyA, [
            'name' => 'Updated Co A',
            'owner_name' => 'Owner Updated',
            'phone' => '+992900000399',
            'address' => 'Dushanbe',
            'currency' => 'TJS',
            'language' => 'tg',
            'timezone' => 'Asia/Dushanbe',
            'invoice_prefix' => 'NK',
        ], null, false);

        $row = Company::findForCompany(self::$companyA, self::$companyA);
        $this->assertSame('Updated Co A', $row['name']);
        $this->assertSame('TJS', $row['currency']);
        $this->assertSame('tg', $row['language']);
        $this->assertSame('Asia/Dushanbe', $row['timezone']);
        $this->assertSame('NK', $row['invoice_prefix']);
    }

    public function testInvalidCurrencyRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Company::updateSettings(self::$companyA, self::$companyA, [
            'name' => 'X',
            'owner_name' => 'Y',
            'phone' => '',
            'address' => '',
            'currency' => 'EUR',
            'language' => 'ru',
            'timezone' => 'Asia/Dushanbe',
            'invoice_prefix' => 'NK',
        ], null, false);
    }

    public function testIdorBlocked(): void
    {
        $this->assertNull(Company::findForCompany(self::$companyA, self::$companyB));
        $this->expectException(\RuntimeException::class);
        Company::updateSettings(self::$companyA, self::$companyB, [
            'name' => 'Hack',
            'owner_name' => 'Hack',
            'phone' => '',
            'address' => '',
            'currency' => 'TJS',
            'language' => 'ru',
            'timezone' => 'Asia/Dushanbe',
            'invoice_prefix' => 'XX',
        ], null, false);
    }

    public function testRemoveLogoClearsPath(): void
    {
        $db = Database::getInstance();
        $db->prepare('UPDATE companies SET logo_path = ? WHERE id = ?')
            ->execute(['uploads/logos/test-fake.png', self::$companyA]);

        Company::updateSettings(self::$companyA, self::$companyA, [
            'name' => 'Logo Test',
            'owner_name' => 'Owner',
            'phone' => '',
            'address' => '',
            'currency' => 'TJS',
            'language' => 'ru',
            'timezone' => 'Asia/Dushanbe',
            'invoice_prefix' => 'NK',
        ], null, true);

        $row = Company::findForCompany(self::$companyA, self::$companyA);
        $this->assertNull($row['logo_path']);
    }
}

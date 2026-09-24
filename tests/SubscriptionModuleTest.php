<?php

namespace Tests;

use App\Core\Database;
use App\Models\Subscription;
use PHPUnit\Framework\TestCase;

class SubscriptionModuleTest extends TestCase
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
                "SELECT 1 FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = 'subscription_history' LIMIT 1"
            )->fetch();

            $suffix = (string)random_int(100000, 999999);
            $db->prepare(
                "INSERT INTO companies (name, owner_name, phone, currency, timezone, invoice_prefix, next_invoice_number, status)
                 VALUES (?, 'Sub A', '+992900000101', 'TJS', 'Asia/Dushanbe', 'NK', 1, 'active')"
            )->execute(['SubTest A ' . $suffix]);
            self::$companyA = (int)$db->lastInsertId();

            $db->prepare(
                "INSERT INTO companies (name, owner_name, phone, currency, timezone, invoice_prefix, next_invoice_number, status)
                 VALUES (?, 'Sub B', '+992900000102', 'TJS', 'Asia/Dushanbe', 'NK', 1, 'active')"
            )->execute(['SubTest B ' . $suffix]);
            self::$companyB = (int)$db->lastInsertId();

            self::$ready = self::$companyA > 0 && self::$companyB > 0;
        } catch (\Throwable) {
            self::$ready = false;
        }
    }

    protected function setUp(): void
    {
        if (!self::$ready) {
            $this->markTestSkipped('Database or subscription_history not available');
        }
    }

    public function testTrialCreationIsThreeDays(): void
    {
        $sub = Subscription::createTrial(self::$companyA);
        $this->assertSame('trial', $sub['plan']);
        $this->assertSame('trial', $sub['status']);
        $this->assertGreaterThanOrEqual(2, Subscription::daysRemaining($sub));
        $this->assertLessThanOrEqual(3, Subscription::daysRemaining($sub));

        $again = Subscription::createTrial(self::$companyA);
        $this->assertSame((int)$sub['id'], (int)$again['id'], 'Trial must not duplicate');
    }

    public function testInvalidPeriodRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Subscription::assertPeriodMonths(2);
    }

    public function testAdminActivateAndExtend(): void
    {
        Subscription::adminActivate(self::$companyB, 1);
        $sub = Subscription::findForCompany(self::$companyB);
        $this->assertSame('business', $sub['plan']);
        $this->assertSame('active', $sub['status']);
        $this->assertSame(1, (int)$sub['period_months']);
        $days1 = Subscription::daysRemaining($sub);

        Subscription::adminExtend(self::$companyB, 3);
        $sub2 = Subscription::findForCompany(self::$companyB);
        $days2 = Subscription::daysRemaining($sub2);
        $this->assertGreaterThan($days1, $days2);
        $this->assertSame(3, (int)$sub2['period_months']);

        $history = Subscription::historyForCompany(self::$companyB, 10);
        $this->assertNotEmpty($history);
    }

    public function testExpiryMakesReadOnly(): void
    {
        $db = Database::getInstance();
        $db->prepare(
            "UPDATE subscriptions SET ends_at = DATE_SUB(NOW(), INTERVAL 1 DAY), status = 'active' WHERE company_id = ?"
        )->execute([self::$companyA]);
        $sub = Subscription::findForCompany(self::$companyA);
        Subscription::expireIfNeeded($sub);
        $sub = Subscription::findForCompany(self::$companyA);
        $this->assertSame('expired', $sub['status']);
        $this->assertFalse(Subscription::isWritable($sub));
    }

    public function testCompanyIsolationOnHistory(): void
    {
        $histA = Subscription::historyForCompany(self::$companyA, 5);
        $histB = Subscription::historyForCompany(self::$companyB, 5);
        $this->assertNotSame($histA, $histB);
    }
}

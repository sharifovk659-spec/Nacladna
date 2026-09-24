<?php

namespace Tests;

use App\Core\Database;
use App\Models\CompanyUser;
use App\Models\EmployeeInvite;
use App\Models\Permission;
use App\Services\PermissionRegistry;
use PHPUnit\Framework\TestCase;

class RolesPermissionsModuleTest extends TestCase
{
    private static bool $ready = false;
    private static int $companyA = 0;
    private static int $companyB = 0;
    private static int $ownerA = 0;
    private static int $cashierB = 0;

    public static function setUpBeforeClass(): void
    {
        $root = dirname(__DIR__);
        if (!defined('ROOT_DIR')) {
            define('ROOT_DIR', $root);
        }
        require_once $root . '/vendor/autoload.php';
        if (is_file($root . '/.env')) {
            \Dotenv\Dotenv::createImmutable($root)->safeLoad();
        }

        try {
            $db = Database::getInstance();
            $db->query('SELECT slug FROM permissions LIMIT 1');
            $db->query("SELECT role FROM role_permissions WHERE role='cashier' LIMIT 1");

            $suffix = (string)random_int(100000, 999999);
            $db->prepare(
                "INSERT INTO companies (name, owner_name, phone, currency, timezone, invoice_prefix, next_invoice_number, status)
                 VALUES (?, 'Owner A', '+992900000401', 'TJS', 'Asia/Dushanbe', 'RA', 1, 'active')"
            )->execute(['Roles A ' . $suffix]);
            self::$companyA = (int)$db->lastInsertId();

            $db->prepare(
                "INSERT INTO companies (name, owner_name, phone, currency, timezone, invoice_prefix, next_invoice_number, status)
                 VALUES (?, 'Owner B', '+992900000402', 'TJS', 'Asia/Dushanbe', 'RB', 1, 'active')"
            )->execute(['Roles B ' . $suffix]);
            self::$companyB = (int)$db->lastInsertId();

            $db->prepare(
                "INSERT INTO users (telegram_id, first_name, last_name, status) VALUES (?, 'Owner', 'A', 'active')"
            )->execute([900000000 + random_int(1, 99999)]);
            self::$ownerA = (int)$db->lastInsertId();

            $db->prepare(
                "INSERT INTO users (telegram_id, first_name, last_name, status) VALUES (?, 'Cash', 'B', 'active')"
            )->execute([900000000 + random_int(1, 99999)]);
            self::$cashierB = (int)$db->lastInsertId();

            $db->prepare(
                "INSERT INTO company_users (company_id, user_id, role, status) VALUES (?, ?, 'owner', 'active')"
            )->execute([self::$companyA, self::$ownerA]);

            $db->prepare(
                "INSERT INTO company_users (company_id, user_id, role, status) VALUES (?, ?, 'cashier', 'active')"
            )->execute([self::$companyB, self::$cashierB]);

            self::$ready = true;
        } catch (\Throwable) {
            self::$ready = false;
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (!self::$ready) {
            return;
        }
        $db = Database::getInstance();
        $db->prepare('DELETE FROM company_users WHERE company_id IN (?,?)')->execute([self::$companyA, self::$companyB]);
        $db->prepare('DELETE FROM companies WHERE id IN (?,?)')->execute([self::$companyA, self::$companyB]);
        $db->prepare('DELETE FROM users WHERE id IN (?,?)')->execute([self::$ownerA, self::$cashierB]);
    }

    private function skipIfNotReady(): void
    {
        if (!self::$ready) {
            $this->markTestSkipped('DB or migration 017 not available');
        }
    }

    public function testCashierHasInvoiceCreateNotClientsDelete(): void
    {
        $this->skipIfNotReady();
        $member = CompanyUser::findMembershipByUser(self::$cashierB, self::$companyB);
        $this->assertNotNull($member);
        $perms = Permission::effectiveForMembership($member);
        $this->assertContains('invoices.create', $perms);
        $this->assertNotContains('clients.delete', $perms);
    }

    public function testOwnerHasAllPermissions(): void
    {
        $this->skipIfNotReady();
        $member = CompanyUser::findMembershipByUser(self::$ownerA, self::$companyA);
        $perms = Permission::effectiveForMembership($member);
        $this->assertContains('employees.create', $perms);
        $this->assertContains('settings.edit', $perms);
    }

    public function testIdorEmployeeLookupBlocked(): void
    {
        $this->skipIfNotReady();
        $member = CompanyUser::findForCompany(
            (int)CompanyUser::listForCompany(self::$companyB)[0]['id'],
            self::$companyA
        );
        $this->assertNull($member);
    }

    public function testCannotAssignOwnerRole(): void
    {
        $this->skipIfNotReady();
        $this->expectException(\RuntimeException::class);
        CompanyUser::updateEmployee(
            (int)CompanyUser::listForCompany(self::$companyB)[0]['id'],
            self::$companyB,
            'admin',
            self::$cashierB,
            null,
            'owner',
            null
        );
    }

    public function testInviteTokenCreatesMembership(): void
    {
        $this->skipIfNotReady();
        $invite = EmployeeInvite::create(self::$companyA, self::$ownerA, 'New Manager', 'manager', 'owner');
        $newUserId = self::$cashierB;
        $db = Database::getInstance();
        $db->prepare('DELETE FROM company_users WHERE user_id = ?')->execute([$newUserId]);

        $result = EmployeeInvite::consume($invite['token'], $newUserId);
        $this->assertSame(self::$companyA, $result['company_id']);
        $member = CompanyUser::findMembershipByUser($newUserId, self::$companyA);
        $this->assertSame('manager', $member['role']);
    }

    public function testPrivilegeEscalationBlockedForCashierActor(): void
    {
        $this->skipIfNotReady();
        $this->assertFalse(PermissionRegistry::canAssignRole('cashier', 'admin'));
    }
}

<?php

namespace Tests;

use App\Core\Database;
use PHPUnit\Framework\TestCase;

class OnboardingTransactionTest extends TestCase
{
    private static bool $ready = false;

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
            Database::getInstance()->query('SELECT 1');
            self::$ready = true;
        } catch (\Throwable) {
            self::$ready = false;
        }
    }

    protected function setUp(): void
    {
        if (!self::$ready) {
            $this->markTestSkipped('Database not available');
        }
    }

    public function testCompanyTrialCreatedAtomicallyAndDuplicateBlocked(): void
    {
        $db = Database::getInstance();
        $tgId = 910000000 + random_int(1, 999999);

        $db->prepare(
            "INSERT INTO users (telegram_id, telegram_username, first_name, last_name, status, last_login_at)
             VALUES (?, ?, 'Owner', 'Test', 'active', NOW())"
        )->execute([$tgId, 'owner_' . $tgId]);
        $userId = (int)$db->lastInsertId();

        $create = function () use ($db, $userId): int {
            $db->beginTransaction();
            try {
                $existing = $db->prepare('SELECT id FROM company_users WHERE user_id = ? LIMIT 1 FOR UPDATE');
                $existing->execute([$userId]);
                if ($existing->fetch()) {
                    $db->rollBack();
                    return 0;
                }

                $db->prepare(
                    "INSERT INTO companies (name, owner_name, phone, currency, timezone, invoice_prefix, next_invoice_number, status)
                     VALUES ('Test Co', 'Owner Test', '+992900000001', 'TJS', 'Asia/Dushanbe', 'NK', 1, 'active')"
                )->execute();
                $companyId = (int)$db->lastInsertId();

                $db->prepare(
                    "INSERT INTO company_users (company_id, user_id, role, status) VALUES (?, ?, 'owner', 'active')"
                )->execute([$companyId, $userId]);

                $now = date('Y-m-d H:i:s');
                $trialEnd = date('Y-m-d H:i:s', strtotime('+3 days'));
                $db->prepare(
                    "INSERT INTO subscriptions (company_id, plan, trial_start, trial_end, starts_at, ends_at, status)
                     VALUES (?, 'trial', ?, ?, ?, ?, 'active')"
                )->execute([$companyId, $now, $trialEnd, $now, $trialEnd]);

                $db->commit();
                return $companyId;
            } catch (\Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }
        };

        $companyId = $create();
        $this->assertGreaterThan(0, $companyId);

        $st = $db->prepare('SELECT * FROM companies WHERE id=?');
        $st->execute([$companyId]);
        $company = $st->fetch();
        $this->assertSame('NK', $company['invoice_prefix']);
        $this->assertSame('TJS', $company['currency']);
        $this->assertSame('Asia/Dushanbe', $company['timezone']);

        $cu = $db->prepare('SELECT * FROM company_users WHERE user_id=?');
        $cu->execute([$userId]);
        $membership = $cu->fetch();
        $this->assertSame('owner', $membership['role']);
        $this->assertSame('active', $membership['status']);

        $sub = $db->prepare('SELECT * FROM subscriptions WHERE company_id=? ORDER BY id DESC LIMIT 1');
        $sub->execute([$companyId]);
        $subscription = $sub->fetch();
        $this->assertSame('trial', $subscription['plan']);
        $this->assertSame('active', $subscription['status']);
        $this->assertGreaterThan(time() + 2 * 86400, strtotime($subscription['trial_end']));

        $this->assertSame(0, $create(), 'Duplicate onboarding must be blocked');
    }

    public function testCrossCompanyAccessBlockedByMembershipQuery(): void
    {
        $db = Database::getInstance();
        $tgA = 920000000 + random_int(1, 499999);
        $tgB = 920500000 + random_int(1, 499999);

        $db->prepare("INSERT INTO users (telegram_id, first_name, status) VALUES (?, 'A', 'active')")->execute([$tgA]);
        $userA = (int)$db->lastInsertId();
        $db->prepare("INSERT INTO users (telegram_id, first_name, status) VALUES (?, 'B', 'active')")->execute([$tgB]);
        $userB = (int)$db->lastInsertId();

        $db->prepare(
            "INSERT INTO companies (name, owner_name, phone, currency, timezone, invoice_prefix, next_invoice_number, status)
             VALUES ('CoA', 'A', '+992900000002', 'TJS', 'Asia/Dushanbe', 'NK', 1, 'active')"
        )->execute();
        $companyA = (int)$db->lastInsertId();
        $db->prepare("INSERT INTO company_users (company_id, user_id, role, status) VALUES (?, ?, 'owner', 'active')")
           ->execute([$companyA, $userA]);

        $st = $db->prepare(
            "SELECT c.id FROM companies c
             JOIN company_users cu ON cu.company_id = c.id
             WHERE cu.user_id = ? AND cu.status = 'active' AND c.id = ?"
        );
        $st->execute([$userB, $companyA]);
        $this->assertFalse($st->fetch());
    }
}

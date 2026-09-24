<?php
require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

use App\Core\Database;
use App\Models\Subscription;

$fail = 0;
function ok(string $label, bool $cond, string $detail = ''): void {
    global $fail;
    if ($cond) echo "PASS $label" . ($detail ? " — $detail" : '') . "\n";
    else { $fail++; echo "FAIL $label" . ($detail ? " — $detail" : '') . "\n"; }
}

try {
    $db = Database::getInstance();
    $db->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'subscription_history' LIMIT 1")->fetch();
    ok('Migration subscription_history', true);
} catch (Throwable $e) {
    ok('Migration subscription_history', false, $e->getMessage());
    echo "\nRESULT: FAIL\n";
    exit(1);
}

$suffix = (string)random_int(100000, 999999);
$db = Database::getInstance();
$db->prepare("INSERT INTO companies (name, owner_name, phone, currency, timezone, invoice_prefix, next_invoice_number, status)
 VALUES (?, 'SO', '+992900000200', 'TJS', 'Asia/Dushanbe', 'NK', 1, 'active')")->execute(['SubReady '.$suffix]);
$companyId = (int)$db->lastInsertId();

try {
    $invalidBlocked = false;
    try {
        Subscription::assertPeriodMonths(5);
    } catch (\InvalidArgumentException) {
        $invalidBlocked = true;
    }
    ok('Invalid period blocked', $invalidBlocked);

    $trial = Subscription::createTrial($companyId);
    ok('Trial plan', ($trial['plan'] ?? '') === 'trial');
    ok('Trial status', ($trial['status'] ?? '') === 'trial');
    ok('Trial ~3 days', Subscription::daysRemaining($trial) >= 2 && Subscription::daysRemaining($trial) <= 3);

    Subscription::adminActivate($companyId, 6);
    $paid = Subscription::findForCompany($companyId);
    ok('Activate 6mo', (int)($paid['period_months'] ?? 0) === 6 && ($paid['status'] ?? '') === 'active');

    Subscription::adminExtend($companyId, 1);
    $extended = Subscription::findForCompany($companyId);
    ok('Extend adds time', Subscription::daysRemaining($extended) > Subscription::daysRemaining($paid));

    $hist = Subscription::historyForCompany($companyId, 20);
    ok('History rows', count($hist) >= 3);

    $past = date('Y-m-d H:i:s', time() - 86400 * 5);
    $db->prepare("UPDATE subscriptions SET ends_at = ?, status='active' WHERE company_id=?")->execute([$past, $companyId]);
    $sub = Subscription::findForCompany($companyId);
    Subscription::expireIfNeeded($sub);
    $sub = Subscription::findForCompany($companyId);
    ok('Auto expire', ($sub['status'] ?? '') === 'expired');
    ok('Read-only after expiry', !Subscription::isWritable($sub));
} catch (Throwable $e) {
    ok('Subscription flow', false, $e->getMessage());
}

$db->prepare('DELETE FROM subscription_history WHERE company_id = ?')->execute([$companyId]);
$db->prepare('DELETE FROM subscriptions WHERE company_id = ?')->execute([$companyId]);
$db->prepare('DELETE FROM companies WHERE id = ?')->execute([$companyId]);

echo $fail === 0 ? "\nSUBSCRIPTION READY CHECK PASS\n" : "\nSUBSCRIPTION READY CHECK FAIL ($fail)\n";
exit($fail === 0 ? 0 : 1);

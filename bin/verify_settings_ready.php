<?php
require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

use App\Core\Database;
use App\Models\Company;

$fail = 0;
function ok(string $label, bool $cond, string $detail = ''): void {
    global $fail;
    if ($cond) echo "PASS $label" . ($detail ? " — $detail" : '') . "\n";
    else { $fail++; echo "FAIL $label" . ($detail ? " — $detail" : '') . "\n"; }
}

try {
    $db = Database::getInstance();
    $col = $db->query("SHOW COLUMNS FROM companies LIKE 'language'")->fetch();
    ok('Migration language column', !empty($col));
} catch (Throwable $e) {
    ok('DB', false, $e->getMessage());
    exit(1);
}

$suffix = (string)random_int(100000, 999999);
$db = Database::getInstance();
$db->prepare("INSERT INTO companies (name, owner_name, phone, currency, timezone, language, invoice_prefix, next_invoice_number, status)
 VALUES (?, 'O', '+992900000400', 'TJS', 'Asia/Dushanbe', 'ru', 'RD', 1, 'active')")->execute(['SettingsReady '.$suffix]);
$companyId = (int)$db->lastInsertId();
$otherId = $companyId + 99999;

try {
    ok('IDOR find blocked', Company::findForCompany($companyId, $otherId) === null);

    Company::updateSettings($companyId, $companyId, [
        'name' => 'Ready Co',
        'owner_name' => 'Owner',
        'phone' => '+992900000401',
        'address' => 'Test address',
        'currency' => 'TJS',
        'language' => 'en',
        'timezone' => 'Asia/Dushanbe',
        'invoice_prefix' => 'NK',
    ], null, false);

    $row = Company::findForCompany($companyId, $companyId);
    ok('Name saved', ($row['name'] ?? '') === 'Ready Co');
    ok('Currency TJS', ($row['currency'] ?? '') === 'TJS');
    ok('Language en', ($row['language'] ?? '') === 'en');
    ok('Timezone', ($row['timezone'] ?? '') === 'Asia/Dushanbe');
    ok('Prefix NK', ($row['invoice_prefix'] ?? '') === 'NK');

    $bad = false;
    try {
        Company::updateSettings($companyId, $companyId, [
            'name' => 'X', 'owner_name' => 'Y', 'phone' => '', 'address' => '',
            'currency' => 'XXX', 'language' => 'ru', 'timezone' => 'Asia/Dushanbe', 'invoice_prefix' => 'NK',
        ], null, false);
    } catch (InvalidArgumentException) {
        $bad = true;
    }
    ok('Invalid currency blocked', $bad);
} catch (Throwable $e) {
    ok('Settings flow', false, $e->getMessage());
}

$db->prepare('DELETE FROM companies WHERE id = ?')->execute([$companyId]);

echo $fail === 0 ? "\nSETTINGS READY CHECK PASS\n" : "\nSETTINGS READY CHECK FAIL ($fail)\n";
exit($fail === 0 ? 0 : 1);

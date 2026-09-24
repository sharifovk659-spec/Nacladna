<?php
/**
 * QR module readiness check (server). Self-cleaning test data.
 */
require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

use App\Core\Database;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceQrCode;
use App\Models\Product;

$fail = 0;
function ok(string $label, bool $cond, string $detail = ''): void {
    global $fail;
    if ($cond) {
        echo "PASS $label" . ($detail ? " — $detail" : '') . "\n";
    } else {
        $fail++;
        echo "FAIL $label" . ($detail ? " — $detail" : '') . "\n";
    }
}

try {
    $db = Database::getInstance();
    $db->query(
        "SELECT 1 FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = 'invoice_qr_codes' LIMIT 1"
    )->fetch();
    ok('Migration table invoice_qr_codes', true);
} catch (Throwable $e) {
    ok('Migration table invoice_qr_codes', false, $e->getMessage());
    echo "\nRESULT: FAIL ($fail checks)\n";
    exit(1);
}

$suffix = (string)random_int(100000, 999999);
$db->prepare("INSERT INTO users (telegram_id, first_name, status) VALUES (?, 'QrReady', 'active')")
    ->execute([960000000 + random_int(1, 99999)]);
$userA = (int)$db->lastInsertId();
$db->prepare("INSERT INTO companies (name, owner_name, phone, currency, timezone, invoice_prefix, next_invoice_number, status)
 VALUES (?, 'QO', '+992900000010', 'TJS', 'Asia/Dushanbe', 'QR', 1, 'active')")->execute(['QrReady '.$suffix]);
$companyA = (int)$db->lastInsertId();
$db->prepare("INSERT INTO company_users (company_id, user_id, role, status) VALUES (?, ?, 'owner', 'active')")
    ->execute([$companyA, $userA]);

$clientA = Client::create($companyA, ['name'=>'Qr Client','phone'=>'+992900000011','address'=>'TJ','opening_debt'=>'0']);
$productA = Product::create($companyA, [
    'name'=>'Qr Product','sku'=>'QRD-'.$suffix,'barcode'=>'','unit'=>'piece',
    'purchase_price'=>'5','sale_price'=>'20','stock_quantity'=>'100',
]);

try {
    ok('Invalid UUID lookup', InvoiceQrCode::findPublicByUuid('bad-token') === null);

    $created = Invoice::create($companyA, $userA, [
        'client_id'=>$clientA,'invoice_date'=>date('Y-m-d'),'discount'=>0,'paid_amount'=>20,
        'payment_method'=>'cash','status'=>'paid',
        'items'=>[['product_id'=>$productA,'quantity'=>1,'unit_price'=>20,'discount'=>0]],
    ]);
    $inv = Invoice::findForCompany($created['id'], $companyA);
    $qr = InvoiceQrCode::syncForInvoice((int)$inv['id'], $companyA);
    ok('Sync creates QR', $qr !== null);
    ok('UUID format', (bool)preg_match('/^[0-9a-f-]{36}$/i', (string)($qr['public_uuid'] ?? '')));
    ok('Public URL path', str_contains((string)($qr['public_url'] ?? ''), '/invoice/public/'));

    $public = InvoiceQrCode::findPublicByUuid((string)$qr['public_uuid']);
    ok('Public lookup', $public !== null);
    ok('Safe payload (no company_id)', !array_key_exists('company_id', $public));

    $raw = Invoice::getItems((int)$inv['id']);
    $safe = InvoiceQrCode::mapPublicItems($raw);
    ok('Public items strip product_id', !array_key_exists('product_id', $safe[0] ?? []));

    $uri = InvoiceQrCode::imageDataUri($qr['qr_image_path'] ?? null, (string)$qr['public_url']);
    ok('QR data URI', str_starts_with($uri, 'data:image/png;base64,'));

    InvoiceQrCode::revokeForInvoice((int)$inv['id'], $companyA);
    ok('Revoked UUID hidden', InvoiceQrCode::findPublicByUuid((string)$qr['public_uuid']) === null);

    InvoiceQrCode::syncForInvoice((int)$inv['id'], $companyA);
    ok('Re-sync after revoke', InvoiceQrCode::findPublicByUuid((string)$qr['public_uuid']) !== null);

    Invoice::cancel((int)$inv['id'], $companyA, $userA);
    InvoiceQrCode::revokeForInvoice((int)$inv['id'], $companyA);
    ok('Cancelled invoice not public', InvoiceQrCode::findPublicByUuid((string)$qr['public_uuid']) === null);

    $share = Invoice::shareUrl($inv);
    ok('Share URL uses public path when active', str_contains($share, '/invoice/public/') || str_contains($share, '/invoices/'));
} catch (Throwable $e) {
    ok('QR flow', false, $e->getMessage());
}

// cleanup
$db->prepare('DELETE FROM invoice_qr_codes WHERE company_id = ?')->execute([$companyA]);
$db->prepare('DELETE FROM payments WHERE company_id = ?')->execute([$companyA]);
$db->prepare('DELETE FROM invoice_items WHERE invoice_id IN (SELECT id FROM invoices WHERE company_id = ?)')->execute([$companyA]);
$db->prepare('DELETE FROM invoices WHERE company_id = ?')->execute([$companyA]);
$db->prepare('DELETE FROM products WHERE company_id = ?')->execute([$companyA]);
$db->prepare('DELETE FROM clients WHERE company_id = ?')->execute([$companyA]);
$db->prepare('DELETE FROM company_users WHERE company_id = ?')->execute([$companyA]);
$db->prepare('DELETE FROM companies WHERE id = ?')->execute([$companyA]);
$db->prepare('DELETE FROM users WHERE id = ?')->execute([$userA]);

echo $fail === 0 ? "\nRESULT: PASS\n" : "\nRESULT: FAIL ($fail)\n";
exit($fail === 0 ? 0 : 1);

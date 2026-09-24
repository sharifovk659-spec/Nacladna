<?php
/**
 * Production smoke: public invoice QR HTTP endpoint (no auth).
 */
require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

use App\Core\Database;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceQrCode;
use App\Models\Product;

$base = rtrim($_ENV['APP_URL'] ?? '', '/');
if ($base === '') {
    fwrite(STDERR, "APP_URL not set\n");
    exit(2);
}

$db = Database::getInstance();
$suffix = (string)random_int(100000, 999999);
$db->prepare("INSERT INTO users (telegram_id, first_name, status) VALUES (?, 'QrSmoke', 'active')")
    ->execute([970000000 + random_int(1, 99999)]);
$userA = (int)$db->lastInsertId();
$db->prepare("INSERT INTO companies (name, owner_name, phone, currency, timezone, invoice_prefix, next_invoice_number, status)
 VALUES (?, 'QS', '+992900000020', 'TJS', 'Asia/Dushanbe', 'QS', 1, 'active')")->execute(['QrSmoke '.$suffix]);
$companyA = (int)$db->lastInsertId();
$db->prepare("INSERT INTO company_users (company_id, user_id, role, status) VALUES (?, ?, 'owner', 'active')")
    ->execute([$companyA, $userA]);

$clientA = Client::create($companyA, ['name'=>'Smoke Client','phone'=>'+992900000021','address'=>'TJ','opening_debt'=>'0']);
$productA = Product::create($companyA, [
    'name'=>'Smoke Product','sku'=>'SMK-'.$suffix,'barcode'=>'','unit'=>'piece',
    'purchase_price'=>'1','sale_price'=>'10','stock_quantity'=>'50',
]);

$created = Invoice::create($companyA, $userA, [
    'client_id'=>$clientA,'invoice_date'=>date('Y-m-d'),'discount'=>0,'paid_amount'=>10,
    'payment_method'=>'cash','status'=>'paid',
    'items'=>[['product_id'=>$productA,'quantity'=>1,'unit_price'=>10,'discount'=>0]],
]);
$qr = InvoiceQrCode::syncForInvoice((int)$created['id'], $companyA);
if (!$qr) {
    fwrite(STDERR, "QR sync failed\n");
    exit(1);
}

$uuid = (string)$qr['public_uuid'];
$url = $base . '/invoice/public/' . $uuid;

$ctx = stream_context_create(['http' => ['timeout' => 15, 'ignore_errors' => true]]);
$body = @file_get_contents($url, false, $ctx);
$code = isset($http_response_header[0]) ? (int)explode(' ', $http_response_header[0])[1] : 0;

$badUrl = $base . '/invoice/public/00000000-0000-4000-8000-000000000000';
@file_get_contents($badUrl, false, $ctx);
$badCode = isset($http_response_header[0]) ? (int)explode(' ', $http_response_header[0])[1] : 0;

$ok = $code === 200 && is_string($body) && str_contains($body, (string)$created['number']);
$ok404 = $badCode === 404;

echo ($ok ? 'PASS' : 'FAIL') . " GET public invoice ($code)\n";
echo ($ok404 ? 'PASS' : 'FAIL') . " invalid UUID 404 ($badCode)\n";

$db->prepare('DELETE FROM invoice_qr_codes WHERE company_id = ?')->execute([$companyA]);
$db->prepare('DELETE FROM payments WHERE company_id = ?')->execute([$companyA]);
$db->prepare('DELETE FROM invoice_items WHERE invoice_id IN (SELECT id FROM invoices WHERE company_id = ?)')->execute([$companyA]);
$db->prepare('DELETE FROM invoices WHERE company_id = ?')->execute([$companyA]);
$db->prepare('DELETE FROM products WHERE company_id = ?')->execute([$companyA]);
$db->prepare('DELETE FROM clients WHERE company_id = ?')->execute([$companyA]);
$db->prepare('DELETE FROM company_users WHERE company_id = ?')->execute([$companyA]);
$db->prepare('DELETE FROM companies WHERE id = ?')->execute([$companyA]);
$db->prepare('DELETE FROM users WHERE id = ?')->execute([$userA]);

exit($ok && $ok404 ? 0 : 1);

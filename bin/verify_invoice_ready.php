<?php
/**
 * One-shot invoice readiness check on server. Self-cleaning test data.
 */
require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

use App\Core\Database;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;

$fail = 0;
function ok(string $label, bool $cond, string $detail = ''): void {
    global $fail;
    if ($cond) echo "PASS $label" . ($detail ? " — $detail" : '') . "\n";
    else { $fail++; echo "FAIL $label" . ($detail ? " — $detail" : '') . "\n"; }
}
function fmt($v, $s = 2): string { return number_format((float)$v, $s, '.', ''); }

$db = Database::getInstance();
$suffix = (string)random_int(100000, 999999);

$db->prepare("INSERT INTO users (telegram_id, first_name, status) VALUES (?, 'InvReady', 'active')")
    ->execute([940000000 + random_int(1, 99999)]);
$userA = (int)$db->lastInsertId();
$db->prepare("INSERT INTO users (telegram_id, first_name, status) VALUES (?, 'InvReadyB', 'active')")
    ->execute([945000000 + random_int(1, 99999)]);
$userB = (int)$db->lastInsertId();

$db->prepare("INSERT INTO companies (name, owner_name, phone, currency, timezone, invoice_prefix, next_invoice_number, status)
 VALUES (?, 'OA', '+992900000001', 'TJS', 'Asia/Dushanbe', 'NK', 1, 'active')")->execute(['ReadyA '.$suffix]);
$companyA = (int)$db->lastInsertId();
$db->prepare("INSERT INTO companies (name, owner_name, phone, currency, timezone, invoice_prefix, next_invoice_number, status)
 VALUES (?, 'OB', '+992900000002', 'TJS', 'Asia/Dushanbe', 'NK', 1, 'active')")->execute(['ReadyB '.$suffix]);
$companyB = (int)$db->lastInsertId();

$db->prepare("INSERT INTO company_users (company_id, user_id, role, status) VALUES (?, ?, 'owner', 'active')")->execute([$companyA, $userA]);
$db->prepare("INSERT INTO company_users (company_id, user_id, role, status) VALUES (?, ?, 'owner', 'active')")->execute([$companyB, $userB]);

$clientA = Client::create($companyA, ['name'=>'Ready Client','phone'=>'+992900000003','address'=>'TJ','opening_debt'=>'0']);
$productA = Product::create($companyA, [
    'name'=>'Ready Product','sku'=>'RDY-'.$suffix,'barcode'=>'','unit'=>'piece',
    'purchase_price'=>'10','sale_price'=>'20','stock_quantity'=>'100',
]);

try {
    $before = Product::findForCompany($productA, $companyA);
    $created = Invoice::create($companyA, $userA, [
        'client_id'=>$clientA,'invoice_date'=>date('Y-m-d'),'discount'=>5,'paid_amount'=>10,
        'payment_method'=>'bank','status'=>'partial',
        'items'=>[['product_id'=>$productA,'quantity'=>1,'unit_price'=>20,'discount'=>0]],
    ]);
    $inv = Invoice::findForCompany($created['id'], $companyA);
    ok('Create number', (bool)preg_match('/^NK-\d{6}$/', $created['number']));
    ok('Partial status', $inv['status']==='partial' && $inv['payment_status']==='partial');
    ok('Paid/debt amounts', fmt($inv['paid_amount'])==='10.00' && fmt($inv['debt_amount'])==='5.00');
    $after = Product::findForCompany($productA, $companyA);
    ok('Stock decrease', fmt($after['stock_quantity'],3) === Product::decimal((float)$before['stock_quantity']-1, 3));

    $over = false;
    try {
        Invoice::create($companyA, $userA, [
            'client_id'=>$clientA,'invoice_date'=>date('Y-m-d'),'discount'=>0,'paid_amount'=>0,
            'payment_method'=>'cash','status'=>'unpaid',
            'items'=>[['product_id'=>$productA,'quantity'=>999999,'unit_price'=>20,'discount'=>0]],
        ]);
    } catch (Throwable) { $over = true; }
    ok('Oversell blocked', $over);

    $paid = Invoice::create($companyA, $userA, [
        'client_id'=>$clientA,'invoice_date'=>date('Y-m-d'),'discount'=>0,'paid_amount'=>1500,
        'payment_method'=>'cash','status'=>'paid',
        'items'=>[['product_id'=>$productA,'quantity'=>1,'unit_price'=>1000,'discount'=>0]],
    ]);
    $pInv = Invoice::findForCompany($paid['id'], $companyA);
    ok('Overpay capped', $pInv['status']==='paid' && fmt($pInv['paid_amount'])==='1000.00' && fmt($pInv['debt_amount'])==='0.00');

    $part = Invoice::create($companyA, $userA, [
        'client_id'=>$clientA,'invoice_date'=>date('Y-m-d'),'discount'=>0,'paid_amount'=>500,
        'payment_method'=>'cash','status'=>'partial',
        'items'=>[['product_id'=>$productA,'quantity'=>1,'unit_price'=>1000,'discount'=>0]],
    ]);
    $pr = Invoice::findForCompany($part['id'], $companyA);
    ok('Underpay partial', $pr['status']==='partial' && fmt($pr['paid_amount'])==='500.00' && fmt($pr['debt_amount'])==='500.00');

    ok('IDOR blocked', Invoice::findForCompany($created['id'], $companyB) === null);

    $start = Product::findForCompany($productA, $companyA);
    $c2 = Invoice::create($companyA, $userA, [
        'client_id'=>$clientA,'invoice_date'=>date('Y-m-d'),'discount'=>0,'paid_amount'=>0,
        'payment_method'=>'cash','status'=>'unpaid',
        'items'=>[['product_id'=>$productA,'quantity'=>2,'unit_price'=>20,'discount'=>0]],
    ]);
    Invoice::cancel($c2['id'], $companyA, $userA);
    $cInv = Invoice::findForCompany($c2['id'], $companyA);
    ok('Cancel clears debt', $cInv['status']==='cancelled' && fmt($cInv['debt_amount'])==='0.00');
    $rest = Product::findForCompany($productA, $companyA);
    ok('Cancel restores stock', Product::decimal($rest['stock_quantity'],3) === Product::decimal($start['stock_quantity'],3));

    $paymentsBefore = Invoice::getPayments($created['id']);
    Invoice::update($created['id'], $companyA, $userA, [
        'client_id'=>$clientA,'invoice_date'=>date('Y-m-d'),'discount'=>5,'paid_amount'=>10,
        'payment_method'=>'bank','status'=>'partial',
        'items'=>[['product_id'=>$productA,'quantity'=>1,'unit_price'=>20,'discount'=>0]],
    ]);
    $paymentsAfter = Invoice::getPayments($created['id']);
    ok('Update keeps payments', count($paymentsAfter) >= count($paymentsBefore) && fmt($paymentsAfter[0]['amount'])===fmt($paymentsBefore[0]['amount']));

    $dup = Invoice::duplicate($created['id'], $companyA, $userA);
    $dInv = Invoice::findForCompany($dup['id'], $companyA);
    ok('Duplicate draft', $dInv['status']==='draft' && fmt($dInv['paid_amount'])==='0.00');

    $del = Invoice::create($companyA, $userA, [
        'client_id'=>$clientA,'invoice_date'=>date('Y-m-d'),'discount'=>0,'paid_amount'=>0,
        'payment_method'=>'cash','status'=>'unpaid',
        'items'=>[['product_id'=>$productA,'quantity'=>1,'unit_price'=>20,'discount'=>0]],
    ]);
    $stockBeforeDel = Product::findForCompany($productA, $companyA);
    Invoice::softDelete($del['id'], $companyA, $userA);
    ok('Soft delete hides', Invoice::findForCompany($del['id'], $companyA) === null);
    $stockAfterDel = Product::findForCompany($productA, $companyA);
    ok('Soft delete restores stock', Product::decimal($stockAfterDel['stock_quantity'],3) === Product::decimal((float)$stockBeforeDel['stock_quantity']+1, 3));

    $blocked = false;
    try { Invoice::cancel($del['id'], $companyA, $userA); } catch (Throwable) { $blocked = true; }
    ok('No cancel after soft-delete', $blocked);

    $delCol = $db->query("SHOW COLUMNS FROM invoices LIKE 'deleted_at'")->fetch();
    ok('deleted_at column', !empty($delCol));

} catch (Throwable $e) {
    $fail++;
    echo "FAIL Exception — ".$e->getMessage()."\n";
}

// cleanup
try {
    $db->prepare("DELETE FROM payments WHERE company_id IN (?,?)")->execute([$companyA,$companyB]);
    $db->prepare("DELETE FROM invoice_items WHERE invoice_id IN (SELECT id FROM invoices WHERE company_id IN (?,?))")->execute([$companyA,$companyB]);
    // MariaDB may not allow subquery delete — fallback
} catch (Throwable) {}
try {
    $ids = $db->prepare("SELECT id FROM invoices WHERE company_id IN (?,?)");
    $ids->execute([$companyA,$companyB]);
    foreach ($ids->fetchAll() as $row) {
        $db->prepare("DELETE FROM invoice_items WHERE invoice_id=?")->execute([$row['id']]);
        $db->prepare("DELETE FROM payments WHERE invoice_id=?")->execute([$row['id']]);
        $db->prepare("DELETE FROM invoices WHERE id=?")->execute([$row['id']]);
    }
    $db->prepare("DELETE FROM products WHERE company_id IN (?,?)")->execute([$companyA,$companyB]);
    $db->prepare("DELETE FROM clients WHERE company_id IN (?,?)")->execute([$companyA,$companyB]);
    $db->prepare("DELETE FROM company_users WHERE company_id IN (?,?)")->execute([$companyA,$companyB]);
    $db->prepare("DELETE FROM subscriptions WHERE company_id IN (?,?)")->execute([$companyA,$companyB]);
    $db->prepare("DELETE FROM companies WHERE id IN (?,?)")->execute([$companyA,$companyB]);
    $db->prepare("DELETE FROM users WHERE id IN (?,?)")->execute([$userA,$userB]);
    echo "CLEANUP ok\n";
} catch (Throwable $e) {
    echo "CLEANUP warn — ".$e->getMessage()."\n";
}

echo $fail===0 ? "\nINVOICE READY CHECK PASS\n" : "\nINVOICE READY CHECK FAIL ($fail)\n";
exit($fail===0 ? 0 : 1);

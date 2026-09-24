<?php

namespace Tests;

use App\Core\Database;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use PHPUnit\Framework\TestCase;

class InvoiceModuleTest extends TestCase
{
    private static bool $ready = false;
    private static int $companyA = 0;
    private static int $companyB = 0;
    private static int $userA = 0;
    private static int $userB = 0;
    private static int $clientA = 0;
    private static int $productA = 0;

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

            $suffix = (string)random_int(100000, 999999);
            $db->prepare(
                "INSERT INTO users (telegram_id, first_name, status) VALUES (?, 'Invoice', 'active')"
            )->execute([930000000 + random_int(1, 499999)]);
            self::$userA = (int)$db->lastInsertId();

            $db->prepare(
                "INSERT INTO users (telegram_id, first_name, status) VALUES (?, 'InvoiceB', 'active')"
            )->execute([931000000 + random_int(1, 499999)]);
            self::$userB = (int)$db->lastInsertId();

            $db->prepare(
                "INSERT INTO companies (name, owner_name, phone, currency, timezone, invoice_prefix, next_invoice_number, status)
                 VALUES (?, 'Owner A', '+992900001111', 'TJS', 'Asia/Dushanbe', 'NK', 1, 'active')"
            )->execute(['InvoiceTest A ' . $suffix]);
            self::$companyA = (int)$db->lastInsertId();

            $db->prepare(
                "INSERT INTO companies (name, owner_name, phone, currency, timezone, invoice_prefix, next_invoice_number, status)
                 VALUES (?, 'Owner B', '+992900001112', 'TJS', 'Asia/Dushanbe', 'NK', 1, 'active')"
            )->execute(['InvoiceTest B ' . $suffix]);
            self::$companyB = (int)$db->lastInsertId();

            $db->prepare("INSERT INTO company_users (company_id, user_id, role, status) VALUES (?, ?, 'owner', 'active')")
                ->execute([self::$companyA, self::$userA]);
            $db->prepare("INSERT INTO company_users (company_id, user_id, role, status) VALUES (?, ?, 'owner', 'active')")
                ->execute([self::$companyB, self::$userB]);

            self::$clientA = Client::create(self::$companyA, [
                'name' => 'Invoice Client',
                'phone' => '+992900001113',
                'address' => 'Dushanbe',
                'opening_debt' => '0',
            ]);

            self::$productA = Product::create(self::$companyA, [
                'name' => 'Invoice Product',
                'sku' => 'INV-T-1',
                'barcode' => '',
                'unit' => 'piece',
                'purchase_price' => '10.00',
                'sale_price' => '20.00',
                'stock_quantity' => '25',
            ]);

            self::$ready = self::$companyA > 0 && self::$companyB > 0 && self::$userA > 0 && self::$clientA > 0 && self::$productA > 0;
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

    private function fmt(float|int|string $value, int $scale = 2): string
    {
        return number_format((float)$value, $scale, '.', '');
    }

    public function testInvoiceCreateGeneratesNumberAndAdjustsStock(): void
    {
        $before = Product::findForCompany(self::$productA, self::$companyA);
        $result = Invoice::create(self::$companyA, self::$userA, [
            'client_id' => self::$clientA,
            'invoice_date' => date('Y-m-d'),
            'discount' => 5,
            'paid_amount' => 10,
            'payment_method' => 'bank',
            'status' => 'partial',
            'items' => [[
                'product_id' => self::$productA,
                'quantity' => 1,
                'unit_price' => 20,
                'discount' => 0,
            ]],
        ]);

        $this->assertGreaterThan(0, $result['id']);
        $this->assertMatchesRegularExpression('/^NK-\d{6}$/', $result['number']);

        $invoice = Invoice::findForCompany($result['id'], self::$companyA);
        $this->assertSame('partial', $invoice['status']);
        $this->assertSame('partial', $invoice['payment_status']);
        $this->assertSame('10.00', $this->fmt($invoice['paid_amount']));
        $this->assertSame('5.00', $this->fmt($invoice['debt_amount']));

        $after = Product::findForCompany(self::$productA, self::$companyA);
        $this->assertSame(
            Product::decimal((float)$before['stock_quantity'] - 1, 3),
            Product::decimal($after['stock_quantity'], 3)
        );
    }

    public function testInvoiceCancelRestoresStockAndDeleteHidesInvoice(): void
    {
        $start = Product::findForCompany(self::$productA, self::$companyA);
        $result = Invoice::create(self::$companyA, self::$userA, [
            'client_id' => self::$clientA,
            'invoice_date' => date('Y-m-d'),
            'discount' => 0,
            'paid_amount' => 0,
            'payment_method' => 'cash',
            'status' => 'unpaid',
            'items' => [[
                'product_id' => self::$productA,
                'quantity' => 2,
                'unit_price' => 20,
                'discount' => 0,
            ]],
        ]);

        $reduced = Product::findForCompany(self::$productA, self::$companyA);
        $this->assertSame(
            Product::decimal((float)$start['stock_quantity'] - 2, 3),
            Product::decimal($reduced['stock_quantity'], 3)
        );

        Invoice::cancel($result['id'], self::$companyA, self::$userA);
        $cancelled = Invoice::findForCompany($result['id'], self::$companyA);
        $this->assertSame('cancelled', $cancelled['status']);

        $restored = Product::findForCompany(self::$productA, self::$companyA);
        $this->assertSame(
            Product::decimal($start['stock_quantity'], 3),
            Product::decimal($restored['stock_quantity'], 3)
        );

        Invoice::softDelete($result['id'], self::$companyA, self::$userA);
        $this->assertNull(Invoice::findForCompany($result['id'], self::$companyA));
    }

    public function testInvoiceValidationAndIsolation(): void
    {
        $this->expectException(\RuntimeException::class);
        Invoice::create(self::$companyA, self::$userA, [
            'client_id' => self::$clientA,
            'invoice_date' => date('Y-m-d'),
            'discount' => 0,
            'paid_amount' => 0,
            'payment_method' => 'cash',
            'status' => 'unpaid',
            'items' => [[
                'product_id' => self::$productA,
                'quantity' => 999999,
                'unit_price' => 20,
                'discount' => 0,
            ]],
        ]);
    }

    public function testInvoiceIdorBlockedAcrossCompanies(): void
    {
        $result = Invoice::create(self::$companyA, self::$userA, [
            'client_id' => self::$clientA,
            'invoice_date' => date('Y-m-d'),
            'discount' => 0,
            'paid_amount' => 0,
            'payment_method' => 'cash',
            'status' => 'draft',
            'items' => [[
                'product_id' => self::$productA,
                'quantity' => 1,
                'unit_price' => 20,
                'discount' => 0,
            ]],
        ]);

        $this->assertNull(Invoice::findForCompany($result['id'], self::$companyB));
    }

    public function testPaidPartialUnpaidAndOverpayCap(): void
    {
        $paid = Invoice::create(self::$companyA, self::$userA, [
            'client_id' => self::$clientA,
            'invoice_date' => date('Y-m-d'),
            'discount' => 0,
            'paid_amount' => 1500,
            'payment_method' => 'cash',
            'status' => 'paid',
            'items' => [[
                'product_id' => self::$productA,
                'quantity' => 1,
                'unit_price' => 1000,
                'discount' => 0,
            ]],
        ]);
        $invPaid = Invoice::findForCompany($paid['id'], self::$companyA);
        $this->assertSame('paid', $invPaid['status']);
        $this->assertSame('1000.00', $this->fmt($invPaid['paid_amount']));
        $this->assertSame('0.00', $this->fmt($invPaid['debt_amount']));

        $partial = Invoice::create(self::$companyA, self::$userA, [
            'client_id' => self::$clientA,
            'invoice_date' => date('Y-m-d'),
            'discount' => 0,
            'paid_amount' => 500,
            'payment_method' => 'cash',
            'status' => 'partial',
            'items' => [[
                'product_id' => self::$productA,
                'quantity' => 1,
                'unit_price' => 1000,
                'discount' => 0,
            ]],
        ]);
        $invPartial = Invoice::findForCompany($partial['id'], self::$companyA);
        $this->assertSame('partial', $invPartial['status']);
        $this->assertSame('500.00', $this->fmt($invPartial['paid_amount']));
        $this->assertSame('500.00', $this->fmt($invPartial['debt_amount']));

        $unpaid = Invoice::create(self::$companyA, self::$userA, [
            'client_id' => self::$clientA,
            'invoice_date' => date('Y-m-d'),
            'discount' => 0,
            'paid_amount' => 0,
            'payment_method' => 'cash',
            'status' => 'unpaid',
            'items' => [[
                'product_id' => self::$productA,
                'quantity' => 1,
                'unit_price' => 100,
                'discount' => 0,
            ]],
        ]);
        $invUnpaid = Invoice::findForCompany($unpaid['id'], self::$companyA);
        $this->assertSame('unpaid', $invUnpaid['status']);
        $this->assertSame('100.00', $this->fmt($invUnpaid['debt_amount']));
    }

    public function testSoftDeleteBlocksFurtherMutationAndNoDoubleStockRestore(): void
    {
        $start = Product::findForCompany(self::$productA, self::$companyA);
        $result = Invoice::create(self::$companyA, self::$userA, [
            'client_id' => self::$clientA,
            'invoice_date' => date('Y-m-d'),
            'discount' => 0,
            'paid_amount' => 0,
            'payment_method' => 'cash',
            'status' => 'unpaid',
            'items' => [[
                'product_id' => self::$productA,
                'quantity' => 3,
                'unit_price' => 20,
                'discount' => 0,
            ]],
        ]);

        Invoice::softDelete($result['id'], self::$companyA, self::$userA);
        $restored = Product::findForCompany(self::$productA, self::$companyA);
        $this->assertSame(
            Product::decimal($start['stock_quantity'], 3),
            Product::decimal($restored['stock_quantity'], 3)
        );

        $this->expectException(\RuntimeException::class);
        Invoice::cancel($result['id'], self::$companyA, self::$userA);
    }

    public function testUpdateKeepsPaymentsAndDuplicateWorks(): void
    {
        $created = Invoice::create(self::$companyA, self::$userA, [
            'client_id' => self::$clientA,
            'invoice_date' => date('Y-m-d'),
            'discount' => 0,
            'paid_amount' => 40,
            'payment_method' => 'cash',
            'status' => 'partial',
            'items' => [[
                'product_id' => self::$productA,
                'quantity' => 2,
                'unit_price' => 20,
                'discount' => 0,
            ]],
        ]);

        $paymentsBefore = Invoice::getPayments($created['id']);
        $this->assertNotEmpty($paymentsBefore);

        Invoice::update($created['id'], self::$companyA, self::$userA, [
            'client_id' => self::$clientA,
            'invoice_date' => date('Y-m-d'),
            'discount' => 0,
            'paid_amount' => 40,
            'payment_method' => 'cash',
            'status' => 'partial',
            'items' => [[
                'product_id' => self::$productA,
                'quantity' => 2,
                'unit_price' => 20,
                'discount' => 0,
            ]],
        ]);

        $paymentsAfter = Invoice::getPayments($created['id']);
        $this->assertGreaterThanOrEqual(count($paymentsBefore), count($paymentsAfter));
        $this->assertSame(
            $this->fmt($paymentsBefore[0]['amount']),
            $this->fmt($paymentsAfter[0]['amount'])
        );

        $dup = Invoice::duplicate($created['id'], self::$companyA, self::$userA);
        $dupInv = Invoice::findForCompany($dup['id'], self::$companyA);
        $this->assertSame('draft', $dupInv['status']);
        $this->assertSame('0.00', $this->fmt($dupInv['paid_amount']));
    }

    public function testCancelClearsDebtAmount(): void
    {
        $result = Invoice::create(self::$companyA, self::$userA, [
            'client_id' => self::$clientA,
            'invoice_date' => date('Y-m-d'),
            'discount' => 0,
            'paid_amount' => 0,
            'payment_method' => 'cash',
            'status' => 'unpaid',
            'items' => [[
                'product_id' => self::$productA,
                'quantity' => 1,
                'unit_price' => 50,
                'discount' => 0,
            ]],
        ]);
        Invoice::cancel($result['id'], self::$companyA, self::$userA);
        $inv = Invoice::findForCompany($result['id'], self::$companyA);
        $this->assertSame('cancelled', $inv['status']);
        $this->assertSame('0.00', $this->fmt($inv['debt_amount']));
    }
}

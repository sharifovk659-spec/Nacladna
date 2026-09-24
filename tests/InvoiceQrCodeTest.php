<?php

namespace Tests;

use App\Core\Database;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceQrCode;
use App\Models\Product;
use PHPUnit\Framework\TestCase;

class InvoiceQrCodeTest extends TestCase
{
    private static bool $ready = false;
    private static int $companyA = 0;
    private static int $companyB = 0;
    private static int $userA = 0;
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
            $db->query(
                "SELECT 1 FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = 'invoice_qr_codes' LIMIT 1"
            )->fetch();

            $suffix = (string)random_int(100000, 999999);
            $db->prepare(
                "INSERT INTO users (telegram_id, first_name, status) VALUES (?, 'QrTest', 'active')"
            )->execute([950000000 + random_int(1, 499999)]);
            self::$userA = (int)$db->lastInsertId();

            $db->prepare(
                "INSERT INTO companies (name, owner_name, phone, currency, timezone, invoice_prefix, next_invoice_number, status)
                 VALUES (?, 'Qr Owner', '+992900009999', 'TJS', 'Asia/Dushanbe', 'QR', 1, 'active')"
            )->execute(['QrTest Co ' . $suffix]);
            self::$companyA = (int)$db->lastInsertId();

            $db->prepare(
                "INSERT INTO companies (name, owner_name, phone, currency, timezone, invoice_prefix, next_invoice_number, status)
                 VALUES (?, 'Qr Owner B', '+992900009998', 'TJS', 'Asia/Dushanbe', 'QR', 1, 'active')"
            )->execute(['QrTest Co B ' . $suffix]);
            self::$companyB = (int)$db->lastInsertId();

            $db->prepare("INSERT INTO company_users (company_id, user_id, role, status) VALUES (?, ?, 'owner', 'active')")
                ->execute([self::$companyA, self::$userA]);

            self::$clientA = Client::create(self::$companyA, [
                'name' => 'Qr Client',
                'phone' => '+992900009997',
                'address' => 'Dushanbe',
                'opening_debt' => '0',
            ]);

            self::$productA = Product::create(self::$companyA, [
                'name' => 'Qr Product',
                'sku' => 'QR-T-' . $suffix,
                'barcode' => '',
                'unit' => 'piece',
                'purchase_price' => '5.00',
                'sale_price' => '15.00',
                'stock_quantity' => '50',
            ]);

            self::$ready = self::$companyA > 0 && self::$clientA > 0 && self::$productA > 0;
        } catch (\Throwable) {
            self::$ready = false;
        }
    }

    protected function setUp(): void
    {
        if (!self::$ready) {
            $this->markTestSkipped('Database or invoice_qr_codes table not available');
        }
    }

    private function createPaidInvoice(): array
    {
        $result = Invoice::create(self::$companyA, self::$userA, [
            'client_id' => self::$clientA,
            'invoice_date' => date('Y-m-d'),
            'discount' => 0,
            'paid_amount' => 15,
            'payment_method' => 'cash',
            'status' => 'paid',
            'items' => [[
                'product_id' => self::$productA,
                'quantity' => 1,
                'unit_price' => 15,
                'discount' => 0,
            ]],
        ]);
        $invoice = Invoice::findForCompany($result['id'], self::$companyA);
        $this->assertNotNull($invoice);
        return $invoice;
    }

    public function testSyncCreatesUuidAndPublicUrl(): void
    {
        $invoice = $this->createPaidInvoice();
        $qr = InvoiceQrCode::syncForInvoice((int)$invoice['id'], self::$companyA);
        $this->assertNotNull($qr);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            (string)$qr['public_uuid']
        );
        $this->assertStringContainsString('/invoice/public/', (string)$qr['public_url']);
        $this->assertSame((string)$qr['public_uuid'], basename(parse_url((string)$qr['public_url'], PHP_URL_PATH) ?: ''));
    }

    public function testPublicLookupReturnsSafeFieldsOnly(): void
    {
        $invoice = $this->createPaidInvoice();
        $qr = InvoiceQrCode::syncForInvoice((int)$invoice['id'], self::$companyA);
        $this->assertNotNull($qr);

        $public = InvoiceQrCode::findPublicByUuid((string)$qr['public_uuid']);
        $this->assertNotNull($public);
        $this->assertArrayHasKey('company_name', $public);
        $this->assertArrayHasKey('invoice_number', $public);
        $this->assertArrayNotHasKey('company_id', $public);
        $this->assertArrayNotHasKey('owner_name', $public);

        $rawItems = Invoice::getItems((int)$invoice['id']);
        $this->assertNotEmpty($rawItems);
        $this->assertArrayHasKey('product_id', $rawItems[0]);

        $safe = InvoiceQrCode::mapPublicItems($rawItems);
        $this->assertArrayNotHasKey('product_id', $safe[0]);
        $this->assertArrayNotHasKey('current_stock', $safe[0]);
    }

    public function testInvalidAndRevokedUuidReturnNull(): void
    {
        $this->assertNull(InvoiceQrCode::findPublicByUuid('not-a-uuid'));
        $this->assertNull(InvoiceQrCode::findPublicByUuid('00000000-0000-4000-8000-000000000000'));

        $invoice = $this->createPaidInvoice();
        $qr = InvoiceQrCode::syncForInvoice((int)$invoice['id'], self::$companyA);
        $this->assertNotNull($qr);

        InvoiceQrCode::revokeForInvoice((int)$invoice['id'], self::$companyA);
        $this->assertNull(InvoiceQrCode::findPublicByUuid((string)$qr['public_uuid']));
    }

    public function testCancelledInvoiceQrNotPublic(): void
    {
        $invoice = $this->createPaidInvoice();
        $qr = InvoiceQrCode::syncForInvoice((int)$invoice['id'], self::$companyA);
        $uuid = (string)$qr['public_uuid'];

        Invoice::cancel((int)$invoice['id'], self::$companyA, self::$userA);
        InvoiceQrCode::revokeForInvoice((int)$invoice['id'], self::$companyA);

        $this->assertNull(InvoiceQrCode::findPublicByUuid($uuid));
    }

    public function testDraftInvoiceHasNoActiveQr(): void
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
                'unit_price' => 15,
                'discount' => 0,
            ]],
        ]);
        $qr = InvoiceQrCode::syncForInvoice((int)$result['id'], self::$companyA);
        $this->assertNull($qr);
    }

    public function testCompanyIsolationOnPublicLookup(): void
    {
        $invoice = $this->createPaidInvoice();
        $qr = InvoiceQrCode::syncForInvoice((int)$invoice['id'], self::$companyA);
        $this->assertNotNull($qr);

        $other = InvoiceQrCode::findPublicByUuid((string)$qr['public_uuid']);
        $this->assertNotNull($other);
        $this->assertSame((int)$invoice['id'], (int)$other['invoice_id']);

        $db = Database::getInstance();
        $db->prepare('UPDATE companies SET status = ? WHERE id = ?')->execute(['suspended', self::$companyA]);
        $this->assertNull(InvoiceQrCode::findPublicByUuid((string)$qr['public_uuid']));
        $db->prepare('UPDATE companies SET status = ? WHERE id = ?')->execute(['active', self::$companyA]);
    }
}

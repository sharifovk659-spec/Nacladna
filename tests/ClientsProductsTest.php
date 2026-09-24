<?php

namespace Tests;

use App\Core\Database;
use App\Models\Client;
use App\Models\Product;
use PHPUnit\Framework\TestCase;

class ClientsProductsTest extends TestCase
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

            $suffix = (string)random_int(100000, 999999);
            $db->prepare(
                "INSERT INTO companies (name, owner_name, phone, currency, timezone, invoice_prefix, next_invoice_number, status)
                 VALUES (?, 'Owner A', '+992900000010', 'TJS', 'Asia/Dushanbe', 'NK', 1, 'active')"
            )->execute(['ClientsTest A ' . $suffix]);
            self::$companyA = (int)$db->lastInsertId();

            $db->prepare(
                "INSERT INTO companies (name, owner_name, phone, currency, timezone, invoice_prefix, next_invoice_number, status)
                 VALUES (?, 'Owner B', '+992900000011', 'TJS', 'Asia/Dushanbe', 'NK', 1, 'active')"
            )->execute(['ClientsTest B ' . $suffix]);
            self::$companyB = (int)$db->lastInsertId();

            self::$ready = self::$companyA > 0 && self::$companyB > 0;
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

    public function testClientCreateSearchDebtAndStatus(): void
    {
        $id = Client::create(self::$companyA, [
            'name' => 'Али Рахимов',
            'phone' => '+992901112233',
            'address' => 'Душанбе',
            'opening_debt' => '12.50',
        ]);
        $this->assertGreaterThan(0, $id);

        $found = Client::findForCompany($id, self::$companyA);
        $this->assertNotNull($found);
        $this->assertSame('12.50', Client::decimal($found['opening_debt'], 2));
        $this->assertSame('12.50', Client::decimal($found['current_debt'], 2));

        $search = Client::search(self::$companyA, '901112233');
        $this->assertNotEmpty($search);
        $this->assertSame($id, (int)$search[0]['id']);

        $byName = Client::all(self::$companyA, 'Рахимов', 1, 20);
        $this->assertGreaterThanOrEqual(1, $byName['total']);

        Client::update($id, self::$companyA, [
            'name' => 'Али Рахимов',
            'phone' => '+992901112233',
            'address' => 'Душанбе',
            'opening_debt' => '20.00',
            'status' => 'inactive',
        ]);
        $updated = Client::findForCompany($id, self::$companyA);
        $this->assertSame('inactive', $updated['status']);
        $this->assertSame('20.00', Client::decimal($updated['opening_debt'], 2));
    }

    public function testClientIdorBlockedAcrossCompanies(): void
    {
        $id = Client::create(self::$companyA, [
            'name' => 'Secret Client',
            'phone' => '+992909998877',
            'address' => '',
            'opening_debt' => '5.00',
        ]);

        $this->assertNull(Client::findForCompany($id, self::$companyB));
        $this->assertFalse(Client::update($id, self::$companyB, [
            'name' => 'Hacked',
            'phone' => '',
            'address' => '',
            'opening_debt' => '0',
            'status' => 'active',
        ]));

        $still = Client::findForCompany($id, self::$companyA);
        $this->assertSame('Secret Client', $still['name']);
    }

    public function testProductCreateUnitsDecimalsLowStockAndSearch(): void
    {
        $this->assertSame('шт', Product::normalizeUnit('piece'));
        $this->assertSame('кг', Product::normalizeUnit('kg'));
        $this->assertSame('м', Product::normalizeUnit('meter'));
        $this->assertSame('кор', Product::normalizeUnit('box'));

        $id = Product::create(self::$companyA, [
            'name' => 'Цемент',
            'sku' => 'CEM-50',
            'barcode' => '4601234567890',
            'unit' => 'kg',
            'purchase_price' => '40.125',
            'sale_price' => '55.50',
            'stock_quantity' => '3.5',
        ]);
        $this->assertGreaterThan(0, $id);

        $product = Product::findForCompany($id, self::$companyA);
        $this->assertSame('кг', $product['unit']);
        $this->assertSame('55.50', Product::decimal($product['sale_price'], 2));
        $this->assertSame('3.500', Product::decimal($product['stock_quantity'], 3));
        $this->assertTrue(Product::isLowStock($product['stock_quantity']));
        $this->assertFalse(Product::isOutOfStock($product['stock_quantity']));

        $search = Product::search(self::$companyA, 'CEM-50');
        $this->assertNotEmpty($search);
        $this->assertSame($id, (int)$search[0]['id']);

        Product::update($id, self::$companyA, [
            'name' => 'Цемент',
            'sku' => 'CEM-50',
            'barcode' => '4601234567890',
            'unit' => 'box',
            'purchase_price' => '40.13',
            'sale_price' => '60.00',
            'stock_quantity' => '0',
            'status' => 'inactive',
        ]);
        $updated = Product::findForCompany($id, self::$companyA);
        $this->assertSame('кор', $updated['unit']);
        $this->assertSame('inactive', $updated['status']);
        $this->assertTrue(Product::isOutOfStock($updated['stock_quantity']));
    }

    public function testProductIdorBlockedAcrossCompanies(): void
    {
        $id = Product::create(self::$companyA, [
            'name' => 'Private Product',
            'sku' => 'PRIV-1',
            'barcode' => '',
            'unit' => 'piece',
            'purchase_price' => '',
            'sale_price' => '10.00',
            'stock_quantity' => '1',
        ]);

        $this->assertNull(Product::findForCompany($id, self::$companyB));
        $this->assertFalse(Product::update($id, self::$companyB, [
            'name' => 'Hacked Product',
            'sku' => 'X',
            'barcode' => '',
            'unit' => 'piece',
            'purchase_price' => '',
            'sale_price' => '1',
            'stock_quantity' => '0',
            'status' => 'active',
        ]));

        $still = Product::findForCompany($id, self::$companyA);
        $this->assertSame('Private Product', $still['name']);
    }

    public function testPaginationAndCompanyIsolationInLists(): void
    {
        for ($i = 0; $i < 3; $i++) {
            Client::create(self::$companyA, [
                'name' => "Page Client A{$i}",
                'phone' => '',
                'address' => '',
                'opening_debt' => '0',
            ]);
            Product::create(self::$companyB, [
                'name' => "Page Product B{$i}",
                'sku' => '',
                'barcode' => '',
                'unit' => 'piece',
                'purchase_price' => '',
                'sale_price' => '1.00',
                'stock_quantity' => '1',
            ]);
        }

        $clientsA = Client::all(self::$companyA, 'Page Client A', 1, 2);
        $this->assertSame(2, count($clientsA['items']));
        $this->assertGreaterThanOrEqual(3, $clientsA['total']);
        $this->assertGreaterThanOrEqual(2, $clientsA['last_page']);

        $productsA = Product::all(self::$companyA, 'Page Product B', 1, 20);
        $this->assertSame(0, $productsA['total']);

        $productsB = Product::all(self::$companyB, 'Page Product B', 1, 20);
        $this->assertGreaterThanOrEqual(3, $productsB['total']);
    }
}

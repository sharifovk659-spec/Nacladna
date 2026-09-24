<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2));
$dotenv->safeLoad();

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Asia/Dushanbe');

$cfg = require dirname(__DIR__, 2) . '/config/database.php';
$dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset={$cfg['charset']}";

$pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

echo "Seeding demo data...\n";

// Demo user
$pdo->prepare("INSERT IGNORE INTO users (telegram_id, telegram_username, first_name, last_name, status)
               VALUES (?, ?, ?, ?, 'active')")
    ->execute([100000001, 'demo_user', 'Демо', 'Пользователь']);
$userId = $pdo->query("SELECT id FROM users WHERE telegram_id=100000001")->fetchColumn();

// Demo company
$pdo->prepare("INSERT IGNORE INTO companies (id, name, owner_name, phone, address, currency, timezone, invoice_prefix, next_invoice_number, status)
               VALUES (1, ?, ?, ?, ?, 'TJS', 'Asia/Dushanbe', 'ИНВ', 1, 'active')")
    ->execute(['ООО «Демо Компания»', 'Алишер Рахмонов', '+992900000001', 'Душанбе, ул. Рудаки 1']);

// Company user link
$pdo->prepare("INSERT IGNORE INTO company_users (company_id, user_id, role, status) VALUES (1, ?, 'owner', 'active')")
    ->execute([$userId]);

// Trial subscription (3 days)
$now   = date('Y-m-d H:i:s');
$trial = date('Y-m-d H:i:s', strtotime('+3 days'));
$pdo->prepare("INSERT IGNORE INTO subscriptions (company_id, plan, trial_start, trial_end, starts_at, ends_at, status)
               VALUES (1, 'trial', ?, ?, ?, ?, 'trial')")
    ->execute([$now, $trial, $now, $trial]);

// Demo client
$pdo->prepare("INSERT IGNORE INTO clients (id, company_id, name, phone, address, opening_debt, status)
               VALUES (1, 1, ?, ?, ?, 0.00, 'active')")
    ->execute(['Рустам Назаров', '+992917000001', 'Душанбе, ул. Исмоили Сомони 5']);

// Demo products
$products = [
    ['Мука пшеничная 50кг', 'MUK-001', null,       'кг',  120.00, 150.00, 100.000],
    ['Масло подсолнечное 5л', 'MAS-001', null,      'шт',  55.00,  70.00,  50.000],
    ['Сахар-песок 50кг', 'SAH-001', null,           'кг',  7.50,   9.00,   200.000],
    ['Рис длиннозёрный 25кг', 'RIS-001', null,      'кг',  14.00,  18.00,  150.000],
];
$ins = $pdo->prepare("INSERT IGNORE INTO products (company_id, name, sku, barcode, unit, purchase_price, sale_price, stock_quantity, status)
                      VALUES (1, ?, ?, ?, ?, ?, ?, ?, 'active')");
foreach ($products as $p) {
    $ins->execute($p);
}

echo "Demo seed complete!\n";
echo "  User ID:    {$userId}\n";
echo "  Company ID: 1\n";

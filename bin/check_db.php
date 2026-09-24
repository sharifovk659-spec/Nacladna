<?php
require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$db = App\Core\Database::getInstance();
$tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo 'Tables: ' . implode(', ', $tables) . PHP_EOL;
echo 'Database: ' . ($_ENV['DB_DATABASE'] ?? '') . PHP_EOL;
echo 'Users: '     . $db->query('SELECT COUNT(*) FROM users')->fetchColumn()     . PHP_EOL;
echo 'Companies: ' . $db->query('SELECT COUNT(*) FROM companies')->fetchColumn() . PHP_EOL;
echo 'Products: '  . $db->query('SELECT COUNT(*) FROM products')->fetchColumn()  . PHP_EOL;
echo 'Clients: '   . $db->query('SELECT COUNT(*) FROM clients')->fetchColumn()   . PHP_EOL;
echo 'Subscriptions: ' . $db->query('SELECT COUNT(*) FROM subscriptions')->fetchColumn() . PHP_EOL;

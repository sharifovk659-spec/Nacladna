<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Asia/Dushanbe');

$cfg = require dirname(__DIR__) . '/config/database.php';
$dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset={$cfg['charset']}";

try {
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    // Create migrations table first
    $pdo->exec("CREATE TABLE IF NOT EXISTS `migrations` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `migration` VARCHAR(255) NOT NULL,
        `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_migration` (`migration`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $migDir = dirname(__DIR__) . '/database/migrations';
    $files  = glob($migDir . '/*.sql');
    sort($files);

    $executed = $pdo->query("SELECT migration FROM migrations")->fetchAll(PDO::FETCH_COLUMN);
    $executed = array_flip($executed);

    foreach ($files as $file) {
        $name = basename($file);
        if (isset($executed[$name])) {
            echo "  SKIP  {$name}\n";
            continue;
        }
        $sql = file_get_contents($file);
        $pdo->exec($sql);
        $st = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
        $st->execute([$name]);
        echo "  OK    {$name}\n";
    }

    echo "\nMigrations complete.\n";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

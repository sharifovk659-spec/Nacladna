#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

if (class_exists(\Dotenv\Dotenv::class) && is_file($root . '/.env')) {
    \Dotenv\Dotenv::createImmutable($root)->safeLoad();
}

use App\Core\Database;
use App\Models\Company;

$db = Database::getInstance();
$st = $db->query('SELECT id, logo_path FROM companies WHERE logo_path IS NOT NULL AND logo_path <> ""');
$rows = $st->fetchAll() ?: [];

$migrated = 0;
$ok = 0;
$missing = 0;

foreach ($rows as $row) {
    $id = (int)$row['id'];
    $path = (string)$row['logo_path'];
    if (Company::resolveLogoFile($path) === null) {
        echo "MISS  company {$id} — {$path}\n";
        $missing++;
        continue;
    }
    if (str_starts_with($path, 'logos/')) {
        $ok++;
        continue;
    }
    $new = Company::migrateLogoToStorage($path, $id);
    if ($new !== null && str_starts_with($new, 'logos/')) {
        echo "OK    company {$id} migrated → {$new}\n";
        $migrated++;
    } else {
        echo "KEEP  company {$id} — {$path}\n";
        $ok++;
    }
}

echo "\nSummary: migrated={$migrated} ok={$ok} missing={$missing}\n";
exit($missing > 0 ? 1 : 0);

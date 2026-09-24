#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', $root);
}
require $root . '/vendor/autoload.php';
\Dotenv\Dotenv::createImmutable($root)->safeLoad();

$db = App\Core\Database::getInstance();
foreach ($db->query('SELECT id, name, logo_path FROM companies ORDER BY id') as $row) {
    $resolved = App\Models\Company::resolveLogoFile($row['logo_path'] ?? null);
    echo (int)$row['id'] . "\t" . ($row['logo_path'] ?? 'NULL') . "\t" . ($resolved ? 'OK' : 'MISSING') . "\n";
}

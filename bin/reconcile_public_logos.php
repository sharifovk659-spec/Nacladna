#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', $root);
}
require $root . '/vendor/autoload.php';

if (class_exists(\Dotenv\Dotenv::class) && is_file($root . '/.env')) {
    \Dotenv\Dotenv::createImmutable($root)->safeLoad();
}

use App\Core\Database;
use App\Models\Company;

$publicRoot = trim((string)($_ENV['PUBLIC_WEB_ROOT'] ?? ''));
if ($publicRoot === '') {
    echo "SKIP  PUBLIC_WEB_ROOT not set\n";
    exit(0);
}

$glob = glob(rtrim($publicRoot, '/\\') . '/uploads/logos/*/*.*');
if (!$glob) {
    $glob = glob(rtrim($publicRoot, '/\\') . '/uploads/logos/*.*') ?: [];
}

if ($glob === []) {
    echo "SKIP  No logo files under public web root\n";
    exit(0);
}

$db = Database::getInstance();
$fixed = 0;

foreach ($glob as $fullPath) {
    if (!is_file($fullPath)) {
        continue;
    }
    $basename = basename($fullPath);
    $parent = basename(dirname($fullPath));
    if (ctype_digit($parent)) {
        $rel = 'logos/' . $parent . '/' . $basename;
        $companyId = (int)$parent;
    } else {
        $rel = 'uploads/logos/' . $basename;
        $st = $db->prepare(
            'SELECT id FROM companies WHERE logo_path = ? OR logo_path LIKE ? ORDER BY id DESC LIMIT 1'
        );
        $st->execute([$rel, '%' . $basename]);
        $row = $st->fetch();
        $companyId = $row ? (int)$row['id'] : 0;
    }

    if ($companyId <= 0) {
        $st = $db->query(
            'SELECT id FROM companies WHERE (logo_path IS NULL OR logo_path = "") AND status = "active" ORDER BY id DESC LIMIT 1'
        );
        $fallback = $st->fetch();
        if ($fallback && count($glob) === 1) {
            $companyId = (int)$fallback['id'];
            $rel = 'uploads/logos/' . $basename;
            echo "LINK  orphan {$basename} → company {$companyId}\n";
        } else {
            echo "SKIP  orphan file {$fullPath}\n";
            continue;
        }
    }

    $row = Company::findForCompany($companyId, $companyId);
    if (!$row) {
        echo "SKIP  unknown company {$companyId}\n";
        continue;
    }

    if (empty($row['logo_path']) || Company::resolveLogoFile((string)$row['logo_path']) === null) {
        $db->prepare('UPDATE companies SET logo_path = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$rel, $companyId]);
        echo "OK    company {$companyId} logo_path → {$rel}\n";
        $fixed++;
    }

    if (!str_starts_with((string)$row['logo_path'], 'logos/')) {
        Company::migrateLogoToStorage((string)($row['logo_path'] ?: $rel), $companyId);
        echo "OK    company {$companyId} migrated to storage\n";
        $fixed++;
    }
}

echo "\nReconcile complete (actions={$fixed})\n";

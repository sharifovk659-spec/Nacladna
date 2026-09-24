#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

if (class_exists(\Dotenv\Dotenv::class) && is_file($root . '/.env')) {
    \Dotenv\Dotenv::createImmutable($root)->safeLoad();
}

$base = rtrim((string)($_ENV['APP_URL'] ?? ''), '/');
if ($base === '') {
    fwrite(STDERR, "APP_URL not set\n");
    exit(1);
}

$fail = 0;
$pass = function (string $label, bool $ok): void {
    global $fail;
    echo ($ok ? 'PASS' : 'FAIL') . "  {$label}\n";
    if (!$ok) {
        $fail++;
    }
};

$ch = curl_init($base . '/media/company-logo/0');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_NOBODY => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_FOLLOWLOCATION => true,
]);
curl_exec($ch);
$codeInvalid = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$pass('Invalid company logo returns 404', $codeInvalid === 404);

use App\Core\Database;
use App\Models\Company;

try {
    $db = Database::getInstance();
    $st = $db->query(
        'SELECT id, logo_path FROM companies WHERE logo_path IS NOT NULL AND logo_path <> "" ORDER BY id ASC LIMIT 1'
    );
    $row = $st->fetch();
    if (!$row) {
        echo "SKIP  No company with logo in DB\n";
    } else {
        $id = (int)$row['id'];
        $url = Company::logoPublicUrl((string)$row['logo_path'], $id);
        $pass('logoPublicUrl builds HTTPS media URL', $url !== null && str_contains($url, '/media/company-logo/' . $id));

        if ($url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            $raw = (string)curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $ctype = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
            $pass('Media logo HTTP 200', $code === 200);
            $pass('Media logo content-type image/*', str_starts_with($ctype, 'image/'));
            $pass('Media response has body', strlen($raw) > 100);
        }
    }
} catch (\Throwable $e) {
    $pass('DB logo smoke', false);
    fwrite(STDERR, $e->getMessage() . "\n");
}

echo $fail === 0 ? "\nLogo smoke PASS\n" : "\nLogo smoke FAIL\n";
exit($fail === 0 ? 0 : 1);

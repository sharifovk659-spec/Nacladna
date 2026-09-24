#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', $root);
}
require $root . '/vendor/autoload.php';
\Dotenv\Dotenv::createImmutable($root)->safeLoad();

$base = rtrim((string)($_ENV['APP_URL'] ?? ''), '/');
if ($base === '') {
    fwrite(STDERR, "APP_URL not set\n");
    exit(1);
}

$fail = 0;
$pass = function (string $label, bool $ok) use (&$fail): void {
    echo ($ok ? 'PASS' : 'FAIL') . "  {$label}\n";
    if (!$ok) {
        $fail++;
    }
};

$ch = curl_init($base . '/employees');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 15]);
curl_exec($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$pass('GET /employees auth gate (302)', $code === 302);

$ch = curl_init($base . '/join/invalid-token-test');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 15]);
curl_exec($ch);
$joinCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$pass('GET /join invalid token (404)', $joinCode === 404);

echo $fail === 0 ? "\nRoles smoke PASS\n" : "\nRoles smoke FAIL\n";
exit($fail === 0 ? 0 : 1);

<?php

declare(strict_types=1);

/**
 * Production / local smoke for Invoice module readiness.
 * Usage: php bin/smoke_invoice.php [baseUrl]
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$baseUrl = rtrim($argv[1] ?? ($_ENV['APP_URL'] ?? 'http://127.0.0.1:8000'), '/');
$fail = 0;

function smokeGet(string $url): array
{
    $ctx = stream_context_create([
        'http' => [
            'ignore_errors' => true,
            'timeout' => 12,
            'follow_location' => 0,
            'header' => "Accept: application/json\r\nUser-Agent: NakladnaSmoke/1.0\r\n",
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $code = (int)$m[1];
    }
    return [$code, (string)$body];
}

function assertSmoke(string $label, bool $ok, string $detail = ''): void
{
    global $fail;
    if ($ok) {
        echo "PASS  {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    } else {
        $fail++;
        echo "FAIL  {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

echo "Invoice smoke against: {$baseUrl}\n";

[$code, $body] = smokeGet($baseUrl . '/health');
$json = json_decode($body, true);
assertSmoke('Health HTTP 200', $code === 200, "code={$code}");
assertSmoke('Health status ok', is_array($json) && ($json['status'] ?? '') === 'ok');
assertSmoke('Health DB connected', is_array($json) && ($json['database'] ?? '') === 'connected');

foreach (['/invoices', '/invoices/create', '/api/invoices'] as $path) {
    [$c] = smokeGet($baseUrl . $path);
    assertSmoke("Auth gate {$path}", in_array($c, [302, 303, 401], true), "code={$c}");
}

// Model-level checks if DB available locally
try {
    $db = \App\Core\Database::getInstance();
    $db->query('SELECT 1');
    $cols = $db->query("SHOW COLUMNS FROM invoices LIKE 'deleted_at'")->fetch();
    assertSmoke('Migration soft-delete column', !empty($cols));
    $hasStatus = $db->query("SHOW COLUMNS FROM invoices LIKE 'status'")->fetch();
    assertSmoke('Invoices status column', !empty($hasStatus));
} catch (\Throwable $e) {
    echo "SKIP  Local DB model checks — " . $e->getMessage() . "\n";
}

echo $fail === 0 ? "\nInvoice smoke PASS\n" : "\nInvoice smoke FAIL ({$fail})\n";
exit($fail === 0 ? 0 : 1);

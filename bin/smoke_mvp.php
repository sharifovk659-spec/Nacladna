<?php
/**
 * End-to-end MVP HTTP smoke (production-safe, read-only checks).
 * Usage: php bin/smoke_mvp.php [baseUrl]
 */
require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$base = rtrim($argv[1] ?? ($_ENV['APP_URL'] ?? 'http://127.0.0.1:8000'), '/');
$fail = 0;

function mvpGet(string $url, array $opts = []): array
{
    $ctx = stream_context_create([
        'http' => array_merge([
            'ignore_errors' => true,
            'timeout' => 15,
            'follow_location' => (int)($opts['follow'] ?? 0),
            'header' => ($opts['accept'] ?? 'text/html') === 'json'
                ? "Accept: application/json\r\nUser-Agent: NakladnaMvpSmoke/1.0\r\n"
                : "Accept: text/html\r\nUser-Agent: NakladnaMvpSmoke/1.0\r\n",
        ], $opts['http'] ?? []),
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $code = (int)$m[1];
    }
    return [$code, (string)$body];
}

function ok(string $label, bool $cond, string $detail = ''): void
{
    global $fail;
    if ($cond) {
        echo "PASS  {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    } else {
        $fail++;
        echo "FAIL  {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

echo "MVP smoke: {$base}\n";

[$code, $body] = mvpGet($base . '/health', ['accept' => 'json']);
$health = json_decode($body, true);
ok('Health 200', $code === 200, "code={$code}");
ok('Health ok', is_array($health) && ($health['status'] ?? '') === 'ok');
ok('DB connected', is_array($health) && ($health['database'] ?? '') === 'connected');

[$miniCode, $miniBody] = mvpGet($base . '/mini-app');
ok('Mini App entry 200', $miniCode === 200, "code={$miniCode}");
ok('Mini App markup', str_contains($miniBody, 'Nakladna Cloud') && str_contains($miniBody, 'Telegram'));

foreach ([
    '/dashboard' => [302, 303],
    '/onboarding' => [302, 303],
    '/clients' => [302, 303],
    '/products' => [302, 303],
    '/invoices' => [302, 303],
    '/debts' => [302, 303],
    '/profile' => [302, 303],
] as $path => $expect) {
    [$c] = mvpGet($base . $path);
    ok("Auth gate {$path}", in_array($c, $expect, true), "code={$c}");
}

[$apiCode] = mvpGet($base . '/api/dashboard/summary', ['accept' => 'json']);
ok('API dashboard protected', in_array($apiCode, [401, 302, 303], true), "code={$apiCode}");

[$invApi] = mvpGet($base . '/api/invoices', ['accept' => 'json']);
ok('API invoices protected', in_array($invApi, [401, 302, 303], true), "code={$invApi}");

echo $fail === 0 ? "\nMVP smoke PASS\n" : "\nMVP smoke FAIL ({$fail})\n";
exit($fail === 0 ? 0 : 1);

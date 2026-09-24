<?php
require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$base = rtrim($_ENV['APP_URL'] ?? '', '/');
if ($base === '') {
    fwrite(STDERR, "APP_URL not set\n");
    exit(2);
}

$fail = 0;
function getUrl(string $url): int {
    $ctx = stream_context_create(['http' => ['timeout' => 15, 'ignore_errors' => true, 'follow_location' => 0]]);
    @file_get_contents($url, false, $ctx);
    return isset($http_response_header[0]) ? (int)explode(' ', $http_response_header[0])[1] : 0;
}

$code = getUrl($base . '/subscription');
echo ($code === 302 ? 'PASS' : 'FAIL') . " GET /subscription auth gate ($code)\n";
if ($code !== 302) $fail++;

try {
    $db = \App\Core\Database::getInstance();
    $db->query("SELECT 1 FROM subscription_history LIMIT 1");
    echo "PASS subscription_history table\n";
} catch (Throwable $e) {
    echo "FAIL subscription_history table — {$e->getMessage()}\n";
    $fail++;
}

exit($fail === 0 ? 0 : 1);

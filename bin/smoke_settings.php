<?php
require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$base = rtrim($_ENV['APP_URL'] ?? '', '/');
if ($base === '') {
    fwrite(STDERR, "APP_URL not set\n");
    exit(2);
}

$fail = 0;
function code(string $url): int {
    $ctx = stream_context_create(['http' => ['timeout' => 12, 'ignore_errors' => true, 'follow_location' => 0]]);
    @file_get_contents($url, false, $ctx);
    return isset($http_response_header[0]) ? (int)explode(' ', $http_response_header[0])[1] : 0;
}

$c1 = code($base . '/settings');
echo ($c1 === 302 ? 'PASS' : 'FAIL') . " GET /settings auth gate ($c1)\n";
if ($c1 !== 302) $fail++;

try {
    $db = \App\Core\Database::getInstance();
    $db->query("SELECT language FROM companies LIMIT 1");
    echo "PASS companies.language column\n";
} catch (Throwable $e) {
    echo "FAIL language column — {$e->getMessage()}\n";
    $fail++;
}

exit($fail === 0 ? 0 : 1);

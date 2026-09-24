<?php

declare(strict_types=1);

define('START_TIME', microtime(true));

// Allow Hostinger document root (PUBLIC_DIR) to bootstrap app from APP_DIR
$appRootOverride = getenv('APP_ROOT') ?: null;
if (!$appRootOverride && is_file(__DIR__ . '/.app_root')) {
    $appRootOverride = trim((string)file_get_contents(__DIR__ . '/.app_root'));
}
define('ROOT_DIR', ($appRootOverride !== null && $appRootOverride !== '')
    ? rtrim($appRootOverride, "/\\")
    : dirname(__DIR__));

// Autoloader
require ROOT_DIR . '/vendor/autoload.php';

// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(ROOT_DIR);
$dotenv->safeLoad();

// Timezone
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Asia/Dushanbe');

// Error handling
$isDebug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
if ($isDebug) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

set_exception_handler(function (\Throwable $e) use ($isDebug) {
    \App\Core\Logger::error($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    http_response_code(500);
    if ($isDebug) {
        echo '<pre>' . htmlspecialchars($e->getMessage()) . "\n" . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Internal server error']);
    }
    exit;
});

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Session
session_name($_ENV['SESSION_NAME'] ?? 'nakladna_session');
$isProduction = ($_ENV['APP_ENV'] ?? 'production') === 'production';
$secureCookie = array_key_exists('SESSION_SECURE', $_ENV)
    ? filter_var($_ENV['SESSION_SECURE'], FILTER_VALIDATE_BOOLEAN)
    : $isProduction;
$httpOnly = array_key_exists('SESSION_HTTP_ONLY', $_ENV)
    ? filter_var($_ENV['SESSION_HTTP_ONLY'], FILTER_VALIDATE_BOOLEAN)
    : true;
$sameSite = $_ENV['SESSION_SAME_SITE'] ?? 'Lax';
session_set_cookie_params([
    'lifetime' => (int)($_ENV['SESSION_LIFETIME'] ?? 86400),
    'path'     => '/',
    'secure'   => $secureCookie,
    'httponly' => $httpOnly,
    'samesite' => $sameSite,
]);
session_start();

// Router
$router = new \App\Core\Router();
require ROOT_DIR . '/routes/web.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = $_SERVER['REQUEST_URI']    ?? '/';

$router->dispatch($method, $uri);

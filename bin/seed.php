<?php
/**
 * Runs demo seed script (dev/staging only).
 */
require dirname(__DIR__) . '/vendor/autoload.php';

$env = $_ENV['APP_ENV'] ?? 'production';
if ($env === 'production') {
    fwrite(STDERR, "Refusing to seed in production. Set APP_ENV=local or run database/seeds/demo_seed.php manually.\n");
    exit(1);
}

$seed = dirname(__DIR__) . '/database/seeds/demo_seed.php';
if (!is_file($seed)) {
    fwrite(STDERR, "Seed file not found: {$seed}\n");
    exit(1);
}

require $seed;

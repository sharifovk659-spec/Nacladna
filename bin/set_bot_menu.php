<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/') . '/mini-app';

if ($token === '' || !str_starts_with($appUrl, 'https://')) {
    echo "BOT_MENU_SKIP\n";
    exit(0);
}

$payload = json_encode([
    'menu_button' => [
        'type' => 'web_app',
        'text' => 'Открыть приложение',
        'web_app' => ['url' => $appUrl],
    ],
], JSON_UNESCAPED_UNICODE);

$ch = curl_init('https://api.telegram.org/bot' . $token . '/setChatMenuButton');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => $payload,
]);
$resp = curl_exec($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode((string)$resp, true);
if (!empty($data['ok'])) {
    echo "BOT_MENU_OK url={$appUrl}\n";
    exit(0);
}

echo "BOT_MENU_FAIL http={$code}\n";
exit(1);

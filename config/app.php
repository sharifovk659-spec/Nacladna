<?php

return [
    'env'      => $_ENV['APP_ENV'] ?? 'production',
    'debug'    => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'url'      => $_ENV['APP_URL'] ?? 'https://nakladna.inovaauto.com',
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Asia/Dushanbe',
    'currency' => 'TJS',
];

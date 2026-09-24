<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;

class HealthController
{
    public function index(): void
    {
        $dbOk = false;
        try {
            $db = Database::getInstance();
            $db->query('SELECT 1');
            $dbOk = true;
        } catch (\Throwable $e) {
            $dbOk = false;
        }

        $status = $dbOk ? 'ok' : 'degraded';
        $code   = $dbOk ? 200 : 503;

        Response::json([
            'status'      => $status,
            'database'    => $dbOk ? 'connected' : 'error',
            'environment' => ($_ENV['APP_ENV'] ?? 'production'),
        ], $code);
    }
}

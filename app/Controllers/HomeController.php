<?php

namespace App\Controllers;

use App\Core\Database;

class HomeController
{
    public function index(): void
    {
        $dbConnected = false;

        try {
            $db = Database::getInstance();
            $db->query('SELECT 1');
            $dbConnected = true;
        } catch (\Throwable) {
            $dbConnected = false;
        }

        $pageTitle = 'Nakladna Cloud';
        $hideNav = true;
        $hideHeader = true;
        $hideBottomNav = true;
        $version = '1.0.0-foundation';
        $environment = $_ENV['APP_ENV'] ?? 'production';
        $healthStatus = $dbConnected ? 'ok' : 'degraded';

        ob_start();
        require ROOT_DIR . '/views/home/index.php';
        $content = ob_get_clean();
        require ROOT_DIR . '/views/layouts/app.php';
    }
}

<?php

namespace App\Controllers\Admin;

use App\Core\Logger;
use App\Core\Response;
use App\Helpers\Csrf;
use App\Services\ActivityLogger;
use App\Services\RateLimiter;

class AdminAuthController
{
    public function loginPage(): void
    {
        if (!empty($_SESSION['admin_logged_in'])) {
            Response::redirect('/admin/dashboard');
        }
        $error     = $_SESSION['admin_error'] ?? null;
        $pageTitle = 'Панель администратора';
        unset($_SESSION['admin_error']);
        ob_start();
        require ROOT_DIR . '/views/admin/login.php';
        $content = ob_get_clean();
        echo $content;
    }

    public function login(): void
    {
        Csrf::verifyRequest();

        $ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $limiter = new RateLimiter();
        if (!$limiter->attempt("admin_login:{$ip}", 5, 120)) {
            $_SESSION['admin_error'] = 'Слишком много попыток. Подождите 2 минуты.';
            Response::redirect('/admin');
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $expectedUser = $_ENV['ADMIN_USERNAME'] ?? 'admin';
        $expectedHash = $_ENV['ADMIN_PASSWORD_HASH'] ?? '';

        if ($username !== $expectedUser || !password_verify($password, $expectedHash)) {
            Logger::warn('Admin login failed', ['username' => $username, 'ip' => $ip]);
            ActivityLogger::log('admin_login_failed', null, null, null, null, ['username' => $username]);
            $_SESSION['admin_error'] = 'Неверные учётные данные.';
            Response::redirect('/admin');
        }

        $limiter->clear("admin_login:{$ip}");
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username']  = $username;
        Logger::info('Admin login success', ['username' => $username, 'ip' => $ip]);
        ActivityLogger::log('admin_login');
        Response::redirect('/admin/dashboard');
    }

    public function logout(): void
    {
        unset($_SESSION['admin_logged_in'], $_SESSION['admin_username']);
        Response::redirect('/admin');
    }

    public static function requireAdmin(): void
    {
        if (empty($_SESSION['admin_logged_in'])) {
            Response::redirect('/admin');
        }
    }
}

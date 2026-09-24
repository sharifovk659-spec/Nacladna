<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Response;
use App\Helpers\Csrf;
use App\Models\User;
use App\Models\Company;
use App\Services\ActivityLogger;
use App\Services\RateLimiter;
use App\Middleware\SubscriptionMiddleware;
use App\Services\TelegramAuth;

class AuthController
{
    public function miniApp(): void
    {
        $pageTitle = 'Nakladna Cloud';
        $hideNav = true;
        $hideHeader = true;
        $hideBottomNav = true;
        $bodyClass = 'mini-app-shell';
        $botUsername = ltrim(trim($_ENV['TELEGRAM_BOT_USERNAME'] ?? ''), '@');
        ob_start();
        require ROOT_DIR . '/views/auth/mini-app.php';
        $content = ob_get_clean();
        require ROOT_DIR . '/views/layouts/app.php';
    }

    public function loginPage(): void
    {
        // Browser fallback redirects to Mini App entry
        Response::redirect('/mini-app');
    }

    public function telegramLogin(): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $limiter = new RateLimiter();

        if (!$limiter->attempt("tg_login:{$ip}", 10, 60)) {
            Response::json(['error' => 'Слишком много попыток. Подождите минуту.'], 429);
        }

        $initData = $_POST['initData'] ?? '';
        if ($initData === '' && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            $json = json_decode(file_get_contents('php://input') ?: '{}', true);
            $initData = (string)($json['initData'] ?? '');
        }

        $botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
        if ($botToken === '') {
            Logger::error('Telegram bot token missing');
            Response::json(['error' => 'Сервис авторизации недоступен'], 503);
        }

        try {
            $auth = new TelegramAuth($botToken);
            $tgUser = $auth->verify($initData);
        } catch (\Throwable $e) {
            Logger::warn('Telegram auth failed', ['error' => $e->getMessage(), 'ip' => $ip]);
            ActivityLogger::log('auth_failed', null, null, null, null, ['reason' => $e->getMessage()]);
            Response::json(['error' => 'Ошибка авторизации Telegram', 'code' => 'auth_failed'], 401);
        }

        $user = User::createOrUpdate($tgUser);

        $inviteToken = trim((string)($_POST['invite_token'] ?? $_SESSION['employee_invite_token'] ?? ''));
        if ($inviteToken !== '') {
            try {
                $linked = \App\Models\EmployeeInvite::consume($inviteToken, (int)$user['id']);
                unset($_SESSION['employee_invite_token']);
                $company = User::getCompany((int)$user['id']);
                $limiter->clear("tg_login:{$ip}");
                $this->startSession($user, $company);
                ActivityLogger::log('employee_invite_accepted', (int)$linked['company_id'], (int)$user['id']);
                Response::json([
                    'success' => true,
                    'onboarding_required' => false,
                    'redirect' => '/dashboard',
                    'csrf_token' => Csrf::token(),
                    'user' => [
                        'id' => (int)$user['id'],
                        'first_name' => $user['first_name'],
                        'last_name' => $user['last_name'],
                    ],
                ]);
            } catch (\Throwable $e) {
                Logger::warn('Invite consume failed', ['error' => $e->getMessage()]);
                Response::json(['error' => $e->getMessage(), 'code' => 'invite_failed'], 400);
            }
        }

        $company = User::getCompany((int)$user['id']);

        $limiter->clear("tg_login:{$ip}");
        $this->startSession($user, $company);

        Logger::info('Telegram login', ['user_id' => $user['id']]);
        ActivityLogger::log('auth_login', $company['id'] ?? null, (int)$user['id']);

        Response::json([
            'success' => true,
            'onboarding_required' => $company === null,
            'redirect' => $company ? '/dashboard' : '/onboarding',
            'csrf_token' => Csrf::token(),
            'user' => [
                'id' => (int)$user['id'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
            ],
        ]);
    }

    public function status(): void
    {
        if (empty($_SESSION['user_id'])) {
            Response::json([
                'authenticated' => false,
                'onboarding_required' => false,
            ]);
        }

        $company = User::getCompany((int)$_SESSION['user_id']);
        if ($company) {
            $_SESSION['company_id'] = (int)$company['id'];
            $_SESSION['company_name'] = $company['name'];
            $_SESSION['company_role'] = $company['role'];
            \App\Models\Permission::syncSession((int)$_SESSION['user_id'], (int)$company['id']);
            $sub = $this->getSubscription((int)$company['id']);
            SubscriptionMiddleware::syncSessionStatus($sub);
        }

        Response::json([
            'authenticated' => true,
            'onboarding_required' => $company === null,
            'redirect' => $company ? '/dashboard' : '/onboarding',
            'csrf_token' => Csrf::token(),
            'user' => $_SESSION['user'] ?? null,
            'company' => $company ? [
                'id' => (int)$company['id'],
                'name' => $company['name'],
                'role' => $company['role'],
            ] : null,
        ]);
    }

    public function mockLogin(): void
    {
        $env = $_ENV['APP_ENV'] ?? 'production';
        $enabled = ($_ENV['MOCK_LOGIN_ENABLED'] ?? 'false') === 'true';

        if ($env === 'production' || !$enabled) {
            Response::json(['error' => 'Mock login disabled'], 403);
        }

        $telegramId = (int)($_ENV['MOCK_TELEGRAM_ID'] ?? 100000001);
        $tgUser = [
            'id' => $telegramId,
            'first_name' => 'Демо',
            'last_name' => 'Пользователь',
            'username' => 'demo_user',
        ];

        $user = User::createOrUpdate($tgUser);
        $company = User::getCompany((int)$user['id']);
        $this->startSession($user, $company);
        Logger::info('Mock login', ['user_id' => $user['id']]);

        Response::json([
            'success' => true,
            'onboarding_required' => $company === null,
            'redirect' => $company ? '/dashboard' : '/onboarding',
            'csrf_token' => Csrf::token(),
        ]);
    }

    public function logout(): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        $companyId = $_SESSION['company_id'] ?? null;

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool)$params['secure'], (bool)$params['httponly']);
        }
        session_destroy();

        if ($userId) {
            ActivityLogger::log('auth_logout', $companyId ? (int)$companyId : null, (int)$userId);
        }

        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/') || ($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json') {
            Response::json(['success' => true]);
        }

        Response::redirect('/mini-app');
    }

    private function startSession(array $user, ?array $company): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'username' => $user['telegram_username'],
            'photo_url' => $user['photo_url'],
        ];

        unset($_SESSION['company_id'], $_SESSION['company_name'], $_SESSION['company_role'], $_SESSION['sub_status'], $_SESSION['permissions'], $_SESSION['company_user_id']);

        if ($company) {
            $_SESSION['company_id'] = (int)$company['id'];
            $_SESSION['company_name'] = $company['name'];
            $_SESSION['company_role'] = $company['role'];
            \App\Models\Permission::syncSession((int)$user['id'], (int)$company['id']);
            $full = Company::findForCompany((int)$company['id'], (int)$company['id']);
            if ($full) {
                Company::syncSessionLocale($full);
            }
            $sub = $this->getSubscription((int)$company['id']);
            SubscriptionMiddleware::syncSessionStatus($sub);
        }

        Csrf::token();
    }

    private function getSubscription(int $companyId): ?array
    {
        $db = Database::getInstance();
        $st = $db->prepare('SELECT id, plan, status, trial_end, ends_at FROM subscriptions WHERE company_id = ? ORDER BY id DESC LIMIT 1');
        $st->execute([$companyId]);
        return $st->fetch() ?: null;
    }
}

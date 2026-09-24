<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Middleware\AuthMiddleware;
use App\Middleware\CompanyMiddleware;
use App\Services\ActivityLogger;

class SubscriptionController
{
    public function index(): void
    {
        CompanyMiddleware::check();
        $companyId = CompanyMiddleware::companyId();
        $db        = Database::getInstance();

        $sub = $db->prepare("SELECT * FROM subscriptions WHERE company_id=? ORDER BY id DESC LIMIT 1");
        $sub->execute([$companyId]);
        $subscription = $sub->fetch();

        $daysLeft = 0;
        if ($subscription) {
            $ends     = strtotime($subscription['ends_at'] ?? $subscription['trial_end'] ?? '');
            $daysLeft = max(0, (int)ceil(($ends - time()) / 86400));
        }

        $pageTitle = 'Подписка';
        ob_start();
        require ROOT_DIR . '/views/subscriptions/index.php';
        $content = ob_get_clean();
        require ROOT_DIR . '/views/layouts/app.php';
    }

    public function expired(): void
    {
        AuthMiddleware::check();
        $pageTitle    = 'Подписка истекла';
        $hideNav      = true;
        $hideBottomNav = true;
        ob_start();
        require ROOT_DIR . '/views/subscriptions/expired.php';
        $content = ob_get_clean();
        require ROOT_DIR . '/views/layouts/app.php';
    }

    public function request(): void
    {
        CompanyMiddleware::check();
        $companyId = CompanyMiddleware::companyId();
        ActivityLogger::log('subscription_request', $companyId, $_SESSION['user_id']);
        $_SESSION['flash_success'] = 'Запрос на продление подписки отправлен. Мы свяжемся с вами.';
        Response::redirect('/subscription');
    }
}

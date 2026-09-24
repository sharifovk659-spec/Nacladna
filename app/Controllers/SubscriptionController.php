<?php

namespace App\Controllers;

use App\Core\Response;
use App\Middleware\CompanyMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Middleware\SubscriptionMiddleware;
use App\Models\Subscription;
use App\Services\ActivityLogger;

class SubscriptionController
{
    public function index(): void
    {
        CompanyMiddleware::check();
        PermissionMiddleware::require('subscription.view');
        $companyId = CompanyMiddleware::companyId();
        $subscription = Subscription::findForCompany($companyId);
        if ($subscription) {
            Subscription::expireIfNeeded($subscription);
            $subscription = Subscription::findForCompany($companyId);
        }

        $daysLeft = Subscription::daysRemaining($subscription);
        $history = Subscription::historyForCompany($companyId, 20);
        $periodOptions = Subscription::periodOptions();
        $tariffs = Subscription::tariffs();
        $defaultMonths = 12;
        $planLabel = Subscription::planLabel($subscription);
        $readOnly = !Subscription::isWritable($subscription);

        $pageTitle = 'Подписка';
        ob_start();
        require ROOT_DIR . '/views/subscriptions/index.php';
        $content = ob_get_clean();
        require ROOT_DIR . '/views/layouts/app.php';
    }

    public function expired(): void
    {
        \App\Middleware\AuthMiddleware::check();
        $pageTitle    = 'Подписка истекла';
        $hideNav      = true;
        $hideBottomNav = true;
        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_error']);
        ob_start();
        require ROOT_DIR . '/views/subscriptions/expired.php';
        $content = ob_get_clean();
        require ROOT_DIR . '/views/layouts/app.php';
    }

    public function request(): void
    {
        CompanyMiddleware::check();
        PermissionMiddleware::require('subscription.view');
        SubscriptionMiddleware::requireWrite();
        Csrf::verifyRequest();

        $companyId = CompanyMiddleware::companyId();
        $userId = (int)$_SESSION['user_id'];
        $months = (int)($_POST['period_months'] ?? 1);
        try {
            $months = Subscription::assertPeriodMonths($months);
        } catch (\Throwable) {
            $months = 1;
        }

        $tariff = Subscription::tariffForMonths($months);
        $priceSom = $tariff ? (float)$tariff['price'] : null;
        $note = $tariff
            ? sprintf('Запрос оплаты · %s · %s', $tariff['label'], $tariff['price_label'])
            : 'Запрос ручной оплаты · ' . $months . ' мес.';

        $sub = Subscription::findForCompany($companyId);
        Subscription::logHistory(
            $companyId,
            $sub ? (int)$sub['id'] : null,
            'renewal_requested',
            (string)($sub['plan'] ?? Subscription::PLAN_BUSINESS),
            $months,
            $priceSom,
            $sub['starts_at'] ?? null,
            $sub['ends_at'] ?? $sub['trial_end'] ?? null,
            (string)($sub['status'] ?? 'expired'),
            'user',
            $userId,
            $note
        );

        ActivityLogger::log('subscription_request', $companyId, $userId, 'subscription', null, [
            'period_months' => $months,
            'price_som' => $priceSom,
        ]);
        $_SESSION['flash_success'] = $tariff
            ? 'Запрос отправлен: ' . $tariff['label'] . ' · ' . $tariff['price_label']
            : 'Запрос отправлен. Администратор активирует подписку после оплаты.';
        Response::redirect('/subscription');
    }
}

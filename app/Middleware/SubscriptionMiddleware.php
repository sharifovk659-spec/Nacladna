<?php

namespace App\Middleware;

use App\Core\Response;
use App\Models\Subscription;

class SubscriptionMiddleware
{
    public static function check(): void
    {
        CurrentCompanyMiddleware::check();
        $companyId = CurrentCompanyMiddleware::companyId();
        $sub = self::loadSubscription($companyId);

        if (!$sub) {
            $_SESSION['sub_status'] = 'expired';
            Response::redirect('/subscription/expired');
        }

        if (!Subscription::isWritable($sub)) {
            $_SESSION['sub_status'] = 'expired';
            return;
        }

        self::syncSessionStatus($sub);
    }

    public static function isReadOnly(): bool
    {
        $companyId = CurrentCompanyMiddleware::companyId();
        if ($companyId <= 0) {
            return true;
        }

        $sub = self::loadSubscription($companyId);
        return !Subscription::isWritable($sub);
    }

    public static function requireWrite(): void
    {
        CurrentCompanyMiddleware::check();
        if (self::isReadOnly()) {
            $message = 'Подписка истекла. Доступ только для просмотра.';
            if (self::wantsJsonResponse()) {
                Response::json(['error' => $message], 403);
            }
            $_SESSION['flash_error'] = $message;
            Response::redirect('/subscription/expired');
        }
    }

    public static function sessionStatusFromRow(?array $sub): string
    {
        return Subscription::sessionStatusFromRow($sub);
    }

    public static function syncSessionStatus(?array $sub): void
    {
        $_SESSION['sub_status'] = Subscription::sessionStatusFromRow($sub);
    }

    private static function loadSubscription(int $companyId): ?array
    {
        $sub = Subscription::findForCompany($companyId);
        if ($sub) {
            Subscription::expireIfNeeded($sub);
            $sub = Subscription::findForCompany($companyId);
        }
        return $sub;
    }

    private static function wantsJsonResponse(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (str_starts_with($uri, '/api/')) {
            return true;
        }

        $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
        if (str_contains($accept, 'application/json')) {
            return true;
        }

        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }
}

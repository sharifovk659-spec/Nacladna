<?php

namespace App\Middleware;

use App\Core\Database;
use App\Core\Response;

class SubscriptionMiddleware
{
    public static function check(): void
    {
        CurrentCompanyMiddleware::check();
        $companyId = CurrentCompanyMiddleware::companyId();
        $sub = self::getSubscription($companyId);

        if (!$sub) {
            $_SESSION['sub_status'] = 'expired';
            Response::redirect('/subscription/expired');
        }

        $ends = strtotime($sub['ends_at'] ?? $sub['trial_end'] ?? '');
        $activeStatuses = ['trial', 'active'];

        if ($ends && $ends < time()) {
            self::expireSubscription((int)$sub['id']);
            $_SESSION['sub_status'] = 'expired';
            // Read-only: allow dashboard view, block writes via requireWrite()
            return;
        }

        if (!in_array($sub['status'], $activeStatuses, true)) {
            $_SESSION['sub_status'] = $sub['status'];
            return;
        }

        $_SESSION['sub_status'] = $sub['status'];
    }

    public static function isReadOnly(): bool
    {
        $companyId = CurrentCompanyMiddleware::companyId();
        if ($companyId <= 0) {
            return true;
        }

        $sub = self::getSubscription($companyId);
        if (!$sub) {
            return true;
        }

        if (!in_array($sub['status'], ['trial', 'active'], true)) {
            return true;
        }

        $ends = strtotime($sub['ends_at'] ?? $sub['trial_end'] ?? '');
        return $ends !== false && $ends < time();
    }

    public static function requireWrite(): void
    {
        CurrentCompanyMiddleware::check();
        if (self::isReadOnly()) {
            Response::json(['error' => 'Подписка истекла. Доступ только для просмотра.'], 403);
        }
    }

    private static function getSubscription(int $companyId): ?array
    {
        $db = Database::getInstance();
        $st = $db->prepare(
            "SELECT id, plan, status, trial_end, ends_at
             FROM subscriptions
             WHERE company_id = ?
             ORDER BY id DESC
             LIMIT 1"
        );
        $st->execute([$companyId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    private static function expireSubscription(int $id): void
    {
        $db = Database::getInstance();
        $db->prepare("UPDATE subscriptions SET status='expired', updated_at=NOW() WHERE id=?")->execute([$id]);
    }
}

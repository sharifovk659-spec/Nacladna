<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Middleware\CurrentCompanyMiddleware;
use App\Middleware\SubscriptionMiddleware;
use App\Models\Subscription;

class DashboardController
{
    public function index(): void
    {
        CurrentCompanyMiddleware::check();
        SubscriptionMiddleware::check();

        $summary = $this->buildSummary(CurrentCompanyMiddleware::companyId());
        $stats = $summary['metrics'];
        $subscription = $summary['subscription'];
        $daysLeft = $summary['days_left'];
        $user = $_SESSION['user'];
        $companyName = $_SESSION['company_name'] ?? '';
        $role = CurrentCompanyMiddleware::role();
        $readOnly = SubscriptionMiddleware::isReadOnly();

        $pageTitle = 'Главная';
        ob_start();
        require ROOT_DIR . '/views/dashboard/index.php';
        $content = ob_get_clean();
        require ROOT_DIR . '/views/layouts/app.php';
    }

    public function summary(): void
    {
        CurrentCompanyMiddleware::check();
        SubscriptionMiddleware::check();

        Response::json([
            'success' => true,
            'data' => $this->buildSummary(CurrentCompanyMiddleware::companyId()),
        ]);
    }

    private function buildSummary(int $companyId): array
    {
        $db = Database::getInstance();

        $stats = $db->prepare(
            "SELECT
               (SELECT COUNT(*) FROM invoices WHERE company_id = ? AND deleted_at IS NULL AND status != 'cancelled') AS invoice_count,
               (SELECT COUNT(*) FROM clients WHERE company_id = ? AND status = 'active') AS client_count,
               (SELECT COUNT(*) FROM products WHERE company_id = ? AND status = 'active') AS product_count,
               (SELECT COALESCE(SUM(total), 0) FROM invoices WHERE company_id = ? AND deleted_at IS NULL AND status != 'cancelled') AS total_sales,
               (SELECT COALESCE(SUM(paid_amount), 0) FROM invoices WHERE company_id = ? AND deleted_at IS NULL AND status != 'cancelled') AS total_paid,
               (SELECT COALESCE(SUM(debt_amount), 0) FROM invoices WHERE company_id = ? AND deleted_at IS NULL AND status IN ('unpaid', 'partial')) AS total_debt"
        );
        $stats->execute([$companyId, $companyId, $companyId, $companyId, $companyId, $companyId]);
        $row = $stats->fetch() ?: [];

        $subSt = $db->prepare(
            "SELECT id, plan, status, trial_start, trial_end, starts_at, ends_at
             FROM subscriptions WHERE company_id = ? ORDER BY id DESC LIMIT 1"
        );
        $subSt->execute([$companyId]);
        $subscription = $subSt->fetch() ?: null;

        $daysLeft = Subscription::daysRemaining($subscription);

        return [
            'user' => [
                'first_name' => $_SESSION['user']['first_name'] ?? '',
                'last_name' => $_SESSION['user']['last_name'] ?? '',
            ],
            'company' => [
                'id' => $companyId,
                'name' => $_SESSION['company_name'] ?? '',
                'role' => CurrentCompanyMiddleware::role(),
            ],
            'subscription' => $subscription ? [
                'plan' => $subscription['plan'],
                'status' => $subscription['status'],
                'trial_end' => $subscription['trial_end'],
                'ends_at' => $subscription['ends_at'],
            ] : null,
            'days_left' => $daysLeft,
            'read_only' => SubscriptionMiddleware::isReadOnly(),
            'metrics' => [
                'invoice_count' => (int)($row['invoice_count'] ?? 0),
                'client_count' => (int)($row['client_count'] ?? 0),
                'product_count' => (int)($row['product_count'] ?? 0),
                'total_sales' => (float)($row['total_sales'] ?? 0),
                'total_paid' => (float)($row['total_paid'] ?? 0),
                'total_debt' => (float)($row['total_debt'] ?? 0),
            ],
        ];
    }
}

<?php

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Response;
use App\Helpers\Csrf;
use App\Models\Subscription;
use App\Services\ActivityLogger;

class AdminCompanyController
{
    public function index(): void
    {
        AdminAuthController::requireAdmin();
        $db       = Database::getInstance();
        $search   = trim($_GET['q'] ?? '');
        $where    = '1=1';
        $params   = [];
        if ($search !== '') {
            $where    .= ' AND (c.name LIKE ? OR u.first_name LIKE ?)';
            $like      = '%' . $search . '%';
            $params[]  = $like;
            $params[]  = $like;
        }
        $companies = $db->prepare(
            "SELECT c.*, u.first_name, u.last_name, u.telegram_username,
               s.plan, s.status AS sub_status, s.ends_at, s.period_months, s.starts_at,
               (SELECT COUNT(*) FROM invoices i WHERE i.company_id=c.id AND i.deleted_at IS NULL) AS invoice_count
             FROM companies c
             LEFT JOIN company_users cu ON cu.company_id=c.id AND cu.role='owner'
             LEFT JOIN users u ON u.id=cu.user_id
             LEFT JOIN subscriptions s ON s.id = (
               SELECT id FROM subscriptions WHERE company_id = c.id ORDER BY id DESC LIMIT 1
             )
             WHERE {$where}
             ORDER BY c.id DESC"
        );
        $companies->execute($params);
        $companies = $companies->fetchAll();

        $periodOptions = Subscription::periodOptions();
        require ROOT_DIR . '/views/admin/companies.php';
    }

    public function show(string $id): void
    {
        AdminAuthController::requireAdmin();
        $companyId = (int)$id;
        $db = Database::getInstance();
        $st = $db->prepare(
            "SELECT c.*, u.first_name, u.last_name, u.telegram_username
             FROM companies c
             LEFT JOIN company_users cu ON cu.company_id=c.id AND cu.role='owner'
             LEFT JOIN users u ON u.id=cu.user_id
             WHERE c.id = ?
             LIMIT 1"
        );
        $st->execute([$companyId]);
        $company = $st->fetch();
        if (!$company) {
            http_response_code(404);
            echo 'Company not found';
            return;
        }

        $subscription = Subscription::findForCompany($companyId);
        $history = Subscription::historyForCompany($companyId, 50);
        $periodOptions = Subscription::periodOptions();
        require ROOT_DIR . '/views/admin/company_subscription.php';
    }

    public function activate(string $id): void
    {
        AdminAuthController::requireAdmin();
        Csrf::verifyRequest();
        $companyId = (int)$id;
        try {
            $months = Subscription::assertPeriodMonths((int)($_POST['period_months'] ?? 0));
            Subscription::adminActivate($companyId, $months, null, 'Активация из админ-панели');
            ActivityLogger::log('admin_activate_subscription', $companyId, null, 'company', $companyId, ['months' => $months]);
            $_SESSION['admin_msg'] = "Подписка активирована на {$months} мес.";
        } catch (\Throwable $e) {
            $_SESSION['admin_msg'] = $e->getMessage();
        }
        Response::redirect('/admin/companies');
    }

    public function suspend(string $id): void
    {
        AdminAuthController::requireAdmin();
        Csrf::verifyRequest();
        $db = Database::getInstance();
        $db->prepare("UPDATE companies SET status='suspended' WHERE id=?")->execute([(int)$id]);
        ActivityLogger::log('admin_suspend_company', (int)$id);
        $_SESSION['admin_msg'] = 'Компания приостановлена.';
        Response::redirect('/admin/companies');
    }

    public function extend(string $id): void
    {
        AdminAuthController::requireAdmin();
        Csrf::verifyRequest();
        $companyId = (int)$id;
        try {
            $months = Subscription::assertPeriodMonths((int)($_POST['period_months'] ?? 0));
            Subscription::adminExtend($companyId, $months, null, 'Продление из админ-панели');
            ActivityLogger::log('admin_extend_subscription', $companyId, null, 'company', $companyId, ['months' => $months]);
            $_SESSION['admin_msg'] = "Подписка продлена на {$months} мес.";
        } catch (\Throwable $e) {
            $_SESSION['admin_msg'] = $e->getMessage();
        }
        Response::redirect('/admin/companies');
    }
}

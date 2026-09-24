<?php

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Response;
use App\Helpers\Csrf;
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
            $where    .= " AND (c.name LIKE ? OR u.first_name LIKE ?)";
            $like      = '%' . $search . '%';
            $params[]  = $like;
            $params[]  = $like;
        }
        $companies = $db->prepare(
            "SELECT c.*, u.first_name, u.last_name, u.telegram_username,
               s.plan, s.status AS sub_status, s.ends_at,
               (SELECT COUNT(*) FROM invoices i WHERE i.company_id=c.id) AS invoice_count
             FROM companies c
             LEFT JOIN company_users cu ON cu.company_id=c.id AND cu.role='owner'
             LEFT JOIN users u ON u.id=cu.user_id
             LEFT JOIN subscriptions s ON s.company_id=c.id
             WHERE {$where}
             ORDER BY c.id DESC"
        );
        $companies->execute($params);
        $companies = $companies->fetchAll();

        require ROOT_DIR . '/views/admin/companies.php';
    }

    public function activate(string $id): void
    {
        AdminAuthController::requireAdmin();
        Csrf::verifyRequest();
        $db   = Database::getInstance();
        $days = max(1, (int)($_POST['days'] ?? 30));
        $now  = date('Y-m-d H:i:s');
        $ends = date('Y-m-d H:i:s', strtotime("+{$days} days"));

        $db->prepare("UPDATE subscriptions SET plan='business', status='active', starts_at=?, ends_at=?, updated_at=NOW() WHERE company_id=?")
           ->execute([$now, $ends, (int)$id]);
        $db->prepare("UPDATE companies SET status='active' WHERE id=?")
           ->execute([(int)$id]);

        ActivityLogger::log('admin_activate_subscription', (int)$id, null, 'company', (int)$id, ['days' => $days]);
        $_SESSION['admin_msg'] = "Подписка активирована на {$days} дней.";
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
        $db   = Database::getInstance();
        $days = max(1, (int)($_POST['days'] ?? 30));
        $db->prepare("UPDATE subscriptions SET ends_at = DATE_ADD(COALESCE(ends_at, NOW()), INTERVAL ? DAY), status='active', updated_at=NOW() WHERE company_id=?")
           ->execute([$days, (int)$id]);
        ActivityLogger::log('admin_extend_subscription', (int)$id, null, 'company', (int)$id, ['days' => $days]);
        $_SESSION['admin_msg'] = "Подписка продлена на {$days} дней.";
        Response::redirect('/admin/companies');
    }
}

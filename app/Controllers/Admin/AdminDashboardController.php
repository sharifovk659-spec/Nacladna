<?php

namespace App\Controllers\Admin;

use App\Core\Database;

class AdminDashboardController
{
    public function index(): void
    {
        AdminAuthController::requireAdmin();
        $db = Database::getInstance();

        $stats = $db->query(
            "SELECT
               (SELECT COUNT(*) FROM companies)            AS total_companies,
               (SELECT COUNT(*) FROM users)                AS total_users,
               (SELECT COUNT(*) FROM invoices WHERE status='active') AS total_invoices,
               (SELECT COALESCE(SUM(total),0) FROM invoices WHERE status='active') AS total_revenue,
               (SELECT COUNT(*) FROM subscriptions WHERE status='trial') AS active_trials,
               (SELECT COUNT(*) FROM subscriptions WHERE status='active') AS active_subs,
               (SELECT COUNT(*) FROM subscriptions WHERE status='expired') AS expired_subs"
        )->fetch();

        $recentCompanies = $db->query(
            "SELECT c.*, u.first_name, u.last_name,
               (SELECT COUNT(*) FROM invoices i WHERE i.company_id=c.id) AS invoice_count,
               s.status AS sub_status, s.ends_at AS sub_ends
             FROM companies c
             LEFT JOIN company_users cu ON cu.company_id=c.id AND cu.role='owner'
             LEFT JOIN users u ON u.id=cu.user_id
             LEFT JOIN subscriptions s ON s.company_id=c.id
             ORDER BY c.id DESC LIMIT 10"
        )->fetchAll();

        $recentLogs = $db->query(
            "SELECT al.*, u.first_name, u.telegram_username
             FROM activity_logs al
             LEFT JOIN users u ON u.id=al.user_id
             ORDER BY al.id DESC LIMIT 20"
        )->fetchAll();

        require ROOT_DIR . '/views/admin/dashboard.php';
    }
}

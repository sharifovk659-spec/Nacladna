<?php

namespace App\Middleware;

use App\Core\Database;
use App\Core\Response;

/**
 * Loads company membership from server-side DB — never trusts browser company_id.
 */
class CurrentCompanyMiddleware
{
    public static function check(): void
    {
        AuthMiddleware::check();

        $userId = (int)($_SESSION['user_id'] ?? 0);
        $db = Database::getInstance();
        $st = $db->prepare(
            "SELECT c.id, c.name, c.status AS company_status, cu.role, cu.status AS membership_status
             FROM company_users cu
             JOIN companies c ON c.id = cu.company_id
             WHERE cu.user_id = ?
               AND cu.status = 'active'
               AND c.status = 'active'
             ORDER BY cu.id ASC
             LIMIT 1"
        );
        $st->execute([$userId]);
        $row = $st->fetch();

        if (!$row) {
            unset($_SESSION['company_id'], $_SESSION['company_name'], $_SESSION['company_role'], $_SESSION['sub_status']);
            if (self::isApi()) {
                Response::json(['error' => 'Company required', 'onboarding_required' => true], 403);
            }
            Response::redirect('/onboarding');
        }

        $_SESSION['company_id']   = (int)$row['id'];
        $_SESSION['company_name'] = $row['name'];
        $_SESSION['company_role'] = $row['role'];
    }

    public static function companyId(): int
    {
        return (int)($_SESSION['company_id'] ?? 0);
    }

    public static function role(): string
    {
        return (string)($_SESSION['company_role'] ?? 'member');
    }

    public static function isOwner(): bool
    {
        return self::role() === 'owner';
    }

    private static function isApi(): bool
    {
        return str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/');
    }
}

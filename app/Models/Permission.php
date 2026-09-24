<?php

namespace App\Models;

use App\Core\Database;
use App\Services\PermissionRegistry;

class Permission
{
    public static function syncSession(int $userId, int $companyId): void
    {
        $membership = CompanyUser::findMembershipByUser($userId, $companyId);
        if (!$membership) {
            $_SESSION['permissions'] = [];
            $_SESSION['company_user_id'] = 0;
            return;
        }

        $_SESSION['company_user_id'] = (int)$membership['id'];
        $_SESSION['permissions'] = self::effectiveForMembership($membership);
    }

    /**
     * @return string[]
     */
    public static function effectiveForMembership(array $membership): array
    {
        $role = (string)($membership['role'] ?? 'cashier');
        if ($role === 'owner') {
            return PermissionRegistry::ALL;
        }

        $db = Database::getInstance();
        $st = $db->prepare(
            'SELECT permission_slug FROM role_permissions WHERE role = ?'
        );
        $st->execute([$role]);
        $perms = [];
        foreach ($st->fetchAll() as $row) {
            $perms[(string)$row['permission_slug']] = true;
        }

        $st = $db->prepare(
            'SELECT permission_slug, granted FROM company_user_permissions WHERE company_user_id = ?'
        );
        $st->execute([(int)$membership['id']]);
        foreach ($st->fetchAll() as $row) {
            $slug = (string)$row['permission_slug'];
            if ((int)$row['granted'] === 1) {
                $perms[$slug] = true;
            } else {
                unset($perms[$slug]);
            }
        }

        return array_keys($perms);
    }

    public static function has(string $permission): bool
    {
        $list = $_SESSION['permissions'] ?? [];
        if (!is_array($list)) {
            return false;
        }
        return in_array($permission, $list, true);
    }

    /**
     * @param array<string, bool> $grants slug => granted
     */
    public static function saveOverrides(int $companyUserId, int $companyId, array $grants): void
    {
        $member = CompanyUser::findForCompany($companyUserId, $companyId);
        if (!$member) {
            throw new \RuntimeException('Сотрудник не найден.');
        }
        if ((string)$member['role'] === 'owner') {
            throw new \RuntimeException('Нельзя менять права владельца.');
        }

        $db = Database::getInstance();
        $db->prepare('DELETE FROM company_user_permissions WHERE company_user_id = ?')
            ->execute([$companyUserId]);

        $ins = $db->prepare(
            'INSERT INTO company_user_permissions (company_user_id, permission_slug, granted) VALUES (?, ?, ?)'
        );
        foreach ($grants as $slug => $granted) {
            if (!in_array($slug, PermissionRegistry::ALL, true)) {
                continue;
            }
            $ins->execute([$companyUserId, $slug, $granted ? 1 : 0]);
        }
    }

    /**
     * @return array<string, bool> only explicit overrides
     */
    public static function getOverrides(int $companyUserId): array
    {
        $db = Database::getInstance();
        $st = $db->prepare(
            'SELECT permission_slug, granted FROM company_user_permissions WHERE company_user_id = ?'
        );
        $st->execute([$companyUserId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[(string)$row['permission_slug']] = (int)$row['granted'] === 1;
        }
        return $out;
    }
}

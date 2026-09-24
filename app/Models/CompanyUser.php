<?php

namespace App\Models;

use App\Core\Database;
use App\Services\PermissionRegistry;

class CompanyUser
{
    public static function findMembershipByUser(int $userId, int $companyId): ?array
    {
        if ($userId <= 0 || $companyId <= 0) {
            return null;
        }
        $db = Database::getInstance();
        $st = $db->prepare(
            'SELECT cu.*, u.first_name, u.last_name, u.telegram_username, u.telegram_id, u.last_login_at
             FROM company_users cu
             INNER JOIN users u ON u.id = cu.user_id
             WHERE cu.user_id = ? AND cu.company_id = ?
             LIMIT 1'
        );
        $st->execute([$userId, $companyId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function findForCompany(int $companyUserId, int $scopeCompanyId): ?array
    {
        if ($companyUserId <= 0 || $scopeCompanyId <= 0) {
            return null;
        }
        $db = Database::getInstance();
        $st = $db->prepare(
            'SELECT cu.*, u.first_name, u.last_name, u.telegram_username, u.telegram_id, u.last_login_at
             FROM company_users cu
             INNER JOIN users u ON u.id = cu.user_id
             WHERE cu.id = ? AND cu.company_id = ?
             LIMIT 1'
        );
        $st->execute([$companyUserId, $scopeCompanyId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listForCompany(int $companyId): array
    {
        $db = Database::getInstance();
        $st = $db->prepare(
            "SELECT cu.id, cu.company_id, cu.user_id, cu.display_name, cu.role, cu.status, cu.created_at,
                    u.first_name, u.last_name, u.telegram_username, u.telegram_id, u.last_login_at,
                    (SELECT MAX(al.created_at) FROM activity_logs al
                     WHERE al.company_id = cu.company_id AND al.user_id = cu.user_id) AS last_activity_at
             FROM company_users cu
             INNER JOIN users u ON u.id = cu.user_id
             WHERE cu.company_id = ?
             ORDER BY FIELD(cu.role, 'owner','admin','manager','cashier','accountant'), cu.id ASC"
        );
        $st->execute([$companyId]);
        return $st->fetchAll() ?: [];
    }

    public static function countActiveOwners(int $companyId): int
    {
        $db = Database::getInstance();
        $st = $db->prepare(
            "SELECT COUNT(*) FROM company_users WHERE company_id = ? AND role = 'owner' AND status = 'active'"
        );
        $st->execute([$companyId]);
        return (int)$st->fetchColumn();
    }

    public static function setStatus(int $companyUserId, int $companyId, string $status, string $actorRole): void
    {
        $member = self::findForCompany($companyUserId, $companyId);
        if (!$member) {
            throw new \RuntimeException('Сотрудник не найден.');
        }
        if ((string)$member['role'] === 'owner') {
            throw new \RuntimeException('Нельзя отключить владельца.');
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new \InvalidArgumentException('Недопустимый статус.');
        }
        if ($actorRole !== 'owner' && $actorRole !== 'admin') {
            throw new \RuntimeException('Недостаточно прав.');
        }

        $db = Database::getInstance();
        $db->prepare('UPDATE company_users SET status = ? WHERE id = ? AND company_id = ?')
            ->execute([$status, $companyUserId, $companyId]);
    }

    public static function updateEmployee(
        int $companyUserId,
        int $companyId,
        string $actorRole,
        int $actorUserId,
        ?string $displayName,
        ?string $role,
        ?string $status
    ): void {
        $member = self::findForCompany($companyUserId, $companyId);
        if (!$member) {
            throw new \RuntimeException('Сотрудник не найден.');
        }

        $targetRole = $role ?? (string)$member['role'];
        $isOwnerRow = (string)$member['role'] === 'owner';

        if ($isOwnerRow) {
            if ($role !== null && $role !== 'owner') {
                if (self::countActiveOwners($companyId) <= 1) {
                    throw new \RuntimeException('Нельзя снять роль последнего владельца.');
                }
            }
            if ($status === 'inactive') {
                throw new \RuntimeException('Нельзя отключить владельца.');
            }
            $targetRole = 'owner';
        } else {
            if ($role !== null) {
                if (!PermissionRegistry::canAssignRole($actorRole, $role)) {
                    throw new \RuntimeException('Нельзя назначить эту роль.');
                }
                if ($role === 'owner') {
                    throw new \RuntimeException('Нельзя назначить роль владельца.');
                }
            }
        }

        if ($status !== null && $status === 'inactive' && (int)$member['user_id'] === $actorUserId) {
            throw new \RuntimeException('Нельзя отключить самого себя.');
        }

        $db = Database::getInstance();
        $db->prepare(
            'UPDATE company_users SET display_name = ?, role = ?, status = COALESCE(?, status) WHERE id = ? AND company_id = ?'
        )->execute([
            $displayName !== null ? trim($displayName) : $member['display_name'],
            $targetRole,
            $status,
            $companyUserId,
            $companyId,
        ]);
    }

    public static function createFromInvite(int $companyId, int $userId, string $displayName, string $role): int
    {
        if (!PermissionRegistry::isAssignableRole($role) && $role !== 'admin') {
            throw new \InvalidArgumentException('Недопустимая роль.');
        }

        $db = Database::getInstance();
        $existing = $db->prepare(
            'SELECT id FROM company_users WHERE company_id = ? AND user_id = ? LIMIT 1'
        );
        $existing->execute([$companyId, $userId]);
        if ($existing->fetch()) {
            throw new \RuntimeException('Пользователь уже в компании.');
        }

        $db->prepare(
            'INSERT INTO company_users (company_id, user_id, display_name, role, status) VALUES (?, ?, ?, ?, ?)'
        )->execute([$companyId, $userId, $displayName, $role, 'active']);

        return (int)$db->lastInsertId();
    }
}

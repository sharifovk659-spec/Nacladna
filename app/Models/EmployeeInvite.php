<?php

namespace App\Models;

use App\Core\Database;
use App\Services\PermissionRegistry;

class EmployeeInvite
{
    public static function create(
        int $companyId,
        int $createdByUserId,
        string $displayName,
        string $role,
        string $actorRole,
        int $ttlHours = 168
    ): array {
        if (!PermissionRegistry::canAssignRole($actorRole, $role)) {
            throw new \InvalidArgumentException('Недопустимая роль для назначения.');
        }

        $token = bin2hex(random_bytes(24));
        $hash = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + max(1, $ttlHours) * 3600);

        $db = Database::getInstance();
        $db->prepare(
            'INSERT INTO employee_invites (company_id, token_hash, display_name, role, created_by_user_id, expires_at, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$companyId, $hash, trim($displayName), $role, $createdByUserId, $expires, 'pending']);

        return [
            'id' => (int)$db->lastInsertId(),
            'token' => $token,
            'expires_at' => $expires,
        ];
    }

    public static function findPendingByToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '' || strlen($token) < 16) {
            return null;
        }
        $hash = hash('sha256', $token);
        $db = Database::getInstance();
        $st = $db->prepare(
            "SELECT ei.*, c.name AS company_name
             FROM employee_invites ei
             INNER JOIN companies c ON c.id = ei.company_id AND c.status = 'active'
             WHERE ei.token_hash = ? AND ei.status = 'pending' AND ei.expires_at > NOW()
             LIMIT 1"
        );
        $st->execute([$hash]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function consume(string $token, int $userId): array
    {
        $invite = self::findPendingByToken($token);
        if (!$invite) {
            throw new \RuntimeException('Приглашение недействительно или истекло.');
        }

        $companyId = (int)$invite['company_id'];
        $db = Database::getInstance();

        $existing = $db->prepare(
            'SELECT id, company_id FROM company_users WHERE user_id = ? LIMIT 1'
        );
        $existing->execute([$userId]);
        if ($row = $existing->fetch()) {
            if ((int)$row['company_id'] !== $companyId) {
                throw new \RuntimeException('Вы уже состоите в другой компании.');
            }
            throw new \RuntimeException('Вы уже подключены к этой компании.');
        }

        $db->beginTransaction();
        try {
            $companyUserId = CompanyUser::createFromInvite(
                $companyId,
                $userId,
                (string)$invite['display_name'],
                (string)$invite['role']
            );

            $db->prepare(
                'UPDATE employee_invites SET status = ?, used_at = NOW(), used_by_user_id = ? WHERE id = ? AND status = ?'
            )->execute(['used', $userId, (int)$invite['id'], 'pending']);

            $db->commit();

            return [
                'company_id' => $companyId,
                'company_user_id' => $companyUserId,
                'company_name' => (string)$invite['company_name'],
                'role' => (string)$invite['role'],
            ];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listPendingForCompany(int $companyId): array
    {
        $db = Database::getInstance();
        $st = $db->prepare(
            "SELECT id, display_name, role, expires_at, created_at, status
             FROM employee_invites
             WHERE company_id = ? AND status = 'pending' AND expires_at > NOW()
             ORDER BY id DESC"
        );
        $st->execute([$companyId]);
        return $st->fetchAll() ?: [];
    }

    public static function revoke(int $inviteId, int $companyId): void
    {
        $db = Database::getInstance();
        $db->prepare(
            "UPDATE employee_invites SET status = 'revoked' WHERE id = ? AND company_id = ? AND status = 'pending'"
        )->execute([$inviteId, $companyId]);
    }
}

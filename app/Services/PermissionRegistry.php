<?php

namespace App\Services;

final class PermissionRegistry
{
    /** @var string[] */
    public const ALL = [
        'dashboard.view',
        'clients.view', 'clients.create', 'clients.edit', 'clients.delete',
        'products.view', 'products.create', 'products.edit', 'products.delete',
        'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.cancel', 'invoices.delete', 'invoices.print', 'invoices.share',
        'payments.view', 'payments.create',
        'debts.view', 'debts.manage',
        'reports.view',
        'settings.view', 'settings.edit',
        'subscription.view',
        'employees.view', 'employees.create', 'employees.edit', 'employees.disable', 'employees.permissions',
    ];

    /** Roles assignable to employees (never owner via invite). */
    public const ASSIGNABLE_ROLES = ['admin', 'manager', 'cashier', 'accountant'];

    public const ROLE_LABELS = [
        'owner' => 'Владелец',
        'admin' => 'Администратор',
        'manager' => 'Менеджер',
        'cashier' => 'Кассир',
        'accountant' => 'Бухгалтер',
    ];

    public static function isValidRole(string $role): bool
    {
        return isset(self::ROLE_LABELS[$role]);
    }

    public static function isAssignableRole(string $role): bool
    {
        return in_array($role, self::ASSIGNABLE_ROLES, true);
    }

    public static function canAssignRole(string $actorRole, string $targetRole): bool
    {
        if ($targetRole === 'owner') {
            return false;
        }
        if ($actorRole === 'owner') {
            return $targetRole === 'admin' || self::isAssignableRole($targetRole);
        }
        if ($actorRole === 'admin') {
            return self::isAssignableRole($targetRole) && $targetRole !== 'admin';
        }
        return false;
    }
}

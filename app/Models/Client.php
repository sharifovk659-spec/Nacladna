<?php

namespace App\Models;

use App\Core\Database;

class Client
{
    public static function all(
        int $companyId,
        string $search = '',
        int $page = 1,
        int $perPage = 20,
        string $status = ''
    ): array {
        $db     = Database::getInstance();
        $offset = ($page - 1) * $perPage;

        $where  = 'c.company_id = ?';
        $params = [$companyId];

        if ($search !== '') {
            $where   .= ' AND (c.name LIKE ? OR c.phone LIKE ?)';
            $like     = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $where   .= ' AND c.status = ?';
            $params[] = $status;
        }

        $total = $db->prepare("SELECT COUNT(*) FROM clients c WHERE {$where}");
        $total->execute($params);
        $totalCount = (int)$total->fetchColumn();

        $limitParams = $params;
        $limitParams[] = $perPage;
        $limitParams[] = $offset;

        $rows = $db->prepare(
            "SELECT c.*,
               (c.opening_debt + COALESCE((
                  SELECT SUM(i.debt_amount)
                   FROM invoices i
                   WHERE i.client_id = c.id
                     AND i.company_id = c.company_id
                     AND i.deleted_at IS NULL
                     AND i.status IN ('unpaid', 'partial')
               ), 0)) AS current_debt
             FROM clients c
             WHERE {$where}
             ORDER BY c.name ASC
             LIMIT ? OFFSET ?"
        );
        $rows->execute($limitParams);

        return [
            'items'     => $rows->fetchAll(),
            'total'     => $totalCount,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => max(1, (int)ceil($totalCount / max(1, $perPage))),
            'search'    => $search,
            'status'    => $status,
        ];
    }

    public static function findForCompany(int $id, int $companyId): ?array
    {
        $db = Database::getInstance();
        $st = $db->prepare(
            "SELECT c.*,
               (c.opening_debt + COALESCE((
                  SELECT SUM(i.debt_amount)
                   FROM invoices i
                   WHERE i.client_id = c.id
                     AND i.company_id = c.company_id
                     AND i.deleted_at IS NULL
                     AND i.status IN ('unpaid', 'partial')
               ), 0)) AS current_debt
             FROM clients c
             WHERE c.id = ? AND c.company_id = ?
             LIMIT 1"
        );
        $st->execute([$id, $companyId]);
        return $st->fetch() ?: null;
    }

    public static function create(int $companyId, array $data): int
    {
        $db = Database::getInstance();
        $db->prepare(
            "INSERT INTO clients (company_id, name, phone, address, opening_debt, status)
             VALUES (?, ?, ?, ?, ?, 'active')"
        )->execute([
            $companyId,
            $data['name'],
            $data['phone'] !== '' ? $data['phone'] : null,
            $data['address'] !== '' ? $data['address'] : null,
            self::decimal($data['opening_debt'] ?? 0, 2),
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, int $companyId, array $data): bool
    {
        if (!self::findForCompany($id, $companyId)) {
            return false;
        }

        $db = Database::getInstance();
        $db->prepare(
            "UPDATE clients
             SET name = ?, phone = ?, address = ?, opening_debt = ?, status = ?, updated_at = NOW()
             WHERE id = ? AND company_id = ?"
        )->execute([
            $data['name'],
            $data['phone'] !== '' ? $data['phone'] : null,
            $data['address'] !== '' ? $data['address'] : null,
            self::decimal($data['opening_debt'] ?? 0, 2),
            in_array($data['status'] ?? 'active', ['active', 'inactive'], true)
                ? $data['status']
                : 'active',
            $id,
            $companyId,
        ]);
        return true;
    }

    public static function search(int $companyId, string $q, int $limit = 10): array
    {
        $db = Database::getInstance();
        $st = $db->prepare(
            "SELECT id, name, phone,
               (opening_debt + COALESCE((
                  SELECT SUM(i.debt_amount) FROM invoices i
                  WHERE i.client_id = clients.id
                    AND i.company_id = clients.company_id
                    AND i.deleted_at IS NULL
                    AND i.status IN ('unpaid', 'partial')
               ), 0)) AS current_debt
             FROM clients
             WHERE company_id = ?
               AND status = 'active'
               AND (name LIKE ? OR phone LIKE ?)
             ORDER BY name ASC
             LIMIT ?"
        );
        $like = '%' . $q . '%';
        $st->execute([$companyId, $like, $like, $limit]);
        return $st->fetchAll();
    }

    public static function getInvoices(int $clientId, int $companyId, int $limit = 10): array
    {
        $db = Database::getInstance();
        $st = $db->prepare(
            "SELECT * FROM invoices
             WHERE client_id = ? AND company_id = ? AND deleted_at IS NULL
             ORDER BY invoice_date DESC
             LIMIT ?"
        );
        $st->execute([$clientId, $companyId, $limit]);
        return $st->fetchAll();
    }

    public static function decimal(mixed $value, int $scale = 2): string
    {
        return number_format((float)$value, $scale, '.', '');
    }
}

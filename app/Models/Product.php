<?php

namespace App\Models;

use App\Core\Database;

class Product
{
    public const LOW_STOCK_THRESHOLD = 5.0;

    /** DB ENUM values keyed by English aliases from the product brief. */
    public const UNITS = [
        'piece' => 'шт',
        'kg'    => 'кг',
        'meter' => 'м',
        'box'   => 'кор',
        'шт'    => 'шт',
        'кг'    => 'кг',
        'м'     => 'м',
        'кор'   => 'кор',
    ];

    public const UNIT_LABELS = [
        'шт'  => 'шт (piece)',
        'кг'  => 'кг (kg)',
        'м'   => 'м (meter)',
        'кор' => 'кор (box)',
    ];

    public static function all(
        int $companyId,
        string $search = '',
        int $page = 1,
        int $perPage = 20,
        string $status = ''
    ): array {
        $db     = Database::getInstance();
        $offset = ($page - 1) * $perPage;
        $where  = 'company_id = ?';
        $params = [$companyId];

        if ($search !== '') {
            $where   .= ' AND (name LIKE ? OR barcode LIKE ? OR sku LIKE ?)';
            $like     = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $where   .= ' AND status = ?';
            $params[] = $status;
        }

        $total = $db->prepare("SELECT COUNT(*) FROM products WHERE {$where}");
        $total->execute($params);
        $totalCount = (int)$total->fetchColumn();

        $limitParams = $params;
        $limitParams[] = $perPage;
        $limitParams[] = $offset;

        $rows = $db->prepare(
            "SELECT * FROM products WHERE {$where} ORDER BY name ASC LIMIT ? OFFSET ?"
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
        $st = $db->prepare('SELECT * FROM products WHERE id = ? AND company_id = ? LIMIT 1');
        $st->execute([$id, $companyId]);
        return $st->fetch() ?: null;
    }

    public static function create(int $companyId, array $data): int
    {
        $db = Database::getInstance();
        $db->prepare(
            "INSERT INTO products
             (company_id, name, sku, barcode, unit, purchase_price, sale_price, stock_quantity, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')"
        )->execute([
            $companyId,
            $data['name'],
            $data['sku'] !== '' ? $data['sku'] : null,
            $data['barcode'] !== '' ? $data['barcode'] : null,
            self::normalizeUnit($data['unit'] ?? 'шт'),
            self::nullableDecimal($data['purchase_price'] ?? null, 2),
            self::decimal($data['sale_price'] ?? 0, 2),
            self::decimal($data['stock_quantity'] ?? 0, 3),
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
            "UPDATE products
             SET name = ?, sku = ?, barcode = ?, unit = ?, purchase_price = ?,
                 sale_price = ?, stock_quantity = ?, status = ?, updated_at = NOW()
             WHERE id = ? AND company_id = ?"
        )->execute([
            $data['name'],
            $data['sku'] !== '' ? $data['sku'] : null,
            $data['barcode'] !== '' ? $data['barcode'] : null,
            self::normalizeUnit($data['unit'] ?? 'шт'),
            self::nullableDecimal($data['purchase_price'] ?? null, 2),
            self::decimal($data['sale_price'] ?? 0, 2),
            self::decimal($data['stock_quantity'] ?? 0, 3),
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
        $db   = Database::getInstance();
        $like = '%' . $q . '%';
        $st   = $db->prepare(
            "SELECT id, name, unit, sale_price, stock_quantity, sku, barcode
             FROM products
             WHERE company_id = ?
               AND status = 'active'
               AND (name LIKE ? OR barcode LIKE ? OR sku LIKE ?)
             ORDER BY name ASC
             LIMIT ?"
        );
        $st->execute([$companyId, $like, $like, $like, $limit]);
        return $st->fetchAll();
    }

    public static function normalizeUnit(string $unit): string
    {
        $key = mb_strtolower(trim($unit));
        // preserve Cyrillic keys as-is lookup
        if (isset(self::UNITS[$unit])) {
            return self::UNITS[$unit];
        }
        if (isset(self::UNITS[$key])) {
            return self::UNITS[$key];
        }
        return 'шт';
    }

    public static function isLowStock(float|string $qty): bool
    {
        $q = (float)$qty;
        return $q > 0 && $q <= self::LOW_STOCK_THRESHOLD;
    }

    public static function isOutOfStock(float|string $qty): bool
    {
        return (float)$qty <= 0;
    }

    public static function decimal(mixed $value, int $scale = 2): string
    {
        return number_format((float)$value, $scale, '.', '');
    }

    public static function nullableDecimal(mixed $value, int $scale = 2): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return self::decimal($value, $scale);
    }
}

<?php

namespace App\Models;

use App\Core\Database;

class Invoice
{
    public const PAYMENT_METHODS = ['cash', 'card', 'bank', 'transfer'];
    public const PAYMENT_STATUSES = ['unpaid', 'partial', 'paid'];
    public const STATUSES = ['draft', 'unpaid', 'partial', 'paid', 'cancelled'];

    public static function all(
        int $companyId,
        string $search = '',
        string $status = '',
        int $clientId = 0,
        int $page = 1,
        int $perPage = 20,
        string $dateFrom = '',
        string $dateTo = ''
    ): array {
        $db = Database::getInstance();
        $offset = ($page - 1) * $perPage;
        $where = "i.company_id = ? AND i.deleted_at IS NULL";
        $params = [$companyId];

        if ($search !== '') {
            $like = '%' . $search . '%';
            $where .= " AND (i.invoice_number LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)";
            array_push($params, $like, $like, $like);
        }

        if (in_array($status, self::STATUSES, true)) {
            $where .= " AND i.status = ?";
            $params[] = $status;
        }

        if ($clientId > 0) {
            $where .= " AND i.client_id = ?";
            $params[] = $clientId;
        }

        if (self::isValidDate($dateFrom)) {
            $where .= " AND i.invoice_date >= ?";
            $params[] = $dateFrom;
        }

        if (self::isValidDate($dateTo)) {
            $where .= " AND i.invoice_date <= ?";
            $params[] = $dateTo;
        }

        $countSt = $db->prepare(
            "SELECT COUNT(*)
             FROM invoices i
             LEFT JOIN clients c ON c.id = i.client_id
             WHERE {$where}"
        );
        $countSt->execute($params);
        $total = (int)$countSt->fetchColumn();

        $rowParams = $params;
        $rowParams[] = $perPage;
        $rowParams[] = $offset;

        $rows = $db->prepare(
            "SELECT i.*, c.name AS client_name, c.phone AS client_phone
             FROM invoices i
             LEFT JOIN clients c ON c.id = i.client_id
             WHERE {$where}
             ORDER BY i.invoice_date DESC, i.id DESC
             LIMIT ? OFFSET ?"
        );
        $rows->execute($rowParams);

        return [
            'items' => $rows->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int)ceil($total / max(1, $perPage))),
            'search' => $search,
            'status' => $status,
            'client_id' => $clientId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    public static function findForCompany(int $id, int $companyId, bool $includeDeleted = false): ?array
    {
        $db = Database::getInstance();
        $whereDeleted = $includeDeleted ? '' : ' AND i.deleted_at IS NULL';
        $st = $db->prepare(
            "SELECT i.*, c.name AS client_name, c.phone AS client_phone, c.address AS client_address,
                    u.first_name, u.last_name
             FROM invoices i
             LEFT JOIN clients c ON c.id = i.client_id
             LEFT JOIN users u ON u.id = i.created_by
             WHERE i.id = ? AND i.company_id = ?{$whereDeleted}
             LIMIT 1"
        );
        $st->execute([$id, $companyId]);
        return $st->fetch() ?: null;
    }

    public static function getItems(int $invoiceId): array
    {
        $db = Database::getInstance();
        $st = $db->prepare(
            "SELECT ii.*, p.stock_quantity AS current_stock
             FROM invoice_items ii
             LEFT JOIN products p ON p.id = ii.product_id
             WHERE ii.invoice_id = ?
             ORDER BY ii.id ASC"
        );
        $st->execute([$invoiceId]);
        return $st->fetchAll();
    }

    public static function getPayments(int $invoiceId): array
    {
        $db = Database::getInstance();
        $st = $db->prepare(
            "SELECT p.*, u.first_name, u.last_name
             FROM payments p
             JOIN users u ON u.id = p.created_by
             WHERE p.invoice_id = ?
             ORDER BY p.id ASC"
        );
        $st->execute([$invoiceId]);
        return $st->fetchAll();
    }

    public static function create(int $companyId, int $userId, array $data): array
    {
        return self::save($companyId, $userId, $data);
    }

    public static function update(int $invoiceId, int $companyId, int $userId, array $data): array
    {
        return self::save($companyId, $userId, $data, $invoiceId);
    }

    public static function duplicate(int $invoiceId, int $companyId, int $userId): array
    {
        $invoice = self::findForCompany($invoiceId, $companyId);
        if (!$invoice) {
            throw new \RuntimeException('Накладная не найдена.');
        }

        $items = self::getItems($invoiceId);
        $payload = [
            'client_id' => (int)($invoice['client_id'] ?? 0),
            'invoice_date' => date('Y-m-d'),
            'discount' => (float)$invoice['discount'],
            'paid_amount' => 0,
            'payment_method' => 'cash',
            'notes' => trim((string)($invoice['notes'] ?? '')),
            'status' => 'draft',
            'items' => array_map(static function (array $item): array {
                return [
                    'product_id' => (int)($item['product_id'] ?? 0),
                    'product_name' => $item['product_name'] ?? '',
                    'unit' => $item['unit'] ?? 'шт',
                    'quantity' => (float)$item['quantity'],
                    'unit_price' => (float)$item['unit_price'],
                    'discount' => (float)($item['discount'] ?? 0),
                ];
            }, $items),
        ];

        return self::create($companyId, $userId, $payload);
    }

    public static function cancel(int $invoiceId, int $companyId, int $userId): bool
    {
        $db = Database::getInstance();
        $invoice = self::findForUpdate($invoiceId, $companyId);
        if (!$invoice) {
            throw new \RuntimeException('Накладная не найдена.');
        }
        if ($invoice['status'] === 'cancelled') {
            return true;
        }

        $db->beginTransaction();
        try {
            self::restoreStock($db, $companyId, $invoiceId);
            $db->prepare(
                "UPDATE invoices
                 SET status = 'cancelled',
                     payment_status = 'unpaid',
                     debt_amount = 0,
                     updated_at = NOW()
                 WHERE id = ? AND company_id = ? AND deleted_at IS NULL"
            )->execute([$invoiceId, $companyId]);
            $db->commit();
            return true;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function softDelete(int $invoiceId, int $companyId, int $userId): bool
    {
        $db = Database::getInstance();
        $invoice = self::findForUpdate($invoiceId, $companyId);
        if (!$invoice) {
            throw new \RuntimeException('Накладная не найдена.');
        }

        $db->beginTransaction();
        try {
            if ($invoice['status'] !== 'cancelled') {
                self::restoreStock($db, $companyId, $invoiceId);
            }

            $db->prepare(
                "UPDATE invoices
                 SET deleted_at = NOW(), deleted_by = ?, debt_amount = 0, updated_at = NOW()
                 WHERE id = ? AND company_id = ? AND deleted_at IS NULL"
            )->execute([$userId, $invoiceId, $companyId]);
            $db->commit();
            return true;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function shareUrl(array $invoice): string
    {
        return InvoiceQrCode::publicUrlForInvoice($invoice);
    }

    private static function save(int $companyId, int $userId, array $data, ?int $invoiceId = null): array
    {
        $db = Database::getInstance();
        $isUpdate = $invoiceId !== null;

        if (!$isUpdate) {
            $iKey = substr(trim((string)($data['idempotency_key'] ?? '')), 0, 64);
            if ($iKey !== '') {
                $dup = $db->prepare(
                    "SELECT id, invoice_number
                     FROM invoices
                     WHERE idempotency_key = ? AND company_id = ? AND deleted_at IS NULL
                     LIMIT 1"
                );
                $dup->execute([$iKey, $companyId]);
                if ($existing = $dup->fetch()) {
                    return ['id' => (int)$existing['id'], 'number' => $existing['invoice_number'], 'duplicate' => true];
                }
            }
        }

        $db->beginTransaction();
        try {
            $existing = null;
            if ($isUpdate) {
                $existing = self::findForUpdate($invoiceId, $companyId);
                if (!$existing) {
                    throw new \RuntimeException('Накладная не найдена.');
                }
                if ($existing['status'] === 'cancelled') {
                    throw new \RuntimeException('Отменённую накладную нельзя редактировать.');
                }
                self::restoreStock($db, $companyId, $invoiceId);
            }

            $normalized = self::normalizePayload($db, $companyId, $data, $existing);
            $invoiceNumber = $existing['invoice_number'] ?? self::reserveInvoiceNumber($db, $companyId);

            if ($isUpdate) {
                $db->prepare(
                    "UPDATE invoices
                     SET client_id = ?, invoice_date = ?, subtotal = ?, discount = ?, total = ?,
                         paid_amount = ?, debt_amount = ?, payment_status = ?, status = ?, notes = ?,
                         pdf_path = NULL, updated_at = NOW()
                     WHERE id = ? AND company_id = ? AND deleted_at IS NULL"
                )->execute([
                    $normalized['client_id'],
                    $normalized['invoice_date'],
                    $normalized['subtotal'],
                    $normalized['discount'],
                    $normalized['total'],
                    $normalized['paid_amount'],
                    $normalized['debt_amount'],
                    $normalized['payment_status'],
                    $normalized['status'],
                    $normalized['notes'],
                    $invoiceId,
                    $companyId,
                ]);
                $db->prepare("DELETE FROM invoice_items WHERE invoice_id = ?")->execute([$invoiceId]);
                // Keep payment history (incl. debt payments). Only append delta if paid increased.
                self::insertPaymentDeltaIfNeeded(
                    $db,
                    $companyId,
                    $invoiceId,
                    $normalized,
                    $userId,
                    (float)($existing['paid_amount'] ?? 0)
                );
            } else {
                $db->prepare(
                    "INSERT INTO invoices
                     (company_id, client_id, created_by, invoice_number, invoice_date,
                      subtotal, discount, total, paid_amount, debt_amount, payment_status,
                      notes, status, idempotency_key)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                )->execute([
                    $companyId,
                    $normalized['client_id'],
                    $userId,
                    $invoiceNumber,
                    $normalized['invoice_date'],
                    $normalized['subtotal'],
                    $normalized['discount'],
                    $normalized['total'],
                    $normalized['paid_amount'],
                    $normalized['debt_amount'],
                    $normalized['payment_status'],
                    $normalized['notes'],
                    $normalized['status'],
                    $normalized['idempotency_key'],
                ]);
                $invoiceId = (int)$db->lastInsertId();
                self::insertPaymentIfNeeded($db, $companyId, $invoiceId, $normalized, $userId);
            }

            self::insertItemsAndAdjustStock($db, $companyId, $invoiceId, $normalized['items']);

            $db->commit();
            return ['id' => $invoiceId, 'number' => $invoiceNumber, 'duplicate' => false];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private static function normalizePayload(\PDO $db, int $companyId, array $data, ?array $existing = null): array
    {
        $clientId = (int)($data['client_id'] ?? 0);
        if ($clientId > 0) {
            $clientSt = $db->prepare("SELECT id FROM clients WHERE id = ? AND company_id = ? AND status = 'active' LIMIT 1");
            $clientSt->execute([$clientId, $companyId]);
            if (!$clientSt->fetch()) {
                throw new \RuntimeException('Клиент не найден.');
            }
        } else {
            $clientId = null;
        }

        $invoiceDate = trim((string)($data['invoice_date'] ?? date('Y-m-d')));
        if (!self::isValidDate($invoiceDate)) {
            throw new \RuntimeException('Некорректная дата накладной.');
        }

        $requestedStatus = trim((string)($data['status'] ?? ''));
        $requestedStatus = in_array($requestedStatus, self::STATUSES, true) ? $requestedStatus : '';

        $items = self::normalizeItems($db, $companyId, $data['items'] ?? []);
        if (empty($items)) {
            throw new \RuntimeException('Добавьте хотя бы один товар.');
        }

        $subtotal = '0.00';
        foreach ($items as $item) {
            $subtotal = bcadd($subtotal, $item['line_total'], 2);
        }

        $discount = min((float)($data['discount'] ?? 0), (float)$subtotal);
        if ($discount < 0) {
            throw new \RuntimeException('Скидка не может быть отрицательной.');
        }

        $total = bcsub($subtotal, self::decimal($discount), 2);
        $paidAmount = max(0.0, (float)($data['paid_amount'] ?? 0));
        $paidAmount = min($paidAmount, (float)$total);
        $debtAmount = max(0.0, (float)bcsub($total, self::decimal($paidAmount), 2));

        $paymentMethod = trim((string)($data['payment_method'] ?? 'cash'));
        if (!in_array($paymentMethod, self::PAYMENT_METHODS, true)) {
            $paymentMethod = 'cash';
        }

        $statusData = self::resolveStatuses($requestedStatus, $total, $paidAmount, $debtAmount);

        return [
            'client_id' => $clientId,
            'invoice_date' => $invoiceDate,
            'items' => $items,
            'subtotal' => $subtotal,
            'discount' => self::decimal($discount),
            'total' => $total,
            'paid_amount' => self::decimal($paidAmount),
            'debt_amount' => self::decimal($debtAmount),
            'payment_status' => $statusData['payment_status'],
            'status' => $statusData['status'],
            'payment_method' => $paymentMethod,
            'notes' => trim((string)($data['notes'] ?? '')),
            'idempotency_key' => substr(trim((string)($data['idempotency_key'] ?? '')), 0, 64) ?: null,
        ];
    }

    private static function normalizeItems(\PDO $db, int $companyId, array $rawItems): array
    {
        $items = [];
        foreach ($rawItems as $index => $item) {
            $productId = (int)($item['product_id'] ?? 0);
            $quantity = (float)($item['quantity'] ?? 0);
            $quantity = round($quantity, 3);
            if ($quantity <= 0) {
                throw new \RuntimeException('Количество товара должно быть больше 0.');
            }

            $lineDiscount = max(0.0, (float)($item['discount'] ?? 0));
            if ($productId <= 0) {
                throw new \RuntimeException('Выберите товар для каждой позиции.');
            }

            $prodSt = $db->prepare(
                "SELECT id, name, unit, sale_price, stock_quantity
                 FROM products
                 WHERE id = ? AND company_id = ? AND status = 'active'
                 LIMIT 1"
            );
            $prodSt->execute([$productId, $companyId]);
            $product = $prodSt->fetch();
            if (!$product) {
                throw new \RuntimeException('Один из товаров не найден.');
            }

            $unitPrice = isset($item['unit_price']) ? (float)$item['unit_price'] : (float)$product['sale_price'];
            if ($unitPrice < 0) {
                throw new \RuntimeException('Цена товара не может быть отрицательной.');
            }

            $availableStock = (float)$product['stock_quantity'];
            if ($quantity > $availableStock) {
                throw new \RuntimeException('Недостаточно остатка для товара: ' . $product['name']);
            }

            $lineSubtotal = bcmul(self::decimal($quantity, 3), self::decimal($unitPrice), 2);
            $lineTotal = max(0.0, (float)bcsub($lineSubtotal, self::decimal($lineDiscount), 2));

            $items[] = [
                'product_id' => $productId,
                'product_name' => $product['name'],
                'unit' => $product['unit'],
                'quantity' => self::decimal($quantity, 3),
                'unit_price' => self::decimal($unitPrice),
                'discount' => self::decimal($lineDiscount),
                'line_total' => self::decimal($lineTotal),
            ];
        }

        return $items;
    }

    private static function insertItemsAndAdjustStock(\PDO $db, int $companyId, int $invoiceId, array $items): void
    {
        $insItem = $db->prepare(
            "INSERT INTO invoice_items
             (invoice_id, product_id, product_name, unit, quantity, unit_price, discount, line_total)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $updateStock = $db->prepare(
            "UPDATE products
             SET stock_quantity = stock_quantity - ?
             WHERE id = ? AND company_id = ? AND stock_quantity >= ?"
        );

        foreach ($items as $item) {
            $insItem->execute([
                $invoiceId,
                $item['product_id'],
                $item['product_name'],
                $item['unit'],
                $item['quantity'],
                $item['unit_price'],
                $item['discount'],
                $item['line_total'],
            ]);

            $updateStock->execute([
                $item['quantity'],
                $item['product_id'],
                $companyId,
                $item['quantity'],
            ]);
            if ($updateStock->rowCount() !== 1) {
                throw new \RuntimeException('Не удалось списать остаток товара.');
            }
        }
    }

    private static function insertPaymentIfNeeded(\PDO $db, int $companyId, int $invoiceId, array $data, int $userId): void
    {
        if ((float)$data['paid_amount'] <= 0) {
            return;
        }

        $db->prepare(
            "INSERT INTO payments
             (company_id, invoice_id, client_id, amount, payment_method, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $companyId,
            $invoiceId,
            $data['client_id'],
            $data['paid_amount'],
            $data['payment_method'],
            $data['notes'] !== '' ? $data['notes'] : null,
            $userId,
        ]);
    }

    /** On edit: append only positive paid delta; never wipe debt payment history. */
    private static function insertPaymentDeltaIfNeeded(
        \PDO $db,
        int $companyId,
        int $invoiceId,
        array $data,
        int $userId,
        float $previousPaid
    ): void {
        $newPaid = (float)$data['paid_amount'];
        $delta = round($newPaid - $previousPaid, 2);
        if ($delta <= 0.001) {
            return;
        }

        $db->prepare(
            "INSERT INTO payments
             (company_id, invoice_id, client_id, amount, payment_method, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $companyId,
            $invoiceId,
            $data['client_id'],
            self::decimal($delta),
            $data['payment_method'],
            'Корректировка оплаты при редактировании',
            $userId,
        ]);
    }

    private static function reserveInvoiceNumber(\PDO $db, int $companyId): string
    {
        $lock = $db->prepare("SELECT next_invoice_number FROM companies WHERE id = ? FOR UPDATE");
        $lock->execute([$companyId]);
        $next = (int)$lock->fetchColumn();
        if ($next <= 0) {
            $next = 1;
        }

        $number = 'NK-' . str_pad((string)$next, 6, '0', STR_PAD_LEFT);
        $db->prepare("UPDATE companies SET next_invoice_number = next_invoice_number + 1 WHERE id = ?")
            ->execute([$companyId]);

        return $number;
    }

    private static function restoreStock(\PDO $db, int $companyId, int $invoiceId): void
    {
        $items = $db->prepare("SELECT product_id, quantity FROM invoice_items WHERE invoice_id = ?");
        $items->execute([$invoiceId]);
        $restore = $db->prepare(
            "UPDATE products
             SET stock_quantity = stock_quantity + ?
             WHERE id = ? AND company_id = ?"
        );

        foreach ($items->fetchAll() as $item) {
            if ((int)$item['product_id'] <= 0) {
                continue;
            }
            $restore->execute([$item['quantity'], $item['product_id'], $companyId]);
        }
    }

    private static function findForUpdate(int $invoiceId, int $companyId): ?array
    {
        $db = Database::getInstance();
        $st = $db->prepare(
            "SELECT *
             FROM invoices
             WHERE id = ? AND company_id = ? AND deleted_at IS NULL
             LIMIT 1
             FOR UPDATE"
        );
        $st->execute([$invoiceId, $companyId]);
        return $st->fetch() ?: null;
    }

    private static function resolveStatuses(string $requestedStatus, string $total, float $paidAmount, float $debtAmount): array
    {
        if ($requestedStatus === 'cancelled') {
            return ['status' => 'cancelled', 'payment_status' => 'unpaid'];
        }

        if ($requestedStatus === 'draft') {
            return ['status' => 'draft', 'payment_status' => 'unpaid'];
        }

        if ((float)$total <= 0.0) {
            return ['status' => 'draft', 'payment_status' => 'unpaid'];
        }

        if ($paidAmount >= (float)$total) {
            return ['status' => 'paid', 'payment_status' => 'paid'];
        }

        if ($paidAmount > 0.0 && $debtAmount > 0.0) {
            return ['status' => 'partial', 'payment_status' => 'partial'];
        }

        return ['status' => 'unpaid', 'payment_status' => 'unpaid'];
    }

    private static function isValidDate(string $date): bool
    {
        if ($date === '') {
            return false;
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $date);
        return $dt !== false && $dt->format('Y-m-d') === $date;
    }

    private static function decimal(float|int|string $value, int $scale = 2): string
    {
        return number_format((float)$value, $scale, '.', '');
    }
}

<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Helpers\Csrf;
use App\Middleware\CompanyMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Middleware\SubscriptionMiddleware;
use App\Services\ActivityLogger;

class DebtController
{
    public function index(): void
    {
        CompanyMiddleware::check();
        PermissionMiddleware::require('debts.view');
        $companyId = CompanyMiddleware::companyId();
        $db        = Database::getInstance();
        $search    = trim($_GET['q'] ?? '');

        $where  = "c.company_id = ? AND c.status='active'";
        $params = [$companyId];
        if ($search !== '') {
            $where   .= " AND (c.name LIKE ? OR c.phone LIKE ?)";
            $like     = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $debtors = $db->prepare(
            "SELECT c.*,
               COALESCE(SUM(i.debt_amount), 0) + c.opening_debt AS total_debt
             FROM clients c
             LEFT JOIN invoices i ON i.client_id=c.id
                AND i.deleted_at IS NULL
                AND i.status IN ('unpaid', 'partial')
                AND i.debt_amount > 0
             WHERE {$where}
             GROUP BY c.id
             HAVING total_debt > 0
             ORDER BY total_debt DESC"
        );
        $debtors->execute($params);
        $debtors = $debtors->fetchAll();

        $totalDebt = array_sum(array_column($debtors, 'total_debt'));

        $pageTitle = 'Долги';
        ob_start();
        require ROOT_DIR . '/views/debts/index.php';
        $content = ob_get_clean();
        require ROOT_DIR . '/views/layouts/app.php';
    }

    public function show(string $clientId): void
    {
        CompanyMiddleware::check();
        PermissionMiddleware::require('debts.view');
        $companyId = CompanyMiddleware::companyId();
        $db        = Database::getInstance();

        $client = $db->prepare("SELECT * FROM clients WHERE id=? AND company_id=? LIMIT 1");
        $client->execute([(int)$clientId, $companyId]);
        $client = $client->fetch();
        if (!$client) { http_response_code(404); require ROOT_DIR . '/views/errors/404.php'; return; }

        $invoices = $db->prepare(
            "SELECT * FROM invoices
             WHERE client_id=? AND company_id=?
               AND deleted_at IS NULL
               AND status IN ('unpaid', 'partial')
             ORDER BY invoice_date DESC"
        );
        $invoices->execute([(int)$clientId, $companyId]);
        $invoices = $invoices->fetchAll();

        $payments = $db->prepare(
            "SELECT p.*, i.invoice_number FROM payments p JOIN invoices i ON i.id=p.invoice_id WHERE p.client_id=? AND p.company_id=? ORDER BY p.created_at DESC LIMIT 20"
        );
        $payments->execute([(int)$clientId, $companyId]);
        $payments = $payments->fetchAll();

        $totalDebt = (float)$client['opening_debt'];
        foreach ($invoices as $inv) {
            $totalDebt += (float)$inv['debt_amount'];
        }

        $error = $_SESSION['debt_error'] ?? null;
        unset($_SESSION['debt_error']);
        $flash = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        $pageTitle = 'Долг: ' . htmlspecialchars($client['name']);
        ob_start();
        require ROOT_DIR . '/views/debts/show.php';
        $content = ob_get_clean();
        require ROOT_DIR . '/views/layouts/app.php';
    }

    public function pay(): void
    {
        CompanyMiddleware::check();
        PermissionMiddleware::require('debts.manage');
        PermissionMiddleware::require('payments.create');
        SubscriptionMiddleware::requireWrite();
        Csrf::verifyRequest();

        $companyId = CompanyMiddleware::companyId();
        $userId    = $_SESSION['user_id'];
        $db        = Database::getInstance();

        $invoiceId = (int)($_POST['invoice_id'] ?? 0);
        $amount    = (float)($_POST['amount'] ?? 0);
        $method    = $_POST['payment_method'] ?? 'cash';
        $allowedMethods = ['cash', 'card', 'bank', 'transfer', 'other'];
        if (!in_array($method, $allowedMethods)) $method = 'cash';

        if ($amount <= 0) {
            $_SESSION['debt_error'] = 'Сумма должна быть больше 0.';
            Response::redirect('/debts/' . ($_POST['client_id'] ?? 0));
        }

        // Verify invoice belongs to company
        $invoice = $db->prepare(
            "SELECT * FROM invoices
             WHERE id=? AND company_id=? AND deleted_at IS NULL AND status IN ('unpaid', 'partial')
             LIMIT 1"
        );
        $invoice->execute([$invoiceId, $companyId]);
        $invoice = $invoice->fetch();
        if (!$invoice) {
            $_SESSION['debt_error'] = 'Накладная не найдена.';
            Response::redirect('/debts');
        }

        // Prevent overpayment
        $maxPay = (float)$invoice['debt_amount'];
        if ($amount > $maxPay) {
            $_SESSION['debt_error'] = 'Сумма превышает долг по накладной (' . number_format($maxPay, 2) . ' с.).';
            Response::redirect('/debts/' . ($invoice['client_id'] ?? 0));
        }

        $db->beginTransaction();
        try {
            // Create payment
            $db->prepare(
                "INSERT INTO payments (company_id, invoice_id, client_id, amount, payment_method, created_by)
                 VALUES (?, ?, ?, ?, ?, ?)"
            )->execute([$companyId, $invoiceId, $invoice['client_id'], $amount, $method, $userId]);

            // Update invoice
            $newPaid = bcadd((string)$invoice['paid_amount'], (string)$amount, 2);
            $newDebt = bcsub((string)$invoice['total'], $newPaid, 2);
            $newDebt = max(0, (float)$newDebt);

            $status = match(true) {
                $newDebt <= 0              => 'paid',
                (float)$newPaid > 0        => 'partial',
                default                    => 'unpaid',
            };

            $db->prepare(
                "UPDATE invoices SET paid_amount=?, debt_amount=?, payment_status=?, status=?, updated_at=NOW() WHERE id=?"
            )->execute([$newPaid, $newDebt, $status, $status, $invoiceId]);

            $db->commit();
            ActivityLogger::log('debt_payment', $companyId, $userId, 'invoice', $invoiceId, ['amount' => $amount]);
            $_SESSION['flash_success'] = 'Оплата ' . number_format($amount, 2) . ' с. принята.';
        } catch (\Throwable $e) {
            $db->rollBack();
            \App\Core\Logger::error('Debt payment failed: ' . $e->getMessage());
            $_SESSION['debt_error'] = 'Ошибка при обработке платежа.';
        }

        Response::redirect('/debts/' . ($invoice['client_id'] ?? 0));
    }
}

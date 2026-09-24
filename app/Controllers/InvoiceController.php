<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Helpers\Csrf;
use App\Helpers\Validator;
use App\Middleware\CompanyMiddleware;
use App\Middleware\SubscriptionMiddleware;
use App\Models\Client;
use App\Models\Invoice;
use App\Services\ActivityLogger;
use App\Services\PdfService;

class InvoiceController
{
    public function index(): void
    {
        CompanyMiddleware::check();
        $companyId = CompanyMiddleware::companyId();
        $search = trim($_GET['q'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $clientId = (int)($_GET['client_id'] ?? 0);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $dateFrom = trim($_GET['date_from'] ?? '');
        $dateTo = trim($_GET['date_to'] ?? '');
        $result = Invoice::all($companyId, $search, $status, $clientId, $page, 20, $dateFrom, $dateTo);

        $pageTitle = 'Накладные';
        extract($result);
        ob_start();
        require ROOT_DIR . '/views/invoices/index.php';
        $content = ob_get_clean();
        require ROOT_DIR . '/views/layouts/app.php';
    }

    public function create(): void
    {
        CompanyMiddleware::check();
        SubscriptionMiddleware::check();

        $pageTitle = 'Новая накладная';
        $preClientId = (int)($_GET['client_id'] ?? 0);
        $invoice = null;
        $items = [];
        $old = $_SESSION['invoice_old'] ?? [];
        $error = $_SESSION['invoice_error'] ?? null;
        unset($_SESSION['invoice_old'], $_SESSION['invoice_error']);
        ob_start();
        require ROOT_DIR . '/views/invoices/create.php';
        $content = ob_get_clean();
        require ROOT_DIR . '/views/layouts/app.php';
    }

    public function edit(string $id): void
    {
        CompanyMiddleware::check();
        SubscriptionMiddleware::check();

        $companyId = CompanyMiddleware::companyId();
        $invoice = Invoice::findForCompany((int)$id, $companyId);
        if (!$invoice) {
            http_response_code(404);
            require ROOT_DIR . '/views/errors/404.php';
            return;
        }

        $pageTitle = 'Редактировать накладную';
        $preClientId = (int)($invoice['client_id'] ?? 0);
        $items = Invoice::getItems((int)$id);
        $old = $_SESSION['invoice_old'] ?? [];
        $error = $_SESSION['invoice_error'] ?? null;
        unset($_SESSION['invoice_old'], $_SESSION['invoice_error']);
        ob_start();
        require ROOT_DIR . '/views/invoices/create.php';
        $content = ob_get_clean();
        require ROOT_DIR . '/views/layouts/app.php';
    }

    public function store(): void
    {
        CompanyMiddleware::check();
        SubscriptionMiddleware::requireWrite();
        Csrf::verifyRequest();

        $companyId = CompanyMiddleware::companyId();
        $userId = (int)$_SESSION['user_id'];
        $data = $this->payloadFromRequest();

        try {
            $result = Invoice::create($companyId, $userId, $data);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Invoice create failed: ' . $e->getMessage());
            $_SESSION['invoice_error'] = $e->getMessage();
            $_SESSION['invoice_old'] = $_POST;
            Response::redirect('/invoices/create');
        }

        ActivityLogger::log('invoice_created', $companyId, $userId, 'invoice', $result['id']);
        $_SESSION['flash_success'] = 'Накладная ' . ($result['number'] ?? '') . ' создана!';
        Response::redirect('/invoices/' . $result['id']);
    }

    public function update(string $id): void
    {
        CompanyMiddleware::check();
        SubscriptionMiddleware::requireWrite();
        Csrf::verifyRequest();

        $companyId = CompanyMiddleware::companyId();
        $userId = (int)$_SESSION['user_id'];
        $data = $this->payloadFromRequest();

        try {
            $result = Invoice::update((int)$id, $companyId, $userId, $data);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Invoice update failed: ' . $e->getMessage());
            $_SESSION['invoice_error'] = $e->getMessage();
            $_SESSION['invoice_old'] = $_POST;
            Response::redirect('/invoices/' . (int)$id . '/edit');
        }

        ActivityLogger::log('invoice_updated', $companyId, $userId, 'invoice', $result['id']);
        $_SESSION['flash_success'] = 'Накладная обновлена.';
        Response::redirect('/invoices/' . $result['id']);
    }

    public function show(string $id): void
    {
        CompanyMiddleware::check();
        $companyId = CompanyMiddleware::companyId();
        $invoice = Invoice::findForCompany((int)$id, $companyId);
        if (!$invoice) { http_response_code(404); require ROOT_DIR . '/views/errors/404.php'; return; }

        $items = Invoice::getItems((int)$id);
        $payments = Invoice::getPayments((int)$id);
        $pageTitle = 'Накладная ' . $invoice['invoice_number'];
        ob_start();
        require ROOT_DIR . '/views/invoices/show.php';
        $content = ob_get_clean();
        require ROOT_DIR . '/views/layouts/app.php';
    }

    public function pdf(string $id): void
    {
        CompanyMiddleware::check();
        $companyId = CompanyMiddleware::companyId();
        $invoice = Invoice::findForCompany((int)$id, $companyId);
        if (!$invoice) { http_response_code(404); die('Not found'); }

        $items = Invoice::getItems((int)$id);
        $db = Database::getInstance();
        $company = $db->prepare("SELECT * FROM companies WHERE id=? LIMIT 1");
        $company->execute([$companyId]);
        $company = $company->fetch();

        $pdf = new PdfService();
        $pdf->generate($invoice, $items, $company);
    }

    public function print(string $id): void
    {
        $this->pdf($id);
    }

    public function duplicate(string $id): void
    {
        CompanyMiddleware::check();
        SubscriptionMiddleware::requireWrite();
        Csrf::verifyRequest();

        $companyId = CompanyMiddleware::companyId();
        $userId = (int)$_SESSION['user_id'];
        try {
            $result = Invoice::duplicate((int)$id, $companyId, $userId);
            ActivityLogger::log('invoice_duplicated', $companyId, $userId, 'invoice', $result['id']);
            $_SESSION['flash_success'] = 'Накладная дублирована.';
            Response::redirect('/invoices/' . $result['id'] . '/edit');
        } catch (\Throwable $e) {
            $_SESSION['invoice_error'] = $e->getMessage();
            Response::redirect('/invoices/' . (int)$id);
        }
    }

    public function cancel(string $id): void
    {
        CompanyMiddleware::check();
        SubscriptionMiddleware::requireWrite();
        Csrf::verifyRequest();

        $companyId = CompanyMiddleware::companyId();
        $userId = (int)$_SESSION['user_id'];
        try {
            Invoice::cancel((int)$id, $companyId, $userId);
            ActivityLogger::log('invoice_cancelled', $companyId, $userId, 'invoice', (int)$id);
            $_SESSION['flash_success'] = 'Накладная отменена.';
        } catch (\Throwable $e) {
            $_SESSION['invoice_error'] = $e->getMessage();
        }
        Response::redirect('/invoices/' . (int)$id);
    }

    public function destroy(string $id): void
    {
        CompanyMiddleware::check();
        SubscriptionMiddleware::requireWrite();
        Csrf::verifyRequest();

        $companyId = CompanyMiddleware::companyId();
        $userId = (int)$_SESSION['user_id'];
        try {
            Invoice::softDelete((int)$id, $companyId, $userId);
            ActivityLogger::log('invoice_deleted', $companyId, $userId, 'invoice', (int)$id);
            $_SESSION['flash_success'] = 'Накладная удалена.';
            Response::redirect('/invoices');
        } catch (\Throwable $e) {
            $_SESSION['invoice_error'] = $e->getMessage();
            Response::redirect('/invoices/' . (int)$id);
        }
    }

    public function apiIndex(): void
    {
        CompanyMiddleware::check();
        $companyId = CompanyMiddleware::companyId();
        Response::json(Invoice::all(
            $companyId,
            trim($_GET['q'] ?? ''),
            trim($_GET['status'] ?? ''),
            (int)($_GET['client_id'] ?? 0),
            max(1, (int)($_GET['page'] ?? 1)),
            min(100, max(1, (int)($_GET['per_page'] ?? 20))),
            trim($_GET['date_from'] ?? ''),
            trim($_GET['date_to'] ?? '')
        ));
    }

    public function apiShow(string $id): void
    {
        CompanyMiddleware::check();
        $companyId = CompanyMiddleware::companyId();
        $invoice = Invoice::findForCompany((int)$id, $companyId);
        if (!$invoice) {
            Response::json(['error' => 'Not found'], 404);
        }

        Response::json([
            'invoice' => $invoice,
            'items' => Invoice::getItems((int)$id),
            'payments' => Invoice::getPayments((int)$id),
            'share_url' => Invoice::shareUrl($invoice),
        ]);
    }

    public function apiStore(): void
    {
        CompanyMiddleware::check();
        SubscriptionMiddleware::requireWrite();
        Csrf::verifyRequest();

        $companyId = CompanyMiddleware::companyId();
        $userId = (int)$_SESSION['user_id'];
        try {
            $payload = $this->payloadFromJsonRequest();
            $result = Invoice::create($companyId, $userId, $payload);
            ActivityLogger::log('invoice_created_api', $companyId, $userId, 'invoice', $result['id']);
            Response::json(['ok' => true] + $result, 201);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 422);
        }
    }

    public function apiUpdate(string $id): void
    {
        CompanyMiddleware::check();
        SubscriptionMiddleware::requireWrite();
        Csrf::verifyRequest();

        $companyId = CompanyMiddleware::companyId();
        $userId = (int)$_SESSION['user_id'];
        try {
            $result = Invoice::update((int)$id, $companyId, $userId, $this->payloadFromJsonRequest());
            ActivityLogger::log('invoice_updated_api', $companyId, $userId, 'invoice', $result['id']);
            Response::json(['ok' => true] + $result);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 422);
        }
    }

    public function apiDelete(string $id): void
    {
        CompanyMiddleware::check();
        SubscriptionMiddleware::requireWrite();
        Csrf::verifyRequest();

        $companyId = CompanyMiddleware::companyId();
        $userId = (int)$_SESSION['user_id'];
        try {
            Invoice::softDelete((int)$id, $companyId, $userId);
            ActivityLogger::log('invoice_deleted_api', $companyId, $userId, 'invoice', (int)$id);
            Response::json(['ok' => true]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 422);
        }
    }

    public function apiCancel(string $id): void
    {
        CompanyMiddleware::check();
        SubscriptionMiddleware::requireWrite();
        Csrf::verifyRequest();

        $companyId = CompanyMiddleware::companyId();
        $userId = (int)$_SESSION['user_id'];
        try {
            Invoice::cancel((int)$id, $companyId, $userId);
            ActivityLogger::log('invoice_cancelled_api', $companyId, $userId, 'invoice', (int)$id);
            Response::json(['ok' => true]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 422);
        }
    }

    public function apiDuplicate(string $id): void
    {
        CompanyMiddleware::check();
        SubscriptionMiddleware::requireWrite();
        Csrf::verifyRequest();

        $companyId = CompanyMiddleware::companyId();
        $userId = (int)$_SESSION['user_id'];
        try {
            $result = Invoice::duplicate((int)$id, $companyId, $userId);
            ActivityLogger::log('invoice_duplicated_api', $companyId, $userId, 'invoice', $result['id']);
            Response::json(['ok' => true] + $result, 201);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 422);
        }
    }

    public function apiShare(string $id): void
    {
        CompanyMiddleware::check();
        $companyId = CompanyMiddleware::companyId();
        $invoice = Invoice::findForCompany((int)$id, $companyId);
        if (!$invoice) {
            Response::json(['error' => 'Not found'], 404);
        }

        Response::json([
            'ok' => true,
            'share_url' => Invoice::shareUrl($invoice),
        ]);
    }

    public function apiCreateClient(): void
    {
        CompanyMiddleware::check();
        SubscriptionMiddleware::requireWrite();
        Csrf::verifyRequest();

        $companyId = CompanyMiddleware::companyId();
        $payload = $this->payloadFromJsonRequest();
        $validator = Validator::make($payload)
            ->required('name', 'Имя клиента')
            ->maxLen('name', 200, 'Имя клиента')
            ->phone('phone')
            ->maxLen('address', 500, 'Адрес');

        if (!$validator->isValid()) {
            Response::json(['error' => $validator->firstError()], 422);
        }

        $phone = trim((string)($payload['phone'] ?? ''));
        if ($phone !== '') {
            $phone = Validator::normalizePhone($phone);
        }

        $clientId = Client::create($companyId, [
            'name' => trim((string)$payload['name']),
            'phone' => $phone,
            'address' => trim((string)($payload['address'] ?? '')),
            'opening_debt' => '0',
        ]);

        Response::json([
            'ok' => true,
            'client' => Client::findForCompany($clientId, $companyId),
        ], 201);
    }

    private function payloadFromRequest(): array
    {
        $items = json_decode((string)($_POST['items_json'] ?? '[]'), true);
        if (!is_array($items)) {
            $items = [];
        }

        return [
            'client_id' => (int)($_POST['client_id'] ?? 0),
            'items' => $items,
            'discount' => (float)($_POST['discount'] ?? 0),
            'paid_amount' => (float)($_POST['paid_amount'] ?? 0),
            'payment_method' => trim((string)($_POST['payment_method'] ?? 'cash')),
            'notes' => trim((string)($_POST['notes'] ?? '')),
            'invoice_date' => trim((string)($_POST['invoice_date'] ?? date('Y-m-d'))),
            'status' => trim((string)($_POST['status'] ?? '')),
            'idempotency_key' => trim((string)($_POST['idempotency_key'] ?? '')),
        ];
    }

    private function payloadFromJsonRequest(): array
    {
        $input = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = [];
        }
        return $input;
    }
}

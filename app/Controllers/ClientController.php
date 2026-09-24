<?php

namespace App\Controllers;

use App\Core\Response;
use App\Helpers\Csrf;
use App\Helpers\Validator;
use App\Middleware\CompanyMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Middleware\SubscriptionMiddleware;
use App\Models\Client;
use App\Services\ActivityLogger;

class ClientController
{
    public function index(): void
    {
        CompanyMiddleware::check();
        PermissionMiddleware::require('clients.view');
        $companyId = CompanyMiddleware::companyId();
        $search    = trim($_GET['q'] ?? '');
        $status    = trim($_GET['status'] ?? '');
        $page      = max(1, (int)($_GET['page'] ?? 1));
        $result    = Client::all($companyId, $search, $page, 20, $status);

        $pageTitle = 'Клиенты';
        extract($result);
        ob_start();
        require ROOT_DIR . '/views/clients/index.php';
        $content = ob_get_clean();
        require ROOT_DIR . '/views/layouts/app.php';
    }

    public function create(): void
    {
        CompanyMiddleware::check();
        PermissionMiddleware::require('clients.create');
        SubscriptionMiddleware::check();
        $pageTitle = 'Новый клиент';
        $error     = $_SESSION['client_error'] ?? null;
        $old       = $_SESSION['client_old'] ?? [];
        unset($_SESSION['client_error'], $_SESSION['client_old']);
        ob_start();
        require ROOT_DIR . '/views/clients/create.php';
        $content = ob_get_clean();
        require ROOT_DIR . '/views/layouts/app.php';
    }

    public function store(): void
    {
        CompanyMiddleware::check();
        PermissionMiddleware::require('clients.create');
        SubscriptionMiddleware::requireWrite();
        Csrf::verifyRequest();

        $companyId = CompanyMiddleware::companyId();
        $v = Validator::make($_POST)
            ->required('name', 'Имя клиента')
            ->maxLen('name', 200, 'Имя клиента')
            ->phone('phone')
            ->maxLen('address', 500, 'Адрес')
            ->numeric('opening_debt', 'Начальный долг')
            ->min('opening_debt', 0, 'Начальный долг');

        if (!$v->isValid()) {
            $_SESSION['client_error'] = $v->firstError();
            $_SESSION['client_old']   = $_POST;
            Response::redirect('/clients/create');
        }

        $phone = trim((string)($_POST['phone'] ?? ''));
        if ($phone !== '') {
            $phone = Validator::normalizePhone($phone);
        }

        $id = Client::create($companyId, [
            'name'         => trim((string)$_POST['name']),
            'phone'        => $phone,
            'address'      => trim((string)($_POST['address'] ?? '')),
            'opening_debt' => $_POST['opening_debt'] ?? '0',
        ]);

        ActivityLogger::log('client_created', $companyId, (int)$_SESSION['user_id'], 'client', $id);
        $_SESSION['flash_success'] = 'Клиент добавлен.';
        Response::redirect('/clients/' . $id);
    }

    public function show(string $id): void
    {
        CompanyMiddleware::check();
        PermissionMiddleware::require('clients.view');
        $companyId = CompanyMiddleware::companyId();
        $client    = Client::findForCompany((int)$id, $companyId);
        if (!$client) {
            http_response_code(404);
            require ROOT_DIR . '/views/errors/404.php';
            return;
        }

        $invoices  = Client::getInvoices((int)$id, $companyId);
        $pageTitle = $client['name'];
        ob_start();
        require ROOT_DIR . '/views/clients/show.php';
        $content = ob_get_clean();
        require ROOT_DIR . '/views/layouts/app.php';
    }

    public function edit(string $id): void
    {
        CompanyMiddleware::check();
        PermissionMiddleware::require('clients.edit');
        SubscriptionMiddleware::check();
        $error     = $_SESSION['client_error'] ?? null;
        unset($_SESSION['client_error']);
        ob_start();
        require ROOT_DIR . '/views/clients/edit.php';
        $content = ob_get_clean();
        require ROOT_DIR . '/views/layouts/app.php';
    }

    public function update(string $id): void
    {
        CompanyMiddleware::check();
        PermissionMiddleware::require('clients.edit');
        SubscriptionMiddleware::requireWrite();
        Csrf::verifyRequest();

        $companyId = CompanyMiddleware::companyId();
        $client    = Client::findForCompany((int)$id, $companyId);
        if (!$client) {
            http_response_code(404);
            require ROOT_DIR . '/views/errors/404.php';
            return;
        }

        $v = Validator::make($_POST)
            ->required('name', 'Имя клиента')
            ->maxLen('name', 200, 'Имя клиента')
            ->phone('phone')
            ->maxLen('address', 500, 'Адрес')
            ->numeric('opening_debt', 'Начальный долг')
            ->min('opening_debt', 0, 'Начальный долг');

        if (!$v->isValid()) {
            $_SESSION['client_error'] = $v->firstError();
            Response::redirect('/clients/' . $id . '/edit');
        }

        $status = $_POST['status'] ?? 'active';
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        $phone = trim((string)($_POST['phone'] ?? ''));
        if ($phone !== '') {
            $phone = Validator::normalizePhone($phone);
        }

        $ok = Client::update((int)$id, $companyId, [
            'name'         => trim((string)$_POST['name']),
            'phone'        => $phone,
            'address'      => trim((string)($_POST['address'] ?? '')),
            'opening_debt' => $_POST['opening_debt'] ?? '0',
            'status'       => $status,
        ]);

        if (!$ok) {
            http_response_code(404);
            require ROOT_DIR . '/views/errors/404.php';
            return;
        }

        ActivityLogger::log('client_updated', $companyId, (int)$_SESSION['user_id'], 'client', (int)$id);
        $_SESSION['flash_success'] = 'Клиент обновлён.';
        Response::redirect('/clients/' . $id);
    }

    public function search(): void
    {
        CompanyMiddleware::check();
        PermissionMiddleware::require('clients.view');
        $companyId = CompanyMiddleware::companyId();
        $q         = trim($_GET['q'] ?? '');
        if ($q === '') {
            Response::json([]);
        }
        Response::json(Client::search($companyId, $q));
    }
}

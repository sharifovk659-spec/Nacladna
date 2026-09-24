<?php

namespace App\Controllers;

use App\Core\Response;
use App\Helpers\Csrf;
use App\Helpers\Validator;
use App\Middleware\CompanyMiddleware;
use App\Middleware\SubscriptionMiddleware;
use App\Models\Product;
use App\Services\ActivityLogger;

class ProductController
{
    public function index(): void
    {
        CompanyMiddleware::check();
        $companyId = CompanyMiddleware::companyId();
        $search    = trim($_GET['q'] ?? '');
        $status    = trim($_GET['status'] ?? '');
        $page      = max(1, (int)($_GET['page'] ?? 1));
        $result    = Product::all($companyId, $search, $page, 20, $status);

        $pageTitle = 'Товары';
        extract($result);
        ob_start();
        require ROOT_DIR . '/views/products/index.php';
        $content = ob_get_clean();
        require ROOT_DIR . '/views/layouts/app.php';
    }

    public function create(): void
    {
        CompanyMiddleware::check();
        SubscriptionMiddleware::check();
        $pageTitle = 'Новый товар';
        $error     = $_SESSION['product_error'] ?? null;
        $old       = $_SESSION['product_old'] ?? [];
        unset($_SESSION['product_error'], $_SESSION['product_old']);
        ob_start();
        require ROOT_DIR . '/views/products/create.php';
        $content = ob_get_clean();
        require ROOT_DIR . '/views/layouts/app.php';
    }

    public function store(): void
    {
        CompanyMiddleware::check();
        SubscriptionMiddleware::requireWrite();
        Csrf::verifyRequest();

        $companyId = CompanyMiddleware::companyId();
        $v = Validator::make($_POST)
            ->required('name', 'Название товара')
            ->maxLen('name', 200, 'Название товара')
            ->maxLen('sku', 100, 'SKU')
            ->maxLen('barcode', 100, 'Штрихкод')
            ->numeric('sale_price', 'Цена продажи')
            ->min('sale_price', 0, 'Цена продажи')
            ->numeric('purchase_price', 'Цена покупки')
            ->min('purchase_price', 0, 'Цена покупки')
            ->numeric('stock_quantity', 'Остаток')
            ->min('stock_quantity', 0, 'Остаток');

        if (!$v->isValid()) {
            $_SESSION['product_error'] = $v->firstError();
            $_SESSION['product_old']   = $_POST;
            Response::redirect('/products/create');
        }

        $id = Product::create($companyId, [
            'name'           => trim((string)$_POST['name']),
            'sku'            => trim((string)($_POST['sku'] ?? '')),
            'barcode'        => trim((string)($_POST['barcode'] ?? '')),
            'unit'           => (string)($_POST['unit'] ?? 'шт'),
            'purchase_price' => trim((string)($_POST['purchase_price'] ?? '')),
            'sale_price'     => $_POST['sale_price'],
            'stock_quantity' => $_POST['stock_quantity'] ?? '0',
        ]);

        ActivityLogger::log('product_created', $companyId, (int)$_SESSION['user_id'], 'product', $id);
        $_SESSION['flash_success'] = 'Товар добавлен.';
        Response::redirect('/products');
    }

    public function edit(string $id): void
    {
        CompanyMiddleware::check();
        SubscriptionMiddleware::check();
        $companyId = CompanyMiddleware::companyId();
        $product   = Product::findForCompany((int)$id, $companyId);
        if (!$product) {
            http_response_code(404);
            require ROOT_DIR . '/views/errors/404.php';
            return;
        }

        $pageTitle = 'Редактировать товар';
        $error     = $_SESSION['product_error'] ?? null;
        unset($_SESSION['product_error']);
        ob_start();
        require ROOT_DIR . '/views/products/edit.php';
        $content = ob_get_clean();
        require ROOT_DIR . '/views/layouts/app.php';
    }

    public function update(string $id): void
    {
        CompanyMiddleware::check();
        SubscriptionMiddleware::requireWrite();
        Csrf::verifyRequest();

        $companyId = CompanyMiddleware::companyId();
        $product   = Product::findForCompany((int)$id, $companyId);
        if (!$product) {
            http_response_code(404);
            require ROOT_DIR . '/views/errors/404.php';
            return;
        }

        $v = Validator::make($_POST)
            ->required('name', 'Название товара')
            ->maxLen('name', 200, 'Название товара')
            ->maxLen('sku', 100, 'SKU')
            ->maxLen('barcode', 100, 'Штрихкод')
            ->numeric('sale_price', 'Цена продажи')
            ->min('sale_price', 0, 'Цена продажи')
            ->numeric('purchase_price', 'Цена покупки')
            ->min('purchase_price', 0, 'Цена покупки')
            ->numeric('stock_quantity', 'Остаток')
            ->min('stock_quantity', 0, 'Остаток');

        if (!$v->isValid()) {
            $_SESSION['product_error'] = $v->firstError();
            Response::redirect('/products/' . $id . '/edit');
        }

        $status = $_POST['status'] ?? 'active';
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        $ok = Product::update((int)$id, $companyId, [
            'name'           => trim((string)$_POST['name']),
            'sku'            => trim((string)($_POST['sku'] ?? '')),
            'barcode'        => trim((string)($_POST['barcode'] ?? '')),
            'unit'           => (string)($_POST['unit'] ?? 'шт'),
            'purchase_price' => trim((string)($_POST['purchase_price'] ?? '')),
            'sale_price'     => $_POST['sale_price'],
            'stock_quantity' => $_POST['stock_quantity'] ?? '0',
            'status'         => $status,
        ]);

        if (!$ok) {
            http_response_code(404);
            require ROOT_DIR . '/views/errors/404.php';
            return;
        }

        ActivityLogger::log('product_updated', $companyId, (int)$_SESSION['user_id'], 'product', (int)$id);
        $_SESSION['flash_success'] = 'Товар обновлён.';
        Response::redirect('/products');
    }

    public function search(): void
    {
        CompanyMiddleware::check();
        $companyId = CompanyMiddleware::companyId();
        $q         = trim($_GET['q'] ?? '');
        if ($q === '') {
            Response::json([]);
        }
        Response::json(Product::search($companyId, $q));
    }
}

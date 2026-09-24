<?php

use App\Core\Router;
use App\Controllers\HomeController;
use App\Controllers\HealthController;
use App\Controllers\AuthController;
use App\Controllers\OnboardingController;
use App\Controllers\DashboardController;
use App\Controllers\ClientController;
use App\Controllers\ProductController;
use App\Controllers\InvoiceController;
use App\Controllers\DebtController;
use App\Controllers\SubscriptionController;
use App\Controllers\ProfileController;
use App\Controllers\Admin\AdminAuthController;
use App\Controllers\Admin\AdminDashboardController;
use App\Controllers\Admin\AdminCompanyController;
use App\Controllers\TelegramBotController;

/** @var Router $router */

// Health
$router->get('/health', [HealthController::class, 'index']);

// Public invoice QR view (no auth)
$router->get('/invoice/public/{uuid}', [InvoiceController::class, 'publicShow']);

// Foundation home
$router->get('/', [HomeController::class, 'index']);

// Mini App entry + Auth
$router->get('/mini-app',         [AuthController::class, 'miniApp']);
$router->get('/auth/login',       [AuthController::class, 'loginPage']);
$router->post('/api/auth/telegram',[AuthController::class, 'telegramLogin']);
$router->post('/auth/telegram',   [AuthController::class, 'telegramLogin']); // legacy alias
$router->get('/auth/status',      [AuthController::class, 'status']);
$router->post('/auth/mock-login', [AuthController::class, 'mockLogin']);
$router->post('/logout',          [AuthController::class, 'logout']);
$router->post('/auth/logout',     [AuthController::class, 'logout']); // legacy alias

// Onboarding
$router->get('/onboarding',          [OnboardingController::class, 'index']);
$router->post('/onboarding/company', [OnboardingController::class, 'store']);
$router->post('/onboarding',         [OnboardingController::class, 'store']); // legacy alias
$router->get('/onboarding/success',  [OnboardingController::class, 'success']);

// Dashboard
$router->get('/dashboard',               [DashboardController::class, 'index']);
$router->get('/api/dashboard/summary',   [DashboardController::class, 'summary']);

// Clients
$router->get('/clients',             [ClientController::class, 'index']);
$router->get('/clients/create',      [ClientController::class, 'create']);
$router->post('/clients',            [ClientController::class, 'store']);
$router->get('/clients/{id}',        [ClientController::class, 'show']);
$router->get('/clients/{id}/edit',   [ClientController::class, 'edit']);
$router->post('/clients/{id}',       [ClientController::class, 'update']);

// Products
$router->get('/products',            [ProductController::class, 'index']);
$router->get('/products/create',     [ProductController::class, 'create']);
$router->post('/products',           [ProductController::class, 'store']);
$router->get('/products/{id}/edit',  [ProductController::class, 'edit']);
$router->post('/products/{id}',      [ProductController::class, 'update']);

// Invoices
$router->get('/invoices',            [InvoiceController::class, 'index']);
$router->get('/invoices/create',     [InvoiceController::class, 'create']);
$router->post('/invoices',           [InvoiceController::class, 'store']);
$router->get('/invoices/{id}',       [InvoiceController::class, 'show']);
$router->get('/invoices/{id}/edit',  [InvoiceController::class, 'edit']);
$router->post('/invoices/{id}',      [InvoiceController::class, 'update']);
$router->get('/invoices/{id}/pdf',   [InvoiceController::class, 'pdf']);
$router->get('/invoices/{id}/print', [InvoiceController::class, 'print']);
$router->post('/invoices/{id}/duplicate', [InvoiceController::class, 'duplicate']);
$router->post('/invoices/{id}/cancel',    [InvoiceController::class, 'cancel']);
$router->post('/invoices/{id}/delete',    [InvoiceController::class, 'destroy']);

// Debts
$router->get('/debts',               [DebtController::class, 'index']);
$router->get('/debts/{clientId}',    [DebtController::class, 'show']);
$router->post('/debts/pay',          [DebtController::class, 'pay']);

// Subscription
$router->get('/subscription',         [SubscriptionController::class, 'index']);
$router->get('/subscription/expired', [SubscriptionController::class, 'expired']);
$router->post('/subscription/request',[SubscriptionController::class, 'request']);

// Profile
$router->get('/profile',             [ProfileController::class, 'index']);
$router->post('/profile',            [ProfileController::class, 'update']);
$router->post('/profile/company',    [ProfileController::class, 'updateCompany']);

// Admin
$router->get('/admin',               [AdminAuthController::class, 'loginPage']);
$router->post('/admin/login',        [AdminAuthController::class, 'login']);
$router->post('/admin/logout',       [AdminAuthController::class, 'logout']);
$router->get('/admin/dashboard',     [AdminDashboardController::class, 'index']);
$router->get('/admin/companies',     [AdminCompanyController::class, 'index']);
$router->get('/admin/companies/{id}', [AdminCompanyController::class, 'show']);
$router->post('/admin/companies/{id}/activate',  [AdminCompanyController::class, 'activate']);
$router->post('/admin/companies/{id}/suspend',   [AdminCompanyController::class, 'suspend']);
$router->post('/admin/companies/{id}/extend',    [AdminCompanyController::class, 'extend']);

// Telegram Bot Webhook
$router->post('/bot/webhook',        [TelegramBotController::class, 'handle']);

// API endpoints
$router->get('/api/clients/search',  [ClientController::class, 'search']);
$router->get('/api/products/search', [ProductController::class, 'search']);
$router->get('/api/invoices',        [InvoiceController::class, 'apiIndex']);
$router->get('/api/invoices/{id}',   [InvoiceController::class, 'apiShow']);
$router->post('/api/invoices',       [InvoiceController::class, 'apiStore']);
$router->put('/api/invoices/{id}',   [InvoiceController::class, 'apiUpdate']);
$router->delete('/api/invoices/{id}', [InvoiceController::class, 'apiDelete']);
$router->post('/api/invoices/{id}/cancel',    [InvoiceController::class, 'apiCancel']);
$router->post('/api/invoices/{id}/duplicate', [InvoiceController::class, 'apiDuplicate']);
$router->get('/api/invoices/{id}/share',      [InvoiceController::class, 'apiShare']);
$router->post('/api/invoice-clients',         [InvoiceController::class, 'apiCreateClient']);

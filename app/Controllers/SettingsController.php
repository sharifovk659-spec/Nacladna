<?php

namespace App\Controllers;

use App\Core\Response;
use App\Helpers\Csrf;
use App\Helpers\Validator;
use App\Middleware\CompanyMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Middleware\SubscriptionMiddleware;
use App\Models\Company;
use App\Services\ActivityLogger;

class SettingsController
{
    public function index(): void
    {
        CompanyMiddleware::check();
        PermissionMiddleware::require('settings.view');

        $companyId = CompanyMiddleware::companyId();
        $company = Company::findForCompany($companyId, $companyId);
        if (!$company) {
            http_response_code(404);
            require ROOT_DIR . '/views/errors/404.php';
            return;
        }

        $flash = $_SESSION['flash_success'] ?? null;
        $error = $_SESSION['settings_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['settings_error']);

        $pageTitle = 'Настройки';
        $currencies = Company::CURRENCIES;
        $languages = Company::LANGUAGES;
        $timezones = Company::TIMEZONES;
        $logoUrl = Company::logoPublicUrl($company['logo_path'] ?? null, $companyId);

        ob_start();
        require ROOT_DIR . '/views/settings/index.php';
        $content = ob_get_clean();
        require ROOT_DIR . '/views/layouts/app.php';
    }

    public function update(): void
    {
        CompanyMiddleware::check();
        PermissionMiddleware::require('settings.edit');
        SubscriptionMiddleware::requireWrite();
        Csrf::verifyRequest();

        $companyId = CompanyMiddleware::companyId();
        $userId = (int)$_SESSION['user_id'];

        $v = Validator::make($_POST)
            ->required('name', 'Название компании')
            ->maxLen('name', 200, 'Название компании')
            ->required('owner_name', 'Имя владельца')
            ->maxLen('owner_name', 200, 'Имя владельца')
            ->phone('phone')
            ->maxLen('address', 500, 'Адрес');

        if (!$v->isValid()) {
            $_SESSION['settings_error'] = $v->firstError();
            Response::redirect('/settings');
        }

        $phone = trim((string)($_POST['phone'] ?? ''));
        if ($phone !== '') {
            $phone = Validator::normalizePhone($phone);
        }

        $removeLogo = !empty($_POST['remove_logo']);

        try {
            Company::updateSettings($companyId, $companyId, [
                'name' => $_POST['name'],
                'owner_name' => $_POST['owner_name'],
                'phone' => $phone,
                'address' => $_POST['address'] ?? '',
                'currency' => $_POST['currency'] ?? Company::DEFAULT_CURRENCY,
                'language' => $_POST['language'] ?? Company::DEFAULT_LANGUAGE,
                'timezone' => $_POST['timezone'] ?? Company::DEFAULT_TIMEZONE,
                'invoice_prefix' => $_POST['invoice_prefix'] ?? 'NK',
            ], $_FILES['logo'] ?? null, $removeLogo);

            $company = Company::findForCompany($companyId, $companyId);
            if ($company) {
                Company::syncSessionLocale($company);
            }

            ActivityLogger::log('company_settings_updated', $companyId, $userId, 'company', $companyId);
            $_SESSION['flash_success'] = 'Настройки сохранены.';
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Settings update failed: ' . $e->getMessage());
            $_SESSION['settings_error'] = $e->getMessage();
        }

        Response::redirect('/settings');
    }
}

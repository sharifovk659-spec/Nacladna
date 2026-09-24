<?php

namespace App\Controllers;

use App\Core\Response;
use App\Helpers\Csrf;
use App\Helpers\Validator;
use App\Middleware\CompanyMiddleware;
use App\Middleware\CurrentCompanyMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Middleware\SubscriptionMiddleware;
use App\Models\CompanyUser;
use App\Models\EmployeeInvite;
use App\Models\Permission;
use App\Services\ActivityLogger;
use App\Services\PermissionRegistry;

class EmployeeController
{
    public function index(): void
    {
        CompanyMiddleware::check();
        PermissionMiddleware::require('employees.view');

        $companyId = CompanyMiddleware::companyId();
        $employees = CompanyUser::listForCompany($companyId);
        $invites = EmployeeInvite::listPendingForCompany($companyId);
        $canCreate = PermissionMiddleware::can('employees.create');
        $flash = $_SESSION['flash_success'] ?? null;
        $error = $_SESSION['employee_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['employee_error']);

        $pageTitle = 'Сотрудники';
        ob_start();
        require ROOT_DIR . '/views/employees/index.php';
        $content = ob_get_clean();
        require ROOT_DIR . '/views/layouts/app.php';
    }

    public function create(): void
    {
        CompanyMiddleware::check();
        PermissionMiddleware::require('employees.create');

        $pageTitle = 'Добавить сотрудника';
        $employee = null;
        $mode = 'create';
        ob_start();
        require ROOT_DIR . '/views/employees/form.php';
        $content = ob_get_clean();
        require ROOT_DIR . '/views/layouts/app.php';
    }

    public function store(): void
    {
        CompanyMiddleware::check();
        PermissionMiddleware::require('employees.create');
        SubscriptionMiddleware::requireWrite();
        Csrf::verifyRequest();

        $companyId = CompanyMiddleware::companyId();
        $actorRole = CurrentCompanyMiddleware::role();
        $userId = (int)$_SESSION['user_id'];

        $v = Validator::make($_POST)
            ->required('display_name', 'Имя сотрудника')
            ->maxLen('display_name', 200, 'Имя сотрудника')
            ->required('role', 'Роль');

        if (!$v->isValid()) {
            $_SESSION['employee_error'] = $v->firstError();
            Response::redirect('/employees/create');
        }

        $role = strtolower(trim((string)$_POST['role']));
        $name = trim((string)$_POST['display_name']);

        try {
            $invite = EmployeeInvite::create($companyId, $userId, $name, $role, $actorRole);
            $base = rtrim($_ENV['APP_URL'] ?? '', '/');
            $link = ($base !== '' ? $base : '') . '/join/' . $invite['token'];
            $_SESSION['flash_success'] = 'Приглашение создано. Отправьте ссылку сотруднику в Telegram.';
            $_SESSION['employee_invite_link'] = $link;
            ActivityLogger::log('employee_invite_created', $companyId, $userId, 'employee_invite', (int)$invite['id']);
        } catch (\Throwable $e) {
            $_SESSION['employee_error'] = $e->getMessage();
            Response::redirect('/employees/create');
        }

        Response::redirect('/employees');
    }

    public function edit(string $id): void
    {
        CompanyMiddleware::check();
        PermissionMiddleware::require('employees.edit');

        $companyId = CompanyMiddleware::companyId();
        $member = CompanyUser::findForCompany((int)$id, $companyId);
        if (!$member) {
            http_response_code(404);
            require ROOT_DIR . '/views/errors/404.php';
            return;
        }

        $overrides = Permission::getOverrides((int)$member['id']);
        $canPermissions = PermissionMiddleware::can('employees.permissions');
        $pageTitle = 'Сотрудник';
        $employee = $member;
        $mode = 'edit';
        ob_start();
        require ROOT_DIR . '/views/employees/form.php';
        $content = ob_get_clean();
        require ROOT_DIR . '/views/layouts/app.php';
    }

    public function update(string $id): void
    {
        CompanyMiddleware::check();
        PermissionMiddleware::require('employees.edit');
        SubscriptionMiddleware::requireWrite();
        Csrf::verifyRequest();

        $companyId = CompanyMiddleware::companyId();
        $actorRole = CurrentCompanyMiddleware::role();
        $actorUserId = (int)$_SESSION['user_id'];
        $memberId = (int)$id;

        $displayName = trim((string)($_POST['display_name'] ?? ''));
        $role = isset($_POST['role']) ? strtolower(trim((string)$_POST['role'])) : null;
        $status = isset($_POST['status']) ? trim((string)$_POST['status']) : null;

        try {
            CompanyUser::updateEmployee($memberId, $companyId, $actorRole, $actorUserId, $displayName, $role, $status);

            if (PermissionMiddleware::can('employees.permissions') && isset($_POST['perm']) && is_array($_POST['perm'])) {
                $grants = [];
                foreach ($_POST['perm'] as $slug => $val) {
                    if ($val === '' || $val === null) {
                        continue;
                    }
                    $grants[(string)$slug] = (string)$val === '1';
                }
                Permission::saveOverrides($memberId, $companyId, $grants);
            }

            Permission::syncSession($actorUserId, $companyId);
            ActivityLogger::log('employee_updated', $companyId, $actorUserId, 'company_user', $memberId);
            $_SESSION['flash_success'] = 'Данные сотрудника сохранены.';
        } catch (\Throwable $e) {
            $_SESSION['employee_error'] = $e->getMessage();
        }

        Response::redirect('/employees/' . $memberId . '/edit');
    }

    public function revokeInvite(string $id): void
    {
        CompanyMiddleware::check();
        PermissionMiddleware::require('employees.edit');
        SubscriptionMiddleware::requireWrite();
        Csrf::verifyRequest();

        EmployeeInvite::revoke((int)$id, CompanyMiddleware::companyId());
        $_SESSION['flash_success'] = 'Приглашение отменено.';
        Response::redirect('/employees');
    }
}

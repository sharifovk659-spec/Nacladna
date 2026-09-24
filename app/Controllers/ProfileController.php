<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Helpers\Csrf;
use App\Helpers\Validator;
use App\Middleware\CompanyMiddleware;
use App\Middleware\OwnerMiddleware;
use App\Services\ActivityLogger;

class ProfileController
{
    public function index(): void
    {
        CompanyMiddleware::check();
        $db        = Database::getInstance();
        $userId    = $_SESSION['user_id'];
        $companyId = CompanyMiddleware::companyId();

        $user    = $db->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
        $user->execute([$userId]);
        $user    = $user->fetch();

        $company = $db->prepare("SELECT * FROM companies WHERE id=? LIMIT 1");
        $company->execute([$companyId]);
        $company = $company->fetch();

        $sub = $db->prepare("SELECT * FROM subscriptions WHERE company_id=? ORDER BY id DESC LIMIT 1");
        $sub->execute([$companyId]);
        $subscription = $sub->fetch();

        $flash = $_SESSION['flash_success'] ?? null;
        $error = $_SESSION['profile_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['profile_error']);

        $pageTitle = 'Профиль';
        $isOwner   = CompanyMiddleware::isOwner();
        ob_start();
        require ROOT_DIR . '/views/profile/index.php';
        $content = ob_get_clean();
        require ROOT_DIR . '/views/layouts/app.php';
    }

    public function update(): void
    {
        CompanyMiddleware::check();
        Csrf::verifyRequest();

        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'];

        $phone = trim($_POST['phone'] ?? '');
        if ($phone !== '') $phone = Validator::normalizePhone($phone);

        $db->prepare("UPDATE users SET phone=?, updated_at=NOW() WHERE id=?")
           ->execute([$phone ?: null, $userId]);

        ActivityLogger::log('profile_updated', CompanyMiddleware::companyId(), $userId);
        $_SESSION['flash_success'] = 'Профиль обновлён.';
        Response::redirect('/profile');
    }

    public function updateCompany(): void
    {
        OwnerMiddleware::check();
        Csrf::verifyRequest();

        $companyId = CompanyMiddleware::companyId();
        $db        = Database::getInstance();

        $v = Validator::make($_POST)
            ->required('name', 'Название компании')
            ->maxLen('name', 200)
            ->required('owner_name', 'Имя владельца')
            ->phone('phone');

        if (!$v->isValid()) {
            $_SESSION['profile_error'] = $v->firstError();
            Response::redirect('/profile');
        }

        $phone  = trim($_POST['phone'] ?? '');
        if ($phone !== '') $phone = Validator::normalizePhone($phone);

        $prefix = preg_replace('/[^A-ZА-ЯЁ0-9]/u', '', strtoupper(trim($_POST['invoice_prefix'] ?? 'НКЛ')));
        $prefix = substr($prefix, 0, 10) ?: 'НКЛ';

        // Handle logo upload
        $logoPath = null;
        if (!empty($_FILES['logo']['name'])) {
            $allowed  = ['image/jpeg', 'image/png', 'image/webp'];
            $finfo    = new \finfo(FILEINFO_MIME_TYPE);
            $mime     = $finfo->file($_FILES['logo']['tmp_name']);
            if (!in_array($mime, $allowed) || $_FILES['logo']['size'] > 2*1024*1024) {
                $_SESSION['profile_error'] = 'Недопустимый файл логотипа.';
                Response::redirect('/profile');
            }
            $ext  = match($mime) { 'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp' };
            $dir  = ROOT_DIR . '/public/uploads/logos';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $file = bin2hex(random_bytes(12)) . '.' . $ext;
            move_uploaded_file($_FILES['logo']['tmp_name'], $dir . '/' . $file);
            $logoPath = 'uploads/logos/' . $file;
        }

        $sql = "UPDATE companies SET name=?, owner_name=?, phone=?, address=?, invoice_prefix=?, updated_at=NOW()";
        $params = [
            trim($_POST['name']),
            trim($_POST['owner_name']),
            $phone ?: null,
            trim($_POST['address'] ?? ''),
            $prefix,
        ];
        if ($logoPath) {
            $sql .= ", logo_path=?";
            $params[] = $logoPath;
        }
        $sql .= " WHERE id=?";
        $params[] = $companyId;
        $db->prepare($sql)->execute($params);

        $_SESSION['company_name'] = trim($_POST['name']);
        ActivityLogger::log('company_settings_updated', $companyId, $_SESSION['user_id']);
        $_SESSION['flash_success'] = 'Настройки компании сохранены.';
        Response::redirect('/profile');
    }
}

<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Helpers\Csrf;
use App\Helpers\Validator;
use App\Middleware\CompanyMiddleware;
use App\Middleware\SubscriptionMiddleware;
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
        SubscriptionMiddleware::requireWrite();
        Csrf::verifyRequest();

        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'];

        $phone = trim($_POST['phone'] ?? '');
        if ($phone !== '') {
            $phone = Validator::normalizePhone($phone);
        }

        $db->prepare("UPDATE users SET phone=?, updated_at=NOW() WHERE id=?")
           ->execute([$phone ?: null, $userId]);

        ActivityLogger::log('profile_updated', CompanyMiddleware::companyId(), $userId);
        $_SESSION['flash_success'] = 'Профиль обновлён.';
        Response::redirect('/profile');
    }
}

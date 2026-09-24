<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Helpers\Csrf;
use App\Helpers\Validator;
use App\Middleware\AuthMiddleware;
use App\Middleware\SubscriptionMiddleware;
use App\Models\Subscription;
use App\Services\ActivityLogger;
use App\Services\RateLimiter;

class OnboardingController
{
    public function index(): void
    {
        AuthMiddleware::check();

        $db = Database::getInstance();
        $st = $db->prepare('SELECT id FROM company_users WHERE user_id = ? AND status = ? LIMIT 1');
        $st->execute([(int)$_SESSION['user_id'], 'active']);
        if ($st->fetch()) {
            Response::redirect('/dashboard');
        }

        $pageTitle = 'Создайте свою компанию';
        $hideNav = true;
        $hideHeader = true;
        $hideBottomNav = true;
        $error = $_SESSION['onboarding_error'] ?? null;
        $fieldErrors = $_SESSION['onboarding_field_errors'] ?? [];
        $old = $_SESSION['onboarding_old'] ?? [];
        unset($_SESSION['onboarding_error'], $_SESSION['onboarding_field_errors'], $_SESSION['onboarding_old']);

        ob_start();
        require ROOT_DIR . '/views/onboarding/index.php';
        $content = ob_get_clean();
        require ROOT_DIR . '/views/layouts/app.php';
    }

    public function store(): void
    {
        AuthMiddleware::check();
        Csrf::verifyRequest();

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $limiter = new RateLimiter();
        if (!$limiter->attempt('onboarding:' . $_SESSION['user_id'] . ':' . $ip, 5, 120)) {
            $_SESSION['onboarding_error'] = 'Слишком много попыток. Подождите немного.';
            Response::redirect('/onboarding');
        }

        $v = Validator::make($_POST)
            ->required('name', 'Название компании')
            ->maxLen('name', 200, 'Название компании')
            ->required('owner_name', 'Имя владельца')
            ->maxLen('owner_name', 200, 'Имя владельца')
            ->required('phone', 'Телефон')
            ->phone('phone')
            ->maxLen('address', 500, 'Адрес');

        if (!$v->isValid()) {
            $_SESSION['onboarding_field_errors'] = $v->getErrors();
            $_SESSION['onboarding_error'] = $v->firstError();
            $_SESSION['onboarding_old'] = $_POST;
            Response::redirect('/onboarding');
        }

        $logoPath = null;
        if (!empty($_FILES['logo']['name'])) {
            $upload = $this->handleLogoUpload($_FILES['logo']);
            if ($upload['error']) {
                $_SESSION['onboarding_error'] = $upload['error'];
                $_SESSION['onboarding_old'] = $_POST;
                Response::redirect('/onboarding');
            }
            $logoPath = $upload['path'];
        }

        $name = trim((string)$_POST['name']);
        $ownerName = trim((string)$_POST['owner_name']);
        $phone = Validator::normalizePhone(trim((string)$_POST['phone']));
        $address = trim((string)($_POST['address'] ?? ''));
        $userId = (int)$_SESSION['user_id'];
        $db = Database::getInstance();

        $db->beginTransaction();
        try {
            // Lock user row to prevent race duplicate company creation
            $lock = $db->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
            $lock->execute([$userId]);
            if (!$lock->fetch()) {
                throw new \RuntimeException('User not found');
            }

            $existing = $db->prepare('SELECT id, company_id FROM company_users WHERE user_id = ? LIMIT 1 FOR UPDATE');
            $existing->execute([$userId]);
            if ($row = $existing->fetch()) {
                $db->commit();
                $_SESSION['company_id'] = (int)$row['company_id'];
                Response::redirect('/onboarding/success');
            }

            $db->prepare(
                "INSERT INTO companies (name, owner_name, phone, address, logo_path, currency, timezone, invoice_prefix, next_invoice_number, status)
                 VALUES (?, ?, ?, ?, ?, 'TJS', 'Asia/Dushanbe', 'NK', 1, 'active')"
            )->execute([$name, $ownerName, $phone, $address !== '' ? $address : null, $logoPath]);

            $companyId = (int)$db->lastInsertId();

            $db->prepare(
                "INSERT INTO company_users (company_id, user_id, role, status) VALUES (?, ?, 'owner', 'active')"
            )->execute([$companyId, $userId]);

            Subscription::createTrial($companyId);

            $sub = Subscription::findForCompany($companyId);
            $trialEnd = (string)($sub['ends_at'] ?? $sub['trial_end'] ?? date('Y-m-d H:i:s', strtotime('+3 days')));

            $db->commit();

            ActivityLogger::log('company_created', $companyId, $userId, 'company', $companyId, [
                'trial_end' => $trialEnd,
            ]);

            $_SESSION['company_id'] = $companyId;
            $_SESSION['company_name'] = $name;
            $_SESSION['company_role'] = 'owner';
            SubscriptionMiddleware::syncSessionStatus([
                'plan' => 'trial',
                'status' => 'active',
                'trial_end' => $trialEnd,
                'ends_at' => $trialEnd,
            ]);
            $_SESSION['trial_end'] = $trialEnd;

            Response::redirect('/onboarding/success');
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($logoPath && is_file(ROOT_DIR . '/public/' . $logoPath)) {
                @unlink(ROOT_DIR . '/public/' . $logoPath);
            }
            \App\Core\Logger::error('Onboarding failed: ' . $e->getMessage());
            $_SESSION['onboarding_error'] = 'Произошла ошибка. Попробуйте ещё раз.';
            $_SESSION['onboarding_old'] = $_POST;
            Response::redirect('/onboarding');
        }
    }

    public function success(): void
    {
        AuthMiddleware::check();
        if (empty($_SESSION['company_id'])) {
            Response::redirect('/onboarding');
        }

        $trialEnd = $_SESSION['trial_end'] ?? null;
        if (!$trialEnd) {
            $db = Database::getInstance();
            $st = $db->prepare('SELECT trial_end, ends_at FROM subscriptions WHERE company_id = ? ORDER BY id DESC LIMIT 1');
            $st->execute([(int)$_SESSION['company_id']]);
            $sub = $st->fetch();
            $trialEnd = $sub['trial_end'] ?? $sub['ends_at'] ?? date('Y-m-d H:i:s', strtotime('+3 days'));
        }

        $pageTitle = 'Компания создана';
        $hideNav = true;
        $hideHeader = true;
        $hideBottomNav = true;
        $companyName = $_SESSION['company_name'] ?? '';

        ob_start();
        require ROOT_DIR . '/views/onboarding/success.php';
        $content = ob_get_clean();
        require ROOT_DIR . '/views/layouts/app.php';
    }

    private function handleLogoUpload(array $file): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['error' => 'Ошибка загрузки файла.', 'path' => null];
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            return ['error' => 'Файл слишком большой (макс. 2MB).', 'path' => null];
        }

        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, $allowed, true)) {
            return ['error' => 'Недопустимый тип файла. Только JPEG, PNG, WebP.', 'path' => null];
        }

        $ext = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        };

        $dir = ROOT_DIR . '/public/uploads/logos';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $dir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return ['error' => 'Не удалось сохранить файл.', 'path' => null];
        }

        return ['error' => null, 'path' => 'uploads/logos/' . $filename];
    }
}

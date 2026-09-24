<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;

class Company
{
    public const DEFAULT_CURRENCY = 'TJS';
    public const DEFAULT_TIMEZONE = 'Asia/Dushanbe';
    public const DEFAULT_LANGUAGE = 'ru';

    /** @var array<string, string> */
    public const CURRENCIES = [
        'TJS' => 'TJS (сомони)',
        'USD' => 'USD ($)',
        'RUB' => 'RUB (₽)',
    ];

    /** @var array<string, string> */
    public const LANGUAGES = [
        'ru' => 'Русский',
        'tg' => 'Тоҷикӣ',
        'en' => 'English',
    ];

    /** @var string[] */
    public const TIMEZONES = [
        'Asia/Dushanbe',
        'Asia/Tashkent',
        'Asia/Almaty',
        'Europe/Moscow',
        'UTC',
    ];

    public static function findForCompany(int $companyId, int $scopeCompanyId): ?array
    {
        if ($companyId !== $scopeCompanyId || $companyId <= 0) {
            return null;
        }

        $db = Database::getInstance();
        $st = $db->prepare('SELECT * FROM companies WHERE id = ? LIMIT 1');
        $st->execute([$companyId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function updateSettings(int $companyId, int $scopeCompanyId, array $data, ?array $logoFile = null, bool $removeLogo = false): void
    {
        $company = self::findForCompany($companyId, $scopeCompanyId);
        if (!$company) {
            throw new \RuntimeException('Компания не найдена.');
        }

        $currency = strtoupper(trim((string)($data['currency'] ?? self::DEFAULT_CURRENCY)));
        if (!isset(self::CURRENCIES[$currency])) {
            throw new \InvalidArgumentException('Недопустимая валюта.');
        }

        $language = strtolower(trim((string)($data['language'] ?? self::DEFAULT_LANGUAGE)));
        if (!isset(self::LANGUAGES[$language])) {
            throw new \InvalidArgumentException('Недопустимый язык.');
        }

        $timezone = trim((string)($data['timezone'] ?? self::DEFAULT_TIMEZONE));
        if (!in_array($timezone, self::TIMEZONES, true)) {
            throw new \InvalidArgumentException('Недопустимый часовой пояс.');
        }

        $prefix = preg_replace('/[^A-ZА-ЯЁ0-9]/u', '', strtoupper(trim((string)($data['invoice_prefix'] ?? 'NK'))));
        $prefix = substr($prefix, 0, 10) ?: 'NK';

        $logoPath = $company['logo_path'] ?? null;

        if ($removeLogo) {
            self::deleteLogoFile($logoPath);
            $logoPath = null;
        }

        if ($logoFile && !empty($logoFile['name'])) {
            $newPath = self::storeLogoUpload($logoFile, $companyId);
            if ($newPath) {
                self::deleteLogoFile($logoPath);
                $logoPath = $newPath;
            }
        }

        $db = Database::getInstance();
        $db->prepare(
            "UPDATE companies
             SET name = ?, owner_name = ?, phone = ?, address = ?,
                 currency = ?, timezone = ?, language = ?, invoice_prefix = ?,
                 logo_path = ?, updated_at = NOW()
             WHERE id = ?"
        )->execute([
            trim((string)$data['name']),
            trim((string)$data['owner_name']),
            ($data['phone'] ?? '') !== '' ? $data['phone'] : null,
            trim((string)($data['address'] ?? '')) ?: null,
            $currency,
            $timezone,
            $language,
            $prefix,
            $logoPath,
            $companyId,
        ]);
    }

    public static function logoPublicUrl(?string $relativePath): ?string
    {
        if ($relativePath === null || $relativePath === '') {
            return null;
        }
        return '/' . ltrim($relativePath, '/');
    }

    public static function storeLogoUpload(array $file, int $companyId): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Ошибка загрузки файла.');
        }

        if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
            throw new \RuntimeException('Файл слишком большой (макс. 2MB).');
        }

        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $allowed, true)) {
            throw new \RuntimeException('Недопустимый тип файла. Только JPEG, PNG, WebP.');
        }

        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new \RuntimeException('Недопустимый формат.'),
        };

        $dir = ROOT_DIR . '/public/uploads/logos/' . $companyId;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $dir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new \RuntimeException('Не удалось сохранить логотип.');
        }

        return 'uploads/logos/' . $companyId . '/' . $filename;
    }

    public static function deleteLogoFile(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }
        $full = ROOT_DIR . '/public/' . ltrim($relativePath, '/');
        if (is_file($full)) {
            @unlink($full);
        }
    }

    public static function syncSessionLocale(array $company): void
    {
        $_SESSION['company_name'] = $company['name'] ?? $_SESSION['company_name'] ?? '';
        $_SESSION['company_language'] = $company['language'] ?? self::DEFAULT_LANGUAGE;
        $_SESSION['company_timezone'] = $company['timezone'] ?? self::DEFAULT_TIMEZONE;
        $_SESSION['company_currency'] = $company['currency'] ?? self::DEFAULT_CURRENCY;
    }
}

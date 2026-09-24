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

    /**
     * Resolve stored logo reference to an absolute file path (never exposed to clients).
     */
    public static function resolveLogoFile(?string $relativePath): ?string
    {
        if ($relativePath === null || $relativePath === '') {
            return null;
        }

        $rel = str_replace('\\', '/', ltrim($relativePath, '/'));
        if ($rel === '' || str_contains($rel, '..')) {
            return null;
        }

        $candidates = [];
        if (str_starts_with($rel, 'logos/')) {
            $candidates[] = ROOT_DIR . '/storage/uploads/' . $rel;
        }
        $candidates[] = ROOT_DIR . '/public/' . $rel;
        if (str_starts_with($rel, 'uploads/logos/')) {
            $candidates[] = ROOT_DIR . '/storage/uploads/' . substr($rel, strlen('uploads/'));
        }

        foreach ($candidates as $full) {
            if (is_file($full)) {
                return $full;
            }
        }

        return null;
    }

    /** HTTPS-safe public URL for img/src (Telegram Mini App, PDF remote off). */
    public static function logoPublicUrl(?string $relativePath, int $companyId): ?string
    {
        if ($relativePath === null || $relativePath === '' || $companyId <= 0) {
            return null;
        }

        $full = self::resolveLogoFile($relativePath);
        if ($full === null) {
            return null;
        }

        $v = (string)(filemtime($full) ?: time());
        $path = '/media/company-logo/' . $companyId . '?v=' . rawurlencode($v);
        $base = rtrim((string)($_ENV['APP_URL'] ?? ''), '/');

        return $base !== '' ? $base . $path : $path;
    }

    public static function logoDataUri(?string $relativePath): ?string
    {
        $full = self::resolveLogoFile($relativePath);
        if ($full === null) {
            return null;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($full) ?: 'image/png';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return null;
        }

        $b64 = base64_encode((string)file_get_contents($full));

        return 'data:' . $mime . ';base64,' . $b64;
    }

    public static function storeLogoUpload(array $file, int $companyId): ?string
    {
        if ($companyId <= 0) {
            throw new \RuntimeException('Некорректная компания.');
        }

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

        $dir = ROOT_DIR . '/storage/uploads/logos/' . $companyId;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $dir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new \RuntimeException('Не удалось сохранить логотип.');
        }

        @chmod($dest, 0644);
        self::mirrorLogoToPublicWebroot('logos/' . $companyId . '/' . $filename, $dest);

        return 'logos/' . $companyId . '/' . $filename;
    }

    public static function deleteLogoFile(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }

        $full = self::resolveLogoFile($relativePath);
        if ($full !== null && is_file($full)) {
            @unlink($full);
        }

        self::removeLogoFromPublicWebroot($relativePath);
    }

    /**
     * Copy legacy public/ uploads into storage (one-time friendly).
     */
    public static function migrateLogoToStorage(?string $relativePath, int $companyId): ?string
    {
        if ($relativePath === null || $relativePath === '' || $companyId <= 0) {
            return null;
        }
        if (str_starts_with($relativePath, 'logos/')) {
            return $relativePath;
        }

        $full = self::resolveLogoFile($relativePath);
        if ($full === null) {
            return null;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($full) ?: 'image/png';
        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => pathinfo($full, PATHINFO_EXTENSION) ?: 'png',
        };

        $dir = ROOT_DIR . '/storage/uploads/logos/' . $companyId;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $dir . '/' . $filename;
        if (!copy($full, $dest)) {
            Logger::error('Logo migrate copy failed for company ' . $companyId);
            return $relativePath;
        }

        @chmod($dest, 0644);
        $newRel = 'logos/' . $companyId . '/' . $filename;
        self::mirrorLogoToPublicWebroot($newRel, $dest);

        $db = Database::getInstance();
        $db->prepare('UPDATE companies SET logo_path = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$newRel, $companyId]);

        @unlink($full);
        self::removeLogoFromPublicWebroot($relativePath);

        return $newRel;
    }

    private static function publicWebRoot(): ?string
    {
        $dir = trim((string)($_ENV['PUBLIC_WEB_ROOT'] ?? ''));
        if ($dir !== '') {
            return rtrim($dir, "/\\");
        }

        return null;
    }

    private static function mirrorLogoToPublicWebroot(string $relativePath, string $sourceFile): void
    {
        $publicRoot = self::publicWebRoot();
        if ($publicRoot === null || !is_file($sourceFile)) {
            return;
        }

        $rel = 'uploads/' . ltrim($relativePath, '/');
        $dest = $publicRoot . '/' . $rel;
        $destDir = dirname($dest);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        @copy($sourceFile, $dest);
        @chmod($dest, 0644);
    }

    private static function removeLogoFromPublicWebroot(?string $relativePath): void
    {
        $publicRoot = self::publicWebRoot();
        if ($publicRoot === null || $relativePath === null || $relativePath === '') {
            return;
        }

        $rel = ltrim($relativePath, '/');
        $paths = [$publicRoot . '/' . $rel];
        if (str_starts_with($rel, 'logos/')) {
            $paths[] = $publicRoot . '/uploads/' . $rel;
        }

        foreach ($paths as $full) {
            if (is_file($full)) {
                @unlink($full);
            }
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

<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

class InvoiceQrCode
{
    public static function ensureForInvoice(int $invoiceId, int $companyId): array
    {
        $existing = self::findByInvoice($invoiceId, $companyId);
        if ($existing) {
            return $existing;
        }

        $uuid = self::generateUuid();
        $publicUrl = self::buildPublicUrl($uuid);
        $qrPath = self::generateAndStoreImage($companyId, $invoiceId, $uuid, $publicUrl);

        $db = Database::getInstance();
        $db->prepare(
            "INSERT INTO invoice_qr_codes
             (invoice_id, company_id, public_uuid, public_url, qr_image_path, is_active)
             VALUES (?, ?, ?, ?, ?, 1)"
        )->execute([$invoiceId, $companyId, $uuid, $publicUrl, $qrPath]);

        $created = self::findByInvoice($invoiceId, $companyId);
        if (!$created) {
            throw new \RuntimeException('Не удалось создать QR код накладной.');
        }

        return $created;
    }

    public static function findByInvoice(int $invoiceId, int $companyId): ?array
    {
        $db = Database::getInstance();
        $st = $db->prepare(
            "SELECT *
             FROM invoice_qr_codes
             WHERE invoice_id = ? AND company_id = ? AND is_active = 1
             LIMIT 1"
        );
        $st->execute([$invoiceId, $companyId]);
        return $st->fetch() ?: null;
    }

    public static function findByUuid(string $uuid): ?array
    {
        $uuid = trim($uuid);
        if ($uuid === '' || !preg_match('/^[0-9a-fA-F-]{36}$/', $uuid)) {
            return null;
        }

        $db = Database::getInstance();
        $st = $db->prepare(
            "SELECT q.*, i.invoice_number, i.invoice_date, i.subtotal, i.discount, i.total,
                    i.paid_amount, i.debt_amount, i.status, i.payment_status, i.notes, i.deleted_at,
                    c.name AS company_name, c.phone AS company_phone, c.address AS company_address,
                    c.logo_path, cl.name AS client_name, cl.phone AS client_phone
             FROM invoice_qr_codes q
             JOIN invoices i ON i.id = q.invoice_id
             JOIN companies c ON c.id = q.company_id
             LEFT JOIN clients cl ON cl.id = i.client_id
             WHERE q.public_uuid = ? AND q.is_active = 1
             LIMIT 1"
        );
        $st->execute([$uuid]);
        $row = $st->fetch();
        if (!$row || $row['deleted_at'] !== null) {
            return null;
        }
        return $row;
    }

    public static function recordScan(int $qrId): void
    {
        $db = Database::getInstance();
        $db->prepare(
            "UPDATE invoice_qr_codes
             SET scan_count = scan_count + 1, last_scanned_at = NOW(), updated_at = NOW()
             WHERE id = ?"
        )->execute([$qrId]);
    }

    public static function publicUrlForInvoice(array $invoice): string
    {
        $qr = self::ensureForInvoice((int)$invoice['id'], (int)$invoice['company_id']);
        return $qr['public_url'];
    }

    public static function dataUri(string $publicUrl): string
    {
        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($publicUrl)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::Medium)
            ->size(220)
            ->margin(8)
            ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->validateResult(false)
            ->build();

        return $result->getDataUri();
    }

    private static function generateAndStoreImage(int $companyId, int $invoiceId, string $uuid, string $publicUrl): ?string
    {
        try {
            $result = Builder::create()
                ->writer(new PngWriter())
                ->data($publicUrl)
                ->encoding(new Encoding('UTF-8'))
                ->errorCorrectionLevel(ErrorCorrectionLevel::Medium)
                ->size(320)
                ->margin(10)
                ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
                ->validateResult(false)
                ->build();

            $dir = ROOT_DIR . "/storage/qrcodes/{$companyId}";
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $relative = "storage/qrcodes/{$companyId}/invoice-{$invoiceId}-{$uuid}.png";
            file_put_contents(ROOT_DIR . '/' . $relative, $result->getString());
            return $relative;
        } catch (\Throwable $e) {
            Logger::error('QR image save failed: ' . $e->getMessage());
            return null;
        }
    }

    private static function buildPublicUrl(string $uuid): string
    {
        $base = rtrim($_ENV['APP_URL'] ?? '', '/');
        if ($base === '') {
            return '/invoice/public/' . $uuid;
        }
        return $base . '/invoice/public/' . $uuid;
    }

    private static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

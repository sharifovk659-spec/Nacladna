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
    /** Create or return active QR for a finalized (non-draft, non-cancelled) invoice. */
    public static function syncForInvoice(int $invoiceId, int $companyId): ?array
    {
        $invoice = Invoice::findForCompany($invoiceId, $companyId);
        if (!$invoice) {
            return null;
        }
        if (in_array($invoice['status'], ['draft', 'cancelled'], true)) {
            self::revokeForInvoice($invoiceId, $companyId);
            return null;
        }
        return self::ensureForInvoice($invoiceId, $companyId);
    }

    public static function ensureForInvoice(int $invoiceId, int $companyId): array
    {
        $existing = self::findByInvoice($invoiceId, $companyId);
        if ($existing) {
            return $existing;
        }

        $db = Database::getInstance();
        $st = $db->prepare(
            "SELECT * FROM invoice_qr_codes WHERE invoice_id = ? AND company_id = ? LIMIT 1"
        );
        $st->execute([$invoiceId, $companyId]);
        $inactive = $st->fetch();
        if ($inactive) {
            $db->prepare(
                "UPDATE invoice_qr_codes SET is_active = 1, updated_at = NOW() WHERE id = ?"
            )->execute([(int)$inactive['id']]);
            $reactivated = self::findByInvoice($invoiceId, $companyId);
            if ($reactivated) {
                return $reactivated;
            }
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

    public static function revokeForInvoice(int $invoiceId, int $companyId): void
    {
        $db = Database::getInstance();
        $db->prepare(
            "UPDATE invoice_qr_codes
             SET is_active = 0, updated_at = NOW()
             WHERE invoice_id = ? AND company_id = ?"
        )->execute([$invoiceId, $companyId]);
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

    /** Public lookup — returns null → 404 (invalid, revoked, deleted, cancelled). */
    public static function findPublicByUuid(string $uuid): ?array
    {
        $uuid = trim($uuid);
        if ($uuid === '' || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid)) {
            return null;
        }

        $db = Database::getInstance();
        $st = $db->prepare(
            "SELECT q.id AS qr_id, q.public_uuid, q.public_url, q.qr_image_path,
                    i.id AS invoice_id, i.invoice_number, i.invoice_date,
                    i.subtotal, i.discount, i.total, i.paid_amount, i.debt_amount,
                    i.status, i.payment_status, i.notes,
                    q.company_id AS company_id,
                    c.name AS company_name, c.phone AS company_phone, c.logo_path AS company_logo_path,
                    cl.name AS client_name, cl.phone AS client_phone
             FROM invoice_qr_codes q
             INNER JOIN invoices i ON i.id = q.invoice_id AND i.company_id = q.company_id
             INNER JOIN companies c ON c.id = q.company_id AND c.status = 'active'
             LEFT JOIN clients cl ON cl.id = i.client_id
             WHERE q.public_uuid = ?
               AND q.is_active = 1
               AND i.deleted_at IS NULL
               AND i.status <> 'cancelled'
             LIMIT 1"
        );
        $st->execute([$uuid]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** Strip internal fields from line items for public display. */
    public static function mapPublicItems(array $items): array
    {
        $safe = [];
        foreach ($items as $item) {
            $safe[] = [
                'product_name' => (string)($item['product_name'] ?? ''),
                'unit' => (string)($item['unit'] ?? ''),
                'quantity' => (float)($item['quantity'] ?? 0),
                'unit_price' => (float)($item['unit_price'] ?? 0),
                'discount' => (float)($item['discount'] ?? 0),
                'line_total' => (float)($item['line_total'] ?? 0),
            ];
        }
        return $safe;
    }

    public static function recordScan(int $qrId): void
    {
        $db = Database::getInstance();
        $db->prepare(
            "UPDATE invoice_qr_codes
             SET scan_count = scan_count + 1, last_scanned_at = NOW(), updated_at = NOW()
             WHERE id = ? AND is_active = 1"
        )->execute([$qrId]);
    }

    public static function publicUrlForInvoice(array $invoice): string
    {
        $companyId = (int)($invoice['company_id'] ?? 0);
        $invoiceId = (int)($invoice['id'] ?? 0);
        if ($companyId <= 0 || $invoiceId <= 0) {
            return self::buildPublicUrl('00000000-0000-4000-8000-000000000000');
        }
        $qr = self::syncForInvoice($invoiceId, $companyId);
        if (!$qr) {
            $base = rtrim($_ENV['APP_URL'] ?? '', '/');
            return ($base !== '' ? $base : '') . '/invoices/' . $invoiceId;
        }
        return (string)$qr['public_url'];
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

    public static function imageDataUri(?string $relativePath, string $fallbackUrl): string
    {
        if ($relativePath) {
            $full = ROOT_DIR . '/' . ltrim($relativePath, '/');
            if (is_file($full)) {
                $b64 = base64_encode((string)file_get_contents($full));
                return 'data:image/png;base64,' . $b64;
            }
        }
        return self::dataUri($fallbackUrl);
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

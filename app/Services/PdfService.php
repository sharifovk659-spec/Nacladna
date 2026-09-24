<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use App\Core\Database;
use App\Core\Logger;

class PdfService
{
    public function generate(array $invoice, array $items, array $company): void
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', ROOT_DIR);

        $dompdf = new Dompdf($options);
        $html   = $this->buildHtml($invoice, $items, $company);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Save to disk
        $path = $this->savePdf($dompdf->output(), $invoice);
        if ($path) {
            $db = Database::getInstance();
            $db->prepare("UPDATE invoices SET pdf_path=? WHERE id=?")->execute([$path, $invoice['id']]);
        }

        // Stream to browser
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="invoice-' . $invoice['invoice_number'] . '.pdf"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $dompdf->output();
        exit;
    }

    private function savePdf(string $content, array $invoice): ?string
    {
        try {
            $companyId = $invoice['company_id'];
            $year      = date('Y');
            $month     = date('m');
            $dir       = ROOT_DIR . "/storage/pdfs/{$companyId}/{$year}/{$month}";
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            $filename = 'invoice-' . $invoice['invoice_number'] . '-' . time() . '.pdf';
            $path     = $dir . '/' . $filename;
            file_put_contents($path, $content);

            return "storage/pdfs/{$companyId}/{$year}/{$month}/{$filename}";
        } catch (\Throwable $e) {
            Logger::error('PDF save failed: ' . $e->getMessage());
            return null;
        }
    }

    private function buildHtml(array $invoice, array $items, array $company): string
    {
        $logo = '';
        if (!empty($company['logo_path'])) {
            $logoFile = ROOT_DIR . '/public/' . $company['logo_path'];
            if (file_exists($logoFile)) {
                $ext  = pathinfo($logoFile, PATHINFO_EXTENSION);
                $b64  = base64_encode(file_get_contents($logoFile));
                $logo = "<img src=\"data:image/{$ext};base64,{$b64}\" style=\"height:50px; margin-bottom:8px;\">";
            }
        }

        $itemRows = '';
        foreach ($items as $item) {
            $itemRows .= sprintf(
                '<tr>
                  <td>%s</td>
                  <td style="text-align:center;">%s</td>
                  <td style="text-align:center;">%s</td>
                  <td style="text-align:right;">%s</td>
                  <td style="text-align:right; font-weight:bold;">%s</td>
                </tr>',
                htmlspecialchars($item['product_name']),
                number_format((float)$item['quantity'], 3),
                htmlspecialchars($item['unit']),
                number_format((float)$item['unit_price'], 2),
                number_format((float)$item['line_total'], 2)
            );
        }

        $date       = date('d.m.Y', strtotime($invoice['invoice_date']));
        $clientInfo = $invoice['client_name'] ? htmlspecialchars($invoice['client_name']) : 'Без клиента';
        $clientPhone = !empty($invoice['client_phone']) ? htmlspecialchars($invoice['client_phone']) : '';

        $discount = (float)$invoice['discount'] > 0
            ? '<tr><td style="text-align:right; color:#ef4444;" colspan="2"><strong>Скидка:</strong></td>
               <td style="text-align:right; color:#ef4444;">-' . number_format((float)$invoice['discount'], 2) . ' сом.</td></tr>'
            : '';

        $debt = (float)$invoice['debt_amount'] > 0
            ? '<tr><td style="text-align:right; color:#ef4444;" colspan="2"><strong>Долг:</strong></td>
               <td style="text-align:right; color:#ef4444; font-weight:bold;">' . number_format((float)$invoice['debt_amount'], 2) . ' сом.</td></tr>'
            : '';

        $statusLabel = match($invoice['status']) {
            'paid'      => 'ОПЛАЧЕНО',
            'partial'   => 'ЧАСТИЧНО',
            'cancelled' => 'ОТМЕНЕНО',
            'draft'     => 'ЧЕРНОВИК',
            default     => 'НЕ ОПЛАЧЕНО',
        };
        $statusColor = match($invoice['status']) {
            'paid'      => '#22c55e',
            'partial'   => '#f97316',
            'cancelled' => '#6b7280',
            'draft'     => '#64748b',
            default     => '#ef4444',
        };

        $notesHtml = htmlspecialchars((string)($invoice['notes'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; }
  body { font-family: "DejaVu Sans", sans-serif; font-size: 12px; color: #111; margin: 0; padding: 20px; }
  .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; border-bottom: 2px solid #22c55e; padding-bottom: 16px; }
  .company-info h1 { font-size: 18px; color: #22c55e; margin: 0; }
  .company-info p { margin: 2px 0; color: #555; }
  .invoice-info { text-align: right; }
  .invoice-info h2 { font-size: 22px; margin: 0; color: #111; }
  .invoice-info .number { color: #22c55e; font-size: 16px; }
  .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-weight: bold; font-size: 12px; color: white; background: {$statusColor}; }
  .client-section { background: #f9fafb; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; }
  .client-section h3 { margin: 0 0 6px; font-size: 12px; color: #888; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
  .items-table th { background: #22c55e; color: white; padding: 8px 10px; text-align: left; }
  .items-table td { padding: 8px 10px; border-bottom: 1px solid #e5e7eb; }
  .items-table tr:nth-child(even) td { background: #f9fafb; }
  .totals-table td { padding: 6px 10px; }
  .signature { margin-top: 40px; display: flex; justify-content: space-between; }
  .signature-line { border-top: 1px solid #999; width: 200px; text-align: center; padding-top: 4px; font-size: 11px; color: #888; }
  .notes { font-style: italic; color: #555; font-size: 11px; margin-bottom: 12px; }
  .footer { margin-top: 20px; text-align: center; font-size: 10px; color: #aaa; border-top: 1px solid #e5e7eb; padding-top: 8px; }
</style>
</head>
<body>
  <div class="header">
    <div class="company-info">
      {$logo}
      <h1>{$company['name']}</h1>
      <p>{$company['phone']}</p>
      <p>{$company['address']}</p>
    </div>
    <div class="invoice-info">
      <h2>НАКЛАДНАЯ</h2>
      <div class="number">{$invoice['invoice_number']}</div>
      <p>{$date}</p>
      <div class="status-badge">{$statusLabel}</div>
    </div>
  </div>

  <div class="client-section">
    <h3>КЛИЕНТ</h3>
    <strong>{$clientInfo}</strong>
    {$clientPhone}
  </div>

  <table class="items-table">
    <thead>
      <tr>
        <th>Товар / Услуга</th>
        <th style="text-align:center;">Кол-во</th>
        <th style="text-align:center;">Ед.</th>
        <th style="text-align:right;">Цена</th>
        <th style="text-align:right;">Сумма</th>
      </tr>
    </thead>
    <tbody>
      {$itemRows}
    </tbody>
  </table>

  <table class="totals-table" style="width:240px; float:right;">
    <tr>
      <td style="text-align:right;" colspan="2"><strong>Подытог:</strong></td>
      <td style="text-align:right;">{$invoice['subtotal']} сом.</td>
    </tr>
    {$discount}
    <tr style="background:#f0fdf4;">
      <td style="text-align:right;" colspan="2"><strong>ИТОГО:</strong></td>
      <td style="text-align:right; font-weight:bold; font-size:14px;">{$invoice['total']} сом.</td>
    </tr>
    <tr>
      <td style="text-align:right; color:#22c55e;" colspan="2"><strong>Оплачено:</strong></td>
      <td style="text-align:right; color:#22c55e; font-weight:bold;">{$invoice['paid_amount']} сом.</td>
    </tr>
    {$debt}
  </table>
  <div style="clear:both;"></div>

  <div class="notes">
    {$notesHtml}
  </div>

  <div class="signature">
    <div class="signature-line">Продавец / Подпись</div>
    <div class="signature-line">Покупатель / Подпись</div>
  </div>

  <div class="footer">
    Накладная сгенерирована автоматически · Nakladna Cloud · {$date}
  </div>
</body>
</html>
HTML;
    }
}

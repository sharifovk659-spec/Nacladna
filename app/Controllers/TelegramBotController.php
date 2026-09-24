<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Response;
use App\Services\PdfService;

class TelegramBotController
{
    private string $botToken;

    public function __construct()
    {
        $this->botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
    }

    public function handle(): void
    {
        // Verify webhook secret
        $secret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
        if ($secret !== ($_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? '')) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }

        $input  = file_get_contents('php://input');
        $update = json_decode($input, true);
        if (!$update) {
            http_response_code(200);
            return;
        }

        Logger::info('Bot update received', ['update_id' => $update['update_id'] ?? 0]);

        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
        }

        http_response_code(200);
        echo 'OK';
    }

    private function handleMessage(array $message): void
    {
        $chatId    = $message['chat']['id'];
        $text      = trim($message['text'] ?? '');
        $telegramId = $message['from']['id'];

        if ($text === '/start') {
            $this->sendWelcome($chatId);
            return;
        }

        if (str_starts_with($text, '/invoice ')) {
            $invoiceId = (int)substr($text, 9);
            $this->sendInvoicePdf($chatId, $telegramId, $invoiceId);
            return;
        }

        if ($text === '/status') {
            $this->sendStatus($chatId, $telegramId);
            return;
        }

        $this->sendMessage($chatId, 'Команды:\n/start — Открыть приложение\n/status — Статус подписки');
    }

    private function sendWelcome(int $chatId): void
    {
        $appUrl = rtrim($_ENV['APP_URL'] ?? 'https://nakladna.inovaauto.com', '/') . '/mini-app';
        $this->sendMessage($chatId, "Добро пожаловать в *Nakladna Cloud*!\n\nУправляйте накладными и клиентами прямо из Telegram.", [
            'reply_markup' => json_encode([
                'inline_keyboard' => [[
                    ['text' => 'Открыть приложение', 'web_app' => ['url' => $appUrl]]
                ]]
            ], JSON_UNESCAPED_UNICODE)
        ]);
    }

    private function sendStatus(int $chatId, int $telegramId): void
    {
        $db   = Database::getInstance();
        $user = $db->prepare("SELECT id FROM users WHERE telegram_id=? LIMIT 1");
        $user->execute([$telegramId]);
        $user = $user->fetch();

        if (!$user) {
            $this->sendMessage($chatId, '❌ Пользователь не зарегистрирован.');
            return;
        }

        $sub = $db->prepare(
            "SELECT s.* FROM subscriptions s
             JOIN company_users cu ON cu.company_id=s.company_id
             WHERE cu.user_id=? ORDER BY s.id DESC LIMIT 1"
        );
        $sub->execute([$user['id']]);
        $sub = $sub->fetch();

        if (!$sub) {
            $this->sendMessage($chatId, '⚠️ Подписка не найдена. Пройдите регистрацию в приложении.');
            return;
        }

        $ends     = strtotime($sub['ends_at'] ?? $sub['trial_end'] ?? '');
        $daysLeft = max(0, (int)ceil(($ends - time()) / 86400));
        $isTrial  = ($sub['plan'] ?? '') === 'trial' || ($sub['status'] ?? '') === 'trial';

        if ($ends && $ends < time()) {
            $status = 'Подписка истекла';
        } elseif ($isTrial) {
            $status = "Пробный период — {$daysLeft} дней осталось";
        } elseif (($sub['status'] ?? '') === 'active') {
            $status = "Подписка активна — {$daysLeft} дней";
        } else {
            $status = 'Статус недоступен';
        }

        $this->sendMessage($chatId, "*Статус подписки:*\n{$status}");
    }

    private function sendInvoicePdf(int $chatId, int $telegramId, int $invoiceId): void
    {
        $db   = Database::getInstance();
        $user = $db->prepare("SELECT id FROM users WHERE telegram_id=? LIMIT 1");
        $user->execute([$telegramId]);
        $user = $user->fetch();
        if (!$user) { $this->sendMessage($chatId, '❌ Не авторизован.'); return; }

        // Check authorization: invoice must belong to user's company
        $inv = $db->prepare(
            "SELECT i.* FROM invoices i
             JOIN company_users cu ON cu.company_id=i.company_id
             WHERE i.id=? AND cu.user_id=? AND cu.status='active' LIMIT 1"
        );
        $inv->execute([$invoiceId, $user['id']]);
        $invoice = $inv->fetch();
        if (!$invoice) { $this->sendMessage($chatId, '❌ Накладная не найдена.'); return; }

        // Check if PDF exists
        $pdfPath = ROOT_DIR . '/' . $invoice['pdf_path'];
        if (!$invoice['pdf_path'] || !file_exists($pdfPath)) {
            $this->sendMessage($chatId, "📄 Накладная #{$invoice['invoice_number']} найдена. Откройте приложение для скачивания PDF.");
            return;
        }

        $this->sendDocument($chatId, $pdfPath, 'invoice-' . $invoice['invoice_number'] . '.pdf');
    }

    private function sendMessage(int $chatId, string $text, array $extra = []): void
    {
        $data = array_merge(['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'Markdown'], $extra);
        $this->apiRequest('sendMessage', $data);
    }

    private function sendDocument(int $chatId, string $filePath, string $filename): void
    {
        $url  = "https://api.telegram.org/bot{$this->botToken}/sendDocument";
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => [
                'chat_id'  => $chatId,
                'document' => new \CURLFile($filePath, 'application/pdf', $filename),
            ],
        ]);
        $resp = curl_exec($curl);
        curl_close($curl);
        Logger::debug('sendDocument', ['response' => $resp]);
    }

    private function apiRequest(string $method, array $data): void
    {
        $url  = "https://api.telegram.org/bot{$this->botToken}/{$method}";
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);
        $resp = curl_exec($curl);
        curl_close($curl);
    }
}

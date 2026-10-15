<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OwnerBot\BedrockOwnerBotService;
use App\Services\OwnerBot\OwnerDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * API Controller dedicated to Owner Chatbot and Amazon Bedrock integration.
 */
class OwnerBotController extends Controller
{
    public function __construct(
        private readonly BedrockOwnerBotService $botService,
        private readonly OwnerDataService $dataService
    ) {}

    /**
     * Primary conversational endpoint for Owner Chatbot.
     * Takes natural language question, queries Bedrock & DB tools, and returns response.
     */
    public function chat(Request $request): JsonResponse
    {
        // If accessed via GET in a browser without a message, provide status & documentation
        if ($request->isMethod('get') && ! $request->has('message')) {
            return new JsonResponse([
                'status' => 'online',
                'service' => 'Lukla Operations - Owner Executive AI Assistant API',
                'message' => 'Endpoint aktif. Untuk mengirim pertanyaan, gunakan method POST dengan body JSON {"message": "..."} atau tambahkan parameter ?message=pertanyaan_anda pada URL ini.',
                'example_url' => url('/api/owner/chat?api_key='.config('services.owner_bot.api_key').'&message=Ringkasan+pabrik+hari+ini'),
                'endpoints' => [
                    'POST /api/owner/chat' => 'Kirim pesan chat pertanyaan owner',
                    'GET /api/owner/overview' => 'Ringkasan eksekutif dan KPI pabrik',
                    'GET /api/owner/inventory/track/{identifier}' => 'Lacak status & aktivitas barang/material',
                    'GET /api/owner/clients/{identifier}/billing' => 'Cek tagihan & hutang klien',
                    'GET /api/owner/orders/{identifier}/status' => 'Cek progres pesanan produksi',
                ],
            ]);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'chat_id' => ['nullable', 'string', 'max:100'],
            'telegram_user_id' => ['nullable', 'string', 'max:100'],
        ]);

        $response = $this->botService->handleMessage(
            $validated['message'],
            $validated['chat_id'] ?? null
        );

        return new JsonResponse([
            'success' => true,
            'reply' => $response['reply'],
            'intent' => $response['intent'],
            'tools_used' => $response['tools_used'],
            'data' => $response['data'],
            'source' => $response['source'],
        ]);
    }

    /**
     * Direct structured endpoint: Track inventory item by ID, Tag ID, or batch.
     */
    public function trackInventory(string|int $identifier): JsonResponse
    {
        $result = $this->dataService->trackInventoryItem($identifier);

        return new JsonResponse([
            'success' => $result['found'] ?? false,
            'data' => $result,
        ], ($result['found'] ?? false) ? 200 : 404);
    }

    /**
     * Direct structured endpoint: Get client billing, unpaid invoices, and overdue amounts.
     */
    public function clientBilling(string|int $identifier): JsonResponse
    {
        $result = $this->dataService->getClientBilling($identifier);

        return new JsonResponse([
            'success' => $result['found'] ?? false,
            'data' => $result,
        ], ($result['found'] ?? false) ? 200 : 404);
    }

    /**
     * Direct structured endpoint: Get order status and progress.
     */
    public function orderStatus(string|int $identifier): JsonResponse
    {
        $result = $this->dataService->getOrderStatus($identifier);

        return new JsonResponse([
            'success' => $result['found'] ?? false,
            'data' => $result,
        ], ($result['found'] ?? false) ? 200 : 404);
    }

    /**
     * Direct structured endpoint: Executive overview and KPIs for Owner.
     */
    public function overview(): JsonResponse
    {
        $result = $this->dataService->getOwnerExecutiveOverview();

        return new JsonResponse([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Webhook endpoint directly compatible with Telegram Bot Webhook API.
     * When Telegram sends an update, this endpoint answers and posts back.
     */
    public function telegramWebhook(Request $request): JsonResponse
    {
        $message = $request->input('message') ?? $request->input('edited_message');

        if (! $message || ! isset($message['text'])) {
            return new JsonResponse(['ok' => true, 'note' => 'no_text_message']);
        }

        $chatId = $message['chat']['id'] ?? null;
        $text = $message['text'];
        $senderId = (string) ($message['from']['id'] ?? '');

        // Handle /start command
        if (str_starts_with(trim($text), '/start')) {
            $welcomeText = "👋 *Selamat datang di Lukla Owner Bot!*\n\n"
                ."Bot ini terhubung langsung ke database & Amazon Bedrock untuk Owner.\n\n"
                ."*Contoh perintah/pertanyaan:*\n"
                ."• `ID barang 5 sedang ngapain?`\n"
                ."• `Klien ID 2 harus bayar berapa?`\n"
                ."• `Cek tagihan klien PT Maju`\n"
                ."• `Status order ORD-2026-0001`\n"
                ."• `Ringkasan pabrik hari ini`\n\n"
                .'Silakan kirimkan pertanyaan Anda!';

            $this->sendTelegramMessage($chatId, $welcomeText);

            return new JsonResponse(['ok' => true, 'reply' => $welcomeText]);
        }

        // Process message with Bedrock AI & Database tools
        $response = $this->botService->handleMessage($text, (string) $chatId);
        $reply = $response['reply'];

        // Automatically dispatch response back to Telegram if Bot Token configured
        $this->sendTelegramMessage($chatId, $reply);

        return new JsonResponse([
            'ok' => true,
            'reply' => $reply,
            'intent' => $response['intent'],
        ]);
    }

    /**
     * Helper to send response message to Telegram Chat if token is present.
     */
    private function sendTelegramMessage(int|string|null $chatId, string $text): void
    {
        $token = config('services.owner_bot.telegram_bot_token');

        if (! $token || ! $chatId) {
            return;
        }

        try {
            Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
            ]);
        } catch (\Throwable $e) {
            Log::error('OwnerBotController: Failed to send Telegram message', [
                'error' => $e->getMessage(),
                'chat_id' => $chatId,
            ]);
        }
    }
}

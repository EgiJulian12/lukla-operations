<?php

namespace App\Http\Controllers;

use App\Services\Ai\DatabaseQueryService;
use App\Services\Ai\IntentRouter;
use App\Services\Ai\RagService;
use App\Services\Ai\TelegramRagService;
use App\Services\Ai\TelegramRegistrationService;
use App\Services\Ai\TelegramUserResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Handles incoming Telegram webhook updates.
 *
 * Telegram requires a 2xx response for every request. We always return 200 —
 * even on errors — to prevent Telegram from retrying and creating duplicate
 * processing loops. All errors are logged for inspection.
 *
 * Message processing order:
 *  1. Parse message text + chat_id from the Telegram update payload.
 *  2. Check for /start <TOKEN> → run token-based registration flow.
 *  3. Check for bare /start → send the "request a token from admin" message.
 *  4. Resolve the chat_id to a linked CLIENT_MAKLON user (access gate).
 *  5. Detect intent (db / doc / hybrid) and route to the correct service.
 *  6. Send the answer back via the Bot API.
 */
class TelegramWebhookController extends Controller
{
    public function __construct(
        private readonly TelegramUserResolver $userResolver,
        private readonly TelegramRegistrationService $registrationService,
        private readonly IntentRouter $intentRouter,
        private readonly DatabaseQueryService $databaseQueryService,
        private readonly TelegramRagService $telegramRagService,
        private readonly RagService $ragService,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $message = $request->input('message');

        if (! is_array($message)) {
            return response()->json(['ok' => true]);
        }

        $chatId = data_get($message, 'chat.id');
        $text = data_get($message, 'text');

        if ($chatId === null || ! is_string($text) || trim($text) === '') {
            return response()->json(['ok' => true]);
        }

        $answer = $this->answerFor((string) $chatId, trim($text));

        $this->sendTelegramMessage((string) $chatId, $answer);

        return response()->json(['ok' => true]);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Resolve the reply text, degrading to an apology if processing fails.
     *
     * This is deliberately separate from the send: any exception in the AI/DB
     * path used to escape past the reply entirely, leaving the client with
     * silence and no way to tell a failure from a slow answer.
     */
    private function answerFor(string $chatId, string $text): string
    {
        try {
            return $this->processMessage($chatId, $text);
        } catch (\Throwable $e) {
            Log::error('TelegramWebhookController: unhandled exception.', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 'Maaf, terjadi kendala teknis saat memproses pesan Anda. Silakan coba lagi beberapa saat lagi.';
        }
    }

    private function processMessage(string $chatId, string $text): string
    {
        // --- Step 1: handle /start <TOKEN> registration ---
        $startToken = $this->registrationService->extractStartToken($text);

        if ($startToken !== null) {
            $result = $this->registrationService->register($startToken, $chatId);

            return $result['message'];
        }

        // --- Step 2: handle bare /start (no token) ---
        if ($this->registrationService->isPlainStart($text)) {
            return implode("\n", [
                'Selamat datang di Lukla Operations Bot! 👋',
                '',
                'Untuk menghubungkan akun Anda, minta token registrasi dari admin Lukla.',
                'Kemudian kirim: /start TOKEN',
                '',
                'Jika Anda sudah terdaftar, langsung kirim pertanyaan Anda.',
            ]);
        }

        // --- Step 3: resolve the linked client user ---
        $user = $this->userResolver->resolve($chatId);

        if ($user === null) {
            return implode("\n", [
                'Akun Telegram Anda belum terhubung ke sistem Lukla Operations.',
                '',
                'Ketik /start untuk melihat cara mendaftar.',
            ]);
        }

        // --- Step 4: detect intent and route ---
        $intent = $this->intentRouter->detectIntent($text);

        Log::info('TelegramWebhookController: processing message.', [
            'chat_id' => $chatId,
            'client_id' => $user->client_id,
            'intent' => $intent['intent'],
            'model' => $intent['model'],
        ]);

        return match ($intent['intent']) {
            'db' => $this->handleDbIntent($text, (int) $user->client_id, $intent),
            'hybrid' => $this->handleHybridIntent($text, (int) $user->client_id, $intent),
            default => $this->handleDocIntent($text),
        };
    }

    /**
     * @param  array{intent: string, model: string|null, filters: array<string, mixed>}  $intent
     */
    private function handleDbIntent(string $query, int $clientId, array $intent): string
    {
        if ($intent['model'] === null) {
            return $this->handleDocIntent($query);
        }

        $dbContext = $this->databaseQueryService->query(
            $intent['model'],
            $clientId,
            $intent['filters'],
        );

        return $this->telegramRagService->ask($query, $dbContext)['answer'];
    }

    /**
     * @param  array{intent: string, model: string|null, filters: array<string, mixed>}  $intent
     */
    private function handleHybridIntent(string $query, int $clientId, array $intent): string
    {
        $dbContext = [];

        if ($intent['model'] !== null) {
            $dbContext = $this->databaseQueryService->query(
                $intent['model'],
                $clientId,
                $intent['filters'],
            );
        }

        $s3Chunks = $this->ragService->getRelevantChunksForQuery($query);

        return $this->telegramRagService->ask($query, $dbContext, $s3Chunks)['answer'];
    }

    private function handleDocIntent(string $query): string
    {
        return $this->ragService->askQuestion($query)['answer'];
    }

    private function sendTelegramMessage(string $chatId, string $text): void
    {
        $botToken = (string) config('services.telegram.bot_token');
        $apiUrl = rtrim((string) config('services.telegram.api_url'), '/');

        try {
            $response = Http::withOptions([
                'verify' => config('services.telegram.verify_ssl'),
            ])->post("{$apiUrl}/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
            ]);

            if (! $response->successful()) {
                Log::warning('TelegramWebhookController: failed to send message.', [
                    'chat_id' => $chatId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            // Never log the exception message verbatim: the request URL embeds
            // the bot token, and cURL echoes it back in its error text.
            Log::error('TelegramWebhookController: could not reach the Bot API.', [
                'chat_id' => $chatId,
                'exception' => $e::class,
                'hint' => 'TLS failure? Check TELEGRAM_VERIFY_SSL if a proxy inspects HTTPS.',
            ]);
        }
    }
}

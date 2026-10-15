<?php

namespace App\Http\Controllers\Telegram;

use App\Services\Telegram\TelegramBotConfig;
use App\Services\Telegram\TelegramMessageRouter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Telegram\Bot\Objects\Update;

/**
 * Penerima update webhook dari Telegram.
 *
 * Tanda tangan ganda: segmen {secret} pada URL dan header
 * X-Telegram-Bot-Api-Secret-Token (dipasang Telegram pada setiap update)
 * keduanya wajib sama dengan webhook_secret bot. Salah satu saja tidak cukup
 * karena URL bisa bocor lewat log proxy, sedangkan header bisa bocor lewat
 * salah konfigurasi setWebhook.
 */
abstract class TelegramWebhookController
{
    abstract protected function botName(): string;

    public function __invoke(
        string $secret,
        Request $request,
        TelegramBotConfig $config,
        TelegramMessageRouter $router,
    ): JsonResponse {
        $expected = $config->webhookSecret($this->botName());
        $header = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        if ($expected === '' || ! hash_equals($expected, $secret) || ! hash_equals($expected, $header)) {
            return response()->json(['ok' => false], 403);
        }

        // Update diparse dari input Laravel, bukan getWebhookUpdate() milik SDK
        // yang membaca php://input mentah (tidak tersedia dalam test framework).
        $router->route($this->botName(), new Update($request->json()->all()));

        return response()->json(['ok' => true]);
    }
}

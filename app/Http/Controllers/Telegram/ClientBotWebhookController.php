<?php

namespace App\Http\Controllers\Telegram;

/**
 * Webhook bot client: notifikasi klien maklon dan persetujuan penawaran.
 */
class ClientBotWebhookController extends TelegramWebhookController
{
    protected function botName(): string
    {
        return 'client';
    }
}

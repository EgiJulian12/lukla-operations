<?php

namespace App\Http\Controllers\Telegram;

/**
 * Webhook bot owner: notifikasi internal dan persetujuan sample.
 */
class OwnerBotWebhookController extends TelegramWebhookController
{
    protected function botName(): string
    {
        return 'owner';
    }
}

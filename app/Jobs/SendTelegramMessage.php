<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Telegram\Bot\Laravel\Facades\Telegram;

/**
 * Pengirim pesan Telegram asinkron. Kegagalan HTTP dilempar agar queue retry
 * (3x, interval backoff), bukan menghilang tanpa jejak.
 */
class SendTelegramMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>|null  $replyMarkup  Payload reply_markup Bot API (mis. inline_keyboard).
     */
    public function __construct(
        public readonly string $bot,
        public readonly int|string $chatId,
        public readonly string $text,
        public readonly ?array $replyMarkup = null,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(): void
    {
        $params = ['chat_id' => $this->chatId, 'text' => $this->text];

        if ($this->replyMarkup !== null) {
            $params['reply_markup'] = $this->replyMarkup;
        }

        Telegram::bot($this->bot)->sendMessage($params);
    }
}
